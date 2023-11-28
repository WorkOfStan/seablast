<?php

namespace Seablast\Seablast;

use Webmozart\Assert\Assert;

class SeablastConfiguration
{
    use \Nette\SmartObject;

    /** @var SeablastFlag */
    public $flag;

    /** @var array<string[]> */
    private $optionsArrayString;

    /** @var bool[] */
    private $optionsBool;

    /** @var int[] */
    private $optionsInt;

    /** @var string[] */
    private $optionsString;

    public function __construct()
    {
        $this->flag = new SeablastFlag();
        $this->optionsBool = [];
        $this->optionsInt = [];
        $this->optionsString = [];
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
