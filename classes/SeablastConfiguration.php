<?php

namespace Seablast\Seablast;

use Webmozart\Assert\Assert;

class SeablastConfiguration
{
    use \Nette\SmartObject;

    /** @var SeablastFlag */
    public $flag;
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

    public function setBool(string $property, bool $value) : self
    {
        Assert::string($property);
        Assert::boolean($value);
        $this->optionsBool[$property] = $value;
        return $this;
    }

    public function setInt(string $property, int $value) : self
    {
        Assert::string($property);
        Assert::integer($value);
        $this->optionsInt[$property] = $value;
        return $this;
    }
    
    public function setString(string $property, string $value) : self
    {
        Assert::string($property);
        Assert::string($value);
        $this->optionsString[$property] = $value;
        return $this;
    }

    public function dump() : void {
        var_dump($this->optionsBool);
        var_dump($this->optionsInt, $this->optionsString);
    }

}
