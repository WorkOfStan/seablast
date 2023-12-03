<?php

namespace Seablast\Seablast;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;

//use Tracy\ILogger;
//use Webmozart\Assert\Assert;

class SeablastController
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;

    /** @var Superglobals */
    private $superglobals;

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        // Wrap _GET, _POST, _SESSION and _SERVER for sanitizing and testing
        $this->superglobals = $superglobals;
        $this->configuration = $configuration;
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
        Debugger::barDump($this->configuration, 'configuration');
        // identify UNDER CONSTRUCTION
        if (
            !$this->configuration->flag->status(SeablastConstant::FLAG_WEB_RUNNING)
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
            //SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH, // required if _LIFETIME
            SeablastConstant::SB_SETLOCALE_CATEGORY,
            //SeablastConstant::SB_SETLOCALE_LOCALES, // required if _CATEGORY
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

        foreach ($configurationOrder as $property) {
            if ($this->configuration->exists($property)) {
                switch ($property) {
                    case SeablastConstant::SB_ERROR_REPORTING:
                        error_reporting($this->configuration->getInt($property));
                        break;
                    case SeablastConstant::SB_INI_SET_SESSION_COOKIE_LIFETIME:
                        ini_set('session.gc_divisor', '100');
                        ini_set('session.gc_maxlifetime', strval(2 * $this->configuration->getInt($property)));
                        ini_set('session.cookie_lifetime', strval($this->configuration->getInt($property)));
                        break;
                    case SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME:
                        if (!$this->configuration->exists(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH)) {
                            // TODO test this!
                            throw new \Exception(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH
                                . ' required if following is set: ' . $property);
                        }
                        ini_set('session.http_only', true); // @phpstan-ignore-line TODO true as string?
                        if (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') {
                            ini_set('session.cookie_secure', true); // @phpstan-ignore-line TODO true as string?
                        }
                        ini_set('session.cookie_httponly', true); // @phpstan-ignore-line TODO true as string?
                        session_set_cookie_params(
                            $this->configuration->getInt($property),
                            $this->configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH)
                        );
                        break;
                    case SeablastConstant::SB_SETLOCALE_CATEGORY:
                        if (!$this->configuration->exists(SeablastConstant::SB_SETLOCALE_LOCALES)) {
                            throw new \Exception(SeablastConstant::SB_SETLOCALE_LOCALES
                                . ' required if following is set: ' . $property);
                        }
                        setlocale(
                            $this->configuration->getInt($property),
                            $this->configuration->getString(SeablastConstant::SB_SETLOCALE_LOCALES)
                        );
                        break;
                    case SeablastConstant::SB_ENCODING:
                        mb_internal_encoding($this->configuration->getString($property));
                        mb_http_output($this->configuration->getString($property));
                        break;
                    case SeablastConstant::SB_INI_SET_SESSION_USE_STRICT_MODE:
                        Debugger::barDump($property, 'not coded yet');
                        break;
                    case SeablastConstant::SB_INI_SET_DISPLAY_ERRORS:
                        Debugger::barDump($property, 'not coded yet');
                        break;
                    case SeablastConstant::SB_PHINX_ENVIRONMENT:
                        Debugger::barDump($property, 'not coded yet');
                        break;
                    case SeablastConstant::BACKYARD_LOGGING_LEVEL:
                        Debugger::barDump($property, 'not coded yet');
                        break;
                    case SeablastConstant::BACKYARD_MAIL_FOR_ADMIN_ENABLED:
                        Debugger::barDump($property, 'not coded yet');
                        break;
                    case SeablastConstant::BACKYARD_ADMIN_MAIL_ADDRESS:
                        Debugger::barDump($property, 'not coded yet');
                        break;
                    case SeablastConstant::DEBUG_IP_LIST:
                        Debugger::barDump($property, 'not coded yet');
                        break;
                }
            }
        }

        // TODO rewrite the values below as default configuration
        //error_reporting(E_ALL & ~E_NOTICE);
        // TODO fix 3 lines below: '#Parameter \#2 \$newvalue of function ini_set expects string, true given.#'
//        ini_set('session.http_only', true); // @ phpstan-ignore-line TODO true as string?
//        if (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') {
//            ini_set('session.cookie_secure', true); // @ phpstan-ignore-line TODO true as string?
//        }
//        ini_set('session.cookie_httponly', true); // @ phpstan-ignore-line TODO true as string?
//        ini_set('session.gc_divisor', '100');
//        ini_set('session.gc_maxlifetime', '200000');
//        ini_set('session.cokie_lifetime', '2000000');
//        session_set_cookie_params(10800, '/');
//        setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
//        mb_internal_encoding('UTF-8');
//        mb_http_output('UTF-8');
        //require_once __DIR__ . '/config.php';
    }

    /**
     * TODO really return void? or string?
     * @return void
     */
    private function makeSureUrlIsParametric(): void
    {
        // makes use of $this->superglobals
        /*
          // Redirector -> friendly url / parametric url
          if !flag redirector_off
          ..If Select  * where url
          ....mSUIP //rekurze

          // Friendly url -> parametric url
          If !flag frienflyURL_off
          ..If Select * where url
          ....mSUIP
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
        //F(request type = verb/accepted type, url, url params, auth, language)
        // --> model & params & view type (html, json)
    }
}
