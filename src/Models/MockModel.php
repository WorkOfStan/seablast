<?php

declare(strict_types=1);

namespace Seablast\Seablast\Models;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;

/**
 * A mock model for PHPUnit test.
 */
class MockModel implements SeablastModelInterface
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
        $result = [
            'data' => 'value',
        ];

        if ($this->configuration->exists('testHttpCode')) {
            //echo 'httpCode: ' . $this->configuration->getInt('testHttpCode');
            $result['httpCode'] = $this->configuration->getInt('testHttpCode');
            //} else {
            //    echo 'httpCode: NOPE';
        }

        return (object) $result;
    }
}
