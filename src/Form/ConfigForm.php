<?php declare(strict_types=1);

namespace SearchSparql\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
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
    }
}
