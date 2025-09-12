<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

/**
 * Universal data structure with strict data typing.
 */
class SeablastConfiguration
{
    use \Nette\SmartObject;

    /** @var string|null */
    private $connectionTablePrefix = null;
    /** @var SeablastFlag */
    public $flag;
    /** @var SeablastMysqli|null */
    private $mysqli = null;
    /** @var array<array<string[]>> */
    private $optionsArrayArrayString = [];
    /** @var array<int[]> */
    private $optionsArrayInt = [];
    /** @var array<string[]> */
    private $optionsArrayString = [];
    /** @var int[] */
    private $optionsInt = [];
    /** @var string[] */
    private $optionsString = [];
    /** @var SeablastPdo|null */
    private $pdo = null;
    /** @var string|null for db logging */
    private $user = null;

    public function __construct()
    {
        $this->flag = new SeablastFlag(); // initialization instead of `private $optionsX = [];` above
    }

    /**
     * Access to database using MySQLi adapter with lazy initialization.
     *
     * @deprecated 0.2.8 Use {@see mysqli()} instead.
     * @return SeablastMysqli
     */
    public function dbms(): SeablastMysqli
    {
        Debugger::barDump('Deprecated dbms(). Use mysqli() instead.');
        Debugger::log('Deprecated dbms(). Use mysqli() instead.', \Tracy\ILogger::INFO);
        return $this->mysqli();
    }

    /**
     * Read phinx configuration and provide pertinent parameters in a type strict manner
     */
    private function dbmsExtractProperties(): DatabaseProperties
    {
        $phinx = self::dbmsReadPhinx();
        Assert::isArray($phinx['environments']);
        $environment = $this->exists(SeablastConstant::SB_PHINX_ENVIRONMENT)
            ? $this->getString(SeablastConstant::SB_PHINX_ENVIRONMENT)
            : ($phinx['environments']['default_environment'] ?? 'undefined');
        Assert::string($environment);
        Assert::keyExists(
            $phinx['environments'],
            $environment,
            "Phinx environment `{$environment}` isn't defined - check SB_PHINX_ENVIRONMENT or default_environment"
        );
        Assert::isArray($phinx['environments'][$environment]);
        if (isset($phinx['environments'][$environment]['port'])) {
            Assert::scalar($phinx['environments'][$environment]['port']);
            $port = (int) $phinx['environments'][$environment]['port'];
        } else {
            $port = null;
        }
        return new DatabaseProperties(
            $phinx['environments'][$environment]['adapter'],
            $phinx['environments'][$environment]['host'], // todo fix localhost
            $phinx['environments'][$environment]['user'],
            $phinx['environments'][$environment]['pass'],
            $phinx['environments'][$environment]['name'],
            $port,
            $phinx['environments'][$environment]['table_prefix'] ?? ''
        );
    }

    /**
     * Return the table prefix from phinx config.
     *
     * @return string
     */
    public function dbmsTablePrefix(): string
    {
        if (is_null($this->connectionTablePrefix)) {
            throw new DbmsException('Initiate db first.');
        }
        return $this->connectionTablePrefix;
    }

    /**
     * Read the database connection parameters from an external phinx configuration.
     *
     * @return array<mixed>
     * @throws DbmsException
     */
    private static function dbmsReadPhinx(): array
    {
        if (!file_exists(APP_DIR . '/conf/phinx.local.php')) {
            throw new DbmsException('Provide credentials in conf/phinx.local.php to use database');
        }
        return require APP_DIR . '/conf/phinx.local.php';
    }

    /**
     * Returns true if connected, false otherwise.
     *
     * So that the SQL Bar Panel is not requested in vain.
     *
     * @deprecated 0.2.8 Use {@see mysqliStatus()} instead.
     * @return bool
     */
    public function dbmsStatus(): bool
    {
        Debugger::barDump('Deprecated dbmsStatus(). Use mysqliStatus() instead.');
        Debugger::log('Deprecated dbmsStatus(). Use mysqliStatus() instead.', \Tracy\ILogger::INFO);
        return $this->mysqli instanceof \mysqli;
    }

    /**
     * Check existence of a property within configuration.
     *
     * @param string $property
     * @return bool
     */
    public function exists(string $property): bool
    {
        $methods = [
            'getArrayArrayString',
            'getArrayInt',
            'getArrayString',
            'getInt',
            'getString'
        ];

        foreach ($methods as $method) {
            try {
                $this->$method($property);
                return true;
            } catch (SeablastConfigurationException $ex) {
                // Ignore and continue
            }
        }

        return false;
    }

    /**
     * @param string $property
     * @return array<array<string>>
     * @throws SeablastConfigurationException
     */
    public function getArrayArrayString(string $property): array
    {
        if (!array_key_exists($property, $this->optionsArrayArrayString)) {
            throw new SeablastConfigurationException('No array of string array for the property ' . $property);
        }
        return $this->optionsArrayArrayString[$property];
    }

    /**
     * @param string $property
     * @return array<int>
     * @throws SeablastConfigurationException
     */
    public function getArrayInt(string $property): array
    {
        if (!array_key_exists($property, $this->optionsArrayInt)) {
            throw new SeablastConfigurationException('No array int for the property ' . $property);
        }
        return $this->optionsArrayInt[$property];
    }

    /**
     * @param string $property
     * @return array<string>
     * @throws SeablastConfigurationException
     */
    public function getArrayString(string $property): array
    {
        if (!array_key_exists($property, $this->optionsArrayString)) {
            throw new SeablastConfigurationException('No array string for the property ' . $property);
        }
        return $this->optionsArrayString[$property];
    }

    /**
     * @param string $property
     * @return int
     * @throws SeablastConfigurationException
     */
    public function getInt(string $property): int
    {
        if (!array_key_exists($property, $this->optionsInt)) {
            throw new SeablastConfigurationException('No int value for the property ' . $property);
        }
        return $this->optionsInt[$property];
    }

    /**
     * @param string $property
     * @return string
     * @throws SeablastConfigurationException
     */
    public function getString(string $property): string
    {
        if (!array_key_exists($property, $this->optionsString)) {
            throw new SeablastConfigurationException('No string value for the property ' . $property);
        }
        return $this->optionsString[$property];
    }

    /**
     * Access to database using MySQLi adapter with lazy initialization.
     *
     * @return SeablastMysqli
     */
    public function mysqli(): SeablastMysqli
    {
        //Lazy initialisation
        if (!$this->mysqliStatus()) {
            Debugger::barDump('Creating MySQLi database connection');
            $this->mysqliCreate();
        }
        Assert::object($this->mysqli);
        Assert::isAOf($this->mysqli, '\Seablast\Seablast\SeablastMysqli');
        return $this->mysqli;
    }

    /**
     * Creates a database connection and sets up charset.
     *
     * @return void
     */
    private function mysqliCreate(): void
    {
        $phinx = $this->dbmsExtractProperties();
        $this->mysqli = new SeablastMysqli(
            $phinx->host, // todo fix localhost
            $phinx->user,
            $phinx->pass,
            $phinx->name,
            $phinx->port
        );
        // todo does this really differentiate between successful connection, failed connection and no connection?
        Assert::isAOf($this->mysqli, '\Seablast\Seablast\SeablastMysqli');
        Assert::true(
            $this->mysqli->set_charset($this->getString(SeablastConstant::SB_CHARSET_DATABASE)),
            'Unexpected character set: ' . $this->getString(SeablastConstant::SB_CHARSET_DATABASE)
        );
        $this->connectionTablePrefix = $phinx->tablePrefix;
        // if user already set then setUser
        if (!is_null($this->user)) {
            $this->mysqli->setUser($this->user);
        }
    }

    /**
     * Returns true if mysqli adapter connected, false otherwise.
     *
     * So that the SQL Bar Panel is not requested in vain.
     *
     * @return bool
     */
    public function mysqliStatus(): bool
    {
        return $this->mysqli instanceof \mysqli;
    }

    /**
     * Access to database using PDO adapter with lazy initialization.
     *
     * @return SeablastPdo
     */
    public function pdo(): SeablastPdo
    {
        //Lazy initialisation
        if (!$this->pdoStatus()) {
            Debugger::barDump('Creating PDO database connection');
            $this->pdoCreate();
        }
        Assert::object($this->pdo);
        Assert::isAOf($this->pdo, '\Seablast\Seablast\SeablastPdo');
        return $this->pdo;
    }

    /**
     * Creates a database connection and sets up charset.
     *
     * @return void
     */
    private function pdoCreate(): void
    {
        $phinx = $this->dbmsExtractProperties();
        //PDO("mysql:host=localhost;dbname=DB;charset=UTF8")
        $this->pdo = new SeablastPdo(
            //   $phinx->host, // todo fix localhost
            "{$phinx->adapter}:host={$phinx->host}"
            . (is_null($phinx->port) ? '' : ";port={$phinx->port}")
            . ";dbname={$phinx->name};charset={$this->getString(SeablastConstant::SB_CHARSET_DATABASE)}",
            $phinx->user,
            $phinx->pass
        );
        // todo does this really differentiate between successful connection, failed connection and no connection?
        Assert::isAOf($this->pdo, '\Seablast\Seablast\SeablastPdo');
        $this->connectionTablePrefix = $phinx->tablePrefix;
        // if user already set then setUser
        if (!is_null($this->user)) {
            $this->pdo->setUser($this->user);
        }
    }

    /**
     * Returns true if PDO adapter connected, false otherwise.
     *
     * So that the SQL Bar Panel is not requested in vain.
     *
     * @return bool
     */
    public function pdoStatus(): bool
    {
        return $this->pdo instanceof \PDO;
    }

    /**
     * @param string $property
     * @param string $key
     * @param string[] $value
     * @return $this
     */
    public function setArrayArrayString(string $property, string $key, array $value): self
    {
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        Assert::allString($value);
        $this->optionsArrayArrayString[$property][$key] = $value;
        return $this;
    }

    /**
     * @param string $property
     * @param int[] $value
     * @return $this
     */
    public function setArrayInt(string $property, array $value): self
    {
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        Assert::allInteger($value);
        $this->optionsArrayInt[$property] = $value;
        return $this;
    }

    /**
     * @param string $property
     * @param string[] $value
     * @return $this
     */
    public function setArrayString(string $property, array $value): self
    {
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        Assert::allString($value);
        $this->optionsArrayString[$property] = $value;
        return $this;
    }

    /**
     * @param string $property
     * @param int $value
     * @return $this
     */
    public function setInt(string $property, int $value): self
    {
        $this->optionsInt[$property] = $value;
        return $this;
    }

    /**
     * @param string $property
     * @param string $value
     * @return $this
     */
    public function setString(string $property, string $value): self
    {
        $this->optionsString[$property] = $value;
        return $this;
    }

    /**
     * DI setter for databases.
     *
     * @param int|string $user
     *
     * @return void
     */
    public function setUser($user): void
    {
        $this->user = (string) $user;
        // if mysqli or pdo then setUser
        if ($this->mysqliStatus()) {
            $this->mysqli()->setUser($this->user);
        }
        if ($this->pdoStatus()) {
            $this->pdo()->setUser($this->user);
        }
    }

    /**
     * Show Tracy BarPanel with SQL statements.
     *
     * @return void
     */
    public function showSqlBarPanel(): void
    {
        // if mysqli or pdo then showSqlBarPanel
        if ($this->mysqliStatus()) {
            $this->mysqli()->showSqlBarPanel();
        }
        if ($this->pdoStatus()) {
            $this->pdo()->showSqlBarPanel();
        }
    }
}
