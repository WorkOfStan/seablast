<?php

namespace Seablast\Seablast;

//use Webmozart\Assert\Assert;

class SeablastController
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;

    public function __construct()
    {
        // Create configuration of the app by applying configuration files in order from generic to specific
        $this->configuration = new SeablastConfiguration();
        foreach ([
            __DIR__ . '/../conf/default.conf.php',
            __DIR__ . '/../../../conf/app.conf.php',
            __DIR__ . '/../../../conf/app.conf.local.php',
        ] as $confFilename) {
            $this->updateConfiguration($confFilename);
        }
        $this->applyConfiguration();
        //$this->devenv = xyz;
        $this->route();
    }

    /**
     * Apply the current configuration to the Seablast environment
     * The settings not used here can still be used in Models
     * @return void
     */
    private function applyConfiguration(): void
    {
        // identify UNDER CONSTRUCTION
        if (!$this->configuration->flag->status(SeablastConstant::FLAG_WEB_RUNNING)
            // && not in_array($_SERVER['REMOTE_ADDR'], $debug-IP-array) .. ale ne SERVER napřímo
        ) {
            //TODO include from app, pokud tam je, otherwise use this default:
            include __DIR__ . '/../under-construction.html';
            exit;
        }
        $configurationOrder = [
            SeablastConstant::SB_ERROR_REPORTING,
            SeablastConstant::SB_INI_SET_SESSION_COOKIE_LIFETIME,
            SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME,
            SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH,
            SeablastConstant::SB_SETLOCALE_CATEGORY,
            SeablastConstant::SB_SETLOCALE_LOCALES,
            SeablastConstant::SB_ENCODING,
            SeablastConstant::SB_INI_SET_SESSION_USE_STRICT_MODE,
            SeablastConstant::SB_INI_SET_DISPLAY_ERRORS,
            SeablastConstant::SB_PHINX_ENVIRONMENT,
            SeablastConstant::BACKYARD_LOGGING_LEVEL,
            SeablastConstant::BACKYARD_MAIL_FOR_ADMIN_ENABLED,
            SeablastConstant::BACKYARD_ADMIN_MAIL_ADDRESS,
            SeablastConstant::DEBUG_IP_LIST,
        ];
        //$arrayOfSettings = [];
        //foreach ($arrayOfSettings as $setting => $value) {
        //    case
        //}

        foreach ($configurationOrder as $setting) {
            if (isset($this->configuration[$setting])) {
                switch ($setting) {
                    case SeablastConstant::SB_ERROR_REPORTING:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case SeablastConstant::SB_INI_SET_SESSION_COOKIE_LIFETIME:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::SB_SETLOCALE_CATEGORY:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::SB_SETLOCALE_LOCALES:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::SB_ENCODING:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case SeablastConstant::SB_INI_SET_SESSION_USE_STRICT_MODE:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case SeablastConstant::SB_INI_SET_DISPLAY_ERRORS:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case SeablastConstant::SB_PHINX_ENVIRONMENT:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::BACKYARD_LOGGING_LEVEL:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::BACKYARD_MAIL_FOR_ADMIN_ENABLED:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::BACKYARD_ADMIN_MAIL_ADDRESS:
                        error_reporting($this->configuration[$setting]);
                        break;
                    case             SeablastConstant::DEBUG_IP_LIST:
                        error_reporting($this->configuration[$setting]);
                        break;
                    }
            }
        }

        //error_reporting(E_ALL & ~E_NOTICE);
// TODO fix 3 lines below: '#Parameter \#2 \$newvalue of function ini_set expects string, true given.#'
ini_set('session.http_only', true); // @phpstan-ignore-line TODO true as string?
if (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') {
    ini_set('session.cookie_secure', true); // @phpstan-ignore-line TODO true as string?
}
ini_set('session.cookie_httponly', true); // @phpstan-ignore-line TODO true as string?
ini_set('session.gc_divisor', '100');
ini_set('session.gc_maxlifetime', '200000');
ini_set('session.cokie_lifetime', '2000000');
session_set_cookie_params(10800, '/');
setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/config.php';

    }

    private function makeSureUrlIsParametric()
    {
        /*
    // Redirector -> friendly url / parametric url
    if !flag redirector_off
        If Select  * where url
            mSUIP //rekurze

    // Friendly url -> parametric url
    If !flag frienflyURL_off
        If Select * where url
        mSUIP
    return parametric;
    */
    }

    /**
     *
     * @return void
     */
    private function route(): void
    {
        $this->makeSureUrlIsParametric();
        //F(request type = verb/accepted type, url, url params, auth, language) --> model & params & view type (html, json)
    }

    /**
     * process a configuration file
     * @param string $configurationFilename
     * @return void
     */
    private function updateConfiguration(string $configurationFilename): void
    {
        if (!file_exists($configurationFilename)) {
            return;
        }
        $configurationClosure = require $configurationFilename;
        $configurationClosure($this->configuration);
    }
}
