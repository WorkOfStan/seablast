<?php

namespace Seablast\Seablast;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

class SeablastController
{
    use \Nette\SmartObject;

    /** @var string[] mapping of URL to processing */
    public $mapping;
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var Superglobals */
    private $superglobals;
    /** @var string */
    private $uriPath = '';
    /** @var string */
    private $uriQuery = '';

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        // Wrapped _GET, _POST, _SESSION and _SERVER for sanitizing and testing
        $this->superglobals = $superglobals;
        $this->configuration = $configuration;
        Debugger::barDump($this->configuration, 'configuration');
        $this->pageUnderConstruction();
        $this->applyConfiguration();
        $this->route();
    }

    /**
     * Apply the current configuration to the Seablast environment
     * The settings not used here can still be used in Models
     * @return void
     */
    private function applyConfiguration(): void
    {
        $configurationOrder = [
            SeablastConstant::SB_ERROR_REPORTING,
            SeablastConstant::SB_SESSION_SET_COOKIE_LIFETIME,
            SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME,
            //SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH, // required if _LIFETIME
            SeablastConstant::SB_SETLOCALE_CATEGORY,
            //SeablastConstant::SB_SETLOCALE_LOCALES, // required if _CATEGORY
            SeablastConstant::SB_ENCODING,
            SeablastConstant::SB_INI_SET_SESSION_USE_STRICT_MODE,
            SeablastConstant::SB_INI_SET_DISPLAY_ERRORS,
            SeablastConstant::SB_PHINX_ENVIRONMENT,
            SeablastConstant::BACKYARD_LOGGING_LEVEL,
            //SeablastConstant::ADMIN_MAIL_ENABLED, // flag checked if ADMIN_MAIL_ADDRESS is populated
            SeablastConstant::ADMIN_MAIL_ADDRESS,
            //SeablastConstant::DEBUG_IP_LIST, // already used in index.php
        ];
        foreach ($configurationOrder as $property) {
            if ($this->configuration->exists($property)) {
                switch ($property) {
                    case SeablastConstant::SB_ERROR_REPORTING:
                        error_reporting($this->configuration->getInt($property));
                        break;
                    case SeablastConstant::SB_SESSION_SET_COOKIE_LIFETIME:
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
                        if (
                            isset($this->superglobals->server['REQUEST_SCHEME']) &&
                            $this->superglobals->server['REQUEST_SCHEME'] == 'https'
                        ) {
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
                        ini_set('session.use_strict_mode', $this->configuration->getString($property));
                        break;
                    case SeablastConstant::SB_INI_SET_DISPLAY_ERRORS:
                        ini_set('display_errors', $this->configuration->getString($property));
                        break;
//                    case SeablastConstant::SB_PHINX_ENVIRONMENT:
//                        Debugger::barDump($property, 'not coded yet');
//                        break;
//                    case SeablastConstant::BACKYARD_LOGGING_LEVEL:
//                        Debugger::barDump($property, 'not coded yet');
//                        break;
                    case SeablastConstant::ADMIN_MAIL_ADDRESS:
                        if ($this->configuration->flag->status(SeablastConstant::ADMIN_MAIL_ENABLED)) {
                            // set here the admin email address to all debug tools
                            Debugger::$email = $this->configuration->getString($property);
                        }
                        break;
                }
            }
        }
    }

    /**
     *
     * @return SeablastConfiguration
     */
    public function getConfiguration(): SeablastConfiguration
    {
        return $this->configuration;
    }

    /**
     *
     * @param string $requestUri
     * @return void as uriPath and uriQuery are populated
     */
    private function makeSureUrlIsParametric($requestUri): void
    {
        // Use parse_url to parse the URI
        $parsedUrl = parse_url($requestUri);
        Debugger::barDump(
            ['requestUri' => $requestUri, 'parsedUrl' => $parsedUrl],
            'makeSureUrlIsParametric'
        );
        Assert::isArray($parsedUrl, 'MUST be an array with at least field `path`');
        Assert::keyExists($parsedUrl, 'path');

        // Accessing the individual components
        $this->uriPath = $parsedUrl['path']; // Outputs: /myapp/products
        // so that /book and /book/ and /book/?id=1 are all resolved to /book
        $this->uriPath = self::removeSuffix($this->uriPath, '/');
        // TODO refactor the above
        $this->uriQuery = $parsedUrl['query'] ?? ''; // Outputs: category=books&id=123
        //
        // You can further parse the query string if needed
        parse_str($this->uriQuery, $queryParams);
        // $queryParams will be an associative array like:
        // Array ( [category] => books [id] => 123 )
        //
        // makes use of $this->superglobals
        /*
          // Redirector -> friendly url / parametric url
          if FLAG_CHECK_REDIRECTOR
          ..If Select  * where url
          ....mSUIP //rekurze

          // Friendly url -> parametric url
          If !flag frienflyURL_off //TODO v APP_MAPPING musí být jméno sloupce_LANG, kde hledat
          ..If Select * where url
          ....mSUIP //rekurze

          //return parametric;
         */
        return; // uriPath and uriQuery are now parametrically populated
    }

    /**
     *
     * @param string $specificMessage
     * @return never
     */
    private function page404(string $specificMessage): void
    {
        Debugger::barDump($specificMessage, 'HTTP 404');
        http_response_code(404);
        // TODO make it nice
        echo "404 Not found";
        exit;
    }

    /**
     * if string start with prefix, remove it
     *
     * @param string $string
     * @param string $prefix
     * @return string
     */
    private static function removePrefix($string, $prefix): string
    {
        return (substr($string, 0, strlen($prefix)) === $prefix) ? substr($string, strlen($prefix)) : $string;
    }

    /**
     * if string ends with suffix, remove it
     *
     * @param string $string
     * @param string $suffix
     * @return string
     */
    private static function removeSuffix($string, $suffix): string
    {
        return (substr($string, -strlen($suffix)) === $suffix)
            ? substr($string, 0, strlen($string) - strlen($suffix)) : $string;
    }

    /**
     *
     * @return void
     */
    private function route(): void
    {
        Assert::string($this->superglobals->server['REQUEST_URI']);
        $appPath = self::removeSuffix(
                (pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME) === '/')
                    ? '' : pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME),
                '/vendor/seablast/seablast'
        );
        $urlToBeProcessed = self::removePrefix($this->superglobals->server['REQUEST_URI'], $appPath);
        $this->makeSureUrlIsParametric($urlToBeProcessed);
        // uriPath and uriQuery are now populated
        Debugger::barDump([
            'REQUEST_URI' => $this->superglobals->server['REQUEST_URI'],
            'APP_DIR' => APP_DIR,
            'appPath' => $appPath,
            'path' => $this->uriPath,
            'query' => $this->uriQuery,
        ]);
        //phpinfo();exit;//debug
        //F(request type = verb/accepted type, url, url params, auth, language)
        // --> model & params & view type (html, json)
        //
        $mapping = $this->configuration->getArrayArrayString(SeablastConstant::APP_MAPPING);
        if (!isset($mapping[$this->uriPath])) {
            $this->page404("Route {$this->uriPath} not found");
        }
        $this->mapping = $mapping[$this->uriPath];
        Debugger::barDump($this->mapping, 'Mapping');
        // If id argument is expected, it is also required
        if (isset($this->mapping['id'])) {
            if (!isset($this->superglobals->get[$this->mapping['id']])) {
                $this->page404("Route {$this->uriPath} missing numeric parameter {$this->mapping['id']}");
            }
            Assert::scalar($this->superglobals->get[$this->mapping['id']]);
            $this->configuration->setInt(SeablastConstant::SB_GET_ARGUMENT_ID, (int) $this->superglobals->get[$this->mapping['id']]);
        }
        // If code argument is expected, it is also required
        if (isset($this->mapping['code'])) {
            if (!isset($this->superglobals->get[$this->mapping['code']])) {
                $this->page404("Route {$this->uriPath} missing string parameter {$this->mapping['code']}");
            }
            Assert::scalar($this->superglobals->get[$this->mapping['code']]);
            // TODO secure against injection
            $this->configuration->setString(
                SeablastConstant::SB_GET_ARGUMENT_CODE,
                (string) $this->superglobals->get[$this->mapping['code']]
            );
        }
        // if the id or code value is wrong, it MUST fail in the model
    }

    /**
     * Identify UNDER CONSTRUCTION situation and returns an UNDER CONSTRUCTION page
     * @return void
     */
    private function pageUnderConstruction(): void
    {
        if (
            !$this->configuration->flag->status(SeablastConstant::FLAG_WEB_RUNNING)
        ) {
            Debugger::barDump('UNDER_CONSTRUCTION!');
            if (
                !in_array(
                    $this->superglobals->server['REMOTE_ADDR'],
                    $this->configuration->getArrayString(SeablastConstant::DEBUG_IP_LIST)
                )
            ) {
                //TODO TEST include from app, pokud tam je, otherwise use this default:
                include file_exists(APP_DIR . '/under-construction.html') ? APP_DIR . '/under-construction.html' : __DIR__ . '/../under-construction.html';
                exit;
            }
        }
    }
}
