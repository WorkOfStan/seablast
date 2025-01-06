<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use mysqli;
use mysqli_stmt;
use Seablast\Seablast\Exceptions\DbmsException;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\ILogger;

class SeablastMysqliStmt extends mysqli_stmt
{
    use \Nette\SmartObject;
  
    /** @var array<scalar> */
    private $boundParams = [];
    /** @var SeablastMysqli */
    private $mysqli;
    /** @var string */
    private $query;

    /**
     * Constructor that wraps an existing mysqli_stmt object.
     *
     * @param SeablastMysqli $mysqli
     * @param mysqli_stmt $stmt
     * @param string $query
     */
    public function __construct(SeablastMysqli $mysqli, mysqli_stmt $stmt, string $query)
    {
        $this->mysqli = $mysqli;
        // Call parent constructor with the existing statement
        parent::__construct($this->mysqli);
        $this->query = $query;

        // Copy internal state from the original statement
        $this->initFrom($stmt);
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

    public function execute(): bool
    {
        $compiledQuery = $this->compileQuery();
        // Log the SQL query
        if (!$this->isReadDataTypeQuery($compiledQuery)) {
            // Log queries that may change data
            // TODO jak NELOGOVAT hesla? Použít queryNoLog() nebo nějaká chytristika?
            $this->mysqli->logQuery($compiledQuery); // Logs and returns the full query with bound values
        }
        try {
            // Execute the statement
            $result = parent::execute();
            $this->mysqli->addStatement((bool) $result, $compiledQuery, $this);
            return $result;
        } catch (\mysqli_sql_exception $e) {
            // Catch any mysqli_sql_exception and throw it as DbmsException
            throw new DbmsException("mysqli_sql_exception: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * The mysqli_stmt class does not directly provide a method to return the final compiled SQL statement with bound parameters substituted. This is because parameter substitution happens at the database engine level for performance and security reasons. However, we can achieve this functionality by manually substituting parameters in the query string ourselves.
     */
    private function compileQuery(): string
    {
        $loggedQuery = $this->query;
        foreach ($this->boundParams as $param) {
            $value = is_numeric($param) ? $param : "'" . self::escapeParam($param) . "'";
            $loggedQuery = preg_replace('/\?/', $value, $loggedQuery, 1);
        }
        return trim($loggedQuery);
    }

    private static function escapeParam($param): string
    {
        return str_replace("'", "\\'", $param);
    }
}
