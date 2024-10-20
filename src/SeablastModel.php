<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Tracy\Debugger;
use Webmozart\Assert\Assert;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\Superglobals;
use stdClass;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

class SeablastModel
{
    use \Nette\SmartObject;

    /** @var SeablastController */
    private $controller;
    /** @var string[] mapping of URL to processing accessible in SeablastView */
    public $mapping;
    /** @var stdClass */
    private $viewParameters;

    /**
     * @param SeablastController $controller
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastController $controller, Superglobals $superglobals)
    {
        $this->controller = $controller;
        Debugger::barDump($this->controller, 'Controller in SeablastModel'); // debug
        $this->mapping = $this->controller->mapping;
        if (isset($this->mapping['model'])) {
            $className = $this->mapping['model'];
            $model = new $className($this->controller->getConfiguration(), $superglobals);
            Assert::methodExists($model, 'knowledge', "{$className} model MUST have method knowledge()");
            $this->viewParameters = $model->knowledge();
            Assert::object($this->viewParameters, '$this->viewParameters MUST be an object');
        } else {
            // so that csrfToken can be added
            $this->viewParameters = new stdClass();
        }
        // CSRF token to be used by view
        $csrfTokenManager = new CsrfTokenManager();
        $this->viewParameters->csrfToken = $csrfTokenManager->getToken('sb_json')->getValue();
        // todo kdy invalidovat? určitě při logout
    }

    /**
     * @return SeablastConfiguration
     */
    public function getConfiguration(): SeablastConfiguration
    {
        return $this->controller->getConfiguration();
    }

    /**
     * Parameters for Latte render (yes, Latte supports object even for PHP7.2 in 2.x latest).
     *
     * @return stdClass
     */
    public function getParameters()
    {
        return $this->viewParameters;
    }
}
