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
        if (isset($this->superglobals->get['independentPdoFailure'])) {
            $independentPdo = new \PDO('sqlite::memory:');
            $independentPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $independentPdo->query('SELECT * FROM independent_pdo_failure');
        }
        if (isset($this->superglobals->get['regenerate'])) {
            session_regenerate_id(true);
        }
        $result = new stdClass();
        header('X-Fixture: preserved');
        if (isset($this->superglobals->get['authenticated'])) {
            $this->configuration->flag->activate(SeablastConstant::FLAG_USER_IS_AUTHENTICATED);
        }
        if (isset($this->superglobals->get['html']) || isset($this->superglobals->get['redirect'])) {
            $this->configuration->setString(SeablastConstant::LATTE_TEMPLATE, __DIR__ . '/Fixtures');
            $result->cspNonce = 'model-value-must-be-overridden';
            if (isset($this->superglobals->get['redirect'])) {
                $result->redirectionUrl = '/target';
                $result->httpCode = 303;
            }
            return $result;
        }
        $result->rest = [
            'root' => $this->configuration->getString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL),
            'debug' => !Debugger::$productionMode,
            'path' => $this->configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH),
            'sessionId' => session_id(),
        ];
        if (isset($this->superglobals->get['errorToken'])) {
            $result->rest['csrfToken'] = (new \Symfony\Component\Security\Csrf\CsrfTokenManager())
                ->getToken('sb_json')->getValue();
        }
        return $result;
    }
}
