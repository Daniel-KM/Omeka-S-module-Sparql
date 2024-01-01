<?php declare(strict_types=1);

namespace SearchSparql;

return [
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'searchsparql' => [
        'config' => [
            'searchsparql_resource_types' => [
                'item_sets',
                'items',
                // 'media',
            ],
            'searchsparql_resource_query' => '',
            'searchsparql_property_whitelist' => [
            ],
            'searchsparql_property_blacklist' => [
                'dcterms:tableOfContents',
                'bibo:content',
                'extracttext:extracted_text',
            ],
        ],
    ],
];
