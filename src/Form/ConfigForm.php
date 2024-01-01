<?php declare(strict_types=1);

namespace Sparql\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'sparql_resource_types',
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
                    'id' => 'sparql_resource_types',
                ],
            ])
            ->add([
                'name' => 'sparql_resource_query',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'label' => 'Limit indexation of items with a query', // @translate
                ],
                'attributes' => [
                    'id' => 'sparql_resource_query',
                ],
            ])
            ->add([
                'name' => 'sparql_resource_private',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Output private resources and values', // @translate
                ],
                'attributes' => [
                    'id' => 'sparql_resource_private',
                ],
            ])
            ->add([
                'name' => 'sparql_fields_included',
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
                    'id' => 'sparql_fields_included',
                ],
            ])
            ->add([
                'name' => 'sparql_property_whitelist',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Limit indexation to specific properties (white list)', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'sparql_property_whitelist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'sparql_property_blacklist',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Skip indexation for specific properties (black list)', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'sparql_property_blacklist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'sparql_datatype_whitelist',
                'type' => OmekaElement\DataTypeSelect::class,
                'options' => [
                    'label' => 'Limit indexation to specific data types (white list)', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'sparql_datatype_whitelist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select data types…', // @translate
                ],
            ])
            ->add([
                'name' => 'sparql_datatype_blacklist',
                'type' => OmekaElement\DataTypeSelect::class,
                'options' => [
                    'label' => 'Skip indexation for specific data types (black list)', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'sparql_datatype_blacklist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select data types…', // @translate
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
                'name' => 'sparql_resource_types',
                'required' => false,
            ])
            ->add([
                'name' => 'sparql_fields_included',
                'required' => false,
            ])
            ->add([
                'name' => 'sparql_property_whitelist',
                'required' => false,
            ])
            ->add([
                'name' => 'sparql_property_blacklist',
                'required' => false,
            ])
            ->add([
                'name' => 'sparql_datatype_whitelist',
                'required' => false,
            ])
            ->add([
                'name' => 'sparql_datatype_blacklist',
                'required' => false,
            ])
        ;
    }
}
