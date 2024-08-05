<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
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
    private $configMock;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(SeablastConfiguration::class);
        $this->modelMock = $this->createMock(SeablastModel::class);
        $this->modelMock->method('getConfiguration')->willReturn($this->configMock);
    }

    public function testConstructorInitializesParameters()
    {
        $params = new stdClass();
        $params->httpCode = 200;
        $this->modelMock->method('getParameters')->willReturn($params);

        $this->configMock->flag = new SeablastFlag();
        $this->configMock->method('dbmsStatus')->willReturn(false);

        $view = $this->getMockBuilder(SeablastView::class)
            ->setConstructorArgs([$this->modelMock])
            ->onlyMethods(['renderLatte', 'renderJson', 'showHttpErrorPanel'])
            ->getMock();

        $view->expects($this->any())->method('renderLatte');
        $view->expects($this->any())->method('renderJson');
        $view->expects($this->any())->method('showHttpErrorPanel');

        $this->assertSame($params, $view->getParams());
        $this->assertSame($this->configMock, $params->configuration);
    }

    public function testGetTemplatePathReturnsCorrectPath()
    {
        $params = new stdClass();
        $params->configuration = $this->configMock;
        $this->modelMock->method('getParameters')->willReturn($params);

        $this->modelMock->mapping = ['template' => 'exampleTemplate'];
        $this->configMock->method('getString')->willReturn('templates/path');

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
        $params->configuration = $this->configMock;
        $this->modelMock->method('getParameters')->willReturn($params);

        $this->modelMock->mapping = ['template' => 'nonExistentTemplate'];
        $this->configMock->method('getString')->willReturn('templates/path');

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
        $this->configMock->flag = new SeablastFlag();
        $this->configMock->flag->activate('FLAG_DEBUG_JSON');  // Ensure the flag is set

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
        $this->configMock->flag = new SeablastFlag();

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

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('showHttpErrorPanel');
        $method->setAccessible(true);

        $method->invoke($view);

        // Check if Tracy Debugger has the error panel added
        $this->assertTrue(true); // Asserting true as a placeholder; actual check should verify Tracy panel state
    }
}
