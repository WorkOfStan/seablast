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
- if [Seablast/I18n](https://github.com/WorkOfStan/seablast-i18n) extension is present (i.e. referenced in composer.json), use its configuration
- everything can be overriden in the web app's `conf/app.conf.php` or even in its local deployment `conf/app.conf.local.php`
- set the default phinx environment in the phinx configuration: `['environments']['default_environment']` where the database credentials are stored. Then SeablastConfiguration provides access to MySQLi adapter through mysqli() method and PDO adapter through pdo() method.
- the default `log` directory (both for SeablastMysqli/SeablastPdo query.log and Debugger::log()) can be changed as follows `->setString(SeablastConstant::SB_LOG_DIRECTORY, APP_DIR . '/log')`. Anyway, only levels allowed by `SeablastConstant::SB_LOGGING_LEVEL` are logged.

## Model

SeablastModel uses model field in APP_MAPPING to invoke the model in the App.
**Model transforms input into knowledge**, therefore the invoked class MUST have a public method `knowledge()` and expect SeablastConfiguration as a constructor argument.

- SeablastModel also expects Superglobals $superglobals argument (instead of injection like `$model->setSuperglobals($superglobals);` if required by APP_MAPPING), so that the environment variables are always easily available. (Especially important for APIs.)

The minimal requirements can be implemented by [SeablastModelInterface](src/SeablastModelInterface.php).

- If model replies with `rest` property, API response is triggered instead of HTML UI. If the default HTTP code should be changed, set it up in the `httpCode` property.
- If model replies with `redirectionUrl` property, then redirection is triggered (instead of HTML UI) with HTTP code 301. The HTTP code MAY be set to 301, 302 or 303 by the `httpCode` property.
- If using the default BlueprintWeb.latte, the `title` property is displayed as the page title.

```php
SeablastConstant::APP_MAPPING = route => [
    'model' => '\App\Project\ResponseModel', // class name of the model,
    'roleIds' => '1,2', // comma delimited roleIds permitted to access the route,
]
```

## View

- Feel free to use the default latte layout `{layout '../vendor/seablast/seablast/views/BlueprintWeb.latte'}` which can be populated by your local `nav.latte` and `footer.latte`.

### Administration

- By default the route `/poseidon` displays the app administration. It is available only to the admin=1, editor=2 (their IDs same as used in Seablast\Auth) with different rights.

#### Admin Table Configuration

This section describes how database tables and their columns are configured for **viewing and editing** in the admin interface, based on **user roles**.

The configuration is **declarative** and role-driven.

##### Core Concepts

Admin table access is defined on **three levels**:

1. **Role → Accessible tables**
2. **Role → Table → Viewable columns**
3. **Role → Table → Editable columns**

Each level must be explicitly configured.

##### 1. Granting Table Access to a Role

Before a table can be displayed or edited, it must be **explicitly allowed** for the given role.

##### Syntax

```php
->setArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_X,
    ['table1', 'table2']
)
```

##### Meaning

* Defines which tables are visible to a role
* If a table is not listed here, it will **not appear at all**, even if columns are defined later

##### Example

```php
->setArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
    ['audios', 'translations']
)
```

The `EDITOR` role can access the `audios` and `translations` tables.

##### 2. Defining Viewable Columns (READ-ONLY)

Viewable columns are displayed in the admin table but **cannot be edited**.

##### Syntax

```php
->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_X,
    'table_name',
    ['column1', 'column2']
)
```

##### Meaning

* Defines which columns are shown in the table
* Columns listed here are **read-only**
* Columns must **not** be duplicated in the EDIT section

##### Example

```php
->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_ADMIN,
    'users',
    ['id', 'email', 'created', 'last_login']
)
```

The admin can view these columns but cannot modify them.

##### 3. Defining Editable Columns (WRITE)

Editable columns appear as form fields and can be modified.

##### Syntax

```php
->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_EDIT . SeablastConstant::USER_ROLE_X,
    'table_name',
    ['editable_column1', 'editable_column2']
)
```

##### Meaning

* Defines which columns can be edited
* Editable columns automatically appear in edit forms
* Columns must **not** appear in the VIEW section

##### Example

```php
->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_EDIT . SeablastConstant::USER_ROLE_ADMIN,
    'items',
    ['metadata_text', 'active', 'parent_id', 'order']
)
```

The admin can modify these fields.

##### 4. Important Rule: Column Exclusivity

> **A column may appear only once.**

A column must be defined in **either**:

* `VIEW`
  **or**
* `EDIT`

Never in both.

##### Reason

* Prevents UI conflicts
* Avoids ambiguous form behavior
* Ensures clear permission boundaries

##### 5. Configuration Overview

| Level               | Method                  | Purpose                        |
| ------------------- | ----------------------- | ------------------------------ |
| Role → Tables       | `setArrayString()`      | Which tables a role can access |
| Role → Table → VIEW | `setArrayArrayString()` | Read-only columns              |
| Role → Table → EDIT | `setArrayArrayString()` | Editable columns               |

---

##### 6. Typical Workflow: Adding a New Table

##### Step 1 – Allow table access

```php
->setArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
    ['new_table']
)
```

##### Step 2 – Define viewable columns

```php
->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
    'new_table',
    ['id', 'name']
)
```

##### Step 3 – Define editable columns

```php
->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_EDIT . SeablastConstant::USER_ROLE_EDITOR,
    'new_table',
    ['name', 'active']
)
```

##### Summary

* Table access is **role-based**
* Columns are explicitly split into **VIEW** and **EDIT**
* A column may exist in **only one section**
* Tables must be registered **before** defining columns

This design guarantees predictable behavior, secure access control, and a clean admin UI.

## Authentication and authorisation

- Roles are for access.
- Routes can only be allowed for roles (never denied). I.e. access to a route can be restricted to certain roles.
- Menu items can be both allowed and denied (e.g. don't show to an authenticated user).
- Groups are on top of it, e.g. for promotions, subscriptions etc.
- RBAC (Role-Based Access Control): SB_IDENTITY_MANAGER provided by application MUST have methods prescribed in [IdentityManagerInterface](https://github.com/WorkOfStan/seablast-interfaces/blob/main/src/IdentityManagerInterface.php), these populate FLAG_USER_IS_AUTHENTICATED and USER_ROLE_ID and also USER_ID.

## Security

All JSON calls and form submits MUST contain `csrfToken` handed over to the view layer in the `$csrfToken` string latte variable.

## Stack

- PHP >=7.2 <8.6
- [Latte](http://latte.nette.org/) `>=2.10.8 <4`: for templating
- [MySQL](https://dev.mysql.com/)/[MariaDB](http://mariadb.com): for database backend
- [Tracy](https://github.com/nette/tracy) `^2.9.8 || ^2.10.9`: for debugging
- [Nette\SmartObject](https://doc.nette.org/en/3.0/smartobject): for ensuring strict PHP rules
- [jQuery] 3.7.1: as a JavaScript framework
- [Universal Language Selector jQuery library](https://github.com/wikimedia/jquery.uls): for language switching (used by [Seablast\i18n](https://github.com/WorkOfStan/seablast-i18n))

### ULS (Universal Language Selector jQuery library)

- if flag `I18nConstant::FLAG_SHOW_LANGUAGE_SELECTOR` is active (default in Seablast\I18n), then CSS and JS dependencies are already part of the template [BlueprintWeb.latte](views/BlueprintWeb.latte).
- .eslintignore and .prettierignore to ignore third-party libraries, so that super-linter doesn't fail with JAVASCRIPT_ES and so that prettier doesn't change them or super-linter fails with CSS_PRETTIER, JAVASCRIPT_PRETTIER, JSON_PRETTIER, MARKDOWN_PRETTIER
- To make the SVG icon in `.uls-trigger` adopt the `font-color` of the surrounding element, the following style was added into `uls/images/language.svg`: `fill="currentColor"`. Also `uls/css/jquery.uls.css` was changed (changed: `.uls-trigger`, added: `.uls-trigger icon` and `.uls-trigger .icon svg`).
- based on <https://github.com/wikimedia/jquery.uls> Revision: 077c71408284f446b626b656ce206e6ed3af705c Date: 17.07.2025 14:25:10
  - css\jquery.uls.css

    ```css
    .uls-trigger {
      background: url(../images/language.svg) no-repeat left center;
      padding-left: 24px;
    }
    ```

    ... changed to ...

    ```css
    .uls-trigger {
      padding-left: 24px;
    }

    .uls-trigger .icon {
      vertical-align: middle;
    }

    .uls-trigger .icon svg {
      height: 1em; /* přizpůsobí velikost textu */
    }
    ```

  - images\language.svg

    ```svg
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
    ```

    ... changed to ...

    ```svg
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
    ```

- See <https://github.com/wikimedia/jquery.uls/compare/077c71408284f446b626b656ce206e6ed3af705c...master> to compare the changes in the latest version

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
| views/    | Latte templates to be inherited (note: {try}{include file} masks compilation errors by preferring seablast/views)    |

## Testing

The PHPUnit tests use the database configuration from `./conf/phinx.local.php`, so the library require-dev Phinx, ensuring PHPUnit tests work on GitHub as well.

## Development notes

`./blast.sh phpstan` runs PHPStan to test the repository.
It can also be called `./vendor/seablast/seablast/blast.sh` from a Seablast application as a management script for deployment and development. Run `./blast.sh -?` to see all the options.
