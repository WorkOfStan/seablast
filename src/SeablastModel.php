<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Tracy\Debugger;
use Webmozart\Assert\Assert;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\Superglobals;
use stdClass;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

class SeablastModel
{
    use \Nette\SmartObject;

    /** @var SeablastController */
    private $controller;
    /** @ var string[] mapping of URL to processing */
    //public $mapping;
    /** @var stdClass */
    private $viewParameters!

    /**
     *
     * @param SeablastController $controller
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastController $controller, Superglobals $superglobals)
    {
        $this->controller = $controller;
        Debugger::barDump($this->controller, 'Controller'); // debug
        //$this->mapping = $this->controller->mapping;
        // todo $this->mapping is redundant
        if (isset($this->controller->mapping['model'])) {
            $className = $this->controller->mapping['model'];
            $model = new $className($this->controller->getConfiguration(), $superglobals);
            Assert::methodExists($model, 'knowledge', "{$className} model MUST have method knowledge()");
            $this->viewParameters = $model->knowledge();
        // todo pokud potřeba
        //} else {
        //    $this->viewParemeters = new stdClass();
        }
        // CSRF token to be used by view
        $csrfTokenManager = new CsrfTokenManager();
        $this->viewParameters->csrfToken = $csrfTokenManager->getToken('sb_json');
        // todo kdy invalidovat? určitě při logout
    }

    /**
     *
     * @return SeablastConfiguration
     */
    public function getConfiguration(): SeablastConfiguration
    {
        return $this->controller->getConfiguration();
    }

    /**
     * Parameters for Latte render (yes, Latte supports object even for PHP7.2 in 2.x latest)
     * @return stdClass
     */
    public function getParameters()
    {
        //if (is_null($this->viewParameters)) {
        //    // no parameters
        //    return [];
        //}
        return $this->viewParameters;
    }
}
