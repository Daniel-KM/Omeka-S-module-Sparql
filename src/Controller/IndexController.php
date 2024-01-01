<?php declare(strict_types=1);

namespace Sparql\Controller;

use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @todo Manage sparql query fully. See https://github.com/semsol/arc2/wiki/SPARQL-Endpoint-Setup
     */
    public function sparqlAction()
    {
        $sparqlSearch = $this->viewHelpers()->get('sparqlSearch');
        $result = $sparqlSearch(['as_array' => true]);
        if (!$result) {
            $message = new PsrMessage('The RDF triplestore is not available currently.'); // @translate
            $this->messenger()->addError($message);
            return (new ViewModel())
                ->setTemplate('sparql/index/error');
        }

        return new ViewModel($result);
    }
}
