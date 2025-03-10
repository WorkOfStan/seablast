<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\Tracy\BarPanelTemplate;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\ILogger;

/**
 * MySQLi wrapper with logging.
 *
 * setUser() injects user ID to be logged with queries
 */
class SeablastMysqli extends mysqli
{
    use \Nette\SmartObject;

    /** @var bool true if any of the SQL statements ended in an error state */
    private $databaseError = false;
    /** @var string */
    private $logPath;
    /** @var string[] For Tracy Bar Panel. */
    private $statementList = [];
    /** @var string */
    private $user = 'unidentified';

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $dbname
     * @param ?int $port
     * @param ?string $socket
     * @throws DbmsException
     */
    public function __construct(
        string $host,
        string $username,
        string $password,
        string $dbname,
        ?int $port = null,
        ?string $socket = null
    ) {
        // TODO consider enable error reporting for mysqli before attempting to make a connection
        //mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        if (is_null($port)) {
            parent::__construct($host, $username, $password, $dbname);
        } elseif (is_null($socket)) {
            parent::__construct($host, $username, $password, $dbname, $port);
        } else {
            parent::__construct($host, $username, $password, $dbname, $port, $socket);
        }
        if ($this->connect_error) {
            throw new DbmsException(
                'Connection to database failed with error #' . $this->connect_errno . ' ' . $this->connect_error
            );
        }
        // Use Debugger::$logDirectory instead of APP_DIR . '/log'
        $this->logPath = Debugger::$logDirectory . '/query_' . date('Y-m') . '.log';
    }

    /**
     * Populate database TracyBar.
     *
     * @param bool $result
     * @param string $trimmedQuery
     * @param \mysqli|\mysqli_stmt $dbCall
     * @return void
     */
    private function addStatement(bool $result, string $trimmedQuery, object $dbCall): void
    {
        if ($result !== false) {
            $this->statementList[] = $trimmedQuery;
            return;
        }
        $this->databaseError = true;
        $dbError = ['query' => $trimmedQuery, 'Err#' => $dbCall->errno, 'Error:' => $dbCall->error];
        Debugger::barDump(
            ['error' => $dbError, 'where' => debug_backtrace()],
            'Database error',
            [Dumper::TRUNCATE => 1500] // longer than 150 chars
        );
        Debugger::log('Database error' . print_r($dbError, true), ILogger::ERROR);
        $this->statementList[] = "failure {$dbCall->errno}: {$dbCall->error} => " . $trimmedQuery;
        $this->logQuery("{$trimmedQuery} -- {$dbCall->errno}: {$dbCall->error}");
    }

    /**
     * Identify query that doesn't change data.
     *
     * @param string $query
     * @return bool
     */
    private function isReadDataTypeQuery(string $query): bool
    {
        return stripos($query, 'SELECT ') === 0 || stripos($query, 'SHOW ') === 0
            || stripos($query, 'DESCRIBE ') === 0 || stripos($query, 'EXPLAIN ') === 0;
    }

    /**
     * Log this query.
     *
     * @param string $query
     * @return void
     */
    private function logQuery(string $query): void
    {
        //mb_ereg_replace does not destroy multi-byte characters such as character Č
        error_log(
            mb_ereg_replace("\r\n|\r|\n", ' ', $query) . ' -- [' . date('Y-m-d H:i:s') . '] [' . $this->user . ']'
            . PHP_EOL,
            3,
            $this->logPath
        );
    }

    /**
     * Prepare wrapper that logs $query.
     *
     * Note: Other class cannot be used to override mysqli_stmt because mysqli_stmt has readonly properties and
     * that prevent new child class to set the values according to an already initiated instance
     *
     * @param string $query
     * @return \mysqli_stmt|false
     */
    #[\ReturnTypeWillChange]
    public function prepare($query)
    {
        try {
            // Call parent method to prepare the statement
            $stmt = parent::prepare($query);
            $this->addStatement((bool) $stmt, $query, $this);
            return $stmt;
        } catch (\mysqli_sql_exception $e) {
            // Catch any mysqli_sql_exception and throw it as DbmsException
            throw new DbmsException("mysqli_sql_exception: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Prepare wrapper that logs $query. Throws an exception in case of SQL statement failure.
     *
     * @param string $query
     * @return \mysqli_stmt
     * @throws DbmsException in case of failure
     */
    public function prepareStrict($query)
    {
        $stmt = $this->prepare($query);
        if ($stmt === false) {
            // Database Tracy BarPanel is displayed in try-catch in SeablastView
            throw new DbmsException('MySQLi prepare statement failed');
        }
        return $stmt;
    }

    /**
     * Logging wrapper over performing a query on the database.
     *
     * @param string $query
     * @param int $resultmode
     * @return bool|mysqli_result declared as #[\ReturnTypeWillChange] because in PHP/7 variant type cannot be written
     * @throws DbmsException in case of failure instead of mysqli_sql_exception
     */
    #[\ReturnTypeWillChange]
    public function query($query, $resultmode = MYSQLI_STORE_RESULT)
    {
        $trimmedQuery = trim($query);
        if (!$this->isReadDataTypeQuery($trimmedQuery)) {
            // Log queries that may change data
            // TODO jak NELOGOVAT hesla? Použít queryNoLog() nebo nějaká chytristika?
            $this->logQuery($trimmedQuery);
        }
        try {
            $result = parent::query($trimmedQuery, $resultmode);
            $this->addStatement((bool) $result, $trimmedQuery, $this);
            return $result;
        } catch (\mysqli_sql_exception $e) {
            // Catch any mysqli_sql_exception and throw it as DbmsException
            throw new DbmsException("mysqli_sql_exception: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Logging wrapper over performing a query on the database. Throws an exception in case of SQL statement failure.
     *
     * @param string $query
     * @param int $resultmode
     * @return bool|mysqli_result
     * @throws DbmsException in case of failure
     */
    public function queryStrict($query, $resultmode = MYSQLI_STORE_RESULT)
    {
        $result = $this->query($query, $resultmode);
        if ($result === false) {
            throw new DbmsException("{$this->errno}: {$this->error}");
        }
        return $result;
    }

    /**
     * DI setter.
     *
     * @param int|string $user
     *
     * @return void
     */
    public function setUser($user): void
    {
        $this->user = (string) $user;
    }

    /**
     * Show Tracy BarPanel with SQL statements.
     *
     * @return void
     */
    public function showSqlBarPanel(): void
    {
        if (empty($this->statementList)) {
            return;
        }
        $sqlBarPanel = new BarPanelTemplate('MySQLi: ' . count($this->statementList), $this->statementList);
        if ($this->databaseError) {
            $sqlBarPanel->setError();
        }
        Debugger::getBar()->addPanel($sqlBarPanel);
    }
}
