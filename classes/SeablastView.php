<?php

namespace Seablast\Seablast;

//use Webmozart\Assert\Assert;

class SeablastView
{
    use \Nette\SmartObject;

    /** @var SeablastModel */
    private $model;

    public function __construct(SeablastModel $model)
    {
        $this->model = $model;
        echo ('<h1>Minimal model</h1>');
        var_dump($this->model); // minimal
    }
}
