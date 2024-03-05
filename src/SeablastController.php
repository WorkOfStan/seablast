<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\IdentityManagerInterface;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

class SeablastController
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;
    /** @var ?IdentityManagerInterface */
    private $identity = null;
    /** @var string[] mapping of URL to processing */
    public $mapping;
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
        Debugger::barDump($this->configuration, 'Configuration at SeablastController start');
        $this->pageUnderConstruction();
        $this->applyConfiguration();
        $this->route();
    }

    /**
     * Apply the current configuration to the Seablast environment.
     *
     * The settings not used here can still be used in Models.
     *
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
            //TODO: REMOVE: SeablastConstant::SB_PHINX_ENVIRONMENT,
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
                        //  use '1' for true and '0' for false; alternatively 'On' as true, and 'Off' as false
                        ini_set('session.http_only', '1');
                        if (
                            isset($this->superglobals->server['REQUEST_SCHEME']) &&
                            $this->superglobals->server['REQUEST_SCHEME'] == 'https'
                        ) {
                            ini_set('session.cookie_secure', '1');
                        }
                        ini_set('session.cookie_httponly', '1');
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
// TODO: REMOVE                    case SeablastConstant::SB_PHINX_ENVIRONMENT:
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
        $this->startSession();
        // Addition to configuration with info derived from superglobals
        $scriptName = filter_var($this->superglobals->server['SCRIPT_NAME'], FILTER_SANITIZE_URL);
        Assert::string($scriptName);
        // more ways to identify HTTPS
        $isHttps = (!empty($this->superglobals->server['REQUEST_SCHEME'])
            && $this->superglobals->server['REQUEST_SCHEME'] == 'https') ||
            (!empty($this->superglobals->server['HTTPS']) && $this->superglobals->server['HTTPS'] == 'on') ||
            (!empty($this->superglobals->server['SERVER_PORT']) && $this->superglobals->server['SERVER_PORT'] == '443');
        $this->configuration->setString(
            SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL,
            'http' .
            ($isHttps ? 's' : '') .
            '://' .
            $this->superglobals->server['HTTP_HOST'] .
            $this->removeSuffix(
                $scriptName,
                '/vendor/seablast/seablast/index.php'
            ) // Note: without trailing slash even for app root in domain root, i.e. https://www.service.com
        );
    }

    /**
     * Getter.
     *
     * @return SeablastConfiguration
     */
    public function getConfiguration(): SeablastConfiguration
    {
        return $this->configuration;
    }

    /**
     * Transform URL from friendly URL etc. to a parametric address that may be further interpreted.
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
        // /app/products and /app/products/ and /app/products/?id=1 are all resolved to /products
        $this->uriPath = self::removeSuffix($parsedUrl['path'], '/');
        if (empty($this->uriPath)) {
            // so that the homepage has non empty path
            $this->uriPath = '/';
        }
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
     * Change mapping because there's an HTTP error (client side).
     *
     * @param string $specificMessage that a user will see
     * @param int $httpCode
     * @return void
     */
    private function page40x(string $specificMessage, int $httpCode = 404): void
    {
        if ($httpCode < 400 || $httpCode > 499) {
            throw new \Exception("{$specificMessage} with HTTP code {$httpCode}");
        }
        Debugger::barDump(['httpCode' => $httpCode, 'message' => $specificMessage], 'HTTP error');
        $this->uriPath = '/error';
        $mapping = $this->configuration->getArrayArrayString(SeablastConstant::APP_MAPPING);
        $this->mapping = $mapping[$this->uriPath]; // todo is it necessary to redefine uriPath? or just use it here
        // TODO - is there a more direct way to propagate it to SeablastView than put it into configuration object?
        $this->configuration->setInt(SeablastConstant::ERROR_HTTP_CODE, $httpCode);
        $this->configuration->setString(SeablastConstant::ERROR_MESSAGE, $specificMessage);
    }

    /**
     * Identify UNDER CONSTRUCTION situation and eventually return an UNDER CONSTRUCTION page.
     *
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
                $this->startSession(); // as it couldn't be started before
                //TODO TEST include from app, pokud tam je, otherwise use this default:
                include file_exists(APP_DIR . '/under-construction.html')
                    ? APP_DIR . '/under-construction.html' : __DIR__ . '/../under-construction.html';
                exit;
            }
        }
    }

    /**
     * If string start with prefix, remove it.
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
     * If string ends with suffix, remove it.
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
     * Transform URI to model with parameters and RBAC.
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
        Debugger::barDump(
            [
                'REQUEST_URI' => $this->superglobals->server['REQUEST_URI'],
                'APP_DIR' => APP_DIR,
                'appPath' => $appPath,
                'path' => $this->uriPath,
                'query' => $this->uriQuery,
            ],
            'route resolved'
        );
        //phpinfo();exit;//debug
        //F(request type = verb/accepted type, url, url params, auth, language)
        // --> model & params & view type (html, json)
        //
        // TODO fix: if deployed standalone, ends with exception `No array string for the property SB:APP_MAPPING`. OK?
        $mapping = $this->configuration->getArrayArrayString(SeablastConstant::APP_MAPPING);
        if (!isset($mapping[$this->uriPath])) {
            Debugger::barDump(
                "Route {$this->uriPath} not found",
                '404 Not found'
            );
            $this->page40x('Stránka neexistuje.');
        } else {
            $this->mapping = $mapping[$this->uriPath];
        }
        Debugger::barDump($this->mapping, 'Mapping');
        // Authenticate: is there an identity manager to be used?
        if ($this->configuration->exists(SeablastConstant::SB_IDENTITY_MANAGER)) {
            $identityManager = $this->configuration->getString(SeablastConstant::SB_IDENTITY_MANAGER);
            /* @phpstan-ignore-next-line Property $identity does not accept object. */
            $this->identity = new $identityManager($this->configuration->dbms());
            if (method_exists($this->identity, 'setTablePrefix')) {
                $this->identity->setTablePrefix($this->configuration->dbmsTablePrefix());
            }
            // TODO consider decoupling dbms from identity
            Assert::methodExists($this->identity, 'isAuthenticated');
            if ($this->identity->isAuthenticated()) {
                $this->configuration->flag->activate(SeablastConstant::FLAG_USER_IS_AUTHENTICATED);
                // Save the current user's role, id and group list into the configuration object
                Assert::methodExists($this->identity, 'getRoleId');
                Assert::methodExists($this->identity, 'getUserId');
                Assert::methodExists($this->identity, 'getGroups');
                $this->configuration->setInt(SeablastConstant::USER_ROLE_ID, $this->identity->getRoleId());
                $this->configuration->setInt(SeablastConstant::USER_ID, $this->identity->getUserId());
                $this->configuration->setArrayInt(SeablastConstant::USER_GROUPS, $this->identity->getGroups());
            }
        }
        // Authenticate: RBAC (Role-Based Access Control)
        if (isset($this->mapping['roleIds']) && !empty($this->mapping['roleIds'])) {
            if (is_null($this->identity)) {
                throw new \Exception('Identity manager expected.');
            }
            // Identity required, if not autheticated => 401
            if (!$this->configuration->flag->status(SeablastConstant::FLAG_USER_IS_AUTHENTICATED)) {
                $this->page40x('401 Unauthorized. Zalogujte se.', 401); // TODO 401 - seamless log in page
                return;
            }
            Debugger::barDump('User is authenticated');
            // Specific role expected, if not authorized => 403
            $roleIds = explode(',', $this->mapping['roleIds']);
            Debugger::barDump($roleIds, 'RoleIds allowed');
            // Read the current user's role from the configuration object
            if (!in_array($this->configuration->getInt(SeablastConstant::USER_ROLE_ID), $roleIds)) {
                $this->page40x('403 Forbidden. Nedostatečná práva k přístupu.', 403);
                return;
            }
        }
        // If id argument is expected, it is also required
        if (isset($this->mapping['id'])) {
            if (!isset($this->superglobals->get[$this->mapping['id']])) {
                Debugger::barDump(
                    "Route {$this->uriPath} missing numeric parameter {$this->mapping['id']}",
                    '404 Not found'
                );
                $this->page40x('Stránka neexistuje.');
                return;
            }
            Assert::scalar($this->superglobals->get[$this->mapping['id']]);
            $this->configuration->setInt(
                SeablastConstant::SB_GET_ARGUMENT_ID,
                (int) $this->superglobals->get[$this->mapping['id']]
            );
        }
        // If code argument is expected, it is also required
        if (isset($this->mapping['code'])) {
            if (!isset($this->superglobals->get[$this->mapping['code']])) {
                Debugger::barDump(
                    "Route {$this->uriPath} missing string parameter {$this->mapping['code']}",
                    '404 Not found'
                );
                $this->page40x('Stránka neexistuje.');
                return;
            }
            Assert::scalar($this->superglobals->get[$this->mapping['code']]);
            // TODO secure against injection
            $this->configuration->setString(
                SeablastConstant::SB_GET_ARGUMENT_CODE,
                (string) $this->superglobals->get[$this->mapping['code']]
            );
        }
        // if the id or code value is wrong, it MUST fail later in the model
    }

    /**
     * Start session in the Seablast app.
     *
     * Starting a session requires more complex initialization, so Tracy was started immediately
     * (so that it could handle any errors that occur).
     * Now initialize the session handler and
     * finally inform Tracy that the session is ready to be used using the dispatch() function.
     *
     * @return void
     */
    private function startSession(): void
    {
        session_start() || error_log('session_start failed');
        Debugger::dispatch();
        $this->superglobals->setSession($_SESSION); // as only now the session started
    }
}
