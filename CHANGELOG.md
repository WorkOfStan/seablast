# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### `Added` for new features
- TableViewModel for admin.latte

### `Changed` for changes in existing functionality

### `Deprecated` for soon-to-be removed features

### `Removed` for now removed features

### `Fixed` for any bugfixes

### `Security` in case of vulnerabilities

## [0.2.3.2] - 2024-03-09
### Added
- SeablastConstant::APP_MAPPING_401 mapping to use in case of authentication required (instead of HTTP code 401)

## [0.2.3.1] - 2024-03-05
### Fixed
- SeablastConfiguration::getArrayInt

## [0.2.3] - 2024-03-03
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
### Added
- SeablastConstant::SB_SMTP_ default parameters
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
### Added
- show HTTP code error Tracy BarPanel
- /api/error for is always available (if not overriden): Log errors reported by Ajax saved to the standard error log

### Changed **BREAKING**
- SeablastModel->getParameters() returns object: no more option to return array<mixed>
- use plural in directories: Exceptions, `Apis`.
- templates folder renamed to views
- model result property `status` renamed to the self-explaining `httpCode`

### Security
- symfony/security-csrf component generates CSRF tokens (always checked if GenericRestApiJsonModel is extended)

## [0.1.1] - 2024-01-12
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

[Unreleased]: https://github.com/WorkOfStan/seablast/compare/v0.2.3.2...HEAD
[0.2.3.1]: https://github.com/WorkOfStan/seablast/compare/v0.2.3.1...v0.2.3.2
[0.2.3.1]: https://github.com/WorkOfStan/seablast/compare/v0.2.3...v0.2.3.1
[0.2.3]: https://github.com/WorkOfStan/seablast/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/WorkOfStan/seablast/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/WorkOfStan/seablast/compare/v0.2...v0.2.1
[0.2]: https://github.com/WorkOfStan/seablast/compare/v0.1.1...v0.2
[0.1.1]: https://github.com/WorkOfStan/seablast/compare/v0.1...v0.1.1
[0.1]: https://github.com/WorkOfStan/seablast/releases/tag/v0.1
