<?php declare(strict_types=1);

namespace Sparql\Job;

use ARC2;
use ARC2_Store;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use Exception;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Job\AbstractJob;

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
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var array
     */
    protected $config;

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
     * @var array
     */
    protected $indexes;

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
    protected $totalErrors = 0;

    /**
     * @var int
     */
    protected $totalResults = 0;

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

    public function perform(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->config = $services->get('Config');
        $this->logger = $services->get('Omeka\Logger');
        $this->settings = $services->get('Omeka\Settings');
        $this->easyMeta= $services->get('EasyMeta');
        $this->connection = $services->get('Omeka\Connection');
        $this->entityManager = $services->get('Omeka\EntityManager');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('sparql/index_triplestore/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $this->datasetName = 'triplestore';

        // Define indexes to create.

        $this->indexes = $this->getArg('indexes', $this->settings->get('sparql_indexes', $this->config['sparql']['config']['sparql_indexes']));
        if (empty($this->indexes)) {
            $this->logger->warn(
                'Sparql dataset "{dataset}": no index defined. Existing indexes are kept.', // @translate
                ['dataset' => $this->datasetName]
            );
            return;
        }

        // Prepare output path.
        $basePath = $this->config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->filepath = $basePath . '/triplestore/' . $this->datasetName . '.ttl';

        if (!in_array('turtle', $this->indexes)) {
            if (!file_exists($this->filepath)) {
                $this->logger->warn(
                    'Sparql dataset "{dataset}": unable to index without triplestore.', // @translate
                    ['dataset' => $this->datasetName]
                );
                return;
            }
            if (!filesize($this->filepath)) {
                $this->logger->warn(
                    'Sparql dataset "{dataset}": unable to index an empty triplestore.', // @translate
                    ['dataset' => $this->datasetName]
                );
                return;
            }
        } else {
            file_put_contents($this->filepath, '');
        }

        if (in_array('turtle', $this->indexes)) {
            $this->initOptions();

            $this->logger->notice(
                'Sparql dataset "{dataset}": start of indexing triplestore.', // @translate
                ['dataset' => $this->datasetName]
            );

            $timeStart = microtime(true);

            $this->processindex();

            $timeTotal = (int) (microtime(true) - $timeStart);

            $this->logger->notice(
                'Sparql dataset "{dataset}": end of indexing. {total} resources indexed ({total_errors} errors). Execution time: {duration} seconds.', // @translate
                ['dataset' => $this->datasetName, 'total' => $this->totalResults, 'total_errors' => $this->totalErrors, 'duration' => $timeTotal]
            );
        }

        if (in_array('arc2', $this->indexes)) {
            $this->logger->notice(
                'Sparql dataset "{dataset}": start of indexing in Arc2.', // @translate
                ['dataset' => $this->datasetName]
            );

            $timeStart = microtime(true);

            $this->indexArc2();

            $timeTotal = (int) (microtime(true) - $timeStart);

            $this->logger->notice(
                'Sparql dataset "{dataset}": end of indexing in Arc2. Execution time: {duration} seconds.', // @translate
                ['dataset' => $this->datasetName, 'duration' => $timeTotal]
            );
        }
    }

    protected function initOptions(): self
    {
        $this->resourceTypes = $this->getArg('resource_types', $this->settings->get('sparql_resource_types', $this->config['sparql']['config']['sparql_resource_types']));

        $this->resourceQuery = $this->getArg('resource_query', $this->settings->get('sparql_resource_query', $this->config['sparql']['config']['sparql_resource_query']));
        if ($this->resourceQuery) {
            $query = [];
            parse_str((string) $this->resourceQuery, $query);
            $this->resourceQuery = $query;
        } else {
            $this->resourceQuery = [];
        }

        $this->resourcePublicOnly = !$this->getArg('resource_private', $this->settings->get('sparql_resource_private', $this->config['sparql']['config']['sparql_resource_private']));
        if ($this->resourcePublicOnly) {
            $this->resourceQuery['is_public'] = true;
        }

        $this->properties = $this->easyMeta->propertyIds();

        $this->propertyWhiteList = $this->getArg('property_whitelist', $this->settings->get('sparql_property_whitelist', $this->config['sparql']['config']['sparql_property_whitelist']));
        $this->propertyWhiteList = array_intersect_key(array_combine($this->propertyWhiteList, $this->propertyWhiteList), $this->properties);

        $this->propertyBlackList = $this->getArg('property_blacklist', $this->settings->get('sparql_property_blacklist', $this->config['sparql']['config']['sparql_property_blacklist']));
        $this->propertyBlackList = array_intersect_key(array_combine($this->propertyBlackList, $this->propertyBlackList), $this->properties);

        $this->initPrefixes();

        $fieldsIncluded = $this->getArg('fields_included', $this->settings->get('sparql_fields_included', $this->config['sparql']['config']['sparql_fields_included']));
        $pos = array_search('rdfs:label', $fieldsIncluded);
        if ($pos !== false) {
            $fieldsIncluded[$pos] = RdfNamespace::prefixOfUri('http://www.w3.org/2000/01/rdf-schema#') . ':label';
            $this->rdfsLabel = $fieldsIncluded[$pos];
        }
        $this->propertyMeta += array_flip($fieldsIncluded);

        $this->initPrefixesShort();

        $this->dataTypeWhiteList = $this->getArg('datatype_whitelist', $this->settings->get('sparql_datatype_whitelist', $this->config['sparql']['config']['sparql_datatype_whitelist']));
        $this->dataTypeWhiteList = array_combine($this->dataTypeWhiteList, $this->dataTypeWhiteList);
        $this->dataTypeBlackList = $this->getArg('datatype_blacklist', $this->settings->get('sparql_datatype_blacklist', $this->config['sparql']['config']['sparql_datatype_blacklist']));
        $this->dataTypeBlackList = array_combine($this->dataTypeBlackList, $this->dataTypeBlackList);

        if (in_array('media', $this->resourceTypes) && !in_array('items', $this->resourceTypes)) {
            $this->logger->warn(
                'Sparql dataset "{dataset}": Medias cannot be indexed without indexing items.', // @translate
                ['dataset' => $this->datasetName]
            );
        }

        return $this;
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

            $this->logger->info(
                'Sparql dataset "{dataset}": indexing {total} item sets.', // @translate
                ['dataset' => $this->datasetName, 'total' => $total]
            );

            $i = 0;
            foreach ($response->getContent() as $id) {
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $itemSet */
                $itemSet = $this->api->read('item_sets', ['id' => $id])->getContent();
                $this->storeResource($itemSet);
                ++$this->totalResults;
                if (++$i % 100 === 0) {
                    if ($this->shouldStop()) {
                        $this->logger->warn(
                            'Sparql dataset "{dataset}": The job was stopped. Indexed {count}/{total} item sets.', // @translate
                            ['dataset' => $this->datasetName, 'count' => $i, 'total' => $total]
                        );
                        return $this;
                    }
                    $this->logger->info(
                        'Sparql dataset "{dataset}": indexed {count}/{total} item sets.', // @translate
                        ['dataset' => $this->datasetName, 'count' => $i, 'total' => $total]
                    );
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
                $this->logger->info(
                    'Sparql dataset "{dataset}": indexing {total} items and {total_medias} medias.', // @translate
                    ['dataset' => $this->datasetName, 'total' => $total, 'total_medias' => $totalMedias]
                );
                */
                $this->logger->info(
                    'Sparql dataset "{dataset}": indexing {total} items and attached medias.', // @translate
                    ['dataset' => $this->datasetName, 'total' => $total]
                );
            } else {
                $this->logger->info(
                    'Sparql dataset "{dataset}": indexing {total} items.', // @translate
                    ['dataset' => $this->datasetName, 'total' => $total]
                );
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
                    if ($this->shouldStop()) {
                        $this->logger->warn(
                            'Sparql dataset "{dataset}": The job was stopped. Indexed {count}/{total} items.', // @translate
                            ['dataset' => $this->datasetName, 'count' => $i, 'total' => $total]
                        );
                        return $this;
                    }
                    $this->logger->info(
                        'Sparql dataset "{dataset}": indexed {count}/{total} items.', // @translate
                        ['dataset' => $this->datasetName, 'count' => $i, 'total' => $total]
                    );
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
        // Don't use jsonSerialize(), that serializes only first level.
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

        $graph = new Graph($id);
        try {
            $graph->parse(json_encode($json), 'jsonld', $id);
        } catch (Exception $e) {
            $this->logger->warn(
                'Sparql dataset "{dataset}": The {resource_type} #{resource_id} cannot be indexed: {message}', // @translate
                ['dataset' => $this->datasetName, 'resource_type' => $resource->resourceName(), 'resource_id' => $resource->id(), 'message' => $e->getMessage()]
            );
            ++$this->totalErrors;
            return $this;
        }

        // Serialize the json as turtle.
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
        // TODO Set the default vocabulary @vocab first but easyrdf returns error.
        $this->context = [
            // '@vocab' => 'http://omeka.org/s/vocabs/o#',
        ];

        // In Omeka, an event is needed to get all the vocabularies.
        $eventManager = $this->getServiceLocator()->get('EventManager');
        $args = $eventManager->prepareArgs(['context' => []]);
        $eventManager->trigger('api.context', null, $args);
        $this->context += $args['context'] + $this->vocabularyIris;
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

        // TODO Manage module Data Type Geometry?
        // http://www.opengis.net/ont/geosparql

        if ($this->rdfsLabel) {
            $prefixIris[strtok($this->rdfsLabel, ':')] = 'http://www.w3.org/2000/01/rdf-schema#';
        }

        ksort($prefixIris);
        $this->contextShort = $prefixIris;

        return $this;
    }

    /**
     * Help on getStoreEndpoint() and getStore().
     * @see https://github.com/semsol/arc2/wiki/Getting-started-with-ARC2
     * @see https://github.com/semsol/arc2/wiki/SPARQL-Endpoint-Setup
     *
     * @see \Sparql\Controller\IndexController::getSparqlTriplestore()
     * @see \Sparql\Job\IndexTriplestore::indexArc2()
     */
    protected function indexArc2(): self
    {
        if (!file_exists($this->filepath) || !is_readable($this->filepath)) {
            $this->logger->warn(
                'Sparql dataset "{dataset}": A triplestore is required first to index in arc2.', // @translate
                ['dataset' => $this->datasetName]
            );
            return $this;
        }
        if (!filesize($this->filepath)) {
            $this->logger->warn(
                'Sparql dataset "{dataset}": The triplestore is empty.', // @translate
                ['dataset' => $this->datasetName]
            );
            return $this;
        }

        /** @var \ARC2_TurtleParser $parser */
        /*
         $parser = ARC2::getRDFParser();
         $parser->parse($this->filepath);
         $triples = $parser->getTriples();
         */

        $writeKey = $this->settings->get('sparql_arc2_write_key') ?: '';

        // Endpoint configuration.
        $db = $this->connection->getParams();
        $configArc2 = [
            // Database.
            'db_host' => $db['host'],
            'db_name' => $db['dbname'],
            'db_user' => $db['user'],
            'db_pwd' => $db['password'],

            // Network.
            // 'proxy_host' => '192.168.1.1',
            // 'proxy_port' => 8080,
            // Parsers.
            // 'bnode_prefix' => 'bn',
            // Semantic html extraction.
            // 'sem_html_formats' => 'rdfa microformats',

            // Store name.
            'store_name' => $this->datasetName,

            // Stop after 100 errors.
            'max_errors' => 100,

            // Endpoint.
            'endpoint_features' => [
                // Read requests.
                'select',
                'construct',
                'ask',
                'describe',
                // Write requests.
                'load',
                'insert',
                'delete',
                // Dump is a special command for streaming SPOG export.
                'dump',
            ],

            // Not implemented in ARC2 preview.
            'endpoint_timeout' => 60,
            'endpoint_read_key' => '',
            'endpoint_write_key' => $writeKey,
            'endpoint_max_limit' => \Omeka\Stdlib\Paginator::PER_PAGE,
        ];

        try {
            /** @var \ARC2_Store $store */
            $store = ARC2::getStore($configArc2);
            $store->createDBCon();
            if (!$store->isSetUp()) {
                $store->setUp();
            }
        } catch (Exception $e) {
            $this->logger->err($e);
            return $this;
        }

        try {
            $store->reset(true);
            $store->query("LOAD <file://{$this->filepath}>");
        } catch (Exception $e) {
            $this->logger->err($e);
            return $this;
        }

        $errors = $store->getErrors();
        if ($errors) {
            $this->logger->err(implode("\n", $errors));
        }

        return $this;
    }
}
