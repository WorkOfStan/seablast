# AGENT-STARTER-KIT.md

This file is for AI agents building or migrating applications that use Seablast for PHP.
Use it when Seablast is a dependency in another project.

For framework maintenance inside `seablast/seablast`, read [AGENTS.md](AGENTS.md). The [architecture
overview](src/README.md) explains
the request lifecycle; [README.md](README.md) covers public usage and
[SECURITY.md](SECURITY.md) holds shared security configuration and deployment details.

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

For new projects, start from [seablast-dist](https://github.com/WorkOfStan/seablast-dist) when
possible and remove demo code deliberately.
For existing app migrations, reshape the app into the Seablast structure instead of keeping a
parallel framework layout alive.

## Bundled Administration Color Fields

Follow the [administration configuration](README.md#administration), including
role inheritance limits. `ADMIN_COLOR_FIELDS` accepts only `#rrggbb` (uppercase is
normalized). When overriding `admin.latte`, retain escaped invalid-value display,
validated background colors, and safe DOM construction of filter options.

## Bootstrap And Project Shape

Normal web requests should enter through:

```txt
vendor/seablast/seablast/index.php
```

The app root should not keep a competing front controller unless the project has a deliberate
replacement for the Seablast runtime.
`defineAppDir.php` resolves `APP_DIR` from the vendor layout, so app code can rely on `APP_DIR`
during Seablast web requests.

The app-level configuration files are:

- `conf/app.conf.php` for committed app configuration
- `conf/app.conf.local.php` for uncommitted environment overrides
- `conf/phinx.local.php` for Seablast's built-in MySQL adapters

`app.conf.php` and `app.conf.local.php` return callables receiving
`SeablastConfiguration`. `phinx.local.php` instead returns a Phinx configuration
array; use [conf/phinx.dist.php](conf/phinx.dist.php) as the shape reference.
`set*()` methods replace previously configured values. Use `addArrayString()` to append strings to
an existing array or initialize a missing property.
Use `addArrayArrayString()` when a later configuration layer should merge strings into an existing
property/key entry instead of replacing the entry with `setArrayArrayString()`.
Reuse existing configuration constants from `SeablastConstant` (or `AuthConstant` or `I18nConstant`,
where applicable) instead of defining new constants for the same settings.

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
        'session' => 'required',
    ]
)
```

Important route behavior:

- one route mapping has one `template`
- one trailing slash is removed
- an empty path becomes `/`
- missing routes render `/error`; its default `auto` session policy also applies
  to these fallbacks, so audit error-page overrides for session access and forms
- `id` and `code` make the matching GET parameter required; `id` casts to int,
  so validate the identifier and resource access in the model
- optional `?id=` or mixed list/detail behavior must be validated inside the model
- protected routes need a configured identity manager
- `session` accepts `required` (default) or `auto`; `auto` allows anonymous GET/HEAD
  pages to avoid sessions until state is needed, while preserving session-backed
  authentication, preferences, protected routes, and authorized Tracy debugging

For `auto` routes, audit the model and complete layout for forms and session access.
`csrfToken` can be null. Call `$configuration->requireSession()` before using state
or returning a protected form, or call `getCsrfToken(API_BASE, csrfToken)` on browser
interaction before a protected request. Never fetch a token on page load.
Keep unadapted forms/selectors `required`; adapt i18n overrides alongside core.
`ErrorLogger` stays local without a token; update its `csrfToken` after interaction.
The PHP session cookie and configured preference/auth cookies trigger state,
not proof of authentication. See [Potentially stateless pages](SECURITY.md#potentially-stateless-pages)
for all triggers, helper usage, and remaining allocation risks.

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
    return (object) ['title' => 'Example'];
}
```

Return an `stdClass`, not an array.
Seablast adds `csrfToken` after the model runs: a token for an active session, or null for a session-free response.

Special response properties:

- `rest`: an object or array to render as JSON instead of HTML
- `httpCode`: set the response status
- `redirectionUrl`: send a redirect and render `redirection.latte`
- `title`: used by the default `BlueprintWeb.latte` page title
- `noStore`: when true, force `Cache-Control: no-store` over the global cache policy

REST responses always use `Content-Type: application/json; charset=utf-8`, including
when Tracy development mode is enabled.

HTML responses with `httpCode >= 400` use the error template.
Redirects default to 301; allowed status codes are `301`, `302`, `303`, `307`, and `308`.
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

Template lookup depends on the vendor working directory; changing `LATTE_TEMPLATE`
changes both application and library lookup paths. Check the
[view lookup and include-fallback caveats](README.md#view) for custom bootstraps.
For list/detail states, use one branching template or separate exact route paths.

## Forms, JSON APIs, And CSRF

Protected form submissions and JSON calls must submit the view-provided token or
one obtained on interaction. A null token does not pass CSRF validation; authorization is a separate check.

Use `GenericRestApiJsonModel` for JSON endpoints when the endpoint accepts:

- JSON `Content-Type`
- an object-shaped JSON body
- body size up to 1 MiB
- `csrfToken` validated with token ID `sb_json`

Models extending `GenericRestApiJsonModel` should call `parent::knowledge()` first and stop when it
returns `httpCode >= 400`.

Do not use `GenericRestApiJsonModel` for multipart uploads or normal form posts.
For those, validate the same token ID manually from a submitted `csrfToken` field.
The generic base does not restrict HTTP methods for you; enforce them in your endpoint.
See [CSRF and JSON requirements](SECURITY.md#forms-json-apis-and-csrf) for parser errors and the token-bootstrap exception.

## Uploads And Downloads

`Superglobals` wraps GET, POST, SERVER, and SESSION, plus cookie names with
nonempty string values (not their secret values).
It does not wrap FILES, so upload models must read `$_FILES` directly or the app must provide its own wrapper.

Binary downloads do not fit the normal Latte or `rest` response flow.
The practical pattern is:

1. validate authentication and authorization
2. resolve the private file path
3. resolve both paths with `realpath()`, reject failures, and enforce containment
   on a directory boundary within the private storage root
4. send headers
5. stream the file
6. `exit` before `SeablastView` continues

Apply the request's security-header policy before streaming, and force no-store
for private responses. Exiting here bypasses the view, so a model's `noStore`
property alone cannot set that header. See the
[custom-renderer contract](#browser-security-header-contract).

## Database Choices

Seablast's built-in database helpers expect Phinx-style MySQL configuration in `conf/phinx.local.php`.
They expose lazy `mysqli()` and `pdo()` adapters through `SeablastConfiguration`.

An app may keep a custom database layer instead, especially during migration.
If it does, document that choice in the app and keep Seablast route/model/view behavior separate
from the custom persistence layer.
PDO exceptions from custom persistence are rethrown unchanged; Seablast annotates its PDO debug
panel only when its own lazy adapter already exists.

Seablast does not require applications to omit `composer.lock`; follow the
application's dependency and deployment policy.

## Web Server And Security Checklist

Before deployment, follow the [deployment safeguards](SECURITY.md#deployment-safeguards):
route requests to Seablast, protect private paths, keep the front controller/assets
reachable, and restrict diagnostic output. In application code, validate identifiers
and authorization, use prepared statements, reject untrusted redirects, and check
resolved private-file paths before streaming.

### Trusted request context and cookies

Set `SB_ALLOWED_HOSTS` to the exact public host authorities in each environment;
explicit ports must match. An empty list validates syntax but does not restrict
membership. Configure exact proxy IPs in `SB_TRUSTED_PROXIES` only when needed,
and keep `REMOTE_ADDR` as
the transport peer. The edge must replace client forwarding metadata; trusted hops
must supply valid `X-Forwarded-Proto` and `X-Forwarded-For`.

`DEBUG_IP_LIST` is an explicit client allow-list for both Tracy and maintenance
bypass; empty means neither, including localhost. For direct local development:

```php
$SBConfig->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['127.0.0.1', '::1']);
```

Behind proxies, allow actual developer clients, not an unconfigured shared proxy.
Session cookies default to host-only, HttpOnly, SameSite=Lax, and Secure on verified
HTTPS. Apply policy before any early application session startup. Review existing
Domain cookies when upgrading or enabling subdomain sharing.
See [host validation](SECURITY.md#allowed-request-hosts) and
[proxy/cookie configuration](SECURITY.md#reverse-proxies-and-session-cookies) before deployment.

## Logging

Use the configured SeablastLogger through Tracy with an explicit severity:

```php
\Tracy\Debugger::log('Unable to process the request.', \Tracy\ILogger::ERROR);
```

This respects `SB_LOGGING_LEVEL`; prefer it to `error_log()`. Never log secrets or
full model payloads. Level 5 enables model/view Tracy dumps that can contain tokens;
restrict diagnostic access and retention.

Import `ErrorLogger` from Seablast's `assets/scripts/seablast.js`, then use the
page's token and application base URL:

```js
import { ErrorLogger } from "/vendor/seablast/seablast/assets/scripts/seablast.js";

// Use the application's base path for imports; BlueprintWeb supplies these values.
const errorLogger = new ErrorLogger(csrfToken, API_BASE);
errorLogger.log("Unable to process the request.", "error");
```

The jQuery client sends to `/api/error`, requires a session-bound token even for
anonymous visitors, honors rate-limit cooldowns, and stops the instance on disabled
403 responses. Configure limits, storage, and enablement using the
[browser reporting contract](SECURITY.md#browser-error-reporting).
`BannerManager.addBanner()` and `changeBanner()` display plain text; do not pass
HTML for formatting.

## Browser Security Header Contract

By default, dynamic responses enforce same-origin framing, nosniff, referrer protection, and
no-store. Content restrictions are report-only; review application templates,
auth/i18n assets, and Tracy before enforcement. Never publicly cache personalized
or token-bearing responses. See [policy settings](SECURITY.md#browser-security-headers).

The view supplies the request's `cspNonce`. Use `nonce="{$cspNonce}"` on intentional
inline script/style blocks. Bundled templates also support direct Latte rendering:

```latte
nonce="{$cspNonce ?? $configuration->securityHeaders()->getNonce()}"
```

The fallback reuses the configuration's nonce; it does not generate another or send
headers. Custom renderers must supply that same configuration and apply its policy
before output, reapplying after session startup. Finish configuration before first
policy access and never reuse it across requests. Do not configure fixed nonces.
The fallback is only a convenience for missing/null `$cspNonce`; templates rendered
exclusively through `SeablastView` need only `nonce="{$cspNonce}"`. Using
`$configuration->securityHeaders()->getNonce()` directly also reuses the same nonce,
but couples the template to the policy API.

Replace inline event/style attributes with listeners, style sheets, or validated DOM
style properties. Preserve SRI and `crossorigin="anonymous"` on CDN scripts. Bump
`SB_WEB_FORCE_ASSET_VERSION` when deploying changed assets. An application
`under-construction.html` override can use `nonce="__SEABLAST_CSP_NONCE__"` on inline
blocks; the controller replaces it with the escaped response nonce.

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

Remove stale front controllers, duplicate routers, and unused framework folders once the Seablast
route map is covered by tests.
