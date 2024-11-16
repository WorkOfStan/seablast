<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\SeablastConfigurationException;
use Seablast\Seablast\SeablastMysqli;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

/**
 * Universal data structure with strict data typing.
 */
class SeablastConfiguration
{
    use \Nette\SmartObject;

    /** @var ?SeablastMysqli */
    private $connection = null;
    /** @var ?string */
    private $connectionTablePrefix = null;
    /** @var SeablastFlag */
    public $flag;
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

    public function __construct()
    {
        $this->flag = new SeablastFlag(); // initialization instead of `private $optionsX = [];` above
    }

    /**
     * Access to database with lazy initialization.
     *
     * @return SeablastMysqli
     */
    public function dbms(): SeablastMysqli
    {
        //Lazy initialisation
        if (!$this->dbmsStatus()) {
            Debugger::barDump('Creating database connection');
            $this->dbmsCreate();
        }
        Assert::object($this->connection);
        Assert::isAOf($this->connection, '\Seablast\Seablast\SeablastMysqli');
        return $this->connection;
    }

    /**
     * Creates a database connection and sets up charset.
     *
     * @return void
     */
    private function dbmsCreate(): void
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
        $this->connection = new SeablastMysqli(
            $phinx['environments'][$environment]['host'], // todo fix localhost
            $phinx['environments'][$environment]['user'],
            $phinx['environments'][$environment]['pass'],
            $phinx['environments'][$environment]['name'],
            $port
        );
        // todo does this really differentiate between successful connection, failed connection and no connection?
        Assert::isAOf($this->connection, '\Seablast\Seablast\SeablastMysqli');
        Assert::true(
            $this->connection->set_charset($this->getString(SeablastConstant::SB_CHARSET_DATABASE)),
            'Unexpected character set: ' . $this->getString(SeablastConstant::SB_CHARSET_DATABASE)
        );
        $this->connectionTablePrefix = $phinx['environments'][$environment]['table_prefix'] ?? '';
    }

    /**
     * Return the table prefix from phinx config.
     *
     * TODO experimental - keep only if working well
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
     * @return bool
     */
    public function dbmsStatus(): bool
    {
        return $this->connection instanceof \mysqli;
    }

    /**
     * Check existence of a property within configuration.
     *
     * @param string $property
     * @return bool
     */
    public function exists(string $property): bool
    {
        //Assert::string($property);
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
        //Assert::string($property);
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
        //Assert::string($property);
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
        //Assert::string($property);
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
        //Assert::string($property);
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
        //Assert::string($property);
        if (!array_key_exists($property, $this->optionsString)) {
            throw new SeablastConfigurationException('No string value for the property ' . $property);
        }
        return $this->optionsString[$property];
    }

    /**
     * @param string $property
     * @param string $key
     * @param string[] $value
     * @return $this
     */
    public function setArrayArrayString(string $property, string $key, array $value): self
    {
        //Assert::string($property);
        //Assert::string($key);
        foreach ($value as $row) {
            /** @phpstan-ignore staticMethod.alreadyNarrowedType */
            Assert::string($row);
        }
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
        //Assert::string($property);
        foreach ($value as $row) {
            /** @phpstan-ignore staticMethod.alreadyNarrowedType */
            Assert::integer($row);
        }
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
        //Assert::string($property);
        foreach ($value as $row) {
            /** @phpstan-ignore staticMethod.alreadyNarrowedType */
            Assert::string($row);
        }
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
        //Assert::string($property);
        //Assert::integer($value);
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
        //Assert::string($property);
        //Assert::string($value);
        $this->optionsString[$property] = $value;
        return $this;
    }
}
