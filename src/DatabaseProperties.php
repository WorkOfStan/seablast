<?php

declare(strict_types=1);

namespace Seablast\Seablast;

/**
 * Type strict holder of properties needed for a database connection
 */
class DatabaseProperties
{
    public string $host;
    public string $user;
    public string $pass;
    public string $name;
    public ?int $port;
    public string $tablePrefix;

    /**
     * Constructor to initialize the database properties.
     *
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $name
     * @param int|null $port
     * @param string $tablePrefix
     */
    public function __construct(
        string $host,
        string $user,
        string $pass,
        string $name,
        ?int $port = null,
        string $tablePrefix = ''
    ) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->name = $name;
        $this->port = $port;
        $this->tablePrefix = $tablePrefix;
    }
}
