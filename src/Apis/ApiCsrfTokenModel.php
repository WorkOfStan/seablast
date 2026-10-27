<?php

declare(strict_types=1);

namespace Seablast\Seablast\Apis;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/** Explicit same-origin bootstrap for interactions on potentially stateless pages. */
class ApiCsrfTokenModel implements SeablastModelInterface
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
        $result = new stdClass();
        $result->noStore = true;
        $result->httpCode = 403;
        $result->rest = ['message' => 'Explicit same-origin request required.'];
        if (($this->superglobals->server['REQUEST_METHOD'] ?? '') !== 'GET') {
            header('Allow: GET');
            $result->httpCode = 405;
            $result->rest = ['message' => 'Method not allowed.'];
            return $result;
        }
        // Custom headers require a CORS preflight cross-origin; core never grants CORS access here.
        if (
            ($this->superglobals->server['HTTP_X_SEABLAST_CSRF'] ?? '') !== '1' ||
            !in_array(
                $this->superglobals->server['HTTP_SEC_FETCH_SITE'] ?? 'same-origin',
                ['same-origin', 'none'],
                true
            )
        ) {
            return $result;
        }
        $this->configuration->requireSession();
        $result->httpCode = 200;
        $result->rest = ['csrfToken' => (new CsrfTokenManager())->getToken('sb_json')->getValue()];
        return $result;
    }
}
