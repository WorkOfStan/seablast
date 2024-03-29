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
    /** @ var bool[] */
    //private $optionsBool = [];
    /** @var int[] */
    private $optionsInt = [];
    /** @var string[] */
    private $optionsString = [];

    public function __construct()
    {
        $this->flag = new SeablastFlag(); // todo je flag zde k něčemu?
    }

    /**
     * Access to database with lazy initialisation.
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
        $environment = $phinx['environments']['default_environment'] ?? 'development';
        Assert::keyExists($phinx['environments'], $environment, "Phinx environment `{$environment}` isn't defined");
        $port = isset($phinx['environments'][$environment]['port'])
            ? (int) $phinx['environments'][$environment]['port'] : null;
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
        // todo keep SBconstant or the $this->connectionTablePrefix accessible through dbmsmethod?
        $this->setString('SB:phinx:table_prefix', $phinx['environments'][$environment]['table_prefix'] ?? '');
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
     * @throws \Exception
     */
    private static function dbmsReadPhinx(): array
    {
        if (!file_exists(APP_DIR . '/conf/phinx.local.php')) {
            // todo DbmsException
            throw new DbmsException('Provide credentials to use database');
        }
        return require APP_DIR . '/conf/phinx.local.php';
    }

    /**
     * Returns true on connected, false on not connected:
     * So that the SQL Bar Panel is not requested in vain.
     *
     * @return bool
     */
    public function dbmsStatus(): bool
    {
        return is_object($this->connection) && is_a($this->connection, '\mysqli');
    }

    /**
     * Check existence of a property within configuration.
     *
     * @param string $property
     * @return bool
     */
    public function exists(string $property): bool
    {
        Assert::string($property);
        $methods = [
            'getArrayArrayString',
            'getArrayInt',
            'getArrayString',
            //'getBool',
            'getInt',
            'getString'
        ];

        $exceptionCount = 0;

        foreach ($methods as $method) {
            try {
                $result = $this->$method($property);
            } catch (SeablastConfigurationException $ex) {
                $exceptionCount++;
            }
        }

        return $exceptionCount < count($methods);
    }

    /**
     * @param string $property
     * @return array<array<string>>
     */
    public function getArrayArrayString(string $property): array
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsArrayArrayString)) {
            throw new SeablastConfigurationException('No array of string array for the property ' . $property);
        }
        return $this->optionsArrayArrayString[$property];
    }

    /**
     * @param string $property
     * @return array<int>
     */
    public function getArrayInt(string $property): array
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsArrayInt)) {
            throw new SeablastConfigurationException('No array int for the property ' . $property);
        }
        return $this->optionsArrayInt[$property];
    }

    /**
     * @param string $property
     * @return array<string>
     */
    public function getArrayString(string $property): array
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsArrayString)) {
            throw new SeablastConfigurationException('No array string for the property ' . $property);
        }
        return $this->optionsArrayString[$property];
    }

    /**
     * @param string $property
     * @return bool
     */
    //public function getBool(string $property): bool
    //{
    //    Assert::string($property);
    //    if (!array_key_exists($property, $this->optionsBool)) {
    //        throw new SeablastConfigurationException('No bool value for the property ' . $property);
    //    }
    //    return $this->optionsBool[$property];
    //}

    /**
     * @param string $property
     * @return int
     */
    public function getInt(string $property): int
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsInt)) {
            throw new SeablastConfigurationException('No int value for the property ' . $property);
        }
        return $this->optionsInt[$property];
    }

    /**
     * @param string $property
     * @return string
     */
    public function getString(string $property): string
    {
        Assert::string($property);
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
        Assert::string($property);
        Assert::string($key);
        foreach ($value as $row) {
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
        Assert::string($property);
        foreach ($value as $row) {
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
        Assert::string($property);
        foreach ($value as $row) {
            Assert::string($row);
        }
        $this->optionsArrayString[$property] = $value;
        return $this;
    }

    /**
     * @param string $property
     * @param bool $value
     * @return $this
     */
    //public function setBool(string $property, bool $value): self
    //{
    //    Assert::string($property);
    //    Assert::boolean($value);
    //    $this->optionsBool[$property] = $value;
    //    return $this;
    //}

    /**
     * @param string $property
     * @param int $value
     * @return $this
     */
    public function setInt(string $property, int $value): self
    {
        Assert::string($property);
        Assert::integer($value);
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
        Assert::string($property);
        Assert::string($value);
        $this->optionsString[$property] = $value;
        return $this;
    }
}
