# Seablast for PHP
[![Total Downloads](https://img.shields.io/packagist/dt/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)
[![Latest Stable Version](https://img.shields.io/packagist/v/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)

A minimalist MVC framework added by composer.
The goal is to be able to create a complex web application ONLY by configuration.
(The future is in an easy to maintain technology.)
- Also render templates are optional.
- Last but not least, the unique business logic MUST by implemented as models.

- See <https://github.com/WorkOfStan/seablast-dist/> for example of how to use it.

## Configuration
- the default environment parameters are set in the [conf/default.conf.php](conf/default.conf.php)
- everything can be overriden in the web app's `conf/app.conf.php` or even in its local deployment `conf/app.conf.local.php`

## Model
SeablastModel uses model field in APP_MAPPING to invoke the model in the App.
Model transforms input into knowledge, therefore the invoked class MUST have a public method `knowledge()` and expect SeablastConfiguration as a constructor argument.
Also SeablastModel expects Superglobals $superglobals argument (instead of injection `$m->setSuperglobals($superglobals);` if required by APP_MAPPING), so that the environment variables are always easily available. (Especially important for APIs.)
The minimal requirements are to be implemented by SeablastModelInterface.

- If model replies with `rest` property, API response is triggered instead of HTML UI.
- If model replies with `redirection` property, then `url` and optionally `httpCode` properties trigger redirection (instead of HTML UI).

## Stack
- PHP7.2+
- Latte
- Tracy

## Notes
- the constant `APP_DIR` = the directory of the current application (or the library if built directly)
- don't start the value of a constant for a configuration field in the app.conf.php with SB to prevent value collision

## App expectation
- SeablastMysqli expects log directory to store query.log there

## Directory description
| Directory | Description |
|-----|------|
| .github/ | automations |
| cache/ | Latte cache - this is just for development as production-wise, there will be cache/ directory in the root of the app |
| conf/ | Default configuration for a Seablast app and for PHPStan |
| log/ | logs - this is just for development as production-wise, there will be log/ directory in the root of the app |
| src/ | Seablast classes |
| templates/ | Latte templates to be inherited |
