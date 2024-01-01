<?php declare(strict_types=1);

namespace Sparql;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\SparqlForm::class => Form\SparqlForm::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\IndexController::class => Service\Controller\IndexControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'sparql' => [
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/sparql',
                    'defaults' => [
                        '__NAMESPACE__' => 'Sparql\Controller',
                        'controller' => Controller\IndexController::class,
                        'action' => 'sparql',
                    ],
                ],
            ],
        ],
    ],
    'sparql' => [
        'config' => [
            'sparql_resource_types' => [
                'item_sets',
                'items',
                // 'media',
            ],
            'sparql_resource_query' => '',
            'sparql_resource_private' => false,
            'sparql_fields_included' => [
                // 'o:owner',
                // 'o:is_public',
                // The class is automatically included as type of the
                // resource according to json-ld representation.
                'o:resource_class',
                'o:resource_template',
                'o:thumbnail',
                // 'o:title',
                'rdfs:label',
            ],
            'sparql_property_whitelist' => [
            ],
            'sparql_property_blacklist' => [
                'dcterms:tableOfContents',
                'bibo:content',
                'extracttext:extracted_text',
            ],
            'sparql_datatype_whitelist' => [
            ],
            'sparql_datatype_blacklist' => [
                'html',
                'xml',
            ],
            'sparql_indexes' => [
                'turtle',
                'arc2',
                // 'fuseki',
            ],
            // TODO Manage api credentials for arc2.
            'sparql_arc2_write_key' => '',
        ],
    ],
];
