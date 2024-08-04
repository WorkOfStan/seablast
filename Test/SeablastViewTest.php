<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastView;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Exceptions\MissingTemplateException;
use Seablast\Seablast\Exceptions\UnknownHttpCodeException;
use Tracy\Debugger;
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
        $this->modelMock->method('getParameters')->willReturn($params);

        $view = new SeablastView($this->modelMock);

        $this->assertSame($params, $view->getParams());
        $this->assertSame($this->configMock, $params->configuration);
    }

    public function testGetTemplatePathReturnsCorrectPath()
    {
        $this->modelMock->mapping['template'] = 'exampleTemplate';
        $this->configMock->method('getString')->willReturn('templates/path');

        $view = new SeablastView($this->modelMock);

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('getTemplatePath');
        $method->setAccessible(true);

        // Mock the file_exists function
        $filePath = 'templates/path/exampleTemplate.latte';
        $this->assertEquals($filePath, $method->invoke($view));
    }

    public function testGetTemplatePathThrowsMissingTemplateException()
    {
        $this->expectException(MissingTemplateException::class);

        $this->modelMock->mapping['template'] = 'nonExistentTemplate';
        $this->configMock->method('getString')->willReturn('templates/path');

        $view = new SeablastView($this->modelMock);

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
        $this->configMock->flag = $this->createMock(\Seablast\Seablast\SeablastFlag::class);
        $this->configMock->flag->method('status')->willReturn(false);

        $view = new SeablastView($this->modelMock);

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
        $this->configMock->flag = $this->createMock(\Seablast\Seablast\SeablastFlag::class);
        $this->configMock->flag->method('status')->willReturn(false);

        $view = new SeablastView($this->modelMock);

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

        $view = new SeablastView($this->modelMock);

        $reflection = new \ReflectionClass($view);
        $method = $reflection->getMethod('showHttpErrorPanel');
        $method->setAccessible(true);

        $method->invoke($view);

        // Check if Tracy Debugger has the error panel added
    }
}
