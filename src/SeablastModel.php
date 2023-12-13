<?php

namespace Seablast\Seablast;

use Tracy\Debugger;
use Webmozart\Assert\Assert;

class SeablastModel
{
    use \Nette\SmartObject;

    /** @var string[] mapping of URL to processing */
    public $mapping;
    /** @var SeablastController */
    private $controller;
    /** @var array<mixed> TODO: use object instead */
    private $viewParameters = []; // null;

    public function __construct(SeablastController $controller)
    {
        $this->controller = $controller;
        Debugger::barDump($this->controller, 'Controller'); // debug
        $this->mapping = $this->controller->mapping;
        if (isset($this->mapping['model'])) {
            $className = $this->mapping['model'];
            $m = new $className();
            Assert::methodExists($m, 'getParameters', "{$className} model MUST have method getParameters()");
            $this->viewParameters = $m->getParameters();
        }
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
     * TODO: change to object as parameters for Latte render (yes, Latte supports object even for PHP7.2 in 2.x latest)
     * @return array<mixed>
     */
    public function getParameters(): array
    {
        //if (is_null($this->viewParameters)) {
        //    // no parameters
        //    return [];
        //}
        return $this->viewParameters;
    }
}
