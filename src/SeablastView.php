<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\Exceptions\MissingTemplateException;
use Seablast\Seablast\Exceptions\UnknownHttpCodeException;
use Seablast\Seablast\Tracy\BarPanelTemplate;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;
use Webmozart\Assert\Assert;

class SeablastView
{
    use \Nette\SmartObject;

    /** @var SeablastModel */
    private $model;
    /** @var stdClass */
    private $params;

    /**
     * @param \Seablast\Seablast\SeablastModel $model
     * @return void
     */
    public function __construct(SeablastModel $model)
    {
        $this->model = $model;
        Debugger::barDump($this->model, 'Model passed to SeablastView'); // debug
        $this->params = $this->model->getParameters();
        Debugger::barDump($this->params, 'Params for SeablastView'); // debug
        Debugger::log('Params for SeablastView: ' . print_r($this->params, true), ILogger::DEBUG);
        $this->params->configuration = $this->model->getConfiguration();
        if (isset($this->params->redirection)) { // TODO remove this condition in higher version than 0.2
            throw new \Exception('not redirection but use redirectionUrl'); // debug deprecated
        }
        if (isset($this->params->rest)) {
            // API
            $this->renderJson($this->params->rest);
        } elseif (!isset($this->params->redirectionUrl)) {
            // HTML UI
            if ($this->params->httpCode >= 400) {
                $this->model->mapping['template'] = 'error';
            }
            $this->renderLatte();
        }
        // Show Tracy BarPanels
        if ($this->model->getConfiguration()->dbmsStatus()) {
            $this->model->getConfiguration()->dbms()->showSqlBarPanel();
        }
        // TODO User BarPanel - generated here with data from IdentityManager? or generated by IdentityManager?
        $this->showHttpErrorPanel();
        // Redirection
        if (isset($this->params->redirectionUrl)) {
            Assert::string($this->params->redirectionUrl);
            if (isset($this->params->httpCode)) {
                Assert::inArray(
                    $this->params->httpCode,
                    [301, 302, 303, 307, 308],
                    'Unauthorized redirect HTTP code %s'
                );
            } else {
                $this->params->httpCode = 301; // better for SEO than 303
            }
            header("Location: {$this->params->redirectionUrl}", true, (int) $this->params->httpCode);
            header('Connection: close');
            $this->model->mapping['template'] = 'redirection';
            $this->renderLatte();
        }
    }

    /**
     * If HTTP error code, show Tracy BarPanel.
     *
     * @return void
     */
    private function showHttpErrorPanel(): void
    {
        if (!isset($this->params->httpCode) || ($this->params->httpCode < 400)) {
            return;
        }
        //$httpBarPanelInfo = []; // 'Params' => $this->params
        //if (isset($this->params->rest->message)) {
        //    $httpBarPanelInfo['message'] = $this->params->rest->message;
        //}
        $httpBarPanel = new BarPanelTemplate(
            'HTTP: ' . (int) $this->params->httpCode,
            isset($this->params->rest->message) ? ['message' => $this->params->rest->message] : []
        );
        $httpBarPanel->setError();
        Debugger::getBar()->addPanel($httpBarPanel);
    }

    /**
     * Use app version of template, if unavailable use Seablast default version of template,
     * If unavailable throw an Exception.
     *
     * @return string
     * @throws MissingTemplateException
     */
    private function getTemplatePath(): string
    {
        Assert::notEmpty($this->model->mapping['template'], 'model->mapping[template] MUST be defined');
        // check file exists + inheritance
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
        throw new MissingTemplateException($this->model->mapping['template'] . ' template is neither in app '
            . $templatePath . ' nor in library'); // TODO improve the error message
    }

    private function httpResponseCode(): void
    {
        if (isset($this->params->httpCode) && is_scalar($this->params->httpCode)) {
            // accepts HTTP codes 100-599 even though some of them might not be defined
            if ((int) $this->params->httpCode < 100 || (int) $this->params->httpCode > 599) {
                throw new UnknownHttpCodeException('Unknown HTTP code: ' . (int) $this->params->httpCode);
            }
            http_response_code((int) $this->params->httpCode); // Send the status code
        }
    }

    /**
     * Outputs the given data as JSON.
     *
     * @param array<mixed>|object $data2json The data to be encoded as JSON.
     * @return void Outputs JSON
     * @throws UnknownHttpCodeException
     */
    private function renderJson($data2json): void
    {
        if (isset($this->params->status)) {
            throw new \Exception('not status but httpCode is wanted'); // debug deprecated remove >0.2.x
        }
        if (!$this->model->getConfiguration()->flag->status(SeablastConstant::FLAG_DEBUG_JSON)) {
            header('Content-Type: application/json; charset=utf-8'); //the flag turns-off this line
        }
        $this->httpResponseCode();
        $result = json_encode($data2json);
        Assert::string($result);
        echo($result);
    }

    /**
     * Render selected latte template with the calculated parameters.
     *
     * @return void
     */
    private function renderLatte(): void
    {
        $this->httpResponseCode();
        $latte = new \Latte\Engine();

        if (
            $this->model->getConfiguration()->exists(SeablastConstant::TRANSLATE_CLASS)
            && (class_exists($this->model->getConfiguration()->getString(SeablastConstant::TRANSLATE_CLASS)))
        ) {
            $translatorClass = $this->model->getConfiguration()->getString(SeablastConstant::TRANSLATE_CLASS);
            Debugger::barDump($translatorClass, 'Translate class');
            $translator = new $translatorClass($this->model->getConfiguration());

            //if (!method_exists($latte, 'addExtension')) {
            // for Latte 2 and 3
            if (method_exists($translator, 'translate')) {
                Debugger::barDump('Translator exists');
                //$latte->addFunction('translate', function ($text) use ($translator) {
                //    return $translator->translate($text);
                //});
                Debugger::barDump($latte, 'before filter');
                $latte->addFilter('translate', function (string $s) use ($translator) {
                    return $translator->translate($s);
                });
                Debugger::barDump($latte, 'after filter');
            }
            /*} else {
                // for Latte 3, i.e. PHP/8.0-8.4
                //$translator = new MyTranslator($lang);
                $extension = new Latte\Essential\TranslatorExtension(
                    //$translator->translate(...),
                    [$translator, 'translate'] // v PHP 8.0
                );
                $latte->addExtension($extension);
            }*/
        } else {
            Debugger::barDump('NO TRANSLATION');
        }

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
        Debugger::barDump($this->getTemplatePath(), 'renderLatte: selected template');
        try {
            $latte->render($this->getTemplatePath(), $this->params);
            // or render to variable
            //$output = $latte->renderToString('template.latte', $params);
        } catch (Exceptions\DbmsException $e) {
            // make sure that the database Tracy BarPanel is displayed when DbmsException is thrown
            $this->model->getConfiguration()->dbms()->showSqlBarPanel();
            throw new Exceptions\DbmsException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
