<?php declare(strict_types=1);

namespace Sparql;

return [
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
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
                'o:resource_template',
                // 'o:is_public',
                // 'o:owner',
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
        ],
    ],
];
