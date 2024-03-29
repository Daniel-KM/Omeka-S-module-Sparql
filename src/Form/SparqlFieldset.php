<?php declare(strict_types=1);

namespace Sparql\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SparqlFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][heading]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Block title', // @translate
                ],
                'attributes' => [
                    'id' => 'sparql_heading',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][interface]',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Interface', // @translate
                    'value_options' => [
                        'default' => 'Simple (internal engine)', // @translate
                        'yasgui' => 'Yasgui', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'sparql_yasgui',
                ],
            ])
        ;
        if (class_exists('BlockPlus\Form\Element\TemplateSelect')) {
            $this
                ->add([
                    'name' => 'o:block[__blockIndex__][o:data][template]',
                    'type' => \BlockPlus\Form\Element\TemplateSelect::class,
                    'options' => [
                        'label' => 'Template to display', // @translate
                        'info' => 'Templates are in folder "common/block-layout" of the theme and should start with "sparql".', // @translate
                        'template' => 'common/block-layout/sparql',
                    ],
                    'attributes' => [
                        'class' => 'chosen-select',
                    ],
                ]);
        }
    }
}
