<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use PDO;
use PDOException;
use PDOStatement;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\Tracy\BarPanelTemplate;
use Tracy\Debugger;
use Tracy\ILogger;

class SeablastPdo extends PDO
{
    use \Nette\SmartObject;

    /** @var bool */
    private $databaseError = false;
    /** @var string */
    private $logPath;
    /** @var string[] */
    private $statementList = [];
    /** @var string */
    private $user = 'unidentified';

    /**
     * Constructor to initialize the PDO connection and configure logging.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array<scalar> $options
     * @throws DbmsException
     */
    public function __construct(string $dsn, string $username, string $password, array $options = [])
    {
        try {
            parent::__construct($dsn, $username, $password, $options);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->logPath = Debugger::$logDirectory . '/query_' . date('Y-m') . '.log';
        } catch (PDOException $e) {
            throw new DbmsException("Connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Executes a query and logs it.
     *
     * @param string $query
     * @return PDOStatement|false
     * @throws DbmsException
     */
    public function query(string $query)
    {
        $trimmedQuery = trim($query);
        if (!$this->isReadDataTypeQuery($trimmedQuery)) {
            $this->logQuery($trimmedQuery);
        }
        try {
            $stmt = parent::query($trimmedQuery);
            $this->addStatement(true, $trimmedQuery);
            return $stmt;
        } catch (PDOException $e) {
            $this->addStatement(false, $trimmedQuery);
            throw new DbmsException("Query failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Prepares a statement and logs the query.
     *
     * Note: when PHP/7 support removed, remove phpstan-ignore-line below
     * (removed)
     *
     * @param string $query but mixed in PHP/7
     * @param array<scalar> $options but mixed in PHP/7
     * @return PDOStatement|false
     * @throws DbmsException
     */
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        try {
            $stmt = parent::prepare($query, $options);
            $this->addStatement((bool)$stmt, $query);
            return $stmt;
        } catch (PDOException $e) {
            $this->addStatement(false, $query);
            throw new DbmsException("Preparation failed 🤔: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Logs the SQL query.
     *
     * @param string $query
     * @return void
     */
    private function logQuery(string $query): void
    {
        error_log(
            $query . ' -- [' . date('Y-m-d H:i:s') . '] [' . $this->user . ']' . PHP_EOL,
            3,
            $this->logPath
        );
    }

    /**
     * Adds a query to the statement list for logging purposes.
     *
     * @param bool $success
     * @param string $query
     * @return void
     */
    private function addStatement(bool $success, string $query): void
    {
        if ($success) {
            $this->statementList[] = $query;
        } else {
            $this->databaseError = true;
            $this->statementList[] = "Failure: $query";
            Debugger::log("Database error: $query", ILogger::ERROR);
        }
    }

    /**
     * Determines if the query is read-only.
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
     * Displays the SQL statements in Tracy's bar panel.
     *
     * @return void
     */
    public function showSqlBarPanel(): void
    {
        if (empty($this->statementList)) {
            return;
        }
        $sqlBarPanel = new BarPanelTemplate('PDO: ' . count($this->statementList), $this->statementList);
        if ($this->databaseError) {
            $sqlBarPanel->setError();
        }
        Debugger::getBar()->addPanel($sqlBarPanel);
    }
}
