<?php declare(strict_types=1);

namespace Sparql\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class SparqlForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'form-sparql')
            ->add([
                'name' => 'query',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Query', // @translate
                ],
                'attributes' => [
                    'id' => 'query',
                    'rows' => 10,
                ],
            ])
            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'submit',
                    'form' => 'form-sparql',
                    'value' => 'Search', // @translate
                ],
            ])
        ;
    }
}
