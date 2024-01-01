<?php declare(strict_types=1);

namespace SearchSparql\Job;

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class IndexTriplestore extends AbstractJob
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $context;

    /**
     * @var string
     */
    protected $datasetName;

    /**
     * @var string
     */
    protected $filepath;

    /**
     * @var array
     */
    protected $options = [
    ];

    /**
     * @var array
     */
    protected $properties;

    /**
     * Specific property prefixes.
     *
     * @see \EasyRdf\RdfNamespace::initial_namespaces
     * @see https://www.w3.org/2011/rdfa-context/rdfa-1.1
     * @see https://www.w3.org/2013/json-ld-context/rdfa11
     *
     * @var array
     */
    protected $vocabularyUris = [
        'o' => 'http://omeka.org/s/vocabs/o#',
        // Used by media "html" and not in the default namespaces.
        'o-cnt' => 'http://www.w3.org/2011/content#',
        // Used by media "youtube". The default prefix "time" is kept.
        'o-time' => 'http://www.w3.org/2006/time#',
        // Add contexts used by easyrdf.
        // The recommended is dc = full dc, but dc11 is not common.
        'dc' => 'http://purl.org/dc/elements/1.1/',
        // dcterms is included in default namespaces.
        // 'dcterms' => 'http://purl.org/dc/terms/',
    ];

    /**
     * RDF resource properties to keep in all cases.
     *
     * @var array
     */
    protected $propertyMeta = [
        '@context' => null,
        '@id' => null,
        '@type' => null,
    ];

    /**
     * @var array
     */
    protected $propertyBlacklist;

    /**
     * @var array
     */
    protected $propertyWhitelist;

    /**
     * @var string
     */
    protected $rdfsLabel;

    /**
     * @var array
     */
    protected $resourceQuery;

    /**
     * @var array
     */
    protected $resourceTypes;

    /**
     * @var int
     */
    protected $totalResults = 0;

    public function perform(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $services = $this->getServiceLocator();

        $this->api = $services->get('Omeka\ApiManager');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->logger = $services->get('Omeka\Logger');

        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $easyMeta = $services->get('EasyMeta');
        $configModule = $config['searchsparql']['config'];

        // The reference id is the job id for now.
        if (class_exists('Log\Stdlib\PsrMessage')) {
            $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
            $referenceIdProcessor->setReferenceId('searchsparql/indextriplestore/job_' . $this->job->getId());
            $this->logger->addProcessor($referenceIdProcessor);
        }

        $this->datasetName = 'triplestore';

        // Init options.

        $this->resourceTypes = $this->getArg('resource_types', $settings->get('searchsparql_resource_types', $configModule['searchsparql_resource_types']));

        $this->resourceQuery = $this->getArg('resource_query', $settings->get('searchsparql_resource_query', $configModule['searchsparql_resource_query']));
        if ($this->resourceQuery) {
            $query = [];
            parse_str((string) $this->resourceQuery, $query);
            $this->resourceQuery = $query;
        } else {
            $this->resourceQuery = [];
        }

        $this->properties = $easyMeta->propertyIds();

        $this->propertyWhitelist = $this->getArg('property_whitelist', $settings->get('searchsparql_property_whitelist', $configModule['searchsparql_property_whitelist']));
        $this->propertyWhitelist = array_intersect_key(array_combine($this->propertyWhitelist, $this->propertyWhitelist), $this->properties);

        $this->propertyBlacklist = $this->getArg('property_blacklist', $settings->get('searchsparql_property_blacklist', $configModule['searchsparql_property_blacklist']));
        $this->propertyBlacklist = array_intersect_key(array_combine($this->propertyBlacklist, $this->propertyBlacklist), $this->properties);

        $this->initPrefixes();

        $fieldsIncluded = $this->getArg('fields_included', $settings->get('searchsparql_fields_included', $configModule['searchsparql_fields_included']));
        $pos = array_search('rdfs:label', $fieldsIncluded);
        if ($pos !== false) {
            $fieldsIncluded[$pos] = RdfNamespace::prefixOfUri('http://www.w3.org/2000/01/rdf-schema#') . ':label';
            $this->rdfsLabel = $fieldsIncluded[$pos];
        }
        $this->propertyMeta += array_flip($fieldsIncluded);

        // Prepare output path.
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->filepath = $basePath . '/triplestore/' . $this->datasetName . '.ttl';
        file_put_contents($this->filepath, '');

        if (in_array('media', $this->resourceTypes) && !in_array('items', $this->resourceTypes)) {
            $this->logger->warn(new Message(
                'Sparql dataset "%1$s": Medias cannot be indexed without indexing items.', // @translate
                $this->datasetName
            ));
        }

        $timeStart = microtime(true);

        $this->logger->notice(new Message(
            'Sparql dataset "%1$s": start of indexing', // @translate
            $this->datasetName
        ));

        $this->processindex();

        $timeTotal = (int) (microtime(true) - $timeStart);

        $this->logger->notice(new Message(
            'Sparql dataset "%1$s": end of indexing. %2$s resources indexed. Execution time: %3$s seconds.', // @translate
            $this->datasetName, $this->totalResults, $timeTotal
        ));
    }

    /**
     * Create the triplestore.
     */
    protected function processIndex(): self
    {
        // Step 1: adding vocabularies.

        $output = '';
        $base = '@prefix %1$s: <%2$s> .';

        // Include omeka vocabularies first.
        $omekaVocabs = [
            'o' => 'http://omeka.org/s/vocabs/o#',
            'o-cnt' => 'http://www.w3.org/2011/content#',
            'o-time' => 'http://www.w3.org/2006/time#',
            // 'o-module-mapping' => 'http://omeka.org/s/vocabs/module/mapping#',
        ];
        foreach ($omekaVocabs + $this->context as $prefix => $namespaceUri) {
            $output .= sprintf($base, $prefix, $namespaceUri) . PHP_EOL;
        }

        /** @var \Omeka\Api\Representation\VocabularyRepresentation[] $vocabularies */
        /*
        $vocabularies = $this->api->search('vocabularies', ['sort_by' => 'prefix'])->getContent();
        foreach ($vocabularies as $vocabulary) {
            $output .= sprintf($base, $vocabulary->prefix(), $vocabulary->namespaceUri()) . PHP_EOL;
        }
        */
        $output .= PHP_EOL;
        file_put_contents($this->filepath, $output, FILE_APPEND | LOCK_EX);

        // Step 2: adding item sets.

        if (in_array('item_sets', $this->resourceTypes)) {
            $response = $this->api->search('item_sets', [], ['returnScalar' => 'id']);
            $total = $response->getTotalResults();

            $this->logger->info(new Message(
                'Sparql dataset "%1$s": indexing %2$d item sets.', // @translate
                $this->datasetName, $total
            ));

            $i = 0;
            foreach ($response->getContent() as $id) {
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $itemSet */
                $itemSet = $this->api->read('item_sets', ['id' => $id])->getContent();
                $this->storeResource($itemSet);
                ++$this->totalResults;
                if (++$i % 100 === 0) {
                    $this->logger->info(new Message(
                        'Sparql dataset "%1$s": indexed %2$d/%3$d item sets.', // @translate
                        $this->datasetName, $i, $total
                    ));
                    $this->entityManager->clear();
                }
            }

            $this->entityManager->clear();
        }

        // Step 3: adding items and attached media.

        if (in_array('items', $this->resourceTypes)) {
            $indexMedia = in_array('media', $this->resourceTypes);

            $response = $this->api->search('items', $this->resourceQuery, ['returnScalar' => 'id']);
            $total = $response->getTotalResults();

            if ($indexMedia) {
                $totalMedias = $this->api->search('media')->getTotalResults();
                $this->logger->info(new Message(
                    'Sparql dataset "%1$s": indexing %2$d items and %3$d medias.', // @translate
                    $this->datasetName, $total, $totalMedias
                ));
            } else {
                $this->logger->info(new Message(
                    'Sparql dataset "%1$s": indexing %2$d items.', // @translate
                    $this->datasetName, $total
                ));
            }

            $i = 0;
            foreach ($response->getContent() as $id) {
                /** @var \Omeka\Api\Representation\ItemRepresentation $item */
                $item = $this->api->read('items', ['id' => $id])->getContent();
                $this->storeResource($item);
                if ($indexMedia) {
                    foreach ($item->media() as $media)  {
                        $this->storeResource($media);
                        ++$this->totalResults;
                    }
                }
                ++$this->totalResults;
                if (++$i % 100 === 0) {
                    $this->logger->info(new Message(
                        'Sparql dataset "%1$s": indexed %2$d/%3$d items.', // @translate
                        $this->datasetName, $i, $total
                    ));
                    $this->entityManager->clear();
                }
            }
            $this->entityManager->clear();
        }

        return $this;
    }

    /**
     * Store a single resource in the triplestore.
     */
    protected function storeResource(AbstractResourceEntityRepresentation $resource): self
    {
        // Don't use jsonSerialize(), that serialize only first level.
        $json = json_decode(json_encode($resource), true);

        // Manage the special case of rdfs:label.
        if ($this->rdfsLabel) {
            $json[$this->rdfsLabel][] = $json['o:title'] ?? $resource->displayTitle();
        }

        // Don't store specific metadata.
        $json = $this->propertyWhitelist
            ? array_intersect_key($json, $this->propertyMeta + $this->propertyWhitelist)
            : array_intersect_key($json, $this->propertyMeta + $this->properties);

        if ($this->propertyBlacklist) {
            $json = array_diff_key($json, $this->propertyBlacklist);
        }

        $id = $resource->apiUrl();
        $json['@context'] = $this->context;

        // Serialize the json as turtle.
        $graph = new Graph($id);
        $graph->parse(json_encode($json), 'jsonld', $id);
        $turtle = $graph->serialise('turtle');

        file_put_contents($this->filepath, $turtle . PHP_EOL, FILE_APPEND | LOCK_EX);

        return $this;
    }

    protected function initPrefixes(): self
    {
        // In Omeka, an event is needed to get all the vocabularies.
        $eventManager = $this->getServiceLocator()->get('EventManager');
        $args = $eventManager->prepareArgs(['context' => []]);
        $eventManager->trigger('api.context', null, $args);
        $this->context = $args['context'] + $this->vocabularyUris;
        ksort($this->context);

        // Initialise namespaces with all prefixes from Omeka.
        /** @see \EasyRdf\RdfNamespace::initial_namespaces */
        $initialNamespaces = RdfNamespace::namespaces();
        foreach ($this->context as $prefix => $uri) {
            $search = array_search($uri, $initialNamespaces);
            if ($search !== false && $prefix !== 'o-time' && $prefix !== 'o-cnt') {
                RdfNamespace::delete($prefix);
            }
            RdfNamespace::set($prefix, $uri);
        }

        return $this;
    }
}
