<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use mysqli;
use Seablast\Seablast\Tracy\BarPanelTemplate;
use Tracy\Debugger;

/**
 * mysqli wrapper with logging
 */
class SeablastMysqli extends mysqli
{
    use \Nette\SmartObject;

    /** @var bool true if any of the SQL statements ended in an error state */
    private $databaseError = false;
    /** @var string */
    private $logPath = APP_DIR . '/log/query.log';
    /** @var string[] For Tracy Bar Panel. */
    private $statementList = [];

    /**
     *
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
    )
    {
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
    }

    public function query($query, $resultmode = MYSQLI_STORE_RESULT)
    {
        $trimmedQuery = trim($query);
        // todo what other keywords?
        if (!$this->isReafDataTypeQuery($trimmedQuery)) {
            // Log queries that may change data
            // TODO jak NELOGOVAT hesla? Použít queryNoLog() nebo nějaká chytristika?
            $this->logQuery($query);
        }
        $result = parent::query($query, $resultmode);
        $this->statementList[] = ($result === false ? 'failure => ' : '') . $trimmedQuery;
        if ($result === false) {
            $this->databaseError = true;
        }
        return $result;
    }

    /**
     * Identify query that doesn't change data
     */
    private function isReadDataTypeQuery(string $query): bool
    {
        return stripos($query, 'SELECT ') === 0 || stripos($query, 'SET ') === 0
            || stripos($query, 'SHOW ') === 0;
    }

    /**
     *
     * @param string $query
     * @return void
     */
    private function logQuery(string $query): void
    {
        // TODO Implement your logging logic here
        // TODO add timestamp, rotating logs, error_log might be better
        file_put_contents($this->logPath, $query . PHP_EOL, FILE_APPEND);
    }

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
