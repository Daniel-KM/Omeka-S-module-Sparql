<?php declare(strict_types=1);

namespace Sparql;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;

/**
 * Sparql
 *
 * @copyright Daniel Berthereau, 2023-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    use TraitModule;

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /**
         * @var \Omeka\Permissions\Acl $acl
         * @see \Omeka\Service\AclFactory
         */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $acl
            // Anybody can use the sparql endpoint.
            // TODO Use credentials in sparql like api.
            ->allow(
                null,
                [\Sparql\Controller\SparqlController::class],
                ['error', 'sparql']
            )
        ;
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $translator = $services->get('MvcTranslator');

        if (!$this->checkDestinationDir($basePath . '/triplestore')) {
            $message = new PsrMessage(
                'The directory "{directory}" is not writeable.', // @translate
                ['directory' => $basePath . '/triplestore']
            );
            throw new ModuleCannotInstallException((string) $message->setTranslator($translator));
        }
    }

    protected function postUninstall(): void
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $dirPath = $basePath . '/encyclopedia';
        $this->rmDir($dirPath);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        if (!$this->handleConfigFormAuto($controller)) {
            return false;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        if (!$settings->get('sparql_arc2_write_key')) {
            $writeKey = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(128))), 0, 24);
            $settings->set('sparql_arc2_write_key', $writeKey);
        }

        $params = $controller->getRequest()->getPost();
        if (empty($params['process'])) {
            return true;
        }

        $config = $services->get('Config');
        $plugins = $services->get('ControllerPluginManager');
        $urlPlugin = $plugins->get('url');
        $messenger = $plugins->get('messenger');

        $configModule = $config['sparql']['config'];
        $args = [
            'resource_types' => $settings->get('sparql_resource_types', $configModule['sparql_resource_types']),
            'resource_query' => $settings->get('sparql_resource_query', $configModule['sparql_resource_query']),
            'fields_included' => $settings->get('sparql_fields_included', $configModule['sparql_fields_included']),
            'property_whitelist' => $settings->get('sparql_property_whitelist', $configModule['sparql_property_whitelist']),
            'property_blacklist' => $settings->get('sparql_property_blacklist', $configModule['sparql_property_blacklist']),
            'datatype_whitelist' => $settings->get('sparql_datatype_whitelist', $configModule['sparql_datatype_whitelist']),
            'datatype_blacklist' => $settings->get('sparql_datatype_blacklist', $configModule['sparql_datatype_blacklist']),
            'indexes' => $settings->get('sparql_indexes', $configModule['sparql_indexes']),
        ];

        if (!in_array('html', $args['datatype_blacklist']) || !in_array('xml', $args['datatype_blacklist'])) {
            $message = new PsrMessage(
                'The data types html and xml are currently not supported and converted into literal.' // @translate
            );
            $messenger->addWarning($message);
        }

        // Use synchronous dispatcher for quick testing purpose.
        $strategy = null;
        $strategy = $strategy === 'synchronous'
            ? $this->getServiceLocator()->get(\Omeka\Job\DispatchStrategy\Synchronous::class)
            : null;

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\Sparql\Job\IndexTriplestore::class, $args, $strategy);

        $message = new PsrMessage(
            'Indexing json-ld triplestore in background ({link_job}job #{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
            [
                'link_job' => sprintf('<a href="%s">',
                    htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => sprintf('<a href="%s">',
                    htmlspecialchars($this->isModuleActive('Log')
                        ? $urlPlugin->fromRoute('admin/log', [], ['query' => ['job_id' => $job->getId()]])
                        : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId(), 'action' => 'log'])
                    )
                )
            ]
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);
        return true;
    }
}
