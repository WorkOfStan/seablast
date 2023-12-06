<?php

namespace Seablast\Seablast;

use Tracy\Debugger;
//use Webmozart\Assert\Assert;

class SeablastView
{
    use \Nette\SmartObject;

    /** @var SeablastModel */
    private $model;

    public function __construct(SeablastModel $model)
    {
        $this->model = $model;
        Debugger::barDump($this->model, 'model');
        $this->params = [];
        $this->params['model'] = $this->model;
        //echo ('<h1>Minimal model</h1>');
        //var_dump($this->model); // minimal
        $this->renderLatte();
    }

    private function getTemplatePath()
    {
        // todo - check file exists + inheritance
        return 'templates/' . 'template.latte';
    }

    private function renderLatte()
    {
        $latte = new \Latte\Engine;
        // cache directory
        $latte->setTempDirectory($this->model->cache());

        //$params = [ /* template variables */ ];
        // or $params = new TemplateParameters(/* ... */);

        // render to output
        $latte->render($this->getTemplatePath(), $this->params);
        // or render to variable
        //$output = $latte->renderToString('template.latte', $params);
    }
}
