<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\SeablastView;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastFlag;
use Seablast\Seablast\Superglobals;
use Seablast\Seablast\Exceptions\MissingTemplateException;
use Seablast\Seablast\Exceptions\UnknownHttpCodeException;
use Tracy\Debugger;

class SeablastViewTest extends TestCase
{
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var SeablastController */
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('APP_DIR')) {
            define('APP_DIR', __DIR__ . '/..');
            Debugger::enable(Debugger::DEVELOPMENT, APP_DIR . '/log');
        }
                
        $this->configuration = new SeablastConfiguration();
        $defaultConfig = __DIR__ . '/../conf/default.conf.php';
        $configurationClosure = require $defaultConfig;
        $configurationClosure($this->configuration);
        $this->assertEquals('views', $this->configuration->getString(SeablastConstant::LATTE_TEMPLATE));

        $this->configuration->setInt(SeablastConstant::SB_LOGGING_LEVEL, 5);
        // todo Contoller must apply this settings -- but SeablastView is not invoked at all?!?!?!?!?
        $this->configuration->setInt('testHttpCode', 200); // for MockModel
        $this->configuration->setString('testRest', '{"a": "b"}'); // for MockJsonModel

        $superglobalsMock = new Superglobals(
            [],
            [],
            [
                'REQUEST_URI' => 'testView',
                'SCRIPT_NAME' => __FILE__,
                'HTTP_HOST' => 'testhost',
            ]
        );
        $this->controller = new SeablastController($this->configuration, $superglobalsMock);
    }

    public function testConstructorInitializesParameters(): void
    {
        $this->configuration->flag = new SeablastFlag();
        $this->controller->mapping = ['template' => 'item']; // to assign an existing latte template
        $model = new SeablastModel($this->controller, new Superglobals());

        // Start output buffering
        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
        $output = ob_get_clean();
        $this->assertNotFalse($output, 'Should contain HTML output.');
        $this->assertStringStartsWith('<!doctype html>', $output);
    }

    public function testGetTemplatePathReturnsCorrectPath(): void
    {
        $this->controller->mapping = ['template' => 'item']; // to assign an existing latte template - exampleTemplate
        $model = new SeablastModel($this->controller, new Superglobals());
        // Start output buffering
        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
        $output = ob_get_clean();
        $this->assertNotFalse($output, 'Should contain HTML output.');
        $this->assertStringStartsWith('<!doctype html>', $output);
    }

    public function testGetTemplatePathThrowsMissingTemplateException(): void
    {
        $this->expectException(MissingTemplateException::class);
        $this->controller->mapping = ['template' => 'nonExistentTemplate']; // to assign an existing latte template - exampleTemplate
        $model = new SeablastModel($this->controller, new Superglobals());
        new SeablastView($model);
    }

    public function testRenderJsonOutputsJson(): void
    {
        $data = ['a' => 'b'];
        $this->controller->mapping = ['model' => '\Seablast\Seablast\Models\MockJsonModel'];
        $model = new SeablastModel($this->controller, new Superglobals());
        // Start output buffering
        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
        $output = ob_get_clean();
        $this->assertNotFalse($output, 'Should contain JSON output.');
        $this->assertEquals(json_encode($data), $output);
    }

    public function testRenderJsonThrowsUnknownHttpCodeException(): void
    {
        $this->expectException(UnknownHttpCodeException::class);
        $this->controller->mapping = ['model' => '\Seablast\Seablast\Models\MockJsonHttpErrorModel'];
        $model = new SeablastModel($this->controller, new Superglobals());
        new SeablastView($model);
    }

    // public function testShowHttpErrorPanel(): void // TODO analyze output HTML?
}
