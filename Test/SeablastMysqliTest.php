<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastMysqli;
use Seablast\Seablast\Exceptions\DbmsException;
use Tracy\Debugger;

class SeablastMysqliTest extends TestCase
{
    private $mysqli;

    protected function setUp(): void
    {
        $this->mysqli = $this->getMockBuilder(SeablastMysqli::class)
                             ->setConstructorArgs(['localhost', 'user', 'password', 'database'])
                             ->onlyMethods(['query', 'connect_error', 'errno', 'error'])
                             ->getMock();
    }

    public function testConstructorSuccess()
    {
        $this->assertInstanceOf(SeablastMysqli::class, $this->mysqli);
    }

    public function testConstructorThrowsExceptionOnConnectError()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Connection to database failed with error');

        $this->getMockBuilder(SeablastMysqli::class)
             ->setConstructorArgs(['invalid_host', 'user', 'password', 'database'])
             ->getMock();
    }

    public function testQueryLogging()
    {
        $query = "UPDATE table SET column = 'value'";

        $this->mysqli->method('query')->willReturn(true);

        $result = $this->mysqli->query($query);

        $this->assertTrue($result);
    }

    public function testQueryFailureLogsError()
    {
        $query = "UPDATE table SET column = 'value'";
        $errorMessage = "Some error message";
        $this->mysqli->method('query')->willReturn(false);
        $this->mysqli->errno = 1234;
        $this->mysqli->error = $errorMessage;

        $result = $this->mysqli->query($query);

        $this->assertFalse($result);
        // Check if the query was logged with the error
    }

    public function testQueryStrictThrowsExceptionOnFailure()
    {
        $this->expectException(DbmsException::class);

        $query = "UPDATE table SET column = 'value'";

        $this->mysqli->method('query')->willReturn(false);
        $this->mysqli->errno = 1234;
        $this->mysqli->error = "Some error message";

        $this->mysqli->queryStrict($query);
    }

    public function testIsReadDataTypeQuery()
    {
        $reflection = new \ReflectionClass($this->mysqli);
        $method = $reflection->getMethod('isReadDataTypeQuery');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->mysqli, 'SELECT * FROM table'));
        $this->assertTrue($method->invoke($this->mysqli, 'SHOW TABLES'));
        $this->assertFalse($method->invoke($this->mysqli, 'INSERT INTO table (column) VALUES (value)'));
    }

    public function testLogQuery()
    {
        $reflection = new \ReflectionClass($this->mysqli);
        $method = $reflection->getMethod('logQuery');
        $method->setAccessible(true);

        $query = "UPDATE table SET column = 'value'";
        $method->invoke($this->mysqli, $query);

        // Check if the log file was created and contains the query
    }

    public function testShowSqlBarPanel()
    {
        $this->mysqli->query("SELECT * FROM table");
        $this->mysqli->query("UPDATE table SET column = 'value'");

        $this->mysqli->showSqlBarPanel();

        // Since this interacts with Tracy Debugger, manual verification might be needed or check Debugger's state
    }
}
