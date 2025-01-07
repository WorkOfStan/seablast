<?php

declare(strict_types=1);

namespace Seablast\Seablast;

/**
 * Type strict holder of properties needed for a database connection
 */
class DatabaseProperties
{
    use \Nette\SmartObject;

    /** @var string */
    public $host;
    /** @var string */
    public $name;
    /** @var string */
    public $pass;
    /** @var int|null */
    public $port;
    /** @var string */
    public $tablePrefix;
    /** @var string */
    public $user;

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
