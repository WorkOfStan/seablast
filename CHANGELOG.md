# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

feat: Seablast\i18n integration

### `Added` for new features

- TableViewModel for admin.latte
- SeablastSetup contains `APP_DIR . '/vendor/seablast/i18n/conf/app.conf.php', // Seablast/i18n extension configuration`
- assets\uls to be used for language switching by Seablast\i18n

### `Changed` for changes in existing functionality

- package limited to the tested PHP versions, i.e. "php": ">=7.2 <8.5"

### `Deprecated` for soon-to-be removed features

### `Removed` for now removed features

### `Fixed` for any bugfixes

### `Security` in case of vulnerabilities

## [0.2.10.1] - 2025-07-09

fix: automatic population of SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH by the app path

## [0.2.10] - 2025-07-09

fix: cookies limited to app path

### Added

- nav.latte is used in the BlueprintWeb.latte and can be used in app (using inherite.latte)
- blast.sh self-update (from the Seablast library)
- assets/scripts expected by plugins (Seablast/Auth) send-auth-token.js expecting both Environment.js and seablast.js

### Changed

- better error reporting in Apis\GenericRestApiJsonModel
- blast.sh main requires confirmation
- PHPUnit tests folder renamed according to a common convention
- GenericRestApiJsinModel.php is more verbose about an input JSON error

### Removed

- phpstan.sh removed because blast.sh can be used instead

### Fixed

- change .htaccess directive for Apache 2.2 `Order Allow,Deny\nDeny from all` to Apache 2.4 `Require all denied` to return 403 (instead of 500)
- blast.sh checks for curl presence before invoking it

### Security

- blast.sh: 403 is also considered as a sufficient folder security
- cookies limited to app path, so various apps in the same domain don't cross-logout

## [0.2.9] - 2025-03-09

chore: PHP/8.4 support added

### Added

- support for PHP/8.4, including GitHub tests
- support for PHP/8.0 added again
- blast.sh - Management script for deployment and development of a Seablast application

### Changed

- mapping['roleIds'] exploded into array of integers is safer even thou in_array comparison is loose (so 2 equals " 2")

## [0.2.8] - 2025-02-09

Strict versions of database adapter calls.

### Added

- SeablastMysqli::prepareStrict throws DbmsException in case of failure
- SeablastPdo::prepareStrict and SeablastPdo::queryStrict throw DbmsException in case of failure
- SeablastConfiguration::mysqli() as replacement for a temporary alias SeablastConfiguration::dbms()
- SeablastConfiguration::mysqliStatus() as replacement for a temporary alias SeablastConfiguration::dbmsStatus()

### Deprecated

- SeablastConfiguration::dbms() - use SeablastConfiguration::mysqli() instead.
- SeablastConfiguration::dbmsStatus() - use SeablastConfiguration::mysqliStatus() instead.

## [0.2.7] - 2025-02-01

SeablastMysqli->prepare() is logged, the database Tracy BarPanel is displayed when DbmsException is thrown.

### Added

- SeablastMysqli->prepare() is logged the same way as SeablastMysqli->query() (not SeablastMysqli->prepare()->execute() however)
- Run super-linter and composer-dependencies workflows at 6:30 AM UTC on the 15th of every month
- Make sure that the database Tracy BarPanel is displayed when DbmsException is thrown
- SeablastPdo added to be logged the same way as SeablastMysqli->prepare()
- constants for user roles admin=1, editor=2 and user=3 (same as used in Seablast/Auth)
- title variable to layout latte

### Changed

- GitHub Action polish-the-code.yml replaced linter, php-composer-dependencies, phpcbf and prettier-fix yamls
- SeablastConfigurationException moved to Exceptions\SeablastConfigurationException
- configuration->setUser for MySQLi and PDO is lazy
- configuration->showSqlBarPanel triggers both MySQLi and PDO

## [0.2.6] - 2024-12-29

Correct HTTP code returned for error page. SeablastMysqli logs the user.

### Added

- GenericRestApiJsonModel::response(int $httpCode, string $message): object as a simple API response

### Fixed

- HTML output also returns HTTP codes other than 200

### Security

- SeablastMysqli::setUser() injects user ID to be logged with queries

## [0.2.5] - 2024-12-20

If Seablast/Auth extension is present, use its configuration. Log location change enabled.

### Added

- PHPUnit tests. The PHPUnit tests use the database configuration from `./conf/phinx.local.php`, so the library require-dev Phinx, ensuring PHPUnit tests work on GitHub as well.
- SeablastMysqli::queryStrict now changes mysqli_sql_exception to the expected DbmsException. (Useful for PHP/8.x tests on GitHub.)
- [prettier-fix](https://github.com/WorkOfStan/prettier-fix) included to fix all those `VALIDATE_something_PRETTIER` that are now crucial part of super-linter
- if [Seablast/Auth](https://github.com/WorkOfStan/seablast-auth) extension is present, use its configuration
- `{block script}{/block}` to BlueprintWeb.latte
- change of the log location enabled (single settings for Seablast\Logger, Tracy\Debugger and SeablastMysqli)

### Changed

- SeablastConfiguration::exists simplified
- updated dependencies not to refer below PHP/7.2
- SeablastMysqli::query error shows up to 1500 characters of a query that ended up with a database error (instead of truncating to the default 150 characters)

### Removed

- PHPUnit generates some weird errors with PHP/8.0. So the PHP/8.0 support removed.
- some Assertions removed as not needed for PHPStan/2

## [0.2.4] - 2024-08-04

Tracy logs through Seablast/logger, which provides verbosity control.

### Added

- redirection HTTP code allows for 307 and 308

### Changed

- use [seablast/logger](https://github.com/WorkOfStan/seablast-logger), a [PSR-3](https://www.php-fig.org/psr/psr-3/) compliant logger with verbosity control, as a logger for Tracy.
- following SQL statements are not logged: DESCRIB, DO, EXPLAIN as they do not change the table data

### Fixed

- `.htaccess` checked

## [0.2.3.5] - 2024-06-09

PHPUnit tests ready

### Added

- JSON_INPUT can be populated to simulate php://input PHPUnit test
- SeablastMysqli::queryStrict throws DbmsException in case of SQL statement failure
- SeablastController::getIdentity to provide access to user identity

### Changed

- SeablastController::applyConfiguration divided to part called only once before session starts, and part that can be called repeatedly (e.g. for PHPUnit)

## [0.2.3.4] - 2024-05-30

SeablastConstant::SB_PHINX_ENVIRONMENT to override `$phinx['environments']['default_environment']`

### Added

- SeablastConstant::SB_PHINX_ENVIRONMENT to override `$phinx['environments']['default_environment']`

### Changed

- use `Seablast\Interfaces\IdentityManagerInterface;` instead of `Seablast\Seablast\IdentityManagerInterface;`
- GitHub workflows uses WorkOfStan/seablast-actions instead of WorkOfStan/MyCMS
- query.log generated by SeablastMysqli is rotating, i.e. query_YYYY-MM.log; includes timestamp and is generated by error_log

### Removed

- table prefix (phinx) no more available through SB:phinx:table_prefix (only via `configuration->dbmsTablePrefix()`)

## [0.2.3.3] - 2024-03-30

- Changed: throw specific Exceptions
- Fixed: UNDER_CONSTRUCTION excludes localhost
- PHP/8.0, PHP/8.1 and PHP/8.2 tested automatically by GitHub for Composer validity and PHPStan

## [0.2.3.2] - 2024-03-09

### Added

- SeablastConstant::APP_MAPPING_401 mapping to use in case of authentication required (instead of HTTP code 401)

## [0.2.3.1] - 2024-03-05

### Fixed

- SeablastConfiguration::getArrayInt

## [0.2.3] - 2024-03-03

SeablastConstant::USER_ID and SeablastConstant::USER_GROUPS in SeablastConfiguration

### Added

- table prefix (phinx) available through SB:phinx:table_prefix OR dbmsTablePrefix()
- SeablastConfiguration contains SeablastConstant::USER_ID and SeablastConstant::USER_GROUPS
- SB_CHARSET_DATABASE to mysqli::set_charset
- IdentityManagerInterface expects also user ID and list of groups **BREAKING CHANGE**

### Changed

- using the documented double pipe `||` as a logical OR operator in composer.json (instead of the older single pipe operator)

### Removed

- SeablastConfiguration::optionsBool as redundant (use flag instead)

## [0.2.2] - 2024-02-18

### Changed

- ErrorModel to display the HTTP errors nicely. User friendly HTTP error messages.

## [0.2.1] - 2024-02-03

Role-Based Access Control

### Added

- `SeablastConstant::SB_SMTP_` default parameters
- RBAC (Role-Based Access Control): SB_IDENTITY_MANAGER provided by application MUST have methods prescribed in IdentityManagerInterface, these populate FLAG_USER_IS_AUTHENTICATED and USER_ROLE_ID.
- Access to a Route can be restricted to certain roles.
- httpCode>=400 in the model response triggers views/error.latte

### Changed

- Starting a session requires more complex initialization, so Tracy starts immediately (so that it can handle any errors that occur) and then the session handler is initialized and finally Tracy is informed that the session is ready to be used.

### Removed

- Model response params->redirection changed to params->redirectionUrl

### Security

- configuration->exists catches all property types separately
- The session.cookie_secure directive expects a boolean value. When you're using ini_set, you can set it to '1' or 'On' to enable it, or '0' or 'Off' to disable it. PHP will correctly interpret both '1' and 'On' as true, and both '0' and 'Off' as false. However, it's generally a good practice to use '1' for true and '0' for false when setting boolean ini values with ini_set, as this is more explicit and less likely to cause confusion.

## [0.2] - 2024-01-27

CSRF, model returns object (not array anymore), directories are in plural

### Added

- show HTTP code error Tracy BarPanel
- /api/error is always available (if not overriden): Log errors reported by Ajax saved to the standard error log

### Changed **BREAKING**

- SeablastModel->getParameters() returns object: no more option to return array<mixed>
- use plural in directories: Exceptions, `Apis`.
- templates folder renamed to views
- model result property `status` renamed to the self-explaining `httpCode`

### Security

- symfony/security-csrf component generates CSRF tokens (always checked if GenericRestApiJsonModel is extended)

## [0.1.1] - 2024-01-12

SeablastMysqli error logging improved, HTTPS identified

### Added

- class Api\GenericRestApiJsonModel implements SeablastModelInterface
- Exception\UploadException to translate (int) code to (string) message
- class Api\ApiErrorModel to be directly used by application
- JSON response uses `status` property as a response HTTP code.

### Changed

- improved SeablastMysqli error logging

### Fixed

- more ways to identify HTTPS from SERVER headers

## [0.1] - 2023-12-30

- MVC architecture
- SeablastConstant class for IDE hinting
- added SeablastSetup to combine configuration files into a valid configuration before starting Tracy/Debugger and then Controller
- added class Superglobals to shield code from direct access to superglobals
- added Latte rendeing, cache added
- added nice layout: BlueprintWeb.latte
- default configuration for environment is ready
- added do not send automatic emails to admin by default
- Code quality: add Assertions to eliminate PHPStan identified issues
- added prototype of parametric routing
- Controller: /item and /item/ and /item/?id=1 are all resolved to /item
- configuration is handed over to renderLatte
- GET parameters passed to model in configuration fields SB_GET_ARGUMENT_ID|SB_GET_ARGUMENT_CODE
- SeablastModelInterface.php to define minimal requirements for a model used by SeablastModel
- SeablastModel uses permanent argument Superglobals $superglobals (instead of injection `$m->setSuperglobals($superglobals);` if required by APP_MAPPING, so that it is always easily available)
- FLAG_DEBUG_JSON: Output JSON as HTML instead of application/json so that Tracy is displayed
- `declare(strict_types=1);` everywhere
- SB_WEB_FORCE_ASSET_VERSION Int to forced update of external CSS and JavaScript files
- SB_APP_ROOT_ABSOLUTE_URL The absolute URL of the root of the application
- show SQL statements in Tracy
- SeablastMysqli lazy initialisation; database load checked, if fails an Exception is thrown
- if Location redirection fails, a nice redirection HTML page ( redirection.latte ) is displayed
- APP_MAPPING controls route to model to view, i.e. URL maps to template (404 otherwise)
- model returns knowledge()
- a nice Under construction page

[Unreleased]: https://github.com/WorkOfStan/seablast/compare/v0.2.10.1...HEAD?w=1
[0.2.10.1]: https://github.com/WorkOfStan/seablast/compare/v0.2.10...v0.2.10.1?w=1
[0.2.10]: https://github.com/WorkOfStan/seablast/compare/v0.2.9...v0.2.10?w=1
[0.2.9]: https://github.com/WorkOfStan/seablast/compare/v0.2.8...v0.2.9?w=1
[0.2.8]: https://github.com/WorkOfStan/seablast/compare/v0.2.7...v0.2.8?w=1
[0.2.7]: https://github.com/WorkOfStan/seablast/compare/v0.2.6...v0.2.7?w=1
[0.2.6]: https://github.com/WorkOfStan/seablast/compare/v0.2.5...v0.2.6?w=1
[0.2.5]: https://github.com/WorkOfStan/seablast/compare/v0.2.4...v0.2.5?w=1
[0.2.4]: https://github.com/WorkOfStan/seablast/compare/v0.2.3.5...v0.2.4?w=1
[0.2.3.5]: https://github.com/WorkOfStan/seablast/compare/v0.2.3.4...v0.2.3.5?w=1
[0.2.3.4]: https://github.com/WorkOfStan/seablast/compare/v0.2.3.3...v0.2.3.4?w=1
[0.2.3.3]: https://github.com/WorkOfStan/seablast/compare/v0.2.3.2...v0.2.3.3?w=1
[0.2.3.2]: https://github.com/WorkOfStan/seablast/compare/v0.2.3.1...v0.2.3.2?w=1
[0.2.3.1]: https://github.com/WorkOfStan/seablast/compare/v0.2.3...v0.2.3.1?w=1
[0.2.3]: https://github.com/WorkOfStan/seablast/compare/v0.2.2...v0.2.3?w=1
[0.2.2]: https://github.com/WorkOfStan/seablast/compare/v0.2.1...v0.2.2?w=1
[0.2.1]: https://github.com/WorkOfStan/seablast/compare/v0.2...v0.2.1?w=1
[0.2]: https://github.com/WorkOfStan/seablast/compare/v0.1.1...v0.2?w=1
[0.1.1]: https://github.com/WorkOfStan/seablast/compare/v0.1...v0.1.1?w=1
[0.1]: https://github.com/WorkOfStan/seablast/releases/tag/v0.1
