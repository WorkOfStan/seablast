<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use mysqli;
use mysqli_stmt;
use Seablast\Seablast\Exceptions\DbmsException;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\ILogger;
use Webmozart\Assert\Assert;

class SeablastMysqliStmt extends mysqli_stmt
{
    use \Nette\SmartObject;

    /** @var array<mixed> */
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

    /**
     * Override the bind_param method.
     *
     * Note:  The mysqli_stmt::bind_param() method's arguments are implicitly passed by reference, but its signature
     * does not explicitly declare & for the parameters. PHPStan enforces strict compliance with the parent class
     * signature, and adding explicit & or using variadic arguments is treated as a mismatch.
     *
     * @param string $types
     * @param mixed $varA
     * @param mixed ...$vars
     * @return bool
     */
    public function bind_param($types, $varA = null, ...$vars): bool // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        // Use reflection to capture all arguments
        $args = func_get_args();

        // Capture all bound parameters, excluding $types
        $this->boundParams = array_slice($args, 1);
        $this->boundParams = $this->boundParams ?? [];

        // Call the parent method directly
//        if (empty($this->boundParams)) {
//            return (bool) parent::bind_param($types);
//        } else {
        return (bool) parent::bind_param($types, ...$this->boundParams);
//        }
        //return (bool) parent::bind_param(...$args);
    }

    /**
     * @param array<string>|null $params PHP/8.1.0 The optional params parameter has been added.
     * //TODO make sure it's array<string>
     * @return bool
     * @throws DbmsException
     */
    public function execute(?array $params = null): bool
    {
        // TODO shouldn't $param be also logged?
        $compiledQuery = $this->compileQuery();
        // Log the SQL query
        if (!$this->mysqli->isReadDataTypeQuery($compiledQuery)) {
            // Log queries that may change data
            // TODO jak NELOGOVAT hesla? Použít queryNoLog() nebo nějaká chytristika?
            $this->mysqli->logQuery($compiledQuery); // Logs and returns the full query with bound values
        }
        try {
            // Execute the statement
            //if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            //    $result = parent::execute($params);
            //} else {
            $result = parent::execute();
            //}
            $this->mysqli->addStatement((bool) $result, $compiledQuery, $this);
            return $result;
        } catch (\mysqli_sql_exception $e) {
            // Catch any mysqli_sql_exception and throw it as DbmsException
            throw new DbmsException("mysqli_sql_exception: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * The mysqli_stmt class does not directly provide a method to return the final compiled SQL statement with bound
     * parameters substituted. This is because parameter substitution happens at the database engine level for
     * performance and security reasons. However, we can achieve this functionality by manually substituting parameters
     * in the query string ourselves.
     *
     * @return string
     */
    private function compileQuery(): string
    {
        $loggedQuery = $this->query;
        //Assert::isIterable($this->boundParams);
        foreach ($this->boundParams as $param) {
            Assert::string($param);
            $value = is_numeric($param) ? $param : "'" . self::escapeParam($param) . "'";
            //Assert::string($value);
            Assert::string($loggedQuery);
            $loggedQuery = preg_replace('/\?/', $value, $loggedQuery, 1);
        }
        Assert::string($loggedQuery);
        return trim($loggedQuery);
    }

    /**
     * @param string $param
     * @return string
     */
    private static function escapeParam(string $param): string
    {
        return str_replace("'", "\\'", $param);
    }
}
