<?php

declare(strict_types=1);

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Tests\RequestContextHttpModel;

return static function (SeablastConfiguration $configuration): void {
    $configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE,
        APP_DIR . '/log/client-error-state.json');
    if (isset($_GET['errorDisabled'])) {
        $configuration->flag->deactivate(SeablastConstant::FLAG_CLIENT_ERROR_LOGGING);
    }
    if (isset($_GET['errorLimited'])) {
        $configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_MINUTE, 1);
    }
    $configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['127.0.0.1', '2001:db8::1']);
    if (isset($_GET['invalidProxy'])) {
        $configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['*']);
    }
    // Including the proxy itself must not grant every external client debug or maintenance access.
    $configuration->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['127.0.0.1', '198.51.100.7']);
    $configuration->setArrayArrayString(SeablastConstant::APP_MAPPING, '/probe', [
        'model' => RequestContextHttpModel::class,
    ]);
    if (isset($_GET['maintenance'])) {
        $configuration->flag->deactivate(SeablastConstant::FLAG_WEB_RUNNING);
    }
    $cases = [
        'sameSite' => SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_SAMESITE,
        'domain' => SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_DOMAIN,
        'path' => SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH,
    ];
    foreach ($cases as $query => $constant) {
        if (isset($_GET[$query]) && is_string($_GET[$query])) {
            $configuration->setString($constant, $_GET[$query]);
        }
    }
};
