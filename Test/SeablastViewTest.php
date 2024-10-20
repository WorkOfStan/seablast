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
use stdClass;
use Tracy\Debugger;

class SeablastViewTest extends TestCase
{
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var SeablastController */
    private $controller;
    /** @ var SeablastModel */
    //private $modelMock;

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

//        $viewParameters = (object) [
//            'httpCode' => 200,
//            'csrfToken' => 'mockCsrfToken',
//        ];

//        $controllerMock = $this->createMock(SeablastController::class);
//        $controllerMock->method('getConfiguration')->willReturn($this->configuration);
//        die(__FILE__);
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
        
//        $this->modelMock = new SeablastModel($controller, $superglobalsMock);
        //TODO!: $this->modelMock->method('getParameters')->willReturn($viewParameters);
    }

    public function testConstructorInitializesParameters(): void
    {
        $this->configuration->flag = new SeablastFlag();
//        $this->configuration->method('dbmsStatus')->willReturn(false);

//        $params = new stdClass();
//        $params->httpCode = 200;
//        $params = (object) [
//            'httpCode' => 200,
//            'csrfToken' => 'mockCsrfToken',
//            'configuration' => $this->configuration,
//        ];
//        var_dump($params);
//        $model = new SeablastModel($this->controller, new Superglobals());

        //TODO!: $this->modelMock->method('getParameters')->willReturn($params);
        //$this->modelMock->mapping = ['template' => 'item']; // to assign an existing latte template
        $this->controller->mapping = ['template' => 'item']; // to assign an existing latte template
        $model = new SeablastModel($this->controller, new Superglobals());
        //var_dump($this->modelMock->mapping);
        //var_dump($this->modelMock->getParameters());        
        
//        var_dump($model->getParameters());
//        exit;//debug

        // Start output buffering
        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
        $output = ob_get_clean();
        $this->assertStringStartsWith('<!doctype html>', $output);

//        $view = $this->getMockBuilder(SeablastView::class)
//            ->setConstructorArgs([$this->modelMock])
////            ->onlyMethods([
////                //'renderLatte',
////                //'renderJson',
//////                'showHttpErrorPanel'
////                ])
//            ->getMock();

//        $view->expects($this->any())->method('renderLatte');
//        $view->expects($this->any())->method('renderJson');
//        $view->expects($this->any())->method('showHttpErrorPanel');

//        $this->assertSame($params, $view->getParams());
//        $this->assertSame($this->configuration, $params->configuration);
    }

    public function testGetTemplatePathReturnsCorrectPath(): void
    {
//        $params = new stdClass();
//        $params->httpCode = 200;
//        $params = (object) [
//            'httpCode' => 200,
//            'csrfToken' => 'mockCsrfToken',
//        ];
//        $params->configuration = $this->configuration;
//        $this->modelMock->method('getParameters')->willReturn($params);

//        $this->modelMock->mapping = ['template' => 'item']; // exampleTemplate
        //$this->configuration->method('getString')->willReturn('templates/path');
        $this->controller->mapping = ['template' => 'item']; // to assign an existing latte template - exampleTemplate
        $model = new SeablastModel($this->controller, new Superglobals());        
        // Start output buffering
        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
        $output = ob_get_clean();
        $this->assertStringStartsWith('<!doctype html>', $output);
    
//    echo "xxxxxxxxxxxxxxxxxxxxxxxxx $output xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
    
//    exit;
    // Now you can assert the captured output
    //$this->assertEquals('Hello, World!', $output);
    
        
//        $view = $this->getMockBuilder(SeablastView::class)
//            ->setConstructorArgs([$this->modelMock])
//            ->onlyMethods(['renderLatte',
//                //'fileExists'
//                ])
//            ->getMock();

//        $view->method('fileExists')->willReturn(true);

//        $reflection = new \ReflectionClass($view);
//        $method = $reflection->getMethod('getTemplatePath');
//        $method->setAccessible(true);

//        $filePath = 'views/item.latte'; // todo fix the path - replace by SB_Constant
//        $this->assertEquals($filePath, $view);
    }

    public function testGetTemplatePathThrowsMissingTemplateException(): void
    {
//        $this->expectException(MissingTemplateException::class);
//
//        $params = new stdClass();
//        $params->httpCode = 200;
//        $params->configuration = $this->configuration;
////        $this->modelMock->method('getParameters')->willReturn($params);
//
//        $this->modelMock->mapping = ['template' => 'nonExistentTemplate'];
//        //$this->configuration->method('getString')->willReturn('templates/path');
//
//        $view = $this->getMockBuilder(SeablastView::class)
//            ->setConstructorArgs([$this->modelMock])
//            ->onlyMethods(['renderLatte',
//                //'fileExists'
//                ])
//            ->getMock();
//
////        $view->method('fileExists')->willReturn(false);
//
//        $reflection = new \ReflectionClass($view);
//        $method = $reflection->getMethod('getTemplatePath');
//        $method->setAccessible(true);
//
//        $method->invoke($view);
//        
        $this->expectException(MissingTemplateException::class);
        $this->controller->mapping = ['template' => 'nonExistentTemplate']; // to assign an existing latte template - exampleTemplate
        $model = new SeablastModel($this->controller, new Superglobals());        
        // Start output buffering
//        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
//        $output = ob_get_clean();
//        $this->assertStringStartsWith('<!doctype html>', $output);
        
    }

    public function testRenderJsonOutputsJson(): void
    {
        $data = ['a' => 'b'];
//        $params = new stdClass();
//        $params->httpCode = 200;
//        $params = (object) [
//            'httpCode' => 200,
//            'rest' => '{"a" => "b"}',
//        ];
//
////        $this->modelMock->method('getParameters')->willReturn($params);
//        $this->configuration->flag = new SeablastFlag();
//        //$this->configuration->flag->activate('FLAG_DEBUG_JSON');  // Ensure the flag is set
//
//        $view = $this->getMockBuilder(SeablastView::class)
//            ->setConstructorArgs([$this->modelMock])
//            ->onlyMethods(['renderLatte'])
//            ->getMock();
//
//        $reflection = new \ReflectionClass($view);
//        $method = $reflection->getMethod('renderJson');
//        $method->setAccessible(true);
//
//        ob_start();
//        $method->invoke($view, $data);
//        $output = ob_get_clean();

        
        $this->controller->mapping = ['model' => '\Seablast\Seablast\Models\MockJsonModel'];
        $model = new SeablastModel($this->controller, new Superglobals());        
        // Start output buffering
        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
        $output = ob_get_clean();
//        $this->assertStringStartsWith('<!doctype html>', $output);
//
//        
//        $this->assertNotFalse($output);
//        $this->assertJson($output);
        $this->assertEquals(json_encode($data), $output);

        
        
    }

    public function testRenderJsonThrowsUnknownHttpCodeException(): void
    {
        $this->expectException(UnknownHttpCodeException::class);

//        $params = new stdClass();
//        $params->httpCode = 999; // Invalid HTTP code
//        $params = (object) [
//            'httpCode' => 999, // Invalid HTTP code
//            'rest' => '{"a" => "b"}',
//        ];

        //$this->controller
        $this->controller->mapping = ['model' => '\Seablast\Seablast\Models\MockJsonHttpErrorModel'];
        $model = new SeablastModel($this->controller, new Superglobals());        
        // Start output buffering
//        ob_start();
        new SeablastView($model);
        // Get and clean the output buffer
//        $output = ob_get_clean();
//        $this->assertStringStartsWith('<!doctype html>', $output);
//
//        
//        $this->assertNotFalse($output);
//        $this->assertJson($output);
        $this->assertEquals(json_encode($data), $output);
    }

    // public function testShowHttpErrorPanel(): void // TODO analyze output HTML?
}
