<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

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
    private $modelMock;
    private $configuration;

    protected function setUp(): void
    {
        $this->configuration = new SeablastConfiguration();
        $defaultConfig = __DIR__ . '/../conf/default.conf.php';
        $configurationClosure = require $defaultConfig;
        $configurationClosure($this->configuration);
        $this->assertEquals('views', $this->configuration->getString(SeablastConstant::LATTE_TEMPLATE));

        $this->modelMock = $this->createMock(SeablastModel::class);
        $this->modelMock->method('getConfiguration')->willReturn($this->configuration);
    }

    public function testConstructorInitializesParameters()
    {
        $params = new stdClass();
        $params->httpCode = 200;
        $this->modelMock->method('getParameters')->willReturn($params);

        $this->configuration->flag = new SeablastFlag();
        $this->configuration->method('dbmsStatus')->willReturn(false);

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte', 'renderJson', 'showHttpErrorPanel'])
            ->getMock();

        $view->expects($this->any())->method('renderLatte');
        $view->expects($this->any())->method('renderJson');
        $view->expects($this->any())->method('showHttpErrorPanel');

        $this->assertSame($params, $view->getParams());
        $this->assertSame($this->configuration, $params->configuration);
    }

    public function testGetTemplatePathReturnsCorrectPath()
    {
        $params = new stdClass();
        $params->configuration = $this->configuration;
        $this->modelMock->method('getParameters')->willReturn($params);

        $this->modelMock->mapping = ['template' => 'exampleTemplate'];
        //$this->configuration->method('getString')->willReturn('templates/path');

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte', 'fileExists'])
            ->getMock();

        $view->method('fileExists')->willReturn(true);

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('getTemplatePath');
        $method->setAccessible(true);

        $filePath = 'templates/path/exampleTemplate.latte';
        $this->assertEquals($filePath, $method->invoke($view));
    }

    public function testGetTemplatePathThrowsMissingTemplateException()
    {
        $this->expectException(MissingTemplateException::class);

        $params = new stdClass();
        $params->configuration = $this->configuration;
        $this->modelMock->method('getParameters')->willReturn($params);

        $this->modelMock->mapping = ['template' => 'nonExistentTemplate'];
        //$this->configuration->method('getString')->willReturn('templates/path');

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte', 'fileExists'])
            ->getMock();

        $view->method('fileExists')->willReturn(false);

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('getTemplatePath');
        $method->setAccessible(true);

        $method->invoke($view);
    }

    public function testRenderJsonOutputsJson()
    {
        $data = ['key' => 'value'];
        $params = new stdClass();
        $params->httpCode = 200;

        $this->modelMock->method('getParameters')->willReturn($params);
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

        $this->assertJson($output);
        $this->assertEquals(json_encode($data), $output);
    }

    public function testRenderJsonThrowsUnknownHttpCodeException()
    {
        $this->expectException(UnknownHttpCodeException::class);

        $params = new stdClass();
        $params->httpCode = 999; // Invalid HTTP code

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

    public function testShowHttpErrorPanel()
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
