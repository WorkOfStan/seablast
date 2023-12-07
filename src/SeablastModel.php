<?php

namespace Seablast\Seablast;

use Tracy\Debugger;

//use Webmozart\Assert\Assert;

class SeablastModel
{
    use \Nette\SmartObject;

    /** @var string[] mapping of URL to processing */
    public $collection;
    /** @var SeablastController */
    private $controller;

    public function __construct(SeablastController $controller)
    {
        $this->controller = $controller;
        Debugger::barDump($this->controller, 'Controller'); // debug
        $this->collection = $this->controller->collection;
    }

    /**
     *
     * @return SeablastConfiguration
     */
    public function getConfiguration(): SeablastConfiguration
    {
        return $this->controller->getConfiguration();
    }
}
