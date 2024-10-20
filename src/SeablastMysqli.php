<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use mysqli;
use mysqli_result;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\Tracy\BarPanelTemplate;
use Tracy\Debugger;

/**
 * mysqli wrapper with logging
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

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $dbname
     * @param int|null $port
     * @param string|null $socket
     * @throws \Exception
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
            throw new \Exception(
                'Connection to database failed with error #' . $this->connect_errno . ' ' . $this->connect_error
            );
        }
        $this->logPath = APP_DIR . '/log/query_' . date('Y-m') . '.log';
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
        try {
            $trimmedQuery = trim($query);
            if (!$this->isReadDataTypeQuery($trimmedQuery)) {
                // Log queries that may change data
                // TODO jak NELOGOVAT hesla? Použít queryNoLog() nebo nějaká chytristika?
                $this->logQuery($query);
            }
            $result = parent::query($query, $resultmode);
            $this->statementList[] = ($result === false ? 'failure => ' : '') . $trimmedQuery;
            if ($result === false) {
                $this->databaseError = true;
                // TODO optimize error logging
                Debugger::barDump(
                        ['query' => $trimmedQuery, 'Err#' => $this->errno, 'Error:' => $this->error],
                        'Database error'
                );
                $this->statementList[] = "{$this->errno}: {$this->error}";
                $this->logQuery("{$trimmedQuery} -- {$this->errno}: {$this->error}");
            }
            return $result;
        } catch (mysqli_sql_exception $e) {
            // Catch any mysqli_sql_exception and throw it as DbmsException
            throw new DbmsException("mysqli_sql_exception: " . $e->getMessage()); //, $e->getCode(), $e);
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
        //try {
            $result = $this->query($query, $resultmode);
            if ($result === false) {
                throw new DbmsException("{$this->errno}: {$this->error}");
            }
            return $result;
//        } catch (mysqli_sql_exception $e) {
//            // Catch any mysqli_sql_exception and throw it as DbmsException
//            throw new DbmsException("mysqli_sql_exception: " . $e->getMessage()); //, $e->getCode(), $e);
//        }
    }

    /**
     * Identify query that doesn't change data.
     *
     * @param string $query
     * @return bool
     */
    private function isReadDataTypeQuery(string $query): bool
    {
        return stripos($query, 'SELECT ') === 0 || stripos($query, 'SET ') === 0 || stripos($query, 'SHOW ') === 0
            || stripos($query, 'DESCRIBE ') === 0 || stripos($query, 'DO ') === 0 || stripos($query, 'EXPLAIN ') === 0;
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
            mb_ereg_replace("\r\n|\r|\n", ' ', $query) . ' -- [' . date('Y-m-d H:i:s') . ']' . PHP_EOL,
            3,
            $this->logPath
        );
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
