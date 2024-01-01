<?php declare(strict_types=1);

namespace SearchSparql;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\Mvc\Controller\AbstractController;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Stdlib\Message;

/**
 * Search Sparql
 *
 * @copyright Daniel Berthereau, 2023-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    use TraitModule;

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $translator = $services->get('MvcTranslator');

        if (!$this->checkDestinationDir($basePath . '/triplestore')) {
            $message = new Message(
                $translator->translate('The directory "%s" is not writeable.'), // @translate
                $basePath . '/triplestore'
            );
            throw new ModuleCannotInstallException((string) $message);
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

        $params = $controller->getRequest()->getPost();
        if (empty($params['process_triplestore'])) {
            return true;
        }

        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $plugins = $services->get('ControllerPluginManager');
        $settings = $services->get('Omeka\Settings');
        $urlPlugin = $plugins->get('url');
        $messenger = $plugins->get('messenger');

        $configModule = $config['searchsparql']['config'];
        $args = [
            'resource_types' => $settings->get('searchsparql_resource_types', $configModule['searchsparql_resource_types']),
            'resource_query' => $settings->get('searchsparql_resource_query', $configModule['searchsparql_resource_query']),
            'fields_included' => $settings->get('searchsparql_fields_included', $configModule['searchsparql_fields_included']),
            'property_whitelist' => $settings->get('searchsparql_property_whitelist', $configModule['searchsparql_property_whitelist']),
            'property_blacklist' => $settings->get('searchsparql_property_blacklist', $configModule['searchsparql_property_blacklist']),
            'datatype_whitelist' => $settings->get('searchsparql_datatype_whitelist', $configModule['searchsparql_datatype_whitelist']),
            'datatype_blacklist' => $settings->get('searchsparql_datatype_blacklist', $configModule['searchsparql_datatype_blacklist']),
        ];

        // Use synchronous dispatcher for quick testing purpose.
        $strategy = null;
        $strategy = $strategy === 'synchronous'
            ? $this->getServiceLocator()->get(\Omeka\Job\DispatchStrategy\Synchronous::class)
            : null;

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\SearchSparql\Job\IndexTriplestore::class, $args, $strategy);

        $message = new Message(
            'Indexing json-ld triplestore in background (%1$sjob #%2$d%3$s, %4$slogs%3$s).', // @translate
            sprintf('<a href="%s">',
                htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
            ),
            $job->getId(),
            '</a>',
            sprintf('<a href="%s">',
                htmlspecialchars($this->isModuleActive('Log')
                    ? $urlPlugin->fromRoute('admin/log', [], ['query' => ['job_id' => $job->getId()]])
                    : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId(), 'action' => 'log'])
                )
            )
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);
        return true;
    }
}
