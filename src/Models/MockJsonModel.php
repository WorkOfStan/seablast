<?php

declare(strict_types=1);

namespace Seablast\Seablast\Models;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;
use Webmozart\Assert\Assert;

/**
 * A mock model for PHPUnit test.
 */
class MockJsonModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        unset($superglobals); // just to do something
    }

    /**
     * Return the knowledge calculated in this model.
     *
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        Assert::true($this->configuration->exists('testRest'), 'testRest for JSON test is missing');

        Debugger::log('testRest: ' . $this->configuration->getString('testRest'), ILogger::DEBUG);
        $result = ['rest' => json_decode($this->configuration->getString('testRest'), false)];

        if ($this->configuration->exists('testHttpCode')) {
            $result['httpCode'] = $this->configuration->getInt('testHttpCode');
        }

        return (object) $result;
    }
}
