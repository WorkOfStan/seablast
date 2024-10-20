<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastMysqli;
use Seablast\Seablast\Exceptions\DbmsException;
use Tracy\Debugger;

class SeablastMysqliTest extends TestCase
{
    /** @var SeablastMysqli */
    private $mysqli;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('APP_DIR')) {
            define('APP_DIR', __DIR__ . '/..');
            Debugger::enable(Debugger::DEVELOPMENT, APP_DIR . '/log');
        }

        $configuration = new SeablastConfiguration();
        $defaultConfig = __DIR__ . '/../conf/default.conf.php';
        $configurationClosure = require $defaultConfig;
        $configurationClosure($configuration);
        $this->assertEquals('views', $configuration->getString(SeablastConstant::LATTE_TEMPLATE));
        $configuration->setInt(SeablastConstant::SB_LOGGING_LEVEL, 5);
        $configuration->setString(SeablastConstant::SB_PHINX_ENVIRONMENT, 'testing'); // so that the database test works

        $this->mysqli = $configuration->dbms();
    }

    public function testConstructorSuccess(): void
    {
        $this->assertInstanceOf(SeablastMysqli::class, $this->mysqli);
    }

    public function testConstructorThrowsExceptionOnConnectError(): void
    {
        $this->expectWarning();
        // Note: Expecting E_WARNING and E_USER_WARNING is deprecated and will no longer be possible in PHPUnit 10.
        $this->mysqli = new SeablastMysqli('invalid_host', 'user', 'password', 'database');
    }

    public function testQueryLogging(): void
    {
        $query1 = 'CREATE TABLE IF NOT EXISTS testTable (    id INT AUTO_INCREMENT PRIMARY KEY,'
                . '    name VARCHAR(255) NOT NULL,    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);';
        $result1 = $this->mysqli->query($query1);
        $this->assertTrue($result1);

        $query2 = "UPDATE testTable SET name = 'value'";
        $result2 = $this->mysqli->query($query2);
        $this->assertTrue($result2);
    }

    public function testQueryFailureLogsError(): void
    {
        $query = "UPDATE table SET column = 'value'";
        $result = $this->mysqli->query($query);
        $this->assertFalse($result);
        // Check if the query was logged with the error
    }

    public function testQueryStrictThrowsExceptionOnFailure(): void
    {
        $this->expectException(DbmsException::class);
        $query = "UPDATE table SET column = 'value'";
        $this->mysqli->queryStrict($query);
    }

    public function testIsReadDataTypeQuery(): void
    {
        $reflection = new \ReflectionClass($this->mysqli);
        $method = $reflection->getMethod('isReadDataTypeQuery');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->mysqli, 'SELECT * FROM table'));
        $this->assertTrue($method->invoke($this->mysqli, 'SHOW TABLES'));
        $this->assertFalse($method->invoke($this->mysqli, 'INSERT INTO table (column) VALUES (value)'));
    }

    public function testLogQuery(): void
    {
        $reflection = new \ReflectionClass($this->mysqli);
        $method = $reflection->getMethod('logQuery');
        $method->setAccessible(true);

        $query = "UPDATE table SET column = 'value'";
        $method->invoke($this->mysqli, $query);
        // Check if the log file was created and contains the query
    }
}
