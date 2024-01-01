<?php declare(strict_types=1);

namespace Sparql\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use ARC2;
use ARC2_Store;
use Doctrine\DBAL\Connection;
use EasyRdf\RdfNamespace;
use Exception;
use Laminas\Form\FormElementManager;
use Laminas\Mvc\Controller\Plugin\Params;
use Omeka\Mvc\Controller\Plugin\CurrentSite;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Settings\Settings;

class SparqlSearch extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/sparql-search';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\CurrentSite
     */
    protected $currentSite;

    /**
     * @var \Laminas\Form\FormElementManager
     */
    protected $formManager;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Messenger;
     */
    protected $messenger;

    /**
     * @var \Laminas\Mvc\Controller\Plugin\Params
     */
    protected $params;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var string
     */
    protected $basePath;

    public function __construct(
        Connection $connection,
        CurrentSite $currentSite,
        FormElementManager $formManager,
        Messenger $messenger,
        Params $params,
        Settings $settings,
        string $basePath
    ) {
        $this->connection = $connection;
        $this->currentSite = $currentSite;
        $this->formManager = $formManager;
        $this->messenger = $messenger;
        $this->params = $params;
        $this->settings = $settings;
        $this->basePath = $basePath;
    }

    /**
     * Display the sparql search form.
     *
     * @param array $options
     * - template (string)
     * - method (string): get (default) or post
     * - as_array (bool): return results as array
     * @return string|array Html string or result array.
     */
    public function __invoke(array $options = [])
    {
        $asArray = !empty($options['as_array']);

        /** @var \ARC2_Store $triplestore */
        $triplestore = $this->getSparqlTriplestore();
        if (!$triplestore) {
            return $asArray ? [] : '';
        }

        $view = $this->getView();

        $result = $this->sparqlQueryTriplestore($triplestore);
        $result['options'] = $options;

        if (!empty($options['method'])) {
            $result['form']->setAttribute('method', $options['method']);
        }

        if ($asArray) {
            return $result;
        }

        $template = empty($options['template']) ? self::PARTIAL_NAME : $options['template'];

        return $view->partial($template, $result);
    }

    /**
     * Help on getStoreEndpoint() and getStore().
     * @see https://github.com/semsol/arc2/wiki/Getting-started-with-ARC2
     * @see https://github.com/semsol/arc2/wiki/SPARQL-Endpoint-Setup
     *
     * @see \Sparql\Controller\IndexController::getSparqlTriplestore()
     * @see \Sparql\Job\IndexTriplestore::indexArc2()
     */
    protected function getSparqlTriplestore(): ?ARC2_Store
    {
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
            'store_name' => 'triplestore',

            // Endpoint.
            'endpoint_features' => [
                // Read requests.
                'select',
                'construct',
                'ask',
                'describe',
                // Write requests.
                // 'load',
                // 'insert',
                // 'delete',
                // Dump is a special command for streaming SPOG export.
                // 'dump',
            ],

            // TODO Add read/write key via Omeka credentials.
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
            $this->logger()->err($e);
            return null;
        }

        return $store;
    }

    protected function sparqlQueryTriplestore(ARC2_STORE $triplestore): array
    {
        $form = $this->formManager->get(\Sparql\Form\SparqlForm::class);
        $query = null;
        $result = null;
        $resultArc2 = null;
        $format = null;
        $namespaces = $this->prepareNamespaces();
        $errorMessage = null;

        // Allow query via post and get for end user and view simplicity.
        // It is required by sparql protocol anyway.
        $data = $this->params->fromPost()
            ?: $this->params->fromQuery();

        if ($data) {
            $form->setData($data);
            if ($form->isValid()) {
                $query = $data['query'] ?? null;
                $query = is_string($query) && trim($query) !== ''
                    ? trim($query)
                    :null;
                $format = ($data['format'] ?? null) === 'text' ? 'text' : 'html';
                if ($query) {
                    // TODO Check prepending prefixes: arc2 should work without them.
                    // Prepend all prefixes: only common ones are set.
                    $prefixes = '';
                    foreach ($namespaces as $prefix => $iri) {
                        // Only the addition of prefixes in the query works.
                        $triplestore->setPrefix($prefix, $iri);
                        $prefixes .= "PREFIX $prefix: <$iri>\n";
                    }
                    try {
                        $resultArc2 = $triplestore->query($prefixes . $query);
                        $errors = $triplestore->getErrors();
                        if ($errors) {
                            $errorMessage = implode("\n", $errors);
                        }
                        // TODO Else preprocess result to get a simple table?
                    } catch (Exception $e) {
                        $errorMessage = $e->getMessage();
                    }
                }
        } else {
                $this->messenger->addFormErrors($form);
            }
        }

        return [
            'site' => $this->currentSite->__invoke(),
            'form' => $form,
            'query' => $query,
            'result' => $result,
            'resultArc2' => $resultArc2,
            'format' => $format,
            'namespaces' => $namespaces,
            'errorMessage' => $errorMessage,
        ];
    }

    /**
     * Prepare all vocabulary prefixes used in the database.
     *
     * @todo Use context to create the list of prefixes and iris?
     * @see \Sparql\Job\IndexTriplestore::initPrefixesShort()
     */
    protected function prepareNamespaces(): array
    {
        $prefixIris = [
            'o' => 'http://omeka.org/s/vocabs/o#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        ];

        if (in_array('rdfs:label', $this->settings->get('sparql_fields_included', []))) {
            $prefixIris['rdfs'] = 'http://www.w3.org/2000/01/rdf-schema#';
        }

        $sql = <<<SQL
SELECT vocabulary.prefix, vocabulary.namespace_uri
FROM vocabulary
JOIN property ON property.vocabulary_id = vocabulary.id
JOIN value ON value.property_id = property.id
GROUP BY vocabulary.prefix
ORDER BY vocabulary.prefix ASC
;
SQL;
        $prefixIris += $this->connection->executeQuery($sql)->fetchAllKeyValue();

        foreach ($prefixIris as $prefix => $iri) {
            RdfNamespace::set($prefix, $iri);
        }

        ksort($prefixIris);

        return $prefixIris;
    }
}
