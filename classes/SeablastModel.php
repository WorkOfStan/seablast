<?php

namespace Seablast\Seablast;

use Tracy\Debugger;

//use Webmozart\Assert\Assert;

class SeablastModel
{
    use \Nette\SmartObject;

    /** @var SeablastController */
    private $controller;

    public function __construct(SeablastController $controller)
    {
        $this->controller = $controller;
        Debugger::barDump($this->controller, 'Controller'); // debug
    }
}
