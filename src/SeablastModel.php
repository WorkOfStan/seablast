<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\SeablastController;
use Seablast\Seablast\Superglobals;
use stdClass;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Tracy\Debugger;
use Tracy\ILogger;
use Webmozart\Assert\Assert;

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
            /** @phpstan-ignore staticMethod.alreadyNarrowedType */
            Assert::string($className);
            Assert::true(class_exists($className), "Class {$className} does not exist.");
            try {
                $model = new $className($this->controller->getConfiguration(), $superglobals);
            } catch (Exceptions\DbmsException $e) {
                // make sure that the database Tracy BarPanel is displayed when DbmsException is thrown in the model
                $this->controller->getConfiguration()->showSqlBarPanel();
                throw new Exceptions\DbmsException($e->getMessage(), $e->getCode(), $e);
            }
            Assert::methodExists($model, 'knowledge', "{$className} model MUST have method knowledge()");
            try {
                $knowledge = $model->knowledge();
                Assert::isInstanceOf($knowledge, \stdClass::class);
                $this->viewParameters = $knowledge;
            } catch (Exceptions\DbmsException $e) {
                // make sure that the database Tracy BarPanel is displayed when DbmsException is thrown
                $this->controller->getConfiguration()->showSqlBarPanel();
                throw new Exceptions\DbmsException($e->getMessage(), $e->getCode(), $e);
            }
            Debugger::log('knowledge of ' . $className . ': ' . print_r($this->viewParameters, true), ILogger::DEBUG);
            Assert::isAOf($this->viewParameters, 'stdClass', "The knowledge of {$className} MUST be of stdClass type.");
        } else {
            Debugger::log('No model, no knowledge.', ILogger::DEBUG);
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
    public function getParameters(): stdClass
    {
        return $this->viewParameters;
    }
}
