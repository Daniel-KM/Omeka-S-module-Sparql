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
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

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
     * @var array
     */
    protected $contextShort;

    /**
     * @var string
     */
    protected $datasetName;

    /**
     * @var string
     */
    protected $dataTypeWhiteList;

    /**
     * @var string
     */
    protected $dataTypeBlackList;

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
    protected $vocabularyIris = [
        'o' => 'http://omeka.org/s/vocabs/o#',
        // Used by media "html" and not in the default namespaces.
        /** @see \Omeka\Module::filterHtmlMediaJsonLd() */
        'o-cnt' => 'http://www.w3.org/2011/content#',
        // Used by media "youtube". The default prefix "time" is kept.
        /** @see \Omeka\Module::filterYoutubeMediaJsonLd() */
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
    protected $propertyBlackList;

    /**
     * @var array
     */
    protected $propertyWhiteList;

    /**
     * @var string
     */
    protected $rdfsLabel;

    /**
     * @var bool
     */
    protected $resourcePublicOnly;

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
        $this->connection = $services->get('Omeka\Connection');
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

        $this->resourcePublicOnly = !$this->getArg('resource_private', $settings->get('searchsparql_resource_private', $configModule['searchsparql_resource_private']));
        if ($this->resourcePublicOnly) {
            $this->resourceQuery['is_public'] = true;
        }

        $this->properties = $easyMeta->propertyIds();

        $this->propertyWhiteList = $this->getArg('property_whitelist', $settings->get('searchsparql_property_whitelist', $configModule['searchsparql_property_whitelist']));
        $this->propertyWhiteList = array_intersect_key(array_combine($this->propertyWhiteList, $this->propertyWhiteList), $this->properties);

        $this->propertyBlackList = $this->getArg('property_blacklist', $settings->get('searchsparql_property_blacklist', $configModule['searchsparql_property_blacklist']));
        $this->propertyBlackList = array_intersect_key(array_combine($this->propertyBlackList, $this->propertyBlackList), $this->properties);

        $this->initPrefixes();

        $fieldsIncluded = $this->getArg('fields_included', $settings->get('searchsparql_fields_included', $configModule['searchsparql_fields_included']));
        $pos = array_search('rdfs:label', $fieldsIncluded);
        if ($pos !== false) {
            $fieldsIncluded[$pos] = RdfNamespace::prefixOfUri('http://www.w3.org/2000/01/rdf-schema#') . ':label';
            $this->rdfsLabel = $fieldsIncluded[$pos];
        }
        $this->propertyMeta += array_flip($fieldsIncluded);

        $this->initPrefixesShort();

        $this->dataTypeWhiteList = $this->getArg('datatype_whitelist', $settings->get('searchsparql_datatype_whitelist', $configModule['searchsparql_datatype_whitelist']));
        $this->dataTypeWhiteList = array_combine($this->dataTypeWhiteList, $this->dataTypeWhiteList);
        $this->dataTypeBlackList = $this->getArg('datatype_blacklist', $settings->get('searchsparql_datatype_blacklist', $configModule['searchsparql_datatype_blacklist']));
        $this->dataTypeBlackList = array_combine($this->dataTypeBlackList, $this->dataTypeBlackList);

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
        // Step 1: adding vocabularies used in triplestore.

        $output = '';
        $base = '@prefix %1$s: <%2$s> .';
        foreach ($this->contextShort as $prefix => $iri) {
            $output .= sprintf($base, $prefix, $iri) . "\n";
        }
        $output .= "\n\n";
        file_put_contents($this->filepath, $output, LOCK_EX);

        // Step 2: adding item sets.

        $queryVisibility = $this->resourcePublicOnly ? ['is_public' => true] : [];

        if (in_array('item_sets', $this->resourceTypes)) {
            $response = $this->api->search('item_sets', $queryVisibility, ['returnScalar' => 'id']);
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
                /*
                $ids = $response->getContent();
                $totalMedias = $this->api->search('media', ['item_id' => $ids])->getTotalResults();
                $this->logger->info(new Message(
                    'Sparql dataset "%1$s": indexing %2$d items and %3$d medias.', // @translate
                    $this->datasetName, $total, $totalMedias
                ));
                */
                $this->logger->info(new Message(
                    'Sparql dataset "%1$s": indexing %2$d items and attached medias.', // @translate
                    $this->datasetName, $total
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
                        if ($this->resourcePublicOnly && !$media->isPublic()) {
                            continue;
                        }
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
        $json = $this->propertyWhiteList
            ? array_intersect_key($json, $this->propertyMeta + $this->propertyWhiteList)
            : array_intersect_key($json, $this->propertyMeta + $this->properties);

        if ($this->propertyBlackList) {
            $json = array_diff_key($json, $this->propertyBlackList);
        }

        $skips = [
            'html' => 'html',
            'xml' => 'xml',
        ];
        if ($this->resourcePublicOnly
            || $this->dataTypeWhiteList
            || $this->dataTypeBlackList
            || count(array_intersect_key($skips, $this->dataTypeBlackList)) !== count($skips)
        ) {
            foreach (array_keys(array_intersect_key($this->properties, $json)) as $property) {
                foreach ($json[$property] as $key => $value) {
                    if ($this->resourcePublicOnly && !$value['is_public']) {
                        unset($json[$property][$key]);
                        continue;
                    }
                    if ($this->dataTypeWhiteList && !isset($this->dataTypeWhiteList[$value['type']])) {
                        unset($json[$property][$key]);
                        continue;
                    }
                    if ($this->dataTypeBlackList && isset($this->dataTypeBlackList[$value['type']])) {
                        unset($json[$property][$key]);
                        continue;
                    }
                    if (in_array($value['type'], $skips)) {
                        $json[$property][$key]['type'] = 'literal';
                    }
                }
            }
        }

        $id = $resource->apiUrl();
        $json['@context'] = $this->context;

        // Serialize the json as turtle.
        $graph = new Graph($id);
        $graph->parse(json_encode($json), 'jsonld', $id);
        $turtle = $graph->serialise('turtle');

        // Remove vocabularies.
        $turtle = mb_substr($turtle, mb_strpos($turtle, "\n\n") + 2);

        file_put_contents($this->filepath, $turtle . "\n", FILE_APPEND | LOCK_EX);

        return $this;
    }

    /**
     * Prepare all vocabulary prefixes used in the database.
     */
    protected function initPrefixes(): self
    {
        // In Omeka, an event is needed to get all the vocabularies.
        $eventManager = $this->getServiceLocator()->get('EventManager');
        $args = $eventManager->prepareArgs(['context' => []]);
        $eventManager->trigger('api.context', null, $args);
        $this->context = $args['context'] + $this->vocabularyIris;
        ksort($this->context);

        // Initialise namespaces with all prefixes from Omeka.
        /** @see \EasyRdf\RdfNamespace::initial_namespaces */
        $initialNamespaces = RdfNamespace::namespaces();
        foreach ($this->context as $prefix => $iri) {
            $search = array_search($iri, $initialNamespaces);
            if ($search !== false && $prefix !== 'o-time' && $prefix !== 'o-cnt') {
                RdfNamespace::delete($prefix);
            }
            RdfNamespace::set($prefix, $iri);
        }

        return $this;
    }

    /**
     * Prepare the vocabulary prefixes used in the list of resources.
     */
    protected function initPrefixesShort(): self
    {
        $prefixIris = [
            'o' => 'http://omeka.org/s/vocabs/o#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        ];

        $sql = <<<SQL
SELECT vocabulary.prefix, vocabulary.namespace_uri
FROM vocabulary
JOIN property ON property.vocabulary_id = vocabulary.id
JOIN value ON value.property_id = property.id
WHERE value.resource_id IN (:ids)
GROUP BY vocabulary.prefix
ORDER BY vocabulary.prefix ASC
;
SQL;

        if (in_array('item_sets', $this->resourceTypes)) {
            $ids = $this->api->search('item_sets', [], ['returnScalar' => 'id'])->getContent();
            $prefixIris += $this->connection->executeQuery($sql, ['ids' => $ids], ['ids' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY])->fetchAllKeyValue();
        }

        if (in_array('items', $this->resourceTypes)) {
            $ids = $this->api->search('items', $this->resourceQuery, ['returnScalar' => 'id'])->getContent();
            $prefixIris += $this->connection->executeQuery($sql, ['ids' => $ids], ['ids' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY])->fetchAllKeyValue();

            $indexMedia = in_array('media', $this->resourceTypes);
            if ($indexMedia) {
                $sql = <<<SQL
SELECT vocabulary.prefix, vocabulary.namespace_uri
FROM vocabulary
JOIN property ON property.vocabulary_id = vocabulary.id
JOIN value ON value.property_id = property.id
JOIN media ON media.id = value.resource_id
WHERE media.item_id IN (:ids)
GROUP BY vocabulary.prefix
ORDER BY vocabulary.prefix ASC
;
SQL;
                $prefixIris += $this->connection->executeQuery($sql, ['ids' => $ids], ['ids' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY])->fetchAllKeyValue();

                // Manage special prefixes.
                $sql = <<<SQL
SELECT media.renderer
FROM media
WHERE media.item_id IN (:ids)
GROUP BY media.renderer
ORDER BY media.renderer ASC
;
SQL;
                $renderers = $this->connection->executeQuery($sql, ['ids' => $ids], ['ids' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY])->fetchFirstColumn();
                /** @see \Omeka\Module::filterHtmlMediaJsonLd() */
                if (in_array('html', $renderers)) {
                    $prefixIris['o-cnt'] = 'http://www.w3.org/2011/content#';
                }
                /** @see \Omeka\Module::filterYoutubeMediaJsonLd() */
                if (in_array('youtube', $renderers)) {
                    $prefixIris['o-time'] = 'http://www.w3.org/2006/time#';
                }
            }
        }

        if ($this->rdfsLabel) {
            $prefixIris[strtok($this->rdfsLabel, ':')] = 'http://www.w3.org/2000/01/rdf-schema#';
        }

        ksort($prefixIris);
        $this->contextShort = $prefixIris;

        return $this;
    }
}
