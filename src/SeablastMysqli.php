<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use mysqli;
use mysqli_result;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\Tracy\BarPanelTemplate;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\ILogger;

/**
 * MySQLi wrapper with logging.
 *
 * setUser() injects user ID to be logged with queries
 * TODO: explore logging of prepared statements
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
     * This method is public, so that SeablastMysqliStmt may use it.
     *
     * @param bool $result
     * @param string $trimmedQuery
     * @param \mysqli|\mysqli_stmt $dbCall
     * @return void
     */
    public function addStatement(bool $result, string $trimmedQuery, object $dbCall): void
    {
        if ($result !== false) {
            $this->statementList[] = $trimmedQuery;
            return;
        }
        $this->databaseError = true;
        $dbError = ['query' => $trimmedQuery, 'Err#' => $dbCall->errno, 'Error:' => $dbCall->error];
        Debugger::barDump($dbError, 'Database error', [Dumper::TRUNCATE => 1500]); // longer than 150 chars
        Debugger::log('Database error' . print_r($dbError, true), ILogger::ERROR);
        $this->statementList[] = "failure {$dbCall->errno}: {$dbCall->error} => " . $trimmedQuery;
        $this->logQuery("{$trimmedQuery} -- {$dbCall->errno}: {$dbCall->error}");
    }

    /**
     * Override the prepare method to return LoggedMysqliStmt.
     *
     * @param string $query
     * @return SeablastMysqliStmt|false #[\ReturnTypeWillChange] because original @return \mysqli_stmt|false
     */
    #[\ReturnTypeWillChange]
    public function prepare($query)
    {
        // Call parent method to prepare the statement
        $stmt = parent::prepare($query);
        if ($stmt === false) {
            return false;
        }

        // Wrap the existing mysqli_stmt with LoggedMysqliStmt
        return new SeablastMysqliStmt($this, $stmt, $query);
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
     * @return bool|mysqli_result declared as #[\ReturnTypeWillChange] because in PHP/7 variant type cannot be written
     * @throws DbmsException in case of failure
     */
    #[\ReturnTypeWillChange]
    public function queryStrict($query, $resultmode = MYSQLI_STORE_RESULT)
    {
        $result = $this->query($query, $resultmode);
        if ($result === false) {
            throw new DbmsException("{$this->errno}: {$this->error}");
        }
        return $result;
    }

    /**
     * Identify query that doesn't change data.
     *
     * public, so that SeablastMysqliStmt may use it - TODO DRY query logging
     *
     * @param string $query
     * @return bool
     */
    public function isReadDataTypeQuery(string $query): bool
    {
        return stripos($query, 'SELECT ') === 0 || stripos($query, 'SET ') === 0 || stripos($query, 'SHOW ') === 0
            || stripos($query, 'DESCRIBE ') === 0 || stripos($query, 'DO ') === 0 || stripos($query, 'EXPLAIN ') === 0;
    }

    /**
     * Log this query.
     *
     * public so that SeablastMysqliStmt may use it.
     *
     * @param string $query
     * @return void
     */
    public function logQuery(string $query): void
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
        $sqlBarPanel = new BarPanelTemplate('SQL: ' . count($this->statementList), $this->statementList);
        if ($this->databaseError) {
            $sqlBarPanel->setError();
        }
        Debugger::getBar()->addPanel($sqlBarPanel);
    }
}
