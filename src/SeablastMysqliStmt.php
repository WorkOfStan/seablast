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
    /** @//var string */
  //  private $logPath;
    /** @var SeablastMysqli */
    private $mysqli;
    /** @var string */
    private $query;
    /** @//var string */
//    private $user = 'unidentified';

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

    public function execute()
    {
        $compiledQuery = $this->logThisQuery();
        // Log the SQL query
        if (!$this->isReadDataTypeQuery($trimmedQuery)) {
            // Log queries that may change data
            // TODO jak NELOGOVAT hesla? Použít queryNoLog() nebo nějaká chytristika?
$this->mysqli->logQuery($compiledQuery); // Logs and returns the full query with bound values
        }
                try {
                    // Execute the statement
        $result = parent::execute();
                    $this->mysqli->addStatement((bool) $result, $compiledQuery, $this);
                    /*
// todo dry logging instead of
                    Debugger::log($compiledQuery, 'compiled query');
if ($result === false) {
    //echo "Error: " . parent::error;
    // todo dry
    $dbError = ['query' => $compiledQuery, 'Err#' => $this->errno, 'Error:' => $this->error];
                Debugger::barDump($dbError, 'Database error', [Dumper::TRUNCATE => 1500]); // longer than 150 chars
                Debugger::log('Database error' . print_r($dbError, true), ILogger::ERROR);
                // todo end the rest of SeablastMysqli::query logging
}
                    */
        return $result;
                    } catch (\mysqli_sql_exception $e) {
            // Catch any mysqli_sql_exception and throw it as DbmsException
            throw new DbmsException("mysqli_sql_exception: " . $e->getMessage(), $e->getCode(), $e);
                }
    }
    
    /**
     * The mysqli_stmt class does not directly provide a method to return the final compiled SQL statement with bound parameters substituted. This is because parameter substitution happens at the database engine level for performance and security reasons. However, we can achieve this functionality by manually substituting parameters in the query string ourselves.
     */
    public function logThisQuery(): string
    {
        $loggedQuery = $this->query;
        foreach ($this->boundParams as $param) {
            $value = is_numeric($param) ? $param : "'" . self::escapeParam($param) . "'";
            $loggedQuery = preg_replace('/\?/', $value, $loggedQuery, 1);
        }

        //file_put_contents('query.log', $loggedQuery . PHP_EOL, FILE_APPEND);

        return trim($loggedQuery);
    }
    /**
     * Log this query.
     *
     * //@ param string $query
     * @return void
     */
/*    private function logQuery(): void
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
 /*   public function setUser($user): void
    {
        $this->user = (string) $user;
    }*/

    private static function escapeParam($param): string
    {
        return str_replace("'", "\\'", $param);
    }
}
