# AGENTS.md

This file is the maintainer and integration guide for `seablast/seablast`.
It is written primarily for maintainers changing the framework itself and for framework integrators who need to preserve Seablast's real runtime contract.

Agents building applications on top of Seablast should start with `AGENT-STARTER-KIT.md` and return here only when they need deeper framework behavior.

This document describes the current behavior of the codebase, including important caveats that are not obvious from the marketing-level `README.md` alone.

## Companion Documentation

Use these documents for the intended audience:

- `AGENTS.md`: framework maintenance, internal contracts, and integration caveats
- `AGENT-STARTER-KIT.md`: AI agents building or migrating applications that use Seablast as a dependency
- `README.md`: public human-facing overview and usage guidance

When changing app-facing behavior, keep `AGENT-STARTER-KIT.md` aligned with `AGENTS.md` so downstream agents do not learn stale application patterns.

## What Seablast Is

Seablast is a composer-installed minimalist MVC runtime for PHP applications.
The core runtime is small and centered around four classes:

1. `SeablastSetup`
2. `SeablastController`
3. `SeablastModel`
4. `SeablastView`

Applications extend the framework mostly by:

- providing configuration closures in `conf/app.conf.php` and `conf/app.conf.local.php`
- registering routes in `SeablastConstant::APP_MAPPING`
- implementing models that return `stdClass`
- overriding or inheriting Latte templates

Optional integrations include:

- `seablast/auth`
- `seablast/i18n`

## Bootstrap and Request Lifecycle

The runtime flow is:

1. `defineAppDir.php` defines `APP_DIR`.
   If the library is executed from `vendor/seablast/seablast`, `APP_DIR` resolves to the application root.
2. `index.php` loads `APP_DIR . '/vendor/autoload.php'`, enables Tracy, and creates `SeablastSetup`.
3. `SeablastSetup` builds a single `SeablastConfiguration` instance by loading configuration closures in precedence order.
4. `SeablastController` applies environment configuration, derives runtime values, starts the session, resolves the route, and enforces authentication/authorization.
5. `SeablastModel` instantiates the mapped application model, calls `knowledge()`, and always injects a CSRF token into the returned parameters.
6. `SeablastView` renders JSON, HTML, or redirect output and then exposes Tracy SQL and HTTP panels.

When maintaining the framework, preserve this order. Session setup, Tracy wiring, runtime-derived configuration, and route resolution are coupled.

## Configuration Layering

`SeablastSetup` currently loads configuration files in this order, from lowest to highest priority:

1. `conf/default.conf.php`
2. `APP_DIR/vendor/seablast/auth/conf/app.conf.php`
3. `APP_DIR/vendor/seablast/i18n/conf/app.conf.php`
4. `APP_DIR/conf/app.conf.php`
5. `APP_DIR/conf/app.conf.local.php`

Each file must return a callable that accepts `SeablastConfiguration`.

This means application config can override:

- framework defaults
- auth defaults
- i18n defaults
- environment-local overrides

If you change this order, you are changing a public integration contract.

## Runtime Values Added by Core

The controller populates several values at runtime that applications may read later:

- `SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL`
- `SeablastConstant::SB_GET_ARGUMENT_ID`
- `SeablastConstant::SB_GET_ARGUMENT_CODE`
- `SeablastConstant::ERROR_HTTP_CODE`
- `SeablastConstant::ERROR_MESSAGE`
- `SeablastConstant::USER_ID`
- `SeablastConstant::USER_ROLE_ID`
- `SeablastConstant::USER_GROUPS`
- `SeablastConstant::FLAG_USER_IS_AUTHENTICATED`

Applications should treat these as framework-owned runtime state, not as values to preseed manually.

## Route Contract

Routes live in `SeablastConstant::APP_MAPPING` and are matched by exact path after:

- removing the application base path from `REQUEST_URI`
- trimming a trailing slash
- defaulting an empty path to `/`

The framework currently consumes these mapping keys:

- `model`: fully qualified class name of the model
- `template`: Latte template name without `.latte`
- `roleIds`: comma-separated allow-list of numeric role IDs
- `id`: required GET parameter name, stored as `SB_GET_ARGUMENT_ID`
- `code`: required GET parameter name, validated and stored as `SB_GET_ARGUMENT_CODE`

Notes:

- Missing routes are converted to `/error`, not to a generic JSON error route.
- A route with `roleIds` requires a configured identity manager.
- `code` is intentionally restricted and rejects control characters, quotes, backslashes, semicolons, and SQL comment markers.

If you add new mapping keys in core, document them in `README.md`, this file, and tests.

## Model Contract for Applications

Application models are expected to implement the same contract as `SeablastModelInterface`:

- constructor signature: `__construct(SeablastConfiguration $configuration, Superglobals $superglobals)`
- method: `knowledge(): stdClass`

`SeablastModel` instantiates the mapped class directly and then calls `knowledge()`.

Special properties in the returned `stdClass`:

- `rest`: when present, Seablast renders JSON instead of HTML
- `httpCode`: optional HTTP status code
- `redirectionUrl`: when present, Seablast sends a redirect and renders `redirection.latte`
- `title`: used by `BlueprintWeb.latte` as page title

The framework also always adds:

- `csrfToken`

Important consequences:

- A model must return `stdClass`, not an array.
- JSON responses can return `rest` as either an object or an array.
- Redirect codes are limited to `301`, `302`, `303`, `307`, and `308`.
- For non-REST HTML responses with `httpCode >= 400`, `SeablastView` forces the `error` template.

## Generic JSON API Contract

`GenericRestApiJsonModel` is the base implementation for JSON endpoints.

Current behavior:

- requires `REQUEST_METHOD` in `Superglobals->server`
- for real HTTP input, requires JSON `Content-Type` before reading the body
- rejects real or injected JSON input over 1 MiB before decoding
- reads JSON from `php://input`, or from `SeablastConstant::JSON_INPUT` when injected for tests
- accepts only a decoded JSON object
- requires `csrfToken`
- validates the token against the `sb_json` token ID

Default error behavior:

- `400` for invalid JSON or wrong payload shape
- `401` for missing or invalid CSRF token
- `413` for JSON request bodies over 1 MiB
- `415` for missing or non-JSON `Content-Type`

Applications extending this class should call `parent::knowledge()` first and stop when it already returns `httpCode >= 400`.

## View and Template Contract

`SeablastView` injects one more template variable before rendering:

- `configuration`

Bundled template lookup behavior:

- first try `../../../<LATTE_TEMPLATE>/<template>.latte`
- otherwise use `<LATTE_TEMPLATE>/<template>.latte` from the bundled library

In the standard vendor layout, the first path resolves to the application template directory. If you change bootstrap location or current working directory assumptions, re-check template inheritance carefully.

With the default `LATTE_TEMPLATE = 'views'`, applications override bundled templates by placing files in:

- `APP_DIR/views/*.latte`

### BlueprintWeb Expectations

The bundled `views/BlueprintWeb.latte` assumes these are available:

- `$csrfToken`
- `$configuration`
- `SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL`
- `SeablastConstant::SB_WEB_FORCE_ASSET_VERSION`

Important: `SB_WEB_FORCE_ASSET_VERSION` is not set by `conf/default.conf.php`, but the bundled layout reads it unconditionally. Applications that use `BlueprintWeb.latte` should define it explicitly.

The layout also:

- loads jQuery
- loads `assets/scripts/seablast-bridge.js`
- optionally includes Seablast I18n ULS assets when the i18n flag is active

### Template CDN Security

External scripts loaded from CDN must use Subresource Integrity (SRI) and
`crossorigin="anonymous"`.

SRI hashes are tied to the exact URL and response bytes that the browser loads,
not only to the library name and version. For example, the official jQuery SRI
snippet for `https://code.jquery.com/jquery-3.7.1.min.js` does not match the
Google CDN URL currently used by `BlueprintWeb.latte`:

- `https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js`

If a CDN URL changes, recompute the SRI hash for the new URL from the raw
downloaded bytes. Do not compute it from decoded text, because even subtle byte
differences make the browser reject the script.

## Authentication and Authorization

If `SeablastConstant::SB_IDENTITY_MANAGER` is configured, the controller instantiates the class with:

- `new $identityManager($configuration->mysqli())`

The class is expected to match `seablast/interfaces` behavior.

The controller also supports optional integration methods when they exist:

- `setTablePrefix()`
- `setCookiePath()`

If the identity says the user is authenticated, core writes user data into configuration and injects the user ID into DB adapters for query logging.

Route protection behavior:

- no authentication and protected route: use `APP_MAPPING_401` when configured, otherwise render a `401` error page
- authenticated but wrong role: render a `403` error page

## Database Contract

Database credentials are read from:

- `APP_DIR/conf/phinx.local.php`

The effective environment is:

- `SeablastConstant::SB_PHINX_ENVIRONMENT` when set
- otherwise `environments.default_environment` from the Phinx config

`SeablastConfiguration` exposes two lazy adapters:

- `mysqli()`
- `pdo()`

Useful behavior to preserve:

- both adapters log write-like queries
- both adapters collect statements for Tracy bar panels
- `setUser()` propagates the current user to both adapters
- `dbmsTablePrefix()` lazily initializes a preferred connection when needed

Deprecated compatibility methods still present:

- `dbms()`
- `dbmsStatus()`

Maintain backward compatibility unless you intentionally ship a breaking change.

## Admin Contract

Bundled default routes:

- `/poseidon`
- `/api/poseidon`

Admin permissions are split into:

- table visibility: `ADMIN_TABLE_VIEW`
- editable columns: `ADMIN_TABLE_EDIT`
- insert permission: `ADMIN_TABLE_INSERT_ROW`
- delete permission: `ADMIN_TABLE_DELETE_ROW`

Applications configure them by appending the role suffix constant, for example:

- `SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR`

### Current Admin Behavior

The bundled admin currently supports:

- table listing
- column-level view/edit permissions
- filtering
- ordering
- inline cell updates through `/api/poseidon`

Filtering and ordering details:

- selected table comes from GET parameter `t`
- filters are passed as repeated `condition[]` values in the format `columnIndex|value`
- ordering is passed as `order=a0,d2`
- result limit is hardcoded to `50`

Inline update details:

- GET parameters: `t`, `key`, `id`
- JSON body must contain `csrfToken` and `val`

### Important Admin Caveats

These are part of the current reality and should be documented for app integrators:

- `AdminHelper` hardcodes role inheritance for role `1` as admin and role `2` as editor. The role constants exist in config, but the current helper logic still assumes those numeric values.
- Insert/delete permission flags are exposed, but the bundled admin UI/API is still focused on view/edit and does not yet implement a full insert/delete workflow.
- The bundled admin menu contains opinionated links such as `/user-pages` and `/user/?logout`; applications may need to override the admin template or model if those routes do not exist.
- Some bundled admin and error UI strings are still Czech.

## Under Construction Mode

If `FLAG_WEB_RUNNING` is not active, the controller serves `under-construction.html` and exits.

Bypass rules:

- localhost (`::1`, `127.0.0.1`)
- IPs listed in `DEBUG_IP_LIST`

The application may override the static file by placing:

- `APP_DIR/under-construction.html`

## Debugging and Logging

Tracy is enabled in development mode for:

- localhost
- IPs listed in `DEBUG_IP_LIST`

`FLAG_DEBUG_JSON` intentionally suppresses the JSON `Content-Type` header so Tracy can render debug output more comfortably. This is a local debugging aid and should not be enabled in normal production traffic.

SQL bar panels are rendered after output selection so that database diagnostics survive normal page, JSON, and redirect flows.

## Current Realities and Non-Goals

These points are worth keeping in mind when maintaining or integrating Seablast:

- The controller currently performs direct path matching. Friendly URL database resolution and redirector logic are still marked as `TODO` in code.
- Route misses currently fall back to `/error`, which means missing API routes are not automatically rendered as JSON.
- The bundled templates are functional defaults, not a complete design system.
- `README.md` contains the broad usage story, but this file should remain the source of truth for framework internals and hidden contracts.

## Change Checklist for Maintainers

When changing core behavior, verify all affected layers together:

- bootstrap: `defineAppDir.php`, `index.php`, session timing, Tracy setup
- configuration: new constants, defaults, override order, runtime-populated values
- routing: `APP_MAPPING`, auth flow, error flow, GET parameter extraction
- model/view contract: `stdClass`, `csrfToken`, `rest`, `httpCode`, redirects
- templates/assets: override rules, required config keys, asset versioning
- database: lazy init, table prefix, query logging, Tracy panels
- admin: role inheritance, table permissions, filtering, inline edit behavior
- docs/tests: `README.md`, `CHANGELOG.md`, this file, PHPUnit expectations

## Testing and Local Development

Useful local commands:

- PHPUnit: run the repository test suite with your usual PHP/PHPUnit entry point
- PHPStan on Windows: `& "C:\Program Files\Git\bin\bash.exe" -lc "./blast.sh phpstan"`

Important test assumptions:

- tests expect `conf/phinx.local.php`
- tests commonly switch `SB_PHINX_ENVIRONMENT` to `testing`
- some bundled templates assume app-level config such as `SB_WEB_FORCE_ASSET_VERSION`

## Documentation Policy

If a change affects framework behavior that applications depend on, update all of these together:

- `README.md` for public usage guidance
- `AGENT-STARTER-KIT.md` for AI agents building or migrating apps
- `CHANGELOG.md` for release notes
- `AGENTS.md` for maintainer and integration detail
- tests where the behavior is asserted

That keeps Seablast honest for both maintainers and downstream applications.
