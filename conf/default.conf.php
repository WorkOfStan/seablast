<?php

/**
 * SeablastConfiguration structure accepts all values, however only the expected ones are processed.
 * The usage of constants defined in the SeablastConstant class is encouraged for the sake of hinting within IDE.
 */

declare(strict_types=1);

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        ->activate(SeablastConstant::FLAG_WEB_RUNNING) // debug
        ->deactivate(SeablastConstant::ADMIN_MAIL_ENABLED) // default is not sending emails to admin
        ->deactivate(SeablastConstant::USER_MAIL_ENABLED) // default is not sending emails to users
        //->activate(SeablastConstant::FLAG_DEBUG_JSON) // JSON would be displayed directly with Tracy
    ;
    $SBConfig
        // Environment
        ->setInt(SeablastConstant::SB_ERROR_REPORTING, E_ALL & ~E_NOTICE)
        ->setInt(SeablastConstant::SB_SESSION_SET_COOKIE_LIFETIME, 60 * 60 * 24 * 2) // 2 days
        ->setInt(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME, 60 * 60 * 3) // 3 hours
        ->setString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH, '/') // TODO just app directory
        ->setInt(SeablastConstant::SB_SETLOCALE_CATEGORY, LC_CTYPE)
        ->setString(SeablastConstant::SB_SETLOCALE_LOCALES, 'cs_CZ.UTF-8')
        ->setString(SeablastConstant::SB_ENCODING, 'UTF-8')
        ->setString(SeablastConstant::SB_CHARSET_DATABASE, 'utf8')
        ->setString(SeablastConstant::SB_INI_SET_SESSION_USE_STRICT_MODE, '1')
        ->setString(SeablastConstant::SB_INI_SET_DISPLAY_ERRORS, '0') // errors only in the log; override locally
        ->setArrayString(SeablastConstant::DEBUG_IP_LIST, []) // default list with IPs to show Tracy
        ->setInt(SeablastConstant::SB_LOGGING_LEVEL, 3) // log warnings and more severe events
        ->setString(SeablastConstant::SB_LOG_DIRECTORY, APP_DIR . '/log') // change of the log location is possible
        // Database
        // Does not have to be 'testing' expected by the automatic GitHub tests as environment is set in the unit tests
        // like this: `$setup->getConfiguration()->setString(SeablastConstant::SB_PHINX_ENVIRONMENT, 'testing');`
        ->setString(SeablastConstant::SB_PHINX_ENVIRONMENT, 'development')
        // Latte templates
        ->setString(SeablastConstant::LATTE_TEMPLATE, 'views')
        ->setString(SeablastConstant::LATTE_CACHE, APP_DIR . '/cache')
        // Error API is always available (if not overriden)
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/api/error', // todo demonstrate in SB-dist
            [
                'model' => '\Seablast\Seablast\Apis\ApiErrorModel',
            ]
        )
        // Error page is always available (if not overriden)
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/error', // todo demonstrate in SB-dist
            [
                'template' => 'error',
                'model' => '\Seablast\Seablast\Models\ErrorModel',
            ]
        )
        // Default SMTP parameters
        ->setString(SeablastConstant::SB_SMTP_HOST, 'localhost')
        ->setInt(SeablastConstant::SB_SMTP_PORT, 25)
        ->setString(SeablastConstant::SB_SMTP_USERNAME, '')
        ->setString(SeablastConstant::SB_SMTP_PASSWORD, '')
        // User roles according to Seablast/Auth // TODO get rid of these?
        ->setInt(SeablastConstant::USER_ROLE_ADMIN, 1)
        ->setInt(SeablastConstant::USER_ROLE_EDITOR, 2)
        ->setInt(SeablastConstant::USER_ROLE_USER, 3)
    ;
};
