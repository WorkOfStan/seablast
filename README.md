# Seablast for PHP
[![Total Downloads](https://img.shields.io/packagist/dt/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)
[![Latest Stable Version](https://img.shields.io/packagist/v/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)

This minimalist MVC framework added by [composer](https://getcomposer.org/) helps you to create a complex web application ONLY by configuration: 
- you configure routes for controller,
- add models for the app business functionality,
- optionally modify view templates.

The framework takes care of logs, database, multiple languages, friendly URL.
(The future is in an easy to maintain technology.)

- See <https://github.com/WorkOfStan/seablast-dist/> for example of how to use it. It's a public template, so you can start creating your app by duplicating that repo.

## Configuration
- the default environment parameters are set in the [conf/default.conf.php](conf/default.conf.php)
- everything can be overriden in the web app's `conf/app.conf.php` or even in its local deployment `conf/app.conf.local.php`

## Model
SeablastModel uses model field in APP_MAPPING to invoke the model in the App.
**Model transforms input into knowledge**, therefore the invoked class MUST have a public method `knowledge()` and expect SeablastConfiguration as a constructor argument.
- SeablastModel also expects Superglobals $superglobals argument (instead of injection `$model->setSuperglobals($superglobals);` if required by APP_MAPPING), so that the environment variables are always easily available. (Especially important for APIs.)

The minimal requirements can be implemented by [SeablastModelInterface](src/SeablastModelInterface.php).

- If model replies with `rest` property, API response is triggered instead of HTML UI. In that case, `status` property is used as the response HTTP code. - TODO change `status` to `httpCode` on the same level.
- If model replies with `redirection` property, then its sub-properties `url` and optionally `httpCode` (301, 302 or 303) trigger redirection (instead of HTML UI).

## Security
All JSON calls and form submits MUST contain `csrfToken` handed over in the `$csrfToken` string latte variable.

## Stack
- PHP7.2+
- Latte
- Tracy

## Notes
- the constant `APP_DIR` = the directory of the current application (or the library, if deployed directly)
- don't start the value of a constant for a configuration field in the app.conf.php with SB to prevent value collision

## App expectation
- SeablastMysqli expects `log` directory to store query.log there

## Framework directory description
| Directory | Description |
|-----|------|
| .github/ | Automations |
| cache/ | Latte cache - this is just for development as production-wise, there will be cache/ directory in the root of the app |
| conf/ | Default configuration for a Seablast app and for PHPStan |
| log/ | Logs - this one is just for development; as production-wise, there will be `log` directory in the root of the app |
| src/ | Seablast classes |
| views/ | Latte templates to be inherited |
