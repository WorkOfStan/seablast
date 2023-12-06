<?php

namespace Seablast\Seablast;

use Seablast\Seablast\SeablastConfigurationException;
use Webmozart\Assert\Assert;

class SeablastConfiguration
{
    use \Nette\SmartObject;

    /** @var SeablastFlag */
    public $flag;
    /** @var array<array<string[]>> */
    private $optionsArrayArrayString = [];
    /** @var array<string[]> */
    private $optionsArrayString = [];
    /** @var bool[] */
    private $optionsBool = [];
    /** @var int[] */
    private $optionsInt = [];
    /** @var string[] */
    private $optionsString = [];

    public function __construct()
    {
        $this->flag = new SeablastFlag();
    }

    /**
     * Check existence of a property within configuration
     *
     * @param string $property
     * @return bool
     */
    public function exists(string $property): bool
    {
        Assert::string($property);
        try {
            // TODO properly test if one exception doesn't stop further execution,
            // if it would, each call MUST be caught separately!
            $result1 = $this->getArrayArrayString($property);
            $result2 = $this->getArrayString($property);
            $result3 = $this->getBool($property);
            $result4 = $this->getInt($property);
            $result5 = $this->getString($property);
        } catch (SeablastConfigurationException $ex) {
            return false;
        }
        return true;
    }

    /**
     *
     * @param string $property
     * @return array<array<string>>
     */
    public function getArrayArrayString(string $property): array
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsArrayArrayString)) {
            throw new SeablastConfigurationException('No array string for the property ' . $property);
//            return null;
        }
        return $this->optionsArrayArrayString[$property];
    }

    /**
     *
     * @param string $property
     * @return array<string>
     */
    public function getArrayString(string $property): array
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsArrayString)) {
            throw new SeablastConfigurationException('No array string for the property ' . $property);
//            return null;
        }
        return $this->optionsArrayString[$property];
    }

    /**
     *
     * @param string $property
     * @return bool
     */
    public function getBool(string $property): bool
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsBool)) {
            //    return null;
            throw new SeablastConfigurationException('No bool value for the property ' . $property);
        }
        return $this->optionsBool[$property];
    }

    /**
     *
     * @param string $property
     * @return int
     */
    public function getInt(string $property): int
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsInt)) {
            throw new SeablastConfigurationException('No int value for the property ' . $property);
//            return null;
        }
        return $this->optionsInt[$property];
    }

    /**
     *
     * @param string $property
     * @return string
     */
    public function getString(string $property): string
    {
        Assert::string($property);
        if (!array_key_exists($property, $this->optionsString)) {
            throw new SeablastConfigurationException('No string value for the property ' . $property);
//            return null;
        }
        return $this->optionsString[$property];
    }

    /**
     *
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
     *
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
     *
     * @param string $property
     * @param bool $value
     * @return $this
     */
    public function setBool(string $property, bool $value): self
    {
        Assert::string($property);
        Assert::boolean($value);
        $this->optionsBool[$property] = $value;
        return $this;
    }

    /**
     *
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
     *
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

    /**
     *
     * @return void
     */
    public function dump(): void
    {
        var_dump($this->optionsBool);
        var_dump($this->optionsInt, $this->optionsString);
    }
}
