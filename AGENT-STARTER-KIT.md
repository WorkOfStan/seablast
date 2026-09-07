# AGENT-STARTER-KIT.md

This file is for AI agents building or migrating applications that use Seablast for PHP.
Use it when Seablast is a dependency in another project.

For framework maintenance inside `seablast/seablast`, read `AGENTS.md` instead.

## What To Build With Seablast

Seablast is a composer-installed MVC runtime for vanilla PHP applications.
It works best when the app keeps business code simple and explicit:

- configuration in `conf/app.conf.php`
- route declarations in `SeablastConstant::APP_MAPPING`
- models in `src/Models/`
- Latte templates in `views/`
- browser assets in `assets/`
- runtime write directories such as `cache/` and `log/`
- private app storage in a non-public directory such as `var/`

For new projects, start from `seablast-dist` when possible and remove demo code deliberately.
For existing app migrations, reshape the app into the Seablast structure instead of keeping a parallel framework layout alive.

## Bootstrap And Project Shape

Normal web requests should enter through:

```txt
vendor/seablast/seablast/index.php
```

The app root should not keep a competing front controller unless the project has a deliberate replacement for the Seablast runtime.
`defineAppDir.php` resolves `APP_DIR` from the vendor layout, so app code can rely on `APP_DIR` during Seablast web requests.

The app-level configuration files are:

- `conf/app.conf.php` for committed app configuration
- `conf/app.conf.local.php` for uncommitted environment overrides
- `conf/phinx.local.php` for Seablast's built-in MySQL adapters

Each Seablast config file returns a callable that receives `SeablastConfiguration`.

## Routing

Routes are exact path matches. Seablast does not choose a different model by HTTP method at the mapping layer.

Use one of these styles:

- separate exact paths for separate actions, for example `/invoice`, `/invoice/edit`, `/api/invoice`
- one resource path with a model that branches on `REQUEST_METHOD`, query parameters, or POST data

Route keys used by core:

```php
->setArrayArrayString(
    SeablastConstant::APP_MAPPING,
    '/example',
    [
        'model' => '\App\Models\ExampleModel',
        'template' => 'example',
        'roleIds' => '1,2',
        'id' => 'id',
        'code' => 'code',
    ]
)
```

Important route behavior:

- one route mapping has one `template`
- trailing slashes are trimmed
- an empty path becomes `/`
- missing routes render `/error`
- `id` and `code` mapping keys make the matching GET parameter required
- optional `?id=` or mixed list/detail behavior must be validated inside the model
- protected routes need a configured identity manager

## Models And Responses

Application models should match `SeablastModelInterface`:

```php
public function __construct(
    SeablastConfiguration $configuration,
    Superglobals $superglobals
) {
}

public function knowledge(): stdClass
{
}
```

Return an `stdClass`, not an array.
Seablast adds `csrfToken` after the model runs.

Special response properties:

- `rest`: render JSON instead of HTML
- `httpCode`: set the response status
- `redirectionUrl`: send a redirect and render `redirection.latte`
- `title`: used by the default `BlueprintWeb.latte` page title

Redirect status codes are limited to `301`, `302`, `303`, `307`, and `308`.
Prefer app-relative redirect URLs unless a trusted external host is explicitly allowed.

## Templates And Assets

With the default `LATTE_TEMPLATE = 'views'`, app templates live in `APP_DIR/views/*.latte`.
A typical app template extends the bundled layout:

```latte
{layout '../vendor/seablast/seablast/views/BlueprintWeb.latte'}
```

When using `BlueprintWeb.latte`, define:

```php
->setInt(SeablastConstant::SB_WEB_FORCE_ASSET_VERSION, 1)
```

The layout expects `$csrfToken` and `$configuration`.
Add local `views/nav.latte` and `views/footer.latte` when the app needs custom navigation or footer content.

One route maps to one template.
If a resource has list and detail states, either use one branching template or split the resource into exact paths.

## Forms, JSON APIs, And CSRF

All form submissions and JSON calls should submit the view-provided `csrfToken`.

Use `GenericRestApiJsonModel` for JSON endpoints when the endpoint accepts:

- JSON `Content-Type`
- an object-shaped JSON body
- body size up to the framework limit
- `csrfToken` validated with token ID `sb_json`

Models extending `GenericRestApiJsonModel` should call `parent::knowledge()` first and stop when it returns `httpCode >= 400`.

Do not use `GenericRestApiJsonModel` for multipart uploads or normal form posts.
For those, validate the same token ID manually, preferably from a submitted `csrfToken` field.

## Uploads And Downloads

`Superglobals` wraps GET, POST, SERVER, and SESSION.
It does not wrap FILES, so upload models must read `$_FILES` directly or the app must provide its own wrapper.

Binary downloads do not fit the normal Latte or `rest` response flow.
The practical pattern is:

1. validate authentication and authorization
2. resolve the private file path
3. compare `realpath()` against the configured private storage root
4. send headers
5. stream the file
6. `exit` before `SeablastView` continues

## Database Choices

Seablast's built-in database helpers expect Phinx-style MySQL configuration in `conf/phinx.local.php`.
They expose lazy `mysqli()` and `pdo()` adapters through `SeablastConfiguration`.

An app may keep a custom database layer instead, especially during migration.
If it does, document that choice in the app and keep Seablast route/model/view behavior separate from the custom persistence layer.

Do not track `composer.lock` in applications so the Seablast runtime can run in multiple environments.

## Web Server And Security Checklist

An app `.htaccess` or server config should:

- route app URLs to `vendor/seablast/seablast/index.php`
- block direct access to `conf/`, `src/`, `views/`, `tests/`, `cache/`, `log/`, and private storage
- block Markdown, config, shell, CI, Composer, PHPUnit, dotfiles, and local metadata from the web
- keep required Seablast front-controller and asset paths reachable

Security defaults for app agents:

- treat all request data as untrusted
- validate optional IDs in the model
- do not derive `redirectionUrl` directly from request input
- prefer prepared database statements
- never stream a private file without a `realpath()` containment check
- keep debug and Tracy development output limited to trusted environments

### Trusted request context and cookies

For direct deployments, keep `SB_TRUSTED_PROXIES` empty. For a proxy deployment,
configure exact transport-peer IPs and the actual client addresses allowed to debug:

```php
$SBConfig
    ->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['192.0.2.10'])
    ->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['198.51.100.7']);
```

Require the edge proxy to replace client-supplied forwarding values. Trusted
proxies must provide a single external `X-Forwarded-Proto` (`http` or `https`)
and a valid `X-Forwarded-For` IP list, appending verified upstream peers through
multiple hops. Preserve `REMOTE_ADDR` as the transport peer. Missing or malformed
trusted metadata returns HTTP 400 before sessions or application routing.
Other forwarding headers are ignored; no CIDRs, wildcards, or automatic trust
discovery are supported. The first untrusted IP found scanning from the right
controls debug and maintenance access. Do not add the proxy to `DEBUG_IP_LIST`
as a substitute for listing actual clients. An all-trusted chain grants no bypass.

Default session cookies are host-only, HttpOnly, `SameSite=Lax`, and Secure on
verified HTTPS. Use `SB_SESSION_SET_COOKIE_PARAMS_SAMESITE` for `Strict` or
`None` (HTTPS required), and `SB_SESSION_SET_COOKIE_PARAMS_DOMAIN` only for
intentional sharing across trusted subdomains. A configured domain must match
the request hostname on a label boundary. Cookie paths reject control characters
and semicolons. Maintenance responses use the same policy. Apps starting sessions
early must apply it before `session_start()`; existing sessions are not restarted.

During upgrades, review prior Domain cookies and subdomain sharing; old Domain
and new host-only cookies can coexist until expiry. Plan explicit old-cookie
expiry or a session-name rotation where needed. The optional auth package's
cookies remain its responsibility. SEC-003 remains open: core still uses the
incoming Host authority for URLs, so retain web-server host restrictions.
PHP 7.2 support is retained; the `TODO PHP-7.2` note in `SeablastSessionCookie`
and `AGENTS.md` marks the legacy SameSite path for later removal.

## Testing Checklist

Useful app-level tests:

- load `conf/app.conf.php`
- read `SeablastConstant::APP_MAPPING`
- assert every mapped app model class exists
- assert every mapped local template exists or intentionally uses a bundled template
- test model behavior directly with `Superglobals`
- keep service tests focused on application behavior
- add at least one browser or HTTP smoke test for the real Seablast request flow

When migrating an existing app, add a test that locks the route map before deleting old framework-parallel folders.

## Migration Checklist

For framework migrations, prefer real moves over compatibility aliases:

- `classes/` to `src/`
- `config/` to `conf/`
- `templates/` to `views/`
- `migrations/` to `conf/db/migrations/`

Keep or add:

- `assets/`
- `cache/`
- `log/`
- private `var/`

Remove stale front controllers, duplicate routers, and unused framework folders once the Seablast route map is covered by tests.
