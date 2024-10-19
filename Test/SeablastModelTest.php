<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\Superglobals;
use Seablast\Seablast\SeablastConfiguration;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfToken;
use stdClass;

class SeablastModelTest extends TestCase
{
    public function testConstructWithModelMapping(): void
    {
        $controllerMock = $this->createMock(SeablastController::class);
        $superglobalsMock = $this->createMock(Superglobals::class);
        $configurationMock = $this->createMock(SeablastConfiguration::class);

        $modelMock = $this->getMockBuilder(stdClass::class)
            ->addMethods(['knowledge'])
            ->getMock();
        $modelMock->expects($this->once())
            ->method('knowledge')
            ->willReturn((object)['data' => 'value']);

        $controllerMock->method('getConfiguration')->willReturn($configurationMock);
        $controllerMock->mapping = ['model' => get_class($modelMock)];

        $this->mockAutoloadClass($modelMock);

        $model = new SeablastModel($controllerMock, $superglobalsMock);

        $params = $model->getParameters();
        $this->assertObjectHasAttribute('data', $params);
        $this->assertEquals('value', $params->data);
    }

    public function testConstructWithoutModelMapping(): void
    {
        $controllerMock = $this->createMock(SeablastController::class);
        $superglobalsMock = $this->createMock(Superglobals::class);
        $configurationMock = $this->createMock(SeablastConfiguration::class);

        $controllerMock->method('getConfiguration')->willReturn($configurationMock);
        $controllerMock->mapping = [];

        $model = new SeablastModel($controllerMock, $superglobalsMock);

        $params = $model->getParameters();
        $this->assertInstanceOf(stdClass::class, $params);
    }

    public function testGetConfiguration(): void
    {
        $controllerMock = $this->createMock(SeablastController::class);
        $superglobalsMock = $this->createMock(Superglobals::class);
        $configurationMock = $this->createMock(SeablastConfiguration::class);

        $controllerMock->method('getConfiguration')->willReturn($configurationMock);

        $model = new SeablastModel($controllerMock, $superglobalsMock);

        $this->assertSame($configurationMock, $model->getConfiguration());
    }

    public function testCsrfTokenIsSet(): void
    {
        $controllerMock = $this->createMock(SeablastController::class);
        $superglobalsMock = $this->createMock(Superglobals::class);
        $configurationMock = $this->createMock(SeablastConfiguration::class);

        $controllerMock->method('getConfiguration')->willReturn($configurationMock);
        $controllerMock->mapping = [];

        $csrfTokenMock = $this->createMock(CsrfToken::class);

        $csrfTokenManagerMock = $this->createMock(CsrfTokenManager::class);
        $csrfTokenManagerMock->method('getToken')->willReturn($csrfTokenMock);

        $model = new SeablastModel($controllerMock, $superglobalsMock);
        $params = $model->getParameters();

        $this->assertTrue(property_exists($params, 'csrfToken'));
        $this->assertTrue(
            strlen($params->csrfToken) > 60,
            'CSRF token is expected to be longer than 60 characters, it has only ' . strlen($params->csrfToken) . '.'
        );
    }

    private function mockAutoloadClass(object $classMock): void
    {
        $class = get_class($classMock);
        if (!class_exists($class, false)) {
            eval('namespace ' . __NAMESPACE__ . '; class ' . $class . ' extends \stdClass {}');
        }
    }
}
