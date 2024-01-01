<?php declare(strict_types=1);

namespace SearchSparql\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'searchsparql_resource_types',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Limit indexation to specific resources', // @translate
                    'value_options' => [
                        'item sets' => 'Item sets', // @translate
                        'items' => 'Items', // @translate
                        'media' => 'Medias', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'searchsparql_resource_types',
                ],
            ])
            ->add([
                'name' => 'searchsparql_resource_query',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'label' => 'Limit indexation of items with a query', // @translate
                ],
                'attributes' => [
                    'id' => 'searchsparql_resource_query',
                ],
            ])
            ->add([
                'name' => 'searchsparql_fields_included',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Omeka metadata to include', // @translate
                    'value_options' => [
                        'o:resource_template' => 'Resource template', // @translate
                        'o:is_public' => 'Visibility', // @translate
                        'o:owner' => 'Owner',
                        // The class is automatically included as type of the
                        // resource according to json-ld representation.
                        // 'o:resource_class' => $resourceClass,
                        'o:thumbnail' => 'Thumbnail', // @translate
                        'o:title' => 'Title', // @translate
                        'rdfs:label' => 'Title as rdf label', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'searchsparql_fields_included',
                ],
            ])
            ->add([
                'name' => 'searchsparql_property_whitelist',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Limit indexation to specific properties (white list)', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'searchsparql_property_whitelist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'searchsparql_property_blacklist',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Skip indexation for specific properties (black list)', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'searchsparql_property_blacklist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'process_triplestore',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Index json-ld database in a triplestore', // @translate
                ],
                'attributes' => [
                    'id' => 'process_triplestore',
                    'value' => 'Index triplestore', // @translate
                ],
            ])
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'searchsparql_resource_types',
                'required' => false,
            ])
            ->add([
                'name' => 'searchsparql_fields_included',
                'required' => false,
            ])
            ->add([
                'name' => 'searchsparql_property_whitelist',
                'required' => false,
            ])
            ->add([
                'name' => 'searchsparql_property_blacklist',
                'required' => false,
            ])
        ;
    }
}
