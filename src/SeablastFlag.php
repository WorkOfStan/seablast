<?php

declare(strict_types=1);

namespace Seablast\Seablast;

//use Webmozart\Assert\Assert;

class SeablastFlag
{
    use \Nette\SmartObject;

    /** @var bool[] */
    private $flags;

    public function __construct()
    {
        $this->flags = [];
    }

    public function activate(string $property): self
    {
        //Assert::string($property);
        $this->flags[$property] = true;
        return $this;
    }

    public function deactivate(string $property): self
    {
        //Assert::string($property);
        unset($this->flags[$property]);
        return $this;
    }

    public function status(string $property): bool
    {
        //Assert::string($property);
        return array_key_exists($property, $this->flags);
    }
}
