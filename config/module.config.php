<?php declare(strict_types=1);

namespace Sparql;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'sparqlSearch' => Service\ViewHelper\SparqlSearchFactory::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'sparql' => Site\BlockLayout\Sparql::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\SparqlForm::class => Form\SparqlForm::class,
            Form\SparqlFieldset::class => Form\SparqlFieldset::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\SparqlController::class => Controller\SparqlController::class,
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
                        'controller' => Controller\SparqlController::class,
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
                // 'turtle',
                // 'arc2',
                // 'fuseki',
            ],
            // TODO Manage api credentials for arc2.
            'sparql_arc2_write_key' => '',
            'sparql_limit_per_page' => 250,
        ],
        'block_settings' => [
            'sparql' => [
                'heading' => null,
                'interface' => 'default',
                'template' => '',
            ],
        ],
    ],
];
