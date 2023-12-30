<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Tracy\Debugger;
use Webmozart\Assert\Assert;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\Superglobals;
use stdClass;

class SeablastModel
{
    use \Nette\SmartObject;

    /** @var SeablastController */
    private $controller;
    /** @var string[] mapping of URL to processing */
    public $mapping;
    /** @var array<mixed>|stdClass TODO: use only object instead */
    private $viewParameters = []; // null;

    /**
     *
     * @param SeablastController $controller
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastController $controller, Superglobals $superglobals)
    {
        $this->controller = $controller;
        Debugger::barDump($this->controller, 'Controller'); // debug
        $this->mapping = $this->controller->mapping;
        if (isset($this->mapping['model'])) {
            $className = $this->mapping['model'];
            $m = new $className($this->controller->getConfiguration(), $superglobals);
            Assert::methodExists($m, 'knowledge', "{$className} model MUST have method knowledge()");
            $this->viewParameters = $m->knowledge();
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
     * @return array<mixed>|stdClass
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
