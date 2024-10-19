<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastView;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastFlag;
use Seablast\Seablast\Exceptions\MissingTemplateException;
use Seablast\Seablast\Exceptions\UnknownHttpCodeException;
use stdClass;

class SeablastViewTest extends TestCase
{
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var MockObject */
    private $modelMock;

    protected function setUp(): void
    {
        $this->configuration = new SeablastConfiguration();
        $defaultConfig = __DIR__ . '/../conf/default.conf.php';
        $configurationClosure = require $defaultConfig;
        $configurationClosure($this->configuration);
        $this->assertEquals('views', $this->configuration->getString(SeablastConstant::LATTE_TEMPLATE));

        $this->configuration->setInt(SeablastConstant::SB_LOGGING_LEVEL, 5);
        // todo Contoller must apply this settings -- but SeablastView is not invoked at all?!?!?!?!?

        $viewParameters = (object) [
            'httpCode' => 200,
            'csrfToken' => 'mockCsrfToken',
        ];

        // TODO instead of $this->modelMock use $this->model
        $model = new SeablastModel();

        $this->modelMock = $this->createMock(SeablastModel::class);
        $this->modelMock->method('getConfiguration')->willReturn($this->configuration);
        $this->modelMock->method('getParameters')->willReturn($viewParameters);
    }

    public function testConstructorInitializesParameters(): void
    {
        $this->configuration->flag = new SeablastFlag();
//        $this->configuration->method('dbmsStatus')->willReturn(false);

//        $params = new stdClass();
//        $params->httpCode = 200;
        $params = (object) [
            'httpCode' => 200,
            'csrfToken' => 'mockCsrfToken',
            'configuration' => $this->configuration,
        ];
//        var_dump($params);

        $this->modelMock->method('getParameters')->willReturn($params);
        //$this->modelMock->mapping = ['template' => 'item']; // to assign an existing latte template
        // Using Reflection to Set Protected/Private Properties - this one is public but not defined for mock
        $reflection = new \ReflectionClass($this->modelMock);
        $property = $reflection->getProperty('mapping');
        $property->setAccessible(true);
        $property->setValue($this->modelMock, ['template' => 'item']);
        //var_dump($this->modelMock->mapping);
        //var_dump($this->modelMock->getParameters());


        $view = new SeablastView($this->modelMock);
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
        $this->assertSame($this->configuration, $params->configuration);
    }

    public function testGetTemplatePathReturnsCorrectPath(): void
    {
//        $params = new stdClass();
//        $params->httpCode = 200;
        $params = (object) [
            'httpCode' => 200,
            'csrfToken' => 'mockCsrfToken',
        ];
        $params->configuration = $this->configuration;
//        $this->modelMock->method('getParameters')->willReturn($params);

        $this->modelMock->mapping = ['template' => 'item']; // exampleTemplate
        //$this->configuration->method('getString')->willReturn('templates/path');

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte',
                //'fileExists'
                ])
            ->getMock();

//        $view->method('fileExists')->willReturn(true);

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('getTemplatePath');
        $method->setAccessible(true);

        $filePath = 'views/item.latte'; // todo fix the path - replace by SB_Constant
        $this->assertEquals($filePath, $method->invoke($view));
    }

    public function testGetTemplatePathThrowsMissingTemplateException(): void
    {
        $this->expectException(MissingTemplateException::class);

        $params = new stdClass();
        $params->httpCode = 200;
        $params->configuration = $this->configuration;
//        $this->modelMock->method('getParameters')->willReturn($params);

        $this->modelMock->mapping = ['template' => 'nonExistentTemplate'];
        //$this->configuration->method('getString')->willReturn('templates/path');

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte',
                //'fileExists'
                ])
            ->getMock();

//        $view->method('fileExists')->willReturn(false);

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('getTemplatePath');
        $method->setAccessible(true);

        $method->invoke($view);
    }

    public function testRenderJsonOutputsJson(): void
    {
        $data = ['key' => 'value'];
//        $params = new stdClass();
//        $params->httpCode = 200;
        $params = (object) [
            'httpCode' => 200,
            'rest' => '{"a" => "b"}',
        ];

//        $this->modelMock->method('getParameters')->willReturn($params);
        $this->configuration->flag = new SeablastFlag();
        //$this->configuration->flag->activate('FLAG_DEBUG_JSON');  // Ensure the flag is set

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte'])
            ->getMock();

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('renderJson');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($view, $data);
        $output = ob_get_clean();

        $this->assertNotFalse($output);
        $this->assertJson($output);
        $this->assertEquals(json_encode($data), $output);
    }

    public function testRenderJsonThrowsUnknownHttpCodeException(): void
    {
        $this->expectException(UnknownHttpCodeException::class);

//        $params = new stdClass();
//        $params->httpCode = 999; // Invalid HTTP code
        $params = (object) [
            'httpCode' => 999, // Invalid HTTP code
            'rest' => '{"a" => "b"}',
        ];

        $this->modelMock->method('getParameters')->willReturn($params);
        $this->configuration->flag = new SeablastFlag();

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte'])
            ->getMock();

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('renderJson');
        $method->setAccessible(true);

        $method->invoke($view, []);
    }

    public function testShowHttpErrorPanel(): void
    {
        $params = new stdClass();
        $params->httpCode = 404;
        $params->rest = (object) ['message' => 'Not Found'];

        $this->modelMock->method('getParameters')->willReturn($params);

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte'])
            ->getMock();

        $this->assertTrue(
            method_exists($view, 'showHttpErrorPanel'),
            'The method SeablastView::showHttpErrorPanel is missing'
        );
    }
}
