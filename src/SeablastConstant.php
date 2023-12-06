<?php

declare(strict_types=1);

namespace Seablast\Seablast;

/**
 * @api
 */
class SeablastConstant
{
    /**
     * @var string Running or under construction
     */
    public const FLAG_WEB_RUNNING = 'SB:web:running';
    /**
     * @var string Level of PHP built-in error_reporting
     */
    public const SB_ERROR_REPORTING = 'SB:error_reporting';
    /**
     * @var string ini_set('session.cokie_lifetime', '2000000');
     */
    public const SB_SESSION_SET_COOKIE_LIFETIME = 'SB_INI_SET_SESSION_COOKIE_LIFETIME';
    /**
     * @var string session_set_cookie_params(10800, '/');
     */
    public const SB_SESSION_SET_COOKIE_PARAMS_LIFETIME = 'SB_SESSION_SET_COOKIE_PARAMS_LIFETIME';
    /**
     * @var string session_set_cookie_params(10800, '/');
     */
    public const SB_SESSION_SET_COOKIE_PARAMS_PATH = 'SB_SESSION_SET_COOKIE_PARAMS_PATH';
    /**
     * @var string setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
     */
    public const SB_SETLOCALE_CATEGORY = 'SB_SETLOCALE_CATEGORY';
    /**
     * @var string setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
     */
    public const SB_SETLOCALE_LOCALES = 'SB_SETLOCALE_LOCALES';
    /**
     * @var string mb_internal_encoding('UTF-8'); mb_http_output('UTF-8');
     */
    public const SB_ENCODING = 'SB_ENCODING';
    /**
     * @var string ini_set('session.use_strict_mode', '1');
     */
    public const SB_INI_SET_SESSION_USE_STRICT_MODE = 'SB_INI_SET_SESSION_USE_STRICT_MODE';
    /**
     * @var string ini_set('display_errors', '0'); // errors only in the log
     * override it in your config.local.php if you need to
     */
    public const SB_INI_SET_DISPLAY_ERRORS = 'SB_INI_SET_DISPLAY_ERRORS';
    /**
     * TODO: make sure this is needed
     * @var string $phinxEnvironment = 'development'; // use this phinx.yml environment for database connection
     */
    public const SB_PHINX_ENVIRONMENT = 'SB_PHINX_ENVIRONMENT';
    /**
     * TODO: make sure this is needed
     * @var string
     */
    public const BACKYARD_LOGGING_LEVEL = 'BACKYARD_LOGGING_LEVEL';
    /**
     * @var string flag whether to send emails to admin
     */
    public const ADMIN_MAIL_ENABLED = 'ADMIN_MAIL:ENABLED';
    /**
     * @var string string admin's email address
     */
    public const ADMIN_MAIL_ADDRESS = 'ADMIN_MAIL:ADDRESS';
    /**
     * @var string string[] IP addresses where to show Tracy
     */
    public const DEBUG_IP_LIST = 'DEBUG_IP_LIST';
    /**
     * @var string string[] mapping slugs to templates and tables
     */
    public const APP_COLLECTION = 'APP_COLLECTION';
    /**
     * @var string string with path to directory with Latte templates
     */
    public const LATTE_TEMPLATE = 'LATTE_TEMPLATE';
    /**
     * @var string string with path to directory with cache for Latte
     */
    public const LATTE_CACHE = 'LATTE_CACHE';
}
