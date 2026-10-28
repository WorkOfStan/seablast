# Seablast for PHP

[![Total Downloads](https://img.shields.io/packagist/dt/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)
[![Latest Stable Version](https://img.shields.io/packagist/v/seablast/seablast.svg)](https://packagist.org/packages/seablast/seablast)
[![Polish the code](https://github.com/WorkOfStan/seablast/actions/workflows/polish-the-code.yml/badge.svg)](https://github.com/WorkOfStan/seablast/actions/workflows/polish-the-code.yml)

Seablast is a minimalist PHP MVC runtime installed with Composer. Applications
provide configuration, exact-path routes, models, and Latte templates. Core supplies
request handling, database adapters, logging, and error responses; optional
[auth](https://github.com/WorkOfStan/seablast-auth) and
[i18n](https://github.com/WorkOfStan/seablast-i18n) packages add identity and language support.
Database-backed friendly-URL resolution is not implemented.

- [Application guide](AGENT-STARTER-KIT.md): build or migrate an application.
- [Architecture](src/README.md): core components and request/response lifecycle.
- [Security reference](SECURITY.md): trust boundaries, sessions, CSRF, headers, and deployment.
- [Maintainer guide](AGENTS.md): repository conventions, invariants, and testing.

## Installation

Start from the [seablast-dist template](https://github.com/WorkOfStan/seablast-dist),
or add the runtime to an existing Composer project:

```sh
composer require seablast/seablast
```

Configure the application as below, make `cache/` and `log/` writable by PHP, and
route web requests to `vendor/seablast/seablast/index.php`. Protect configuration,
source, logs, and private storage from direct web access; see the
[deployment safeguards](SECURITY.md#deployment-safeguards).

## Configuration

Existing configuration files run from lowest to highest priority:

1. [conf/default.conf.php](conf/default.conf.php)
2. `APP_DIR/vendor/seablast/auth/conf/app.conf.php` (optional)
3. `APP_DIR/vendor/seablast/i18n/conf/app.conf.php` (optional)
4. `APP_DIR/conf/app.conf.php`
5. `APP_DIR/conf/app.conf.local.php`

Each returns a callable receiving `SeablastConfiguration`. For example:

```php
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig
        ->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, ['app.example.com'])
        ->setInt(SeablastConstant::SB_WEB_FORCE_ASSET_VERSION, 1)
        ->setArrayArrayString(SeablastConstant::APP_MAPPING, '/', [
            'model' => '\App\Models\HomeModel', // class name of the model,
            'template' => 'home',
        ]);
};
```

`set*()` replaces values; `setArrayArrayString()` replaces one property/key entry.
`addArrayString()` appends strings and initializes a missing property;
`addArrayArrayString()` merges strings into a property/key entry, initializing it
when absent. Reuse existing constants for existing settings.

The lazy `mysqli()` and `pdo()` adapters read `APP_DIR/conf/phinx.local.php`.
`SB_PHINX_ENVIRONMENT` selects the environment (`development` in defaults); only
when absent does Phinx's `environments.default_environment` apply. Applications may
use independent persistence; its PDO failures do not initialize Seablast's adapter
for diagnostics. `dbms()`/`dbmsStatus()` are deprecated aliases for the MySQLi APIs.

Set `SB_LOG_DIRECTORY` to change the default `APP_DIR . '/log'` location for framework
and query logs. Use `Tracy\Debugger::log()` with an explicit `Tracy\ILogger` severity;
the configured SeablastLogger respects `SB_LOGGING_LEVEL`. Never log secrets.
Complete model results and view parameters are not logged, but level 5 enables
Tracy dumps containing them. Protect SQL logs and diagnostics with suitable access
and retention controls.

### Allowed request hosts

Set `SB_ALLOWED_HOSTS` to exact public hosts, including explicit ports. An empty
list validates syntax but does not enforce host membership.
See [host validation and examples](SECURITY.md#allowed-request-hosts).

### Reverse proxies and session cookies

Trust only explicitly configured proxy IPs. Session cookies default to host-only,
HttpOnly, SameSite=Lax, and Secure on verified HTTPS. Debug and maintenance bypass
require an explicit client allow-list, including localhost.
See [proxy trust, cookie settings, and upgrade requirements](SECURITY.md#reverse-proxies-and-session-cookies).

### Potentially stateless pages

Routes use `session => required` by default. Use `auto` only when the model and
layout tolerate session-free requests and null CSRF tokens; request state or a
token before protected interactions.
See [session triggers and CSRF bootstrap](SECURITY.md#potentially-stateless-pages).

### Browser security headers

Dynamic responses default to same-origin framing, nosniff, referrer protection,
and no-store. Content restrictions are report-only; review integrations before
enforcement. Never publicly cache personalized or token-bearing responses.
See [CSP, nonces, cache policy, and custom renderers](SECURITY.md#browser-security-headers).

### Browser error reporting

`ErrorLogger` sends CSRF-protected diagnostics to `/api/error`; token-free messages
stay local. Ingestion is enabled by default with field, body, and rate limits.
See [client usage, limits, storage, and HTTP errors](SECURITY.md#browser-error-reporting).

## Routing

Routes in `APP_MAPPING` match exact paths after removing the application base path
and one trailing slash; an empty path becomes `/`. There is no method-based dispatch:
use separate paths or branch on `REQUEST_METHOD` in the model.

| Mapping key | Meaning |
| --- | --- |
| `model` | Fully qualified application model class; optional for template-only routes |
| `template` | One Latte template name without `.latte` |
| `roleIds` | Comma-separated allowed role IDs; requires an identity manager |
| `id` | Required GET parameter, scalar-cast to `SB_GET_ARGUMENT_ID` (int) |
| `code` | Required GET parameter, restricted string in `SB_GET_ARGUMENT_CODE` |
| `session` | `required` (default) or `auto`; other values are configuration errors |

Missing routes fall back to `/error`, including missing API routes; this does not
create a JSON error automatically. Missing required parameters also select an error.
`code` rejects empty values, controls, quotes, backslashes, semicolons, `#`, and SQL
comment markers. Models must still validate identifiers, optional parameters,
resource access, and HTTP methods; the `id` cast is not strict numeric validation.

## Model

Application models follow [SeablastModelInterface](src/SeablastModelInterface.php):
`__construct(SeablastConfiguration $configuration, Superglobals $superglobals)`
and `knowledge(): stdClass`. Return an object, not an array. The wrapper adds
`csrfToken` (active session's `sb_json` token, otherwise null).

| Result property | Behavior |
| --- | --- |
| `rest` | Render this object or array as JSON |
| `httpCode` | Set HTTP status; HTML errors (`>= 400`) select the `error` template |
| `redirectionUrl` | Send Location and render `redirection.latte`; default 301, allowed 301/302/303/307/308 |
| `title` | Page title in `BlueprintWeb.latte` |
| `noStore` | Boolean true forces `Cache-Control: no-store` over global policy |

Use one response mode per result. JSON always uses
`Content-Type: application/json; charset=utf-8`, including Tracy development mode.
The [application guide](AGENT-STARTER-KIT.md#models-and-responses) covers implementation.

## View

With `LATTE_TEMPLATE = 'views'` and the normal vendor working directory, Seablast
first looks for each template in `APP_DIR/views/`, then in the library's `views/`.
A different `LATTE_TEMPLATE` changes both lookup paths; it does not automatically
fall back to the bundled `views/`. Missing files raise `MissingTemplateException`.
See [the exact relative lookup paths](AGENTS.md#view-and-template-contract) before
changing the bootstrap location or working directory. The view supplies
`configuration` and the request's `cspNonce`, overwriting model-provided values.

Extend the bundled layout with `{layout '../vendor/seablast/seablast/views/BlueprintWeb.latte'}`,
and override `nav.latte`/`footer.latte` as needed. Define `SB_WEB_FORCE_ASSET_VERSION`
explicitly: the layout requires it and defaults do not set it. See
[template integration](AGENT-STARTER-KIT.md#templates-and-assets) and the
[nonce policy](SECURITY.md#browser-security-headers).

The bundled `inherite.latte` include fallback uses Latte `{try}` and can hide a
compilation error in an application `nav.latte` or `footer.latte` by displaying the
bundled version. Check the override itself when unexpected default content appears.

### Administration

`/poseidon` lists permitted tables and supports filtering, ordering, and inline
updates through `/api/poseidon`. Default access is for roles 1 (admin) and 2 (editor).
`AdminHelper` hardcodes these roles: admin table permissions include editor tables,
but an admin-specific column list replaces the editor list for the same table and
access type. Include the full intended columns in such overrides.

#### Admin Table Configuration

<a id="core-concepts"></a>
<a id="1-granting-table-access-to-a-role"></a>
<a id="2-defining-viewable-columns-read-only"></a>
<a id="3-defining-editable-columns-write"></a>
<a id="4-important-rule-column-exclusivity"></a>
<a id="6-configuration-overview"></a>
<a id="7-typical-workflow-adding-a-new-table"></a>
<a id="syntax"></a>
<a id="syntax-1"></a>
<a id="syntax-2"></a>
<a id="meaning"></a>
<a id="meaning-1"></a>
<a id="meaning-2"></a>
<a id="example"></a>
<a id="example-1"></a>
<a id="example-2"></a>
<a id="reason"></a>
<a id="summary"></a>

Grant table access and define disjoint read-only (`VIEW`) and editable (`EDIT`)
column lists. Both lists are displayed; keep each column in only one list to avoid
duplicates. Table access is required even when column permissions exist.
Include the literal `id` column, normally in `VIEW`: row listing indexes results by
`id`, and inline updates also require it. The column lists must remain disjoint
after editor inheritance and admin overrides have been resolved.

| Permission level | Configuration method | Meaning |
| --- | --- | --- |
| Role to tables | `setArrayString(ADMIN_TABLE_VIEW . role, tables)` | Tables the role may access |
| Role to table columns | `setArrayArrayString(ADMIN_TABLE_VIEW . role, table, columns)` | Displayed, read-only columns |
| Role to table columns | `setArrayArrayString(ADMIN_TABLE_EDIT . role, table, columns)` | Displayed, editable columns |

These use separate typed configuration stores even though `ADMIN_TABLE_VIEW . role`
appears in both table and column configuration. When adding a table in a later
configuration layer, use `addArrayString()` to preserve the existing table list;
the `setArrayString()` example below replaces that list.

```php
// Step 1 – Allow table access
$SBConfig->setArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
    ['new_table']
);

// Step 2 – Define viewable columns
$SBConfig->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
    'new_table',
    ['id']
);

// Step 3 – Define editable columns
$SBConfig->setArrayArrayString(
    SeablastConstant::ADMIN_TABLE_EDIT . SeablastConstant::USER_ROLE_EDITOR,
    'new_table',
    ['name', 'active']
);
```

<a id="5-granting-insertdelete-row-permissions-on-a-table-to-a-role"></a>
<a id="syntax-3"></a>

`ADMIN_TABLE_INSERT_ROW` and `ADMIN_TABLE_DELETE_ROW` take role-suffixed table lists
via `setArrayString()`. They expose permission flags; the bundled UI/API does not
implement insert/delete workflows. Custom implementations must enforce permissions,
require appropriate edit rights for inserts, and review cascading deletes.

The selected table is `?t=table`; filters repeat `condition[]=columnIndex|value`,
ordering uses `order=a0,d2`, and results are capped at 250 rows without pagination.
Inline updates use GET parameters `t`, `key`, `id` and JSON fields `csrfToken`, `val`.
Send `val` as a JSON string, including for numeric columns; non-color values are
trimmed and checked against supported SQL column types. Color validation occurs
before trimming, so whitespace around a color is rejected.
Updates target a literal `id` column using a nonzero integer-cast ID; arbitrary or
composite primary keys are unsupported. Override opinionated menu links such as
`/user-pages` and `/user/?logout` if absent in your app; some UI text is Czech.

#### Automatically detected boolean fields

`SeablastConstant::ADMIN_BOOLEAN_FIELDS` exposes a `string[]` of automatically detected boolean-like
column names for the selected admin table. Detection checks the SQL column types and verifies that
stored values are limited to `0`, `1`, or `NULL`.

This is runtime metadata for application code and custom templates. Do not configure it manually:
`AdminModel` overwrites it with the detection result when a table is selected. The bundled admin
template currently does not consume this metadata or automatically render checkboxes.

#### Color editor for selected fields

Fields that use the color editor (instead of a textarea) also display the selected color as the background.

Configured `ADMIN_COLOR_FIELDS` accept only six-digit hexadecimal colors (`#rrggbb`).
Uppercase hexadecimal digits are accepted and stored in lowercase. Whitespace,
empty values, color names, and shorthand `#RGB` are rejected with HTTP 400.
Existing invalid values remain visible as escaped text without a background and
can be corrected with the color editor; no automatic data migration is performed.
Applications overriding `admin.latte` must adopt the validated, escaped rendering
and safe DOM construction for filter options from the bundled template.

This feature applies to all fields with the same name across all tables (field names MUST be unambiguous).

```php
        ->setArrayString(SeablastConstant::ADMIN_COLOR_FIELDS, [
            'color_main', // audios.color_main but also item.color_main which still makes sense
        ])
```

## Authentication and authorisation

Set `SB_IDENTITY_MANAGER` to an implementation of the installed
[`IdentityManagerInterface`](https://github.com/WorkOfStan/seablast-interfaces/blob/main/src/IdentityManagerInterface.php).
Core constructs it with
`$configuration->mysqli()` and optionally calls `setTablePrefix()` and
`setCookiePath()`. Authenticated identities populate `FLAG_USER_IS_AUTHENTICATED`,
`USER_ID`, `USER_ROLE_ID`, and `USER_GROUPS` and propagate user IDs to DB logging.
Groups are available to application logic; route authorization uses role allow-lists.

For example, add this to a route mapping:

```php
'roleIds' => '1,2', // comma delimited roleIds permitted to access the route,
```

Unauthenticated protected requests use the route named by `APP_MAPPING_401`, if
configured, otherwise a 401 error. Authenticated users with an unlisted role get
403. Menu visibility is separate and does not enforce route authorization.

## Security

Use [SECURITY.md](SECURITY.md) as the shared security configuration and deployment
reference. Protected submissions must validate `sb_json` CSRF tokens; ordinary
forms and multipart endpoints require manual validation. Keep private files out
of direct web access and validate authorization separately from CSRF.

See [JSON and form requirements](SECURITY.md#forms-json-apis-and-csrf),
[deployment safeguards](SECURITY.md#deployment-safeguards), and
[maintenance access](SECURITY.md#maintenance-mode).

## Stack

- PHP >=7.2 <8.6
- [Latte](http://latte.nette.org/) `>=2.10.8 <3.1`: For templating.
- [MySQL](https://dev.mysql.com/)/[MariaDB](http://mariadb.com): For database backend.
- [Tracy](https://github.com/nette/tracy) `^2.9.8 || ^2.10.9 || ^2.11.0`: For debugging.
- [Nette\SmartObject](https://doc.nette.org/en/3.0/smartobject): A trait that helps enforce strict PHP behavior.
- [jQuery](https://jquery.com/) 3.7.1: As a JavaScript framework.
- [Universal Language Selector jQuery library](https://github.com/wikimedia/jquery.uls): For
  language switching (used by [Seablast I18n](https://github.com/WorkOfStan/seablast-i18n)).

### ULS (Universal Language Selector jQuery library)

`BlueprintWeb.latte` includes optional i18n assets when
`I18nConstant::FLAG_SHOW_LANGUAGE_SELECTOR` is enabled. Core also contains a
vendored [ULS tree](assets/uls/README.md); exclude third-party assets from automatic
formatting/linting. Preserve the local icon adaptation when updating that tree:
`language.svg` uses `fill="currentColor"`; `.uls-trigger` uses 24px left padding,
its `.icon` aligns vertically, and `.icon svg` uses
`height: 1em; /* Match text size. */` instead of a background-image icon.
The documented [upstream comparison
baseline](https://github.com/wikimedia/jquery.uls/compare/077c71408284f446b626b656ce206e6ed3af705c...master)
is `077c71408284f446b626b656ce206e6ed3af705c`; verify provenance before replacing assets.

## Notes

- the constant `APP_DIR` = the directory of the current application (or the library, if deployed directly)
- don't start the value of a constant for a configuration field in the app.conf.php with SB to prevent value collision

## Framework directory description

See the [repository map](AGENTS.md#repository-structure) and
[architecture overview](src/README.md).

## Testing

PHPUnit database tests require `conf/phinx.local.php`, normally with a `testing`
environment. Composer development dependencies include PHPUnit and Phinx.
Commands and focused regression entry points are in the
[maintainer guide](AGENTS.md#testing-and-local-development).

## Development notes

Run `./blast.sh -?` in Bash for management-script options; applications can invoke
`./vendor/seablast/seablast/blast.sh`. On Windows use Git Bash explicitly, as shown
in the [maintainer commands](AGENTS.md#testing-and-local-development).
