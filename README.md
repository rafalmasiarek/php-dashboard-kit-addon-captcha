# rafalmasiarek/dashboard-kit-addon-captcha

CAPTCHA addon for [`rafalmasiarek/dashboard-kit`](https://github.com/rafalmasiarek/php-dashboard-kit) — injects reCAPTCHA (v2 or v3), Cloudflare Turnstile, or hCaptcha into the login and register forms, verified through [`rafalmasiarek/captcha`](https://github.com/rafalmasiarek/php-captcha).

Supersedes [`rafalmasiarek/dashboard-kit-addon-recaptcha`](https://github.com/rafalmasiarek/php-dashboard-kit-addon-recaptcha): same wiring mechanism, generalized to any provider, with the login x-failed-attempts counter moved from a PHP session (trivially reset by an attacker who simply never retains a session cookie between attempts) to a DB-persisted, IP-keyed counter.

## Install

```bash
composer require rafalmasiarek/dashboard-kit-addon-captcha
```

## Usage

```php
use rafalmasiarek\DashboardKitCaptcha\CaptchaAddon;

CaptchaAddon::register($app, $container, [
    'provider'   => 'recaptcha_v2', // or 'recaptcha_v3', 'turnstile', 'hcaptcha'
    'site_key'   => $siteKey,
    'secret_key' => $secretKey,
    'login'      => ['mode' => 'x_failed', 'threshold' => 3],
    'register'   => true,
]);
```

Or via `app.config['captcha']` if already bound in the container — `register()`'s `$config` argument is merged over it.

### Config

| Key | Scope | Meaning |
|---|---|---|
| `provider` | both | `recaptcha_v2` \| `recaptcha_v3` \| `turnstile` \| `hcaptcha` |
| `site_key`, `secret_key` | both | Provider credentials |
| `login` | — | `false` (default) to disable, `true` to always show, or an array (below) |
| `login.mode` | login | `'always'` (default) or `'x_failed'` |
| `login.threshold` | login | Failed attempts before the widget appears, in `x_failed` mode (default 3) |
| `login.ttl_seconds` | login | How long a failed-attempt count survives without a fresh failure (default 900) |
| `login.min_score`, `login.action` | login | reCAPTCHA v3 / hCaptcha Enterprise only — no-op otherwise |
| `register` | — | `false` (default), `true`, or an array with `min_score`/`action` |

An invisible widget (`recaptcha_v3`) has no checkbox to conditionally show, so `x_failed` mode has no effect on it — it always runs.

## Why the failed-attempt counter moved to the database

The superseded addon counted failed logins in `$_SESSION`. That throttles a human who keeps retyping their password in the same browser tab — it does nothing against an automated attacker, who simply never sends (or keeps) a session cookie between requests; every attempt then starts the counter at zero. `FailedAttemptTracker` instead keys by the caller's real IP (resolved the same way `Captcha` itself resolves it — see below), with the PHP session id only as a last-resort fallback when no IP is available at all. The counter is a small table (`captcha_failed_attempts`), created automatically via dashboard-kit's own schema-sync mechanism, with a configurable TTL so a stale count doesn't linger indefinitely.

## IP resolution

If `rafalmasiarek\RealIpResolver::class` is bound in the container, it's bridged to `rafalmasiarek/captcha`'s `RemoteIpProviderInterface` via `RealIpResolverAdapter` and used both as `Captcha`'s default IP source and as the failed-attempt counter's key. Otherwise, both fall back to the same naive `$_SERVER['REMOTE_ADDR']` (via `rafalmasiarek/captcha`'s own `SystemRemoteIpProvider`) — the addon and the library always agree on which IP they're looking at.

## Pluggable failed-attempt storage

`FailedAttemptStoreInterface` (`increment()`/`count()`/`reset()`, TTL-aware) is the seam — `FailedAttemptTracker` (the default, via dashboard-kit's `Model`) works with whatever PDO driver the app already configured. A different storage backend (e.g. Redis, for a multi-server deployment without a shared database) can implement the same interface.

## Using CaptchaService in your own modules

`CaptchaAddon::register()` builds one `CaptchaService` from the app's single `['captcha']` config and binds it in the container — not just for login/register. Any module that wants its own CAPTCHA (a contact form, a newsletter signup, whatever) injects the same service instead of re-deriving provider/widget/site-key wiring itself:

```php
use rafalmasiarek\DashboardKitCaptcha\CaptchaService;

$captchaService = $container->get(CaptchaService::class);
```

Rendering (give each widget on the page its own `instanceId` — see `rafalmasiarek/captcha`'s own README for why):

```php
echo $captchaService->widget(instanceId: 'contactform');
echo $captchaService->scripts(action: 'contact_submit', instanceId: 'contactform');
```

Verifying — `captchaFor()` returns a real `rafalmasiarek\Captcha\Captcha`, configured with the addon's provider/site/secret but your own `$container` label (for log output) and, optionally, your own `$expectedAction`/`$minScore`:

```php
$captcha = $captchaService->captchaFor('contactform', expectedAction: 'contact_submit');
$token   = $request->getParsedBody()[$captchaService->tokenFieldName()] ?? '';
$result  = $captcha->verify($token, $remoteIp);
```

`CaptchaService` holds no provider-specific knowledge of its own — it's a thin binding of `rafalmasiarek/captcha`'s own `Captcha`/`HtmlHelper`/`CaptchaWidgetDescriptor` to whatever this addon resolved from config, so a module using it gets the exact same behavior (fail-closed score/action checks, the `captcha:error` client-side event, CSP `$nonce` support, ...) `rafalmasiarek/captcha ^2.0` itself provides — see its own README for the full client-side contract.

## License

BUSL-1.1
