# Seablast for PHP
[![Total Downloads](https://img.shields.io/packagist/dt/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)
[![Latest Stable Version](https://img.shields.io/packagist/v/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)

This minimalist MVC framework added by [composer](https://getcomposer.org/) helps you to create a complex, yet easy to maintain, web application by configuration ONLY:
- you configure routes for controller,
- add models for the app business functionality,
- optionally modify view templates.

The framework takes care of logs, database, multiple languages, user friendly HTTP errors, friendly URL.

- See <https://github.com/WorkOfStan/seablast-dist/> for example of how to use it. It's a public template, so you can start creating your app by duplicating that repository.

## Configuration
- the default environment parameters are set in the [conf/default.conf.php](conf/default.conf.php)
- everything can be overriden in the web app's `conf/app.conf.php` or even in its local deployment `conf/app.conf.local.php`
- set the default phinx environment in the phinx configuration: `['environments']['default_environment']`

## Model
SeablastModel uses model field in APP_MAPPING to invoke the model in the App.
**Model transforms input into knowledge**, therefore the invoked class MUST have a public method `knowledge()` and expect SeablastConfiguration as a constructor argument.
- SeablastModel also expects Superglobals $superglobals argument (instead of injection like `$model->setSuperglobals($superglobals);` if required by APP_MAPPING), so that the environment variables are always easily available. (Especially important for APIs.)

The minimal requirements can be implemented by [SeablastModelInterface](src/SeablastModelInterface.php).

- If model replies with `rest` property, API response is triggered instead of HTML UI. In that case, `httpCode` property is used as the response HTTP code.
- If model replies with `redirectionUrl` property, then redirection is triggered (instead of HTML UI) with HTTP code 301. The HTTP code MAY be set to 301, 302 or 303 by the `httpCode` property.

```php
SeablastConstant::APP_MAPPING = route => [
    'model' => '\App\Project\ResponseModel', // class name of the model,
    'roleIds' => '1,2', // comma delimited roleIds permitted to access the route,
]
```

## Authentication and authorisation
- Roles are for access.
- Routes can only be allowed for roles (never denied). I.e. access to a route can be restricted to certain roles.
- Menu items can be both allowed and denied (e.g. don't show to an authenticated user).
- Groups are on top of it, e.g. for promotions etc.
- RBAC (Role-Based Access Control): SB_IDENTITY_MANAGER provided by application MUST have methods prescribed in [IdentityManagerInterface](https://github.com/WorkOfStan/seablast-interfaces/blob/main/src/IdentityManagerInterface.php), these populate FLAG_USER_IS_AUTHENTICATED and USER_ROLE_ID.

## Security
All JSON calls and form submits MUST contain `csrfToken` handed over in the `$csrfToken` string latte variable.

## Stack
- PHP7.2+
- [Latte](http://latte.nette.org/): for templating
- [MySQL](https://dev.mysql.com/)/[MariaDB](http://mariadb.com): for database backend
- [Tracy](https://github.com/nette/tracy): for debugging
- [Nette\SmartObject](https://doc.nette.org/en/3.0/smartobject): for ensuring strict PHP rules

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
