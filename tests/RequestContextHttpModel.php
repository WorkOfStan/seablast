<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;

/** Endpoint used by the isolated HTTP fixture to exercise the real bootstrap and view. */
class RequestContextHttpModel
{
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var Superglobals */
    private $superglobals;

    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        $this->superglobals = $superglobals;
    }

    public function knowledge(): stdClass
    {
        if (isset($this->superglobals->get['regenerate'])) {
            session_regenerate_id(true);
        }
        $result = new stdClass();
        $result->rest = [
            'root' => $this->configuration->getString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL),
            'debug' => !Debugger::$productionMode,
            'path' => $this->configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH),
            'sessionId' => session_id(),
        ];
        return $result;
    }
}
