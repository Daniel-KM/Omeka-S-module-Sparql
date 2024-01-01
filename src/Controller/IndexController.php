<?php declare(strict_types=1);

namespace Sparql\Controller;

use ARC2;
use ARC2_Store;
use Common\Stdlib\PsrMessage;
use Doctrine\DBAL\Connection;
use EasyRdf\RdfNamespace;
use Exception;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function __construct(string $basePath, Connection $connection)
    {
        $this->basePath = $basePath;
        $this->connection = $connection;
    }

    /**
     * @todo Manage sparql query fully. See https://github.com/semsol/arc2/wiki/SPARQL-Endpoint-Setup
     */
    public function sparqlAction()
    {
        /** @var \ARC2_Store $triplestore */
        $triplestore = $this->getSparqlTriplestore();
        if (!$triplestore) {
            $message = new PsrMessage('The RDF triplestore is not available currently.'); // @translate
            $this->messenger()->addError($message);
            return (new ViewModel())
                ->setTemplate('sparql/index/error');
        }

        // Allow query via post and get for end user and view simplicity.
        $data = $this->params()->fromPost() ?: $this->params()->fromQuery();

        $form = $this->getForm(\Sparql\Form\SparqlForm::class);
        $query = null;
        $result = null;
        $resultArc2 = null;
        $format = null;
        $namespaces = $this->prepareNamespaces();
        $errorMessage = null;

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
                        $prefixes .= "PREFIX $prefix: <$iri>";
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
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'form' => $form,
            'query' => $query,
            'result' => $result,
            'resultArc2' => $resultArc2,
            'format' => $format,
            'namespaces' => $namespaces,
            'errorMessage' => $errorMessage,
        ]);
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
        $writeKey = $this->settings()->get('sparql_arc2_write_key') ?: '';

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
                'select',
                // 'construct',
                'ask',
                'describe',
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

        if (in_array('rdfs:label', $this->settings()->get('sparql_fields_included', []))) {
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
