<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastMysqli;
use Seablast\Seablast\SeablastFlag;
use Seablast\Seablast\SeablastConfigurationException;

class SeablastConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('APP_DIR')) {
            define('APP_DIR', __DIR__ . '/..');
        }
    }

    public function testDbmsReturnsSeablastMysqliInstance()
    {
        $config = $this->getMockBuilder(SeablastConfiguration::class)
            ->onlyMethods(['dbmsStatus', 'getString'])
            ->getMockForAbstractClass();

        $config->expects($this->once())
            ->method('dbmsStatus')
            ->willReturn(false);

        $config->expects($this->any())
            ->method('getString')
            ->willReturn('utf8');

        $mockConnection = $this->createMock(SeablastMysqli::class);

        $reflection = new \ReflectionClass($config);
        $property = $reflection->getParentClass()->getProperty('connection');
        $property->setAccessible(true);
        $property->setValue($config, $mockConnection);

        $this->assertInstanceOf(SeablastMysqli::class, $config->dbms());
    }

    public function testDbmsThrowsExceptionIfNoConnection()
    {
        $this->expectException(DbmsException::class);

        $config = $this->getMockBuilder(SeablastConfiguration::class)
            ->onlyMethods(['dbmsStatus'])
            ->getMockForAbstractClass();

        $config->expects($this->once())
            ->method('dbmsStatus')
            ->willReturn(false);

        $config->dbms();
    }

    public function testDbmsTablePrefix()
    {
        $config = $this->getMockBuilder(SeablastConfiguration::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $reflection = new \ReflectionClass($config);
        $property = $reflection->getParentClass()->getProperty('connectionTablePrefix');
        $property->setAccessible(true);
        $property->setValue($config, 'test_prefix');

        $this->assertEquals('test_prefix', $config->dbmsTablePrefix());
    }

    public function testDbmsTablePrefixThrowsExceptionIfNotInitialized()
    {
        $this->expectException(DbmsException::class);

        $config = new SeablastConfiguration();
        $config->dbmsTablePrefix();
    }

    public function testExists()
    {
        $config = $this->getMockBuilder(SeablastConfiguration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getArrayArrayString', 'getArrayInt', 'getArrayString', 'getInt', 'getString'])
            ->getMockForAbstractClass();

        $config->expects($this->any())
            ->method('getArrayArrayString')
            ->will($this->throwException(new SeablastConfigurationException()));

        $config->expects($this->any())
            ->method('getArrayInt')
            ->will($this->throwException(new SeablastConfigurationException()));

        $config->expects($this->any())
            ->method('getArrayString')
            ->will($this->throwException(new SeablastConfigurationException()));

        $config->expects($this->any())
            ->method('getInt')
            ->will($this->throwException(new SeablastConfigurationException()));

        $config->expects($this->once())
            ->method('getString')
            ->willReturn('test');

        $this->assertTrue($config->exists('some_property'));
    }

    public function testGetString()
    {
        $config = new SeablastConfiguration();
        $config->setString('test_property', 'test_value');

        $this->assertEquals('test_value', $config->getString('test_property'));
    }

    public function testGetStringThrowsExceptionIfPropertyNotFound()
    {
        $this->expectException(SeablastConfigurationException::class);

        $config = new SeablastConfiguration();
        $config->getString('non_existent_property');
    }

    public function testSetAndGetArrayInt()
    {
        $config = new SeablastConfiguration();
        $config->setArrayInt('test_property', [1, 2, 3]);

        $this->assertEquals([1, 2, 3], $config->getArrayInt('test_property'));
    }
}
