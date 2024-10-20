<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\Superglobals;
use Seablast\Seablast\SeablastConfiguration;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfToken;
use stdClass;
use Tracy\Debugger;

class SeablastModelTest extends TestCase
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
        $this->controller = new SeablastController(
            $this->configuration,
            new Superglobals(
                [],
                [],
                [
                    'REQUEST_URI' => 'testView',
                ]
            )
        );
    }

    public function testConstructWithModelMapping(): void
    {
        $this->controller->mapping = ['model' => '\Seablast\Seablast\Models\MockModel'];

        $model = new SeablastModel($this->controller, new Superglobals());
        $params = $model->getParameters();
        if (method_exists($this, 'assertObjectHasAttribute')) {
            $this->assertObjectHasAttribute('data', $params); //deprecated in favor is assertObjectHasProperty
        } elseif (method_exists($this, 'assertObjectHasProperty')) {
            $this->assertObjectHasProperty('data', $params);
        } else {
            $this->assertTrue(false, 'Cannot make sure that data has params attribute/property');
        }
        $this->assertEquals('value', $params->data);
    }

    public function testConstructWithoutModelMapping(): void
    {
        $this->controller->mapping = [];

        $model = new SeablastModel($this->controller, new Superglobals());

        $params = $model->getParameters();
        $this->assertInstanceOf(stdClass::class, $params);
    }

    public function testGetConfiguration(): void
    {
        $model = new SeablastModel($this->controller, new Superglobals());
        $this->assertSame($this->configuration, $model->getConfiguration());
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
}
