<?php declare(strict_types=1);

namespace Sparql\Controller;

use ARC2;
use ARC2_Store;
use Common\Stdlib\PsrMessage;
use Exception;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var string
     */
    protected $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
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
}
