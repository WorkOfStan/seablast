# AGENTS.md

<a id="what-seablast-is"></a>

Instructions for agents maintaining `seablast/seablast`. For application work, use
[AGENT-STARTER-KIT.md](AGENT-STARTER-KIT.md).

## Companion Documentation

- [README.md](README.md): public installation, configuration, and API usage.
- [SECURITY.md](SECURITY.md): shared security configuration and deployment requirements.
- [AGENT-STARTER-KIT.md](AGENT-STARTER-KIT.md): application implementation and migration tasks.
- [src/README.md](src/README.md): canonical architecture and request lifecycle, with diagram.
- This file: maintenance rules, invariants, pitfalls, and verification entry points.

## Development Conventions

- Preserve comments; remove only solved TODOs, or translate comments to English.
- Sort PHP imports, properties, and methods alphabetically, ignoring case. Keep PHP
  lines within 120 characters. PHPCS runs in CI; no local PHPCS run is required.
- Preserve supported PHP/Latte versions in [composer.json](composer.json); PHP 7.2
  compatibility is still required. Leave Python `main()` at the bottom.
- Reuse `SeablastConstant`, `AuthConstant`, and `I18nConstant` for existing settings.
  New application configuration keys must not use the framework's `SB` prefix.
- Keep unrelated worktree changes. Review changes for security implications.
- Add an English, present-tense verb-led entry at the end of the relevant
  `CHANGELOG.md` block. Update documentation at its canonical location and check
  the other guides for affected summaries; do not copy full reference sections.

## Repository Structure

| Path | Responsibility |
| --- | --- |
| `index.php`, `defineAppDir.php` | Bootstrap and application-root resolution |
| `src/` | MVC runtime, configuration, request policies, database adapters |
| `src/Apis/`, `src/Admin/` | JSON endpoints and bundled administration |
| `conf/` | Defaults, analysis/test configuration, Phinx example |
| `views/`, `assets/` | Bundled Latte templates and browser assets |
| `tests/` | PHPUnit, real HTTP fixtures, and JavaScript regressions |
| `.github/`, `blast.sh` | CI and development/deployment helpers |
| `cache/`, `log/` | Local runtime output; installed apps use their own directories |

## Bootstrap and Request Lifecycle

Follow the [architecture overview](src/README.md). Preserve ordering in
[index.php](index.php) and [SeablastController](src/SeablastController.php):

- Start Tracy in production mode; apply security headers before request validation.
- Resolve one immutable request-context snapshot before choosing development mode.
  Pass that context to the controller; two-argument construction resolves it internally.
- Validate cookie policy even for active sessions. Apply environment/cookie settings
  before startup, including maintenance responses; bind logging without needing a session.
- Resolve the route before its session decision, then authenticate/authorize.
  Maintenance is an earlier exit and retains session startup.
- Synchronize session state before model token generation. Reapply headers after
  session startup and before output. Preserve SQL/HTTP panels across response modes.

## Configuration Layering

[SeablastSetup](src/SeablastSetup.php) applies defaults, optional auth, optional i18n,
application config, then local config. Files that exist must return callables.
Preserve this precedence and the replacement/append semantics documented in
[Configuration](README.md#configuration). Configuration and its cached security
policy belong to one request; finish configuration before first policy access.

## Runtime Values Added by Core

Do not preseed framework-owned request state: `SB_APP_ROOT_ABSOLUTE_URL`,
`SB_GET_ARGUMENT_ID`, `SB_GET_ARGUMENT_CODE`, `ERROR_HTTP_CODE`, `ERROR_MESSAGE`,
`USER_ID`, `USER_ROLE_ID`, `USER_GROUPS`, and `FLAG_USER_IS_AUTHENTICATED`.

## Route Contract

The [public route table](README.md#routing) is authoritative usage documentation.
Keep exact path matching, base-path removal, trailing-slash normalization, `/error`
fallback (also for missing API paths), and the default `required` session policy.
The `id` key casts a scalar to int; it is not strict identifier validation.
The `code` key rejects empty values, controls, quotes, backslashes, semicolons,
`#`, and SQL comment markers. Models still validate domain-specific values.
New mapping keys require public documentation, application guidance, and tests.

## Model Contract for Applications

Preserve the two-argument constructor and `knowledge(): stdClass` contract in
[SeablastModelInterface](src/SeablastModelInterface.php). A route without a model
starts with an empty object. The wrapper overwrites `csrfToken` with an active
session's `sb_json` token or null; never allocate state just to render a token.
See [Model](README.md#model) for response properties and status rules.

## Generic JSON API Contract

[GenericRestApiJsonModel](src/Apis/GenericRestApiJsonModel.php) requires
`REQUEST_METHOD`, checks real-input content type before reading, bounds real and
injected `JSON_INPUT` to 1 MiB before decoding, accepts only an object, and validates
`sb_json` CSRF. Nonscalar tokens must yield controlled 401 responses.
`JSON_INPUT` replaces the real body and bypasses its Content-Type and Content-Length
checks; the injected string still receives byte-size, shape, and CSRF checks.
Subclasses must stop after `parent::knowledge()` reports an error; see
[CSRF and JSON requirements](SECURITY.md#forms-json-apis-and-csrf) for HTTP codes and manual form validation.

## View and Template Contract

[SeablastView](src/SeablastView.php) adds `configuration` and overwrites `cspNonce`
with the request policy's nonce. JSON is always `application/json; charset=utf-8`,
including in development. HTML errors select `error`; redirects select `redirection`
and allow only 301/302/303/307/308 (default 301). Do not combine response modes.

Template lookup tries `../../../<LATTE_TEMPLATE>/<template>.latte`, then
`<LATTE_TEMPLATE>/<template>.latte`, relative to the working directory. Test custom
bootstrap locations and inheritance; do not silently assume absolute app paths.
Latte `{try}{include ...}` fallbacks can hide application template compilation errors.

### BlueprintWeb Expectations

Applications must define `SB_WEB_FORCE_ASSET_VERSION`; defaults do not supply it.
Direct layout renders also need `$configuration`, `$csrfToken` (possibly null),
and `SB_APP_ROOT_ABSOLUTE_URL`, which the normal controller populates.
Preserve the jQuery/bridge loading order and optional i18n includes. The JavaScript
`flags` array exposes only explicitly selected, enabled flags, not every flag.

### Template CDN Security

External scripts require SRI and `crossorigin="anonymous"`. Compute hashes from
raw response bytes at the exact URL. The Google CDN jQuery URL in the layout has
its own hash; a hash for `code.jquery.com` is not interchangeable.

## Trusted Request Context and Session Policy

Use [SeablastRequestContext](src/SeablastRequestContext.php), never independently
interpret proxy headers for authorization or diagnostics. Public accessors are
`isHttps()`, `getClientIp(): ?string`, `isDebugAllowed()`, `getHost()`,
`getHostname()`, and `hasAllowedHosts()`. Host authority includes the port;
hostname excludes port and IPv6 brackets.

- Catch `InvalidRequestContextException` as generic HTTP 400 before output/session
  creation; do not reflect input. Configuration errors remain configuration exceptions.
- Require `HTTP_HOST`; neither `SERVER_NAME` nor forwarded hosts are fallbacks.
  Preserve rejection of ambiguous numeric IPs, user-info, lists, controls, invalid
  ports and host syntax. See [host policy](SECURITY.md#allowed-request-hosts).
- Exact normalized IPs only for proxy/debug lists. Trust only X-Forwarded-Proto and
  X-Forwarded-For from configured peers; external scheme overrides backend TLS.
  Reject controls, scheme lists, malformed/empty chains, proto over 32 bytes, and
  chains over 8192 bytes. An all-trusted chain yields null and no debug access.
- Empty `DEBUG_IP_LIST` denies diagnostics and maintenance bypass, even on localhost.
  Empty host allow-lists accept valid hosts and emit a static DEBUG reminder through
  the configured logger (or current Tracy logger for active sessions), before maintenance.
  Preserve severity filtering and never include the submitted Host in that reminder.
- `SeablastSessionCookie::apply()` must precede startup; active sessions are not
  restarted and issued cookies cannot be retroactively secured. Host-only defaults
  explicitly clear inherited PHP Domain. Keep the logical path free of SameSite suffixes.

See [proxy/cookie deployment requirements](SECURITY.md#reverse-proxies-and-session-cookies)
for trust boundaries, domain migration, and authentication-package cookie scope.

Todo PHP-7.2: When the minimum becomes PHP 7.3, remove the legacy path-suffix branch
in `SeablastSessionCookie::apply()` and legacy-version coverage in
`RequestContextHttpTest`; retain native cookie, maintenance, and regeneration tests.

### Conditional Session Startup

Preserve all [auto-session triggers](SECURITY.md#potentially-stateless-pages), not
just anonymous versus authenticated behavior. `Superglobals::cookieNames` retains
names with nonempty string values, never cookie secrets; its optional fifth
constructor argument supplies cookies and otherwise snapshots native cookies.
The controller's `setSessionStarter()` callback starts once, reapplies headers,
dispatches Tracy, synchronizes the snapshot, and resolves identity once.
`getIdentity()` also requests state; custom bootstraps must tolerate absent `$_SESSION`. Raw `$_SESSION` writes do not start persistence.

`requireSession()` needs the controller's bound starter (or an already active
session); it is not a standalone session initializer for configuration closures or
isolated model tests. Share the validated request context between custom-bootstrap
debug selection and the controller, rather than resolving a second snapshot.

`/api/csrf` is an explicit GET bootstrap with `X-Seablast-CSRF: 1` and Fetch Metadata
checks, no prior token, and forced no-store. Never grant it permissive CORS.
The browser helper shares concurrent requests, rejects cross-origin bases/redirects,
and permits retry after failure. Token-free `ErrorLogger` diagnostics stay local.
Audit error-page and i18n overrides when adopting `auto`; do not weaken strict mode
or CSRF to avoid session allocation. Early application sessions defeat this optimization.

## Authentication and Authorization

The controller creates `SB_IDENTITY_MANAGER` with `$configuration->mysqli()` and
optionally calls `setTablePrefix()`/`setCookiePath()`. Preserve interface behavior,
identity-once resolution, runtime user fields, and user injection into DB logging.
Unauthenticated protected routes use `APP_MAPPING_401` when configured, otherwise
401; authenticated users with an unlisted role receive 403.

## Database Contract

[SeablastConfiguration](src/SeablastConfiguration.php) lazily exposes `mysqli()` and
`pdo()` using `conf/phinx.local.php`. Defaults select `development`; an absent
`SB_PHINX_ENVIRONMENT` falls back to Phinx `default_environment`.
Preserve write-query logging, SQL panel collection, and `setUser()` propagation to
existing and future adapters. `dbmsTablePrefix()` initializes PDO if needed.
Keep deprecated `dbms()`/`dbmsStatus()` compatibility unless deliberately breaking it.
Bootstrap must annotate PDO failures only if its adapter already exists; never
initialize it to report an exception from independent application persistence.

## Admin Contract

<a id="current-admin-behavior"></a>
<a id="color-field-security"></a>
<a id="important-admin-caveats"></a>

Use [Administration](README.md#administration) for permissions, query parameters,
color validation, and limitations. Preserve table and column checks together.
`AdminHelper` hardcodes roles 1/2: table permissions merge, but admin column lists
replace editor lists for the same table/access type. Updates require a nonzero
integer-cast `id` and target a literal `id` column; arbitrary primary keys are unsupported.
Row listing also indexes results by `id`; the selected column lists must include it.
Insert/delete flags do not implement those workflows. Color values and filter
options must remain safe in both Latte output and DOM construction.

## Under Construction Mode

Inactive `FLAG_WEB_RUNNING` serves application `under-construction.html` or the
bundled fallback and exits. Only a verified debug client bypasses it. Keep nonce
placeholder replacement on both files; they are read as HTML, not executed.

## Browser Security Header Contract

Preserve one immutable policy per request, early-400 coverage, and reapplication
after sessions and before views. Keep unrelated headers/cookies/status unchanged.
`noStore = true` overrides global cache configuration. Nonce retrieval alone does
not apply headers; custom renderers must use the same policy for headers and HTML.
See [public policy settings](SECURITY.md#browser-security-headers) and
[template integration](AGENT-STARTER-KIT.md#browser-security-header-contract).
Report-only content restrictions do not close SEC-009; unconfigured host allow-lists
and partial route adoption likewise leave SEC-003/SEC-015 incompletely mitigated.

## Debugging and Logging

Use `Tracy\Debugger::log($message, Tracy\ILogger::ERROR)` (or another explicit
severity); the controller connects SeablastLogger through `PsrToTracyLoggerAdapter`.
Do not use `error_log()` where this logger is available or log secrets/full model
payloads. Model/view Tracy dumps require `SB_LOGGING_LEVEL >= 5`; diagnostic access
and retention still need protection. Banner creation and updates must render text,
not HTML. Public browser usage and limits: [reporting](SECURITY.md#browser-error-reporting).

### Client error ingestion invariants

`ApiErrorModel::beforeInput()` checks enablement, method, and rate limits before
body reading or CSRF. Keep its 16 KiB cap and disabled input diagnostics: rejection
must not recursively log/dump untrusted input. Only validated fields enter the
escaped, single-line `client_error` record (8 KiB including prefix). Preserve
`EXCEPTION`/`CRITICAL` as their distinct `ILogger` severities for all users.

`ClientErrorRateLimiter` hashes verified client IPs, sharing one unknown bucket.
Read/check/update/write under one nonblocking exclusive lock; bound state to 64 KiB.
Prune expired minute clients together; never evict active clients or reset allowances
on clock rollback. Contention/exhaustion returns 429, corrupt/unavailable storage 503.
Never unlink/replace the locked file during operation. These are per-server ingestion
limits, not general traffic or session-allocation protection.

## Current Realities and Non-Goals

Friendly-URL database resolution and redirector logic are unfinished in the
controller. Bundled templates are defaults, with some Czech strings and opinionated
navigation; do not describe them as a complete application or admin CRUD system.

## Change Checklist for Maintainers

Review each affected boundary: bootstrap/context, configuration/defaults, route/auth
fallbacks, model/view responses, template overrides/assets, lazy DB/diagnostics,
admin permissions, and documentation/tests. Keep app-facing summaries aligned with
the canonical public contract and the architecture overview when behavior changes.

## Testing and Local Development

- Install dependencies in PowerShell with `$env:COMPOSER_CACHE_DIR = "$PWD\.composer-cache"`
  then `php "C:\ProgramData\ComposerSetup\bin\composer.phar" install`.
  Do not modify Composer itself or run self-update.
- PHPUnit: `php vendor/bin/phpunit --bootstrap vendor/autoload.php --do-not-cache-result`.
  DB tests require `conf/phinx.local.php` and the `testing` environment. See
  [conf/phinx.dist.php](conf/phinx.dist.php) and [CI](.github/workflows/polish-the-code.yml).
- On Windows run helpers through Git Bash, never directly from PowerShell:
  `& "C:\Program Files\Git\bin\bash.exe" -lc "./blast.sh phpstan"`, then
  `& "C:\Program Files\Git\bin\bash.exe" -lc "./blast.sh phpstan-remove"` for cleanup.
- Run affected `node tests/*.test.cjs` scripts individually; rendered-template
  regressions also require PHP and Composer dependencies. CI runs all scripts.
- For Python tests use `python -m pytest -q -p no:cacheprovider --basetemp=.tmp/pytest`.
  Do not inspect/lint/recurse into `.tmp`, `.pytest-tmp`, `.pytest_cache`, `.venv`, or
  build artifacts. If temp cleanup fails, remove only `.tmp/pytest` and rerun serially.
- For documentation-only edits, inspect the diff, check links/anchors, and run
  `git diff --check`. State exactly which checks ran and any blockers.

Regression entry points: `RequestContextHttpTest` for real bootstrap, cookie,
session, CSRF, header and error flows; `SeablastRequestContextTest`/
`SeablastRequestHostTest` for trust parsing; `SecurityHeadersTest`/
`TemplateSecurityTest` for policy/templates; `GenericRestApiJsonModelTest`,
`ApiErrorModelTest`, `ClientErrorRateLimiterTest` for input/limits/concurrency;
`AdminColorSecurityTest` plus `admin-color`, `banner-manager`, `csrf-token`, and
`error-logger` JavaScript tests for browser contracts.

## Documentation Policy

Keep unique details in the document for their audience. Update README usage,
SECURITY policy details, starter-kit actions, maintainer invariants, architecture
when affected, release notes, and behavior tests without duplicating full explanations.
