# Seablast Security Configuration

Shared reference for application developers and framework maintainers. Configure
these policies in the application's configuration closure; see
[configuration layering](README.md#configuration). Maintainers should also preserve
the implementation invariants and regression coverage in [AGENTS.md](AGENTS.md).

- [Allowed request hosts](#allowed-request-hosts)
- [Reverse proxies and session cookies](#reverse-proxies-and-session-cookies)
- [Potentially stateless pages](#potentially-stateless-pages)
- [Forms, JSON APIs, and CSRF](#forms-json-apis-and-csrf)
- [Browser security headers](#browser-security-headers)
- [Browser error reporting](#browser-error-reporting)
- [Deployment safeguards](#deployment-safeguards)
- [Maintenance mode](#maintenance-mode)

## Allowed request hosts

Configure the exact public hostnames or IP addresses that may serve the application:

```php
$SBConfig->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, [
    'app.example.com',
    'localhost',
    '192.168.1.10',
    'localhost:8080',
    '[::1]:8080',
]);
```

Use only the entries needed in each environment. This prevents forged Host headers
from controlling `SB_APP_ROOT_ABSOLUTE_URL` and downstream links. Entries are hosts
with optional explicit ports, not URLs or CIDRs; wildcards are not supported.
DNS names are case-insensitive and IPv6 addresses are normalized. IPv6 must use
brackets. Ports must be 1-65535 without leading zeros and must match explicitly:
`localhost` does not allow `localhost:8080` or even an explicit `localhost:80`.
Use ASCII/Punycode DNS names without a trailing dot.

Validation runs before sessions, maintenance output, and Tracy development mode.
Missing or malformed request Host values and hosts outside a configured nonempty
list return a generic HTTP 400. Malformed configuration raises a configuration
exception. The normalized request authority is used for URLs; no canonical-origin
redirect is introduced. Forwarded host/port headers remain ignored; proxies must
preserve a permitted public Host. The web server must reject duplicate Host header
fields before PHP, since its server snapshot may already hide duplicates.

A missing or empty list accepts syntactically valid
hosts and emits only a static DEBUG reminder to define `SB_ALLOWED_HOSTS`. The
normal configured logger filters this reminder through `SB_LOGGING_LEVEL` (5 for
debug). No request Host value is included in the reminder. Fill the list to enable
Host allow-list protection; retain web-server host restrictions in the meantime.

## Reverse proxies and session cookies

Direct HTTP/HTTPS deployments need no proxy configuration. Behind a reverse proxy,
configure its exact IP addresses in the application configuration closure:

```php
$SBConfig
    ->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['192.0.2.10', '2001:db8::10'])
    ->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['198.51.100.7'])
    ->setString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_SAMESITE, 'Lax')
    ->setString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_DOMAIN, '');
```

Trusted proxies must overwrite `X-Forwarded-Proto` with one `http` or `https`
value describing the external connection and sanitize or correctly append
`X-Forwarded-For`. At the public edge, discard any client-supplied forwarding
values. For multiple proxies, preserve the verified external scheme and append
the actual upstream peer to the client IP chain. Keep `REMOTE_ADDR` as the
transport peer; do not independently rewrite it from headers in the web server.
List every trusted hop using exact IPv4/IPv6 addresses, without CIDRs or wildcards.

The shared request context ignores forwarding headers from untrusted peers.
For a trusted peer, missing or malformed required metadata returns HTTP 400
before creating a session. Client resolution walks the IP chain from right to
left and stops at the first untrusted address. That verified client controls
Tracy development mode and maintenance bypass only through an exact match in
`DEBUG_IP_LIST`. The default empty list denies both, including for localhost.
An unknown client or an all-trusted chain grants neither.
Only `X-Forwarded-For` and `X-Forwarded-Proto` are supported; no forwarding-header
family is automatically detected. Configure `SB_ALLOWED_HOSTS` as described above
to restrict the Host authority used for absolute URLs.

Direct local development and localhost maintenance bypass require explicit opt-in
in the application's local configuration closure:

```php
$SBConfig->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['127.0.0.1', '::1']);
```

Use these entries only for direct local requests. Behind a proxy, configure the
actual proxy addresses in `SB_TRUSTED_PROXIES` and the actual developer client
addresses in `DEBUG_IP_LIST`. Explicitly allowing an unconfigured shared proxy
address (including localhost) exposes diagnostics and maintenance access to all
clients using that proxy.

Session cookies default to host-only, HttpOnly, `SameSite=Lax`, and Secure on
verified HTTPS, including maintenance responses. Explicit `Strict` and `None`
are supported; `None` on HTTP is a configuration error. For intentional sharing
across trusted subdomains, configure a parent domain such as `example.com`;
it must match the request hostname on a domain-label boundary. A leading dot is
normalized away; IP addresses and single-label domains are rejected. Existing cookie
lifetime and logical path settings remain available. Paths containing control
characters or semicolons are rejected.

When upgrading, review applications that relied on the previous Domain cookie:
host-only cookies no longer provide subdomain sharing. Existing Domain cookies
may coexist with new host-only cookies until they expire; arrange an explicit
old-cookie expiry or session-name rotation if needed by your deployment.
Cookies created by optional authentication packages have their own policy.
If your application starts sessions before Seablast, apply this policy before
`session_start()` yourself; the controller validates configuration but cannot
retroactively secure an already-issued cookie.

PHP 7.2 remains supported through an isolated SameSite compatibility path.
The `TODO PHP-7.2` removal note in `SeablastSessionCookie` and `AGENTS.md` identifies
the code and legacy tests to simplify when PHP 7.2 support ends.

## Potentially stateless pages

Routes start a session by default (`'session' => 'required'`). Mark a page that can
render for an anonymous visitor without session state as potentially stateless:

```php
$SBConfig->setArrayArrayString(SeablastConstant::APP_MAPPING, '/', [
    'model' => '\App\Models\HomeModel',
    'template' => 'home',
    'session' => 'auto',
]);
```

On an `auto` route, cookie-free GET/HEAD requests skip session startup, identity
lookup, and CSRF token generation only when none of the following state triggers
apply and the model does not explicitly request state. The same route starts or resumes a session
for protected `roleIds`, other HTTP methods, an active/preset session, or a client
explicitly allowed Tracy development mode. Nonempty string cookie values trigger
startup for the PHP session cookie and the extra names in `SB_SESSION_AUTO_COOKIE_NAMES` (defaults:
`sbLanguage` and `sbRememberMe`). Other cookies, such as analytics, do not.
These cookies request state; normal authentication still validates the identity.
Remember-me recovery remains the authentication package's responsibility.
Unknown route session values are configuration errors.

Set `SB_SESSION_AUTO_COOKIE_NAMES` with `setArrayString()` to replace the extra
names or `addArrayString()` to append application preference/authentication cookies.
The PHP session cookie is always checked, including custom `session_name()` values.
A nonempty `sbLanguage` value triggers state even for the default language.

Models that discover a need for state call `$configuration->requireSession()` before
accessing session state or producing output. This applies the prepared cookie policy,
starts the session once, resolves identity, and refreshes `Superglobals->session`.
The wrapper supplies `csrfToken = null` if the request stays session-free, or the
usual `sb_json` token if a session is active. Keep form-rendering routes `required`,
or request the session explicitly before the model returns. A plain login link does
not require a session, but an embedded protected login form does.

For an interaction initiated from a session-free page, obtain a token when the user
acts, then include it in the existing protected request:

```javascript
import { getCsrfToken } from "/vendor/seablast/seablast/assets/scripts/seablast.js";

// Inside the click/submit handler; use the application's base path for imports.
const token = await getCsrfToken(API_BASE, csrfToken);
// Submit the existing JSON payload with csrfToken: token.
```

The bridge also exposes `window.getCsrfToken`. Concurrent token requests share one
request. The helper calls `GET /api/csrf` with `X-Seablast-CSRF: 1`, same-origin
credentials, and redirects disabled. The endpoint requires `X-Seablast-CSRF: 1`;
if `Sec-Fetch-Site` is present, only `same-origin` or `none` is accepted. Missing
Fetch Metadata is tolerated for clients that do not send it. Header failures return
`403`; other methods return `405`. The endpoint always
uses `Cache-Control: no-store`. Do not grant cross-origin access to this endpoint.
Model responses may set `noStore = true` to force this cache policy over the global
override. Never cache personalized or token-bearing responses at the proxy/CDN.

Before opting in a route, adapt application forms and language-selector overrides
to obtain the token on interaction rather than capturing a missing token at page load.
Use an i18n release/override that obtains the token before its language POST and
marks the `/api/language` lookup route `auto`; verify the installed package supports
this contract. Do not fetch a token on page load, which would cancel the saving. `ErrorLogger` keeps
messages local when given a
missing token; update its `csrfToken` after an interaction if server logging is needed.
Bump the application's asset version when deploying updated scripts.

The default `/error` route uses `auto`, so missing routes can also avoid sessions
for anonymous GET/HEAD requests. Audit application error-page overrides for session
access and forms, and use `required` when needed.

This is a partial mitigation for SEC-015: unmarked routes, maintenance output,
deliberate interactions, and forged/stale session cookies can still allocate
sessions. Keep reliable storage cleanup and traffic limits.
Applications starting sessions before Seablast cannot benefit from this optimization.

## Forms, JSON APIs, and CSRF

Protected JSON calls and form submissions must supply and validate `csrfToken`
using the `sb_json` token ID. Session-free pages receive null and must explicitly
[obtain state/token on interaction](#potentially-stateless-pages). `/api/csrf` is the
explicit bootstrap exception; do not grant it cross-origin access.

`GenericRestApiJsonModel` requires `REQUEST_METHOD`, a JSON content type
(`application/json` or a media type ending in `+json`), an object-shaped body of at
most 1 MiB, and a valid token. It returns 400 for invalid JSON/shape, 401 for missing
or invalid CSRF, 413 for excessive size, and 415 for unsupported/missing content type.
The `JSON_INPUT` test override replaces the real HTTP body and bypasses its
Content-Type and Content-Length checks. The injected string still receives the
same byte-size, JSON-shape, and CSRF validation. Keep this override out of production
request configuration. Subclasses may lower `JSON_INPUT_MAX_BYTES`, as `/api/error` does.
Subclasses call `parent::knowledge()` and stop on `httpCode >= 400`; enforce allowed
HTTP methods separately. Multipart and ordinary form models must validate CSRF
manually; they do not use this JSON parser.

JSON output always uses `application/json; charset=utf-8`, including in Tracy
development mode.
When upgrading, remove the obsolete `FLAG_DEBUG_JSON` / `SB:debug:json` configuration: JSON-as-HTML diagnostics have been removed without a compatibility alias.

## Browser security headers

Dynamic responses default to same-origin framing (`frame-ancestors 'self'` and
`X-Frame-Options: SAMEORIGIN`), `X-Content-Type-Options: nosniff`,
`Referrer-Policy: strict-origin-when-cross-origin`, and `Cache-Control: no-store`.
This includes anonymous HTML containing CSRF tokens, JSON, redirects, maintenance,
and invalid-request responses. Static assets remain under web-server cache policy.
HSTS remains a deployment choice: enable it only after reviewing HTTPS and subdomains.

Content restrictions start in **report-only** mode; they do not yet block scripts
or styles. Framing restrictions are always enforced separately. Bundled defaults
allow same-origin resources, nonce-authorized inline scripts/styles, the exact
bundled jQuery and WebHotkeys CDN paths, Google Fonts CSS at `/css2`, and fonts from
`fonts.gstatic.com`. They prohibit objects and limit base URLs to the same origin,
without `unsafe-inline` or `unsafe-eval`. Font file paths vary with font/browser,
so that font origin is allowed rather than a fixed file URL.

Configure the policy inside your application's configuration closure:

```php
$SBConfig
    ->setString(SeablastConstant::SB_CSP_MODE, 'report-only')
    ->setArrayString(SeablastConstant::SB_FRAME_ANCESTORS, ["'self'"])
    ->setString(SeablastConstant::SB_REFERRER_POLICY, 'strict-origin-when-cross-origin')
    ->setString(SeablastConstant::SB_CACHE_CONTROL, 'no-store')
    ->setArrayArrayString(SeablastConstant::SB_CSP_DIRECTIVES, 'connect-src', [
        "'self'", 'https://api.example.com',
    ])
    ->setString(SeablastConstant::SB_CSP_REPORT_URI, '');
```

`SB_CSP_DIRECTIVES` overrides individual default directives with lists of separate
source tokens; other defaults remain. Nonces are generated automatically for
`script-src` and `style-src` unless that directive is exactly `["'none'"]`.
Do not configure fixed nonces. `frame-ancestors` and reporting are managed by their
separate settings. Invalid modes, control characters, and directive separators
inside tokens are rejected before headers are applied.

`SB_CSP_MODE` accepts `report-only`, `enforce`, or `off`; `off` only disables content
restrictions, not framing, MIME, referrer, or cache protection. Framing accepts
`["'none'"]` to prohibit embedding, `["'self'"]` for same-origin embedding, or an
explicit list of HTTP/HTTPS origins (optionally including `'self'`). The first two
also emit `DENY` or `SAMEORIGIN`; explicit external origins omit X-Frame-Options.

Set `SB_CSP_REPORT_URI` to an existing local path such as `/csp-report` or an HTTPS
URL with a path to collect reports through `report-uri`. No collector is bundled;
protect and rate-limit your collector and consider sensitive URLs in reports.
Without a destination, review browser-console violations. Review application
templates, optional auth/i18n integrations, third-party assets, and Tracy diagnostics
before switching to `enforce`. Report-only rollout does not constitute full SEC-009
closure. A content policy does not replace escaping or Host validation.

The view supplies a framework-owned `cspNonce`. Application templates should use
`<script nonce="{$cspNonce}">` and `<style nonce="{$cspNonce}">` for intentional inline
blocks. Move inline event handlers to listeners and style attributes to style sheets
or validated DOM style properties. Bundled admin colors use validated
`data-admin-color` attributes. Upgrade overridden admin/layout templates and bump
your application's `SB_WEB_FORCE_ASSET_VERSION` when deploying changed assets.

`SeablastConfiguration::securityHeaders()` lazily creates one immutable
`SeablastSecurityHeaders` instance per request. Finish configuration before first
use; do not reuse that configuration across requests in long-running workers.
Custom bootstraps should call `securityHeaders()->apply()` before request parsing
and output, and again after any session startup that sets cache headers. The core
controller and view do this automatically. `getNonce()` supports custom renderers;
`getHeaders()` exposes the validated header map. Renderers outside `SeablastView`
must apply the same policy whose nonce they render. The bundled maintenance page
keeps its CSS inline; the controller replaces `__SEABLAST_CSP_NONCE__` with the
HTML-escaped response nonce. Application-provided maintenance HTML supports the
same replacement after reading the HTML file: use `<style nonce="__SEABLAST_CSP_NONCE__">`
for inline styles or the same nonce attribute on intentional inline scripts.

The framework owns these header names in PHP and preserves unrelated headers and
cookies. Web-server/proxy CSP headers can coexist as additional restrictions;
policies are intersected, not relaxed by the application. Coordinate those layers
instead of expecting an application setting to override a stricter server policy.
An explicit `SB_CACHE_CONTROL` override affects every dynamic response in that
configuration: never enable public caching for personalized or token-bearing pages.
PHP may retain more restrictive session-generated `Expires`/`Pragma` headers.

## Browser error reporting

`BannerManager.addBanner(message, type)` and `changeBanner(banner, message, type)`
display messages as plain text. HTML markup is displayed literally, including
when a banner shows an error returned by a server.

`ErrorLogger` is exported by `assets/scripts/seablast.js`. Create it with
`new ErrorLogger(csrfToken, apiBase)`; `log(message, severity)` posts browser
diagnostics to `/api/error` through jQuery Ajax (default severity: `error`).
Reporting remains enabled by default and accepts anonymous visitors with a valid
session-bound CSRF token. To disable ingestion in your application configuration:

```php
$SBConfig->flag->deactivate(SeablastConstant::FLAG_CLIENT_ERROR_LOGGING);
```

Messages must be nonempty strings up to 4,096 UTF-8 bytes; optional `page` strings
are limited to 2,048 bytes. Optional `order` must be an integer from 1 to
2,147,483,647. Severity is case-insensitive: `DEBUG`, `INFO`, `WARNING`, `ERROR`
(default), `EXCEPTION`, or `CRITICAL`. Browser-supplied `EXCEPTION` and `CRITICAL`
are recorded at their matching server severity. Reports are escaped single-line JSON
records prefixed by `client_error`; do not include secrets in diagnostic messages.
The request body is limited to 16 KiB and the escaped log record to 8 KiB.

Default limits count POST attempts, including invalid input: 20 per client per
minute, 100 per application per minute, and 1,000 per application per hour.
Configure positive integer limits with `SB_CLIENT_ERROR_CLIENT_PER_MINUTE`,
`SB_CLIENT_ERROR_APP_PER_MINUTE`, and `SB_CLIENT_ERROR_APP_PER_HOUR`. Client
addresses use the framework's trusted request context; changing sessions does not
reset allowances. Visitors sharing an address share its allowance.

Counters are shared by PHP workers on one server. Fixed time windows can admit a
burst at a window boundary; multiple servers have independent allowances. The
bounded state file defaults to the system temporary directory, named using a hash
of the canonical application directory. Set `SB_CLIENT_ERROR_RATE_LIMIT_FILE`
to an absolute local file path to override it. Its parent directory must already
exist, be writable by PHP, and be protected from other users. Keep it outside the
public web tree; POSIX files must have private permissions, and Windows directories
must have suitable ACLs. Use a local filesystem and PHP deployment with reliable
file locking. Do not delete the state file during operation, as this resets limits.

Responses use `200` for accepted reports, `400` for malformed data, `401` for
invalid CSRF, `403` when disabled, `405` for non-POST methods, `413` for size limits,
`415` for unsupported content types, `429` with `Retry-After` for rate limiting,
and `503` when counter storage is unavailable or corrupt. Corrupt files require
operator repair while ingestion is disabled; they are never silently reset.
`ErrorLogger` pauses sending until `Retry-After` expires and stops sending from
that instance after `403`, keeping console diagnostics without repeated banners.
Reports during that pause are not queued or retried. Other failures retain their
existing browser handling.

## Deployment safeguards

- Route application URLs to `vendor/seablast/seablast/index.php`; keep the required
  front controller and browser assets reachable.
- Block direct web access to `conf/`, `src/`, `views/`, `tests/`, `cache/`, `log/`,
  private storage, Markdown, configuration, shell/CI files, Composer/PHPUnit files,
  dotfiles, and local metadata.
- Treat request data as untrusted. Validate identifiers and resource authorization,
  use prepared statements, and do not derive redirects directly from request input.
- For private downloads, reject failed `realpath()` resolution and enforce containment
  within the resolved storage root on a directory boundary before streaming.
  See the [upload/download integration pattern](AGENT-STARTER-KIT.md#uploads-and-downloads).
- Never log secrets or complete model payloads. Protect logs and diagnostic output
  with appropriate access and retention controls; level 5 model/view Tracy dumps
  can contain tokens and other sensitive data.
- Keep web-server/proxy host and traffic restrictions, session storage cleanup, and
  private filesystem permissions in place. Conditional sessions and client-error
  limits do not protect every route or replace deployment-level traffic controls.

## Maintenance mode

Inactive `FLAG_WEB_RUNNING` serves `APP_DIR/under-construction.html` (or the bundled
fallback) and exits. Only verified clients in `DEBUG_IP_LIST` bypass maintenance.
This happens before route selection and retains session startup even for routes
configured as `auto`. The file is read as static HTML, never executed as PHP; use
the [maintenance nonce placeholder](#browser-security-headers) for inline styles/scripts.
