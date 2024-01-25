# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### `Added` for new features

### `Changed` for changes in existing functionality
- SeablastModel->getParameters() returns object: no more option to return array<mixed>
- use plural in directories: Exceptions, Apis. Views instead of templates

### `Deprecated` for soon-to-be removed features

### `Removed` for now removed features

### `Fixed` for any bugfixes

### `Security` in case of vulnerabilities

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
### Added
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
- URL maps to template (404 otherwise)
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

### Changed
- APP_COLLECTION -> APP_MAPPING
- model->getParameters() -> model->knowledge()
- nice Under construction page

### Deprecated

### Removed

### Fixed

### Security

[Unreleased]: https://github.com/WorkOfStan/seablast/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/WorkOfStan/seablast/compare/v0.1...v0.1.1
[0.1]: https://github.com/WorkOfStan/seablast/releases/tag/v0.1
