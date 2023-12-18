<?php

namespace Seablast\Seablast;

use stdClass;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

class SeablastView
{
    use \Nette\SmartObject;

    /** @var SeablastModel */
    private $model;

    /** @var array<mixed>|stdClass TODO: only Object */
    private $params;

    /**
     *
     * @param \Seablast\Seablast\SeablastModel $model
     * @return void
     */
    public function __construct(SeablastModel $model)
    {
        $this->model = $model;
        Debugger::barDump($this->model, 'model');
        $this->params = $this->model->getParameters();
        if (is_array($this->params)) {
            // array, current way - deprecated
            $this->params['configuration'] = $this->model->getConfiguration();
            $this->params['model'] = $this->model; // debug
        } else {
            // object, the target way
            $this->params->configuration = $this->model->getConfiguration();
            $this->params->model = $this->model; // debug
        }
        //echo ('<h1>Minimal model</h1>');
        //var_dump($this->model); // minimal
        // API
        if (isset($this->params->rest)) {
            $this->renderJson($this->params->rest);
            return;
        }
        // HTML UI
        $this->renderLatte();
    }

    /**
     * Use app version of template, if unavailable use Seablast default version of template
     * If unavailable throw an Exception
     * @return string
     */
    private function getTemplatePath(): string
    {
        // todo - check file exists + inheritance
        $templatePath = $this->model->getConfiguration()->getString(SeablastConstant::LATTE_TEMPLATE) . '/'
            . $this->model->mapping['template'] . '.latte';
        // APP
        if (file_exists('../../../' . $templatePath)) {
            return '../../../' . $templatePath;
        }
        // INHERITED
        if (file_exists($templatePath)) {
            return $templatePath;
        }
        throw new \Exception($this->model->mapping['template'] . ' template is neither in app ' . $templatePath
            . ' nor in library'); // TODO improve the error message
    }

    /**
     * Outputs the given data as JSON.
     *
     * @param array<mixed>|object $json The data to be encoded as JSON.
     */
    private function renderJson($json): string
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = json_encode($json);
        Assert::string($result);
        return $result;
    }

    /**
     *
     * @return void
     */
    private function renderLatte(): void
    {
        $latte = new \Latte\Engine();

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
