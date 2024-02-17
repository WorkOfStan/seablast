<?php

declare(strict_types=1);

namespace Seablast\Seablast\Models;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;

/**
 * Nice error page
 */
class ErrorModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     * @throws \Exception
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        unset($superglobals); // just to do something
    }

    /**
     * Return the knowledge calculated in this model.
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        return (object) [
            'httpCode' => $this->configuration->getInt(SeablastConstant::ERROR_HTTP_CODE),
            'message' => $this->configuration->getString(SeablastConstant::ERROR_MESSAGE),
        ];
    }
}
