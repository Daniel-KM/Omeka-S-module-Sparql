<?php declare(strict_types=1);

namespace SearchSparql\Job;

use EasyRdf\Graph;
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
     * RDF resource properties to keep in all cases.
     *
     * @var array
     */
    protected $propertyMeta = [
        '@context' => null,
        '@id' => null,
        '@type' => null,
        'o:resource_class' => null,
        'o:resource_template' => null,
    ];

    /**
     * Specific property prefixes.
     *
     * @var array
     */
    protected $propertyPrefixes = [
        'o' => 'http://omeka.org/s/vocabs/o#',
        'o-cnt' => 'http://www.w3.org/2011/content#',
        'o-time' => 'http://www.w3.org/2006/time#',
        // Add contexts used by easyrdf.
        'dc' => 'http://purl.org/dc/terms/',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ];

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
        $easyMeta = $services->get('EasyMeta');

        // The reference id is the job id for now.
        if (class_exists('Log\Stdlib\PsrMessage')) {
            $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
            $referenceIdProcessor->setReferenceId('searchsparql/indextriplestore/job_' . $this->job->getId());
            $this->logger->addProcessor($referenceIdProcessor);
        }

        // In Omeka, an event is needed to get all the vocabularies.
        $eventManager = $services->get('EventManager');
        $args = $eventManager->prepareArgs(['context' => []]);
        $eventManager->trigger('api.context', null, $args);
        $this->context = $args['context'] + $this->propertyPrefixes;
        ksort($this->context);

        $this->datasetName = 'triplestore';

        // Init properties.
        $this->properties = $easyMeta->propertyIds();

        // Prepare output path.
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->filepath = $basePath . '/triplestore/' . $this->datasetName . '.ttl';
        file_put_contents($this->filepath, '');

        $timeStart = microtime(true);

        $this->logger->notice(new Message(
            'Sparql dataset "%1$s" : start of indexing', // @translate
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

        $response = $this->api->search('item_sets', [], ['returnScalar' => 'id']);
        $total = $response->getTotalResults();

        $this->logger->info(new Message(
            'Sparql dataset "%1$s" : indexing %2$d item sets.', // @translate
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
                    'Sparql dataset "%1$s" : indexed %2$d/%3$d item sets.', // @translate
                    $this->datasetName, $i, $total
                ));
                $this->entityManager->clear();
            }
        }

        $this->entityManager->clear();

        // Step 3: adding items and attached media.

        $response = $this->api->search('items', [], ['returnScalar' => 'id']);
        $total = $response->getTotalResults();
        $totalMedias = $this->api->search('media')->getTotalResults();

        $this->logger->info(new Message(
            'Sparql dataset "%1$s" : indexing %2$d items and %3$d medias.', // @translate
            $this->datasetName, $total, $totalMedias
        ));

        $i = 0;
        foreach ($response->getContent() as $id) {
            /** @var \Omeka\Api\Representation\ItemRepresentation $item */
            $item = $this->api->read('items', ['id' => $id])->getContent();
            $this->storeResource($item);
            foreach ($item->media() as $media)  {
                $this->storeResource($media);
                ++$this->totalResults;
            }
            ++$this->totalResults;
            if (++$i % 100 === 0) {
                $this->logger->info(new Message(
                    'Sparql dataset "%1$s" : indexed %2$d/%3$d items.', // @translate
                    $this->datasetName, $i, $total
                ));
                $this->entityManager->clear();
            }
        }
        $this->entityManager->clear();

        return $this;
    }

    /**
     * Store a single resource in the triplestore.
     */
    protected function storeResource(AbstractResourceEntityRepresentation $resource): self
    {
        // Don't use jsonSerialize(), that serialize only first level.
        $json = json_decode(json_encode($resource), true);

        // Don't store specific metadata.
        $json = array_intersect_key($json, $this->propertyMeta + $this->properties);

        $id = $resource->apiUrl();
        $json['@context'] = $this->context;

        // Serialize the json as turtle.
        $graph = new Graph($id);
        $graph->parse(json_encode($json), 'jsonld', $id);
        $turtle = $graph->serialise('turtle');

        file_put_contents($this->filepath, $turtle . PHP_EOL, FILE_APPEND | LOCK_EX);

        return $this;
    }
}
