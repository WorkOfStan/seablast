<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use mysqli;
use mysqli_stmt;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\Tracy\BarPanelTemplate;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\ILogger;

class SeablastMysqliStmt extends mysqli_stmt
{
    use \Nette\SmartObject;
  
    /** @var array<scalar> */
    private $boundParams = [];
    /** @var string */
    private $logPath;
    /** @var string */
    private $query;
    /** @var string */
    private $user = 'unidentified';

    /**
     * Constructor that wraps an existing mysqli_stmt object.
     *
     * @param mysqli $mysqli
     * @param mysqli_stmt $stmt
     * @param string $query
     */
    public function __construct(mysqli $mysqli, mysqli_stmt $stmt, string $query)
    {
        // Call parent constructor with the existing statement
        parent::__construct($mysqli);
        $this->query = $query;

        // Copy internal state from the original statement
        $this->initFrom($stmt);

        // Use Debugger::$logDirectory instead of APP_DIR . '/log'
        $this->logPath = Debugger::$logDirectory . '/query_' . date('Y-m') . '.log';
    }

    /**
     * Copy internal state from another mysqli_stmt.
     *
     * @param mysqli_stmt $stmt
     */
    private function initFrom(mysqli_stmt $stmt): void
    {
        $this->affected_rows = $stmt->affected_rows;
        $this->insert_id = $stmt->insert_id;
        $this->num_rows = $stmt->num_rows;
        $this->param_count = $stmt->param_count;
        $this->field_count = $stmt->field_count;
        $this->sqlstate = $stmt->sqlstate;
        $this->error = $stmt->error;
        $this->errno = $stmt->errno;
    }

    public function bind_param(string $types, &...$params): bool
    {
        $this->boundParams = $params;
        return parent::bind_param($types, ...$params);
    }

    public function logThisQuery(): string
    {
        $loggedQuery = $this->query;
        foreach ($this->boundParams as $param) {
            $value = is_numeric($param) ? $param : "'" . $this->escapeParam($param) . "'";
            $loggedQuery = preg_replace('/\?/', $value, $loggedQuery, 1);
        }

        //file_put_contents('query.log', $loggedQuery . PHP_EOL, FILE_APPEND);

        return $loggedQuery;
    }
    /**
     * Log this query.
     *
     * //@ param string $query
     * @return void
     */
    private function logQuery(): void
    {
        //mb_ereg_replace does not destroy multi-byte characters such as character Č
        error_log(
            mb_ereg_replace("\r\n|\r|\n", ' ', $this->logThisQuery()) . ' -- [' . date('Y-m-d H:i:s') . '] [' . $this->user . ']'
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

    private function escapeParam($param): string
    {
        return str_replace("'", "\\'", $param);
    }
}
