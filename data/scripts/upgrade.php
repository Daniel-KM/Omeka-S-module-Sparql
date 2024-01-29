<?php declare(strict_types=1);

namespace Sparql;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var array $config
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$config = $services->get('Config');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.4.2', '<')) {
    if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.49')) {
        $message = new Message(
            'The module %1$s should be upgraded to version %2$s or later.', // @translate
            'Common', '3.4.49'
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
    }

    $indexes = $settings->get('sparql_indexes', []);
    $pos = array_search('arc2', $indexes);
    if ($pos !== false) {
        unset($indexes[$pos]);
        $indexes[] = 'db';
        $settings->set('sparql_indexes', $indexes);
    }

    $settings->set('sparql_endpoint', 'auto');
}
