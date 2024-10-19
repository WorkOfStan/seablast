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
    /** @var SeablastConfiguration */
    private $configuration;

    protected function setUp(): void
    {
        if (!defined('APP_DIR')) {
            define('APP_DIR', __DIR__ . '/..');
        }
        $this->configuration = new SeablastConfiguration();
        $defaultConfig = APP_DIR . '/conf/default.conf.php';
        $configurationClosure = require $defaultConfig;
        $configurationClosure($this->configuration);
    }

    public function testDbmsReturnsSeablastMysqliInstance(): void
    {
//        $config = $this->getMockBuilder(SeablastConfiguration::class)
//            ->onlyMethods([
//                //'dbmsStatus',
//                'getString',
//                //'dbmsCreate'
//                ])
//            ->getMock();

//        $config->expects($this->once())
//            ->method('dbmsStatus')
//            ->willReturn(false);

//        $config->expects($this->any())
//            ->method('getString')
//            ->willReturn('utf8');

//        $config->expects($this->once())
//            ->method('dbmsCreate')
//            ->will($this->returnCallback(function () use ($config) {
//                $reflection = new \ReflectionClass($config);
//                $property = $reflection->getProperty('connection');
//                $property->setAccessible(true);
//                $property->setValue($config, $this->createMock(SeablastMysqli::class));
//            }));

        $this->assertInstanceOf(SeablastMysqli::class, $this->configuration->dbms());
    }

    public function testDbmsThrowsExceptionIfNoConnection(): void
    {
        $this->expectException(DbmsException::class);

//        $config = $this->getMockBuilder(SeablastConfiguration::class)
//            ->onlyMethods(['dbmsStatus'])
//            ->getMock();
//
//        $config->expects($this->once())
//            ->method('dbmsStatus')
//            ->willReturn(false);

        $this->configuration->dbms();
    }

//    public function testDbmsTablePrefix()
//    {
//        $config = $this->getMockBuilder(SeablastConfiguration::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $reflection = new \ReflectionClass($config);
//        $property = $reflection->getProperty('connectionTablePrefix');
//        $property->setAccessible(true);
//        $property->setValue($config, 'test_prefix');
//
//        $this->assertEquals('test_prefix', $config->dbmsTablePrefix());
//    }

    public function testDbmsTablePrefixThrowsExceptionIfNotInitialized(): void
    {
        $this->expectException(DbmsException::class);

        $config = new SeablastConfiguration();
        $config->dbmsTablePrefix();
    }

    public function testExists(): void
    {
        $config = $this->getMockBuilder(SeablastConfiguration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getArrayArrayString', 'getArrayInt', 'getArrayString', 'getInt', 'getString'])
            ->getMock();

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

    public function testGetString(): void
    {
        $config = new SeablastConfiguration();
        $config->setString('test_property', 'test_value');

        $this->assertEquals('test_value', $config->getString('test_property'));
    }

    public function testGetStringThrowsExceptionIfPropertyNotFound(): void
    {
        $this->expectException(SeablastConfigurationException::class);

        $config = new SeablastConfiguration();
        $config->getString('non_existent_property');
    }

    public function testSetAndGetArrayInt(): void
    {
        $config = new SeablastConfiguration();
        $config->setArrayInt('test_property', [1, 2, 3]);

        $this->assertEquals([1, 2, 3], $config->getArrayInt('test_property'));
    }
}
