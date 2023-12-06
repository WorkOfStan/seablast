<?php

namespace Seablast\Seablast;

use Tracy\Debugger;

//use Webmozart\Assert\Assert;

class SeablastView
{
    use \Nette\SmartObject;

    /** @var SeablastModel */
    private $model;

    /** @var array<mixed> TODO: Object */
    private $params;

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

    /**
     *
     * @return string
     */
    private function getTemplatePath(): string
    {
        // todo - check file exists + inheritance
        return $this->model->getConfiguration()->getString(SeablastConstant::LATTE_TEMPLATE) . '/' . 'template.latte';
    }

    /**
     *
     * @return void
     */
    private function renderLatte(): void
    {
        $latte = new \Latte\Engine;

        // Maybe only for PHP8+
        // aktivuje rozšíření pro Tracy
        // $latte->addExtension(new Latte\Bridges\Tracy\TracyExtension);
        //
        // cache directory
        $latte->setTempDirectory($this->model->getConfiguration()->getString(SeablastConstant::LATTE_CACHE));

        //$params = [ /* template variables */ ];
        // or $params = new TemplateParameters(/* ... */);
        //
        // render to output
        $latte->render($this->getTemplatePath(), $this->params);
        // or render to variable
        //$output = $latte->renderToString('template.latte', $params);
    }
}
