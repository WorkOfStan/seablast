<?php

declare(strict_types=1);

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Tests\RequestContextHttpModel;

return static function (SeablastConfiguration $configuration): void {
    if (!isset($_GET['missingHosts'])) {
        $configuration->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, isset($_GET['emptyHosts']) ? [] : [
            'app.example.com', 'localhost', '192.168.1.10', 'localhost:8080', '[::1]:8080',
        ]);
    }
    if (isset($_GET['hostDebugLog'])) {
        $configuration->setInt(SeablastConstant::SB_LOGGING_LEVEL, 5);
    }
    if (isset($_GET['invalidHosts'])) {
        $configuration->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, ['*.example.com']);
    }
    $configuration->setString(
        SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE,
        APP_DIR . '/log/client-error-state.json'
    );
    if (isset($_GET['errorDisabled'])) {
        $configuration->flag->deactivate(SeablastConstant::FLAG_CLIENT_ERROR_LOGGING);
    }
    if (isset($_GET['errorLimited'])) {
        $configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_MINUTE, 1);
    }
    $configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['127.0.0.1', '2001:db8::1']);
    if (isset($_GET['unconfiguredProxy'])) {
        $configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, []);
    }
    if (isset($_GET['invalidProxy'])) {
        $configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['*']);
    }
    // Including the proxy itself must not grant every external client debug or maintenance access.
    if (isset($_GET['allowDebug'])) {
        $configuration->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['127.0.0.1', '::1', '198.51.100.7']);
    }
    $configuration->setArrayArrayString(SeablastConstant::APP_MAPPING, '/probe', [
        'model' => RequestContextHttpModel::class,
        'template' => 'security-headers',
    ]);
    // The model selects the repository template path when HTML output is requested.
    if (isset($_GET['maintenance'])) {
        $configuration->flag->deactivate(SeablastConstant::FLAG_WEB_RUNNING);
    }
    $cases = [
        'sameSite' => SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_SAMESITE,
        'domain' => SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_DOMAIN,
        'path' => SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH,
        'cspMode' => SeablastConstant::SB_CSP_MODE,
        'cachePolicy' => SeablastConstant::SB_CACHE_CONTROL,
        'reportUri' => SeablastConstant::SB_CSP_REPORT_URI,
    ];
    foreach ($cases as $query => $constant) {
        if (isset($_GET[$query]) && is_string($_GET[$query])) {
            $configuration->setString($constant, $_GET[$query]);
        }
    }
    if (isset($_GET['frameOrigin']) && is_string($_GET['frameOrigin'])) {
        $configuration->setArrayString(SeablastConstant::SB_FRAME_ANCESTORS, [$_GET['frameOrigin']]);
    }
};
