<?php

declare(strict_types=1);

namespace Seablast\Seablast;

/**
 * @api
 * Each string MUST start with SB to avoid unintended value collision
 */
class SeablastConstant
{
    /**
     * @var string Running or under construction
     */
    public const FLAG_WEB_RUNNING = 'SB:web:running';
    /**
     * @var string Redirection database should be looked up (unused so far)
     */
    public const FLAG_CHECK_REDIRECTOR = 'SB:redirector:running';
    /**
     * @var string Output JSON as HTML instead of application/json so that Tracy is displayed
     */
    public const FLAG_DEBUG_JSON = 'SB:debug:json';
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
     * @var string mysqli::set_charset('utf8');
     */
    public const SB_CHARSET_DATABASE = 'SB_CHARSET_DATABASE';
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
     * @ var string $phinxEnvironment = 'development'; // use this phinx.yml environment for database connection
     */
    //TODO REMOVE public const SB_PHINX_ENVIRONMENT = 'SB_PHINX_ENVIRONMENT';
    /**
     * TODO: make sure this is needed
     * @var string
     */
    public const BACKYARD_LOGGING_LEVEL = 'SB:BACKYARD_LOGGING_LEVEL';
    /**
     * @var string flag whether to send emails to admin
     */
    public const ADMIN_MAIL_ENABLED = 'SB:ADMIN_MAIL:ENABLED';
    /**
     * @var string string admin's email address
     */
    public const ADMIN_MAIL_ADDRESS = 'SB:ADMIN_MAIL:ADDRESS';
    /**
     * @var string flag whether to send emails to users
     */
    public const USER_MAIL_ENABLED = 'SB:USER_MAIL:ENABLED';
    /**
     * @var string string email address from which emails are sent
     */
    public const FROM_MAIL_ADDRESS = 'SB:FROM_MAIL:ADDRESS';
    /**
     * @var string string[] IP addresses where to show Tracy
     */
    public const DEBUG_IP_LIST = 'SB:DEBUG_IP_LIST';
    /**
     * @var string string[] mapping slugs to templates and tables
     */
    public const APP_MAPPING = 'SB:APP_MAPPING';
    /**
     * @var string string with path to directory with Latte templates
     */
    public const LATTE_TEMPLATE = 'SB:LATTE_TEMPLATE';
    /**
     * @var string string with path to directory with cache for Latte
     */
    public const LATTE_CACHE = 'SB:LATTE_CACHE';
    /**
     * @var string string Name of an expected and accepted numeric GET argument
     */
    public const SB_GET_ARGUMENT_ID = 'SB_GET_ARGUMENT_ID';
    /**
     * @var string stringName of an expected and accepted string GET argument
     */
    public const SB_GET_ARGUMENT_CODE = 'SB_GET_ARGUMENT_CODE';
    /**
     * @var string int to forced update of external CSS and JavaScript files
     * TODO use this in seablast-dist
     */
    public const SB_WEB_FORCE_ASSET_VERSION = 'SB_WEB_FORCE_ASSET_VERSION';
    /**
     * @var string string The absolute URL of the root of the application
     */
    public const SB_APP_ROOT_ABSOLUTE_URL = 'SB_APP_ROOT_ABSOLUTE_URL';
    /**
     * @var string string SMTP host to be used by an email plugin
     */
    public const SB_SMTP_HOST = 'SB_SMTP_HOST';
    /**
     * @var string int SMTP port to be used by an email plugin
     */
    public const SB_SMTP_PORT = 'SB_SMTP_PORT';
    /**
     * @var string string SMTP username to be used by an email plugin
     */
    public const SB_SMTP_USERNAME = 'SB_SMTP_USERNAME';
    /**
     * @var string string SMTP password to be used by an email plugin
     */
    public const SB_SMTP_PASSWORD = 'SB_SMTP_PASSWORD';
    /**
     * @var string class taking care of Authentication (TODO: experiment)
     */
    public const SB_IDENTITY_MANAGER = 'SB_IDENTITY_MANAGER';
    /**
     * @var string bool is user authenticated
     */
    public const FLAG_USER_IS_AUTHENTICATED = 'SB:USER_IS_AUTHENTICATED';
    /**
     * @var string int roleId
     */
    public const USER_ID = 'SB:USER_ID';
    /**
     * @var string int roleId
     */
    public const USER_ROLE_ID = 'SB:USER_ROLE_ID';
    /**
     * @var string int[] groupId
     */
    public const USER_GROUPS = 'SB:USER_GROUPS';
    /**
     * @var string int HTTP code
     */
    public const ERROR_HTTP_CODE = 'SB:ERROR_HTTP_CODE';
    /**
     * @var string string error message
     */
    public const ERROR_MESSAGE = 'SB:ERROR_MESSAGE';
}
