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
- if [Seablast/Auth](https://github.com/WorkOfStan/seablast-auth) extension is present (i.e. referenced in composer.json), use its configuration
- everything can be overriden in the web app's `conf/app.conf.php` or even in its local deployment `conf/app.conf.local.php`
- set the default phinx environment in the phinx configuration: `['environments']['default_environment']` where the database credentials are stored. Then SeablastConfiguration provides access to MySQLi adapter through mysqli() method and PDO adapter through pdo() method.
- the default `log` directory (both for SeablastMysqli/SeablastPdo query.log and Debugger::log()) can be changed as follows `->setString(SeablastConstant::SB_LOG_DIRECTORY, APP_DIR . '/log')`. Anyway, only levels allowed by `SeablastConstant::SB_LOGGING_LEVEL` are logged.

## Model

SeablastModel uses model field in APP_MAPPING to invoke the model in the App.
**Model transforms input into knowledge**, therefore the invoked class MUST have a public method `knowledge()` and expect SeablastConfiguration as a constructor argument.

- SeablastModel also expects Superglobals $superglobals argument (instead of injection like `$model->setSuperglobals($superglobals);` if required by APP_MAPPING), so that the environment variables are always easily available. (Especially important for APIs.)

The minimal requirements can be implemented by [SeablastModelInterface](src/SeablastModelInterface.php).

- If model replies with `rest` property, API response is triggered instead of HTML UI. In that case, `httpCode` property is used as the response HTTP code.
- If model replies with `redirectionUrl` property, then redirection is triggered (instead of HTML UI) with HTTP code 301. The HTTP code MAY be set to 301, 302 or 303 by the `httpCode` property.
- If using the default BlueprintWeb.latte, the `title` property is displayed as the page title.

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
- Groups are on top of it, e.g. for promotions, subscriptions etc.
- RBAC (Role-Based Access Control): SB_IDENTITY_MANAGER provided by application MUST have methods prescribed in [IdentityManagerInterface](https://github.com/WorkOfStan/seablast-interfaces/blob/main/src/IdentityManagerInterface.php), these populate FLAG_USER_IS_AUTHENTICATED and USER_ROLE_ID.

## Security

All JSON calls and form submits MUST contain `csrfToken` handed over to the view layer in the `$csrfToken` string latte variable.

## Stack

- PHP ^7.2 || ^8.0
- [Latte](http://latte.nette.org/) ^2.11.7 || ^3: for templating
- [MySQL](https://dev.mysql.com/)/[MariaDB](http://mariadb.com): for database backend
- [Tracy](https://github.com/nette/tracy) ^2.9.8 || ^2.10.9: for debugging
- [Nette\SmartObject](https://doc.nette.org/en/3.0/smartobject): for ensuring strict PHP rules
- [Universal Language Selector jQuery library](https://github.com/wikimedia/jquery.uls.git) **Todo add version** : for language switching (used by [Seablast\i18n](https://github.com/WorkOfStan/seablast-i18n))

### ULS (Universal Language Selector jQuery library)

- To make the SVG icon in `.uls-trigger` adopt the `font-color` of the surrounding element, the following style was added into `uls/images/language.svg`: `fill="currentColor"`. Also `uls/css/jquery.uls.css` was changed (changed: `.uls-trigger`, added: `.uls-trigger icon` and `.uls-trigger .icon svg`).
- .eslintignore and .prettierignore to ignore 3rd party libraries, so that super-linter doesn't fail with JAVASCRIPT_ES and so that prettier doesn't change them or super-linter fails with CSS_PRETTIER, JAVASCRIPT_PRETTIER, JSON_PRETTIER, MARKDOWN_PRETTIER

## Notes

- the constant `APP_DIR` = the directory of the current application (or the library, if deployed directly)
- don't start the value of a constant for a configuration field in the app.conf.php with SB to prevent value collision

## Framework directory description

| Directory | Description                                                                                                          |
| --------- | -------------------------------------------------------------------------------------------------------------------- |
| .github/  | Automations                                                                                                          |
| assets/   | Web assets available for browser (such as shared scripts)                                                            |
| cache/    | Latte cache - this is just for development as production-wise, there will be cache/ directory in the root of the app |
| conf/     | Default configuration for a Seablast app and for PHPStan                                                             |
| log/      | Logs - this one is just for development; as production-wise, there will be `log` directory in the root of the app    |
| src/      | Seablast classes                                                                                                     |
| tests/    | PHPUnit tests                                                                                                        |
| views/    | Latte templates to be inherited                                                                                      |

## Testing

The PHPUnit tests use the database configuration from `./conf/phinx.local.php`, so the library require-dev Phinx, ensuring PHPUnit tests work on GitHub as well.

## Development notes

`./blast.sh phpstan` runs PHPStan to test the repository.
It can also be called `./vendor/seablast/seablast/blast.sh` from a Seablast application as a management script for deployment and development. Run `./blast.sh -?` to see all the options.
