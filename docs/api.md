## Index

### Classes

- [`CaptchaAddon`](#captchaaddon) — `rafalmasiarek\DashboardKitCaptcha`
- [`CaptchaService`](#captchaservice) — `rafalmasiarek\DashboardKitCaptcha`
- [`FailedAttemptTracker`](#failedattempttracker) — `rafalmasiarek\DashboardKitCaptcha`
- [`RealIpResolverAdapter`](#realipresolveradapter) — `rafalmasiarek\DashboardKitCaptcha`

### Interfaces

- [`FailedAttemptStoreInterface`](#failedattemptstoreinterface) — `rafalmasiarek\DashboardKitCaptcha`

## `CaptchaAddon`

*final class* — `rafalmasiarek\DashboardKitCaptcha\CaptchaAddon` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaAddon.php#L35)

Wires CAPTCHA (reCAPTCHA v2/v3, Cloudflare Turnstile, or hCaptcha — via rafalmasiarek/captcha) into dashboard-kit login and register forms, and binds CaptchaService in the container so any OTHER module can render a widget and verify a token too, without re-deriving the same wiring.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`register()`](#captchaaddon-register) | public static | `void` | Register the CAPTCHA addon with the given application and container. |
| [`buildProvider()`](#captchaaddon-buildprovider) | private static | `array{CaptchaProviderInterface, CaptchaWidgetDescriptor}` | Builds the verifier provider + widget descriptor pair for a provider key. |
| [`syncSchema()`](#captchaaddon-syncschema) | private static | `void` | Creates/updates the captcha_failed_attempts table via ModuleSchemaBuilder, the same mechanism and config['schema_defaults'] convention core modules use. |
| [`attemptKey()`](#captchaaddon-attemptkey) | private static | `string` | Resolves the throttling key for the current request: the same IP Captcha itself would resolve to (real IP when available, otherwise the naive $_SERVER['REMOTE_ADDR']), falling back to the PHP session id only when no IP information exists at all (e.g. a non-HTTP CLI context). |
| [`wireLogin()`](#captchaaddon-wirelogin) | private static | `void` | Wire CAPTCHA into the login form. In x_failed mode, the widget is shown/verified only after $threshold consecutive failed attempts within $ttlSeconds. An invisible widget (reCAPTCHA v3) has no checkbox to conditionally show, so it always runs. |
| [`wireRegister()`](#captchaaddon-wireregister) | private static | `void` | Wire CAPTCHA into the register form. Always shown — there is no x-failed-attempts mode for registration. |

<details>
<summary><code>register()</code></summary>

#### `CaptchaAddon register()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaAddon.php#L57)

```php
public static function register(App $app, ContainerInterface $container, array $config): void
```

Register the CAPTCHA addon with the given application and container.

| Param | Type | Description |
|---|---|---|
| `$app` | `App` | Slim application instance. |
| `$container` | `ContainerInterface` | PHP-DI container. |
| `$config` | `array<string, mixed>` | Addon configuration: 'provider' — either a built-in shorthand string ('recaptcha_v2'\|'recaptcha_v3'\|'turnstile'\| 'hcaptcha', using 'secret_key') or your own CaptchaProviderInterface instance (in which case 'widget' must be a matching CaptchaWidgetDescriptor) — plus 'site_key', 'login', 'register'. login/register may be `true`/`false` or an array with 'mode'/'threshold'/'ttl_seconds' (x-failed-attempts, login only), 'min_score' and 'action' (reCAPTCHA v3 / hCaptcha Enterprise only, both optional, built-in providers only). |

</details>

<details>
<summary><code>buildProvider()</code></summary>

#### `CaptchaAddon buildProvider()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaAddon.php#L138)

```php
private static function buildProvider(string $providerKey, string $secretKey, string $siteKey): array
```

Builds the verifier provider + widget descriptor pair for a provider key.

| Param | Type | Description |
|---|---|---|
| `$providerKey` | `string` | 'recaptcha_v2'\|'recaptcha_v3'\|'turnstile'\|'hcaptcha'. |
| `$secretKey` | `string` | — |
| `$siteKey` | `string` | — |

**Returns:** `array{CaptchaProviderInterface, CaptchaWidgetDescriptor}`

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When $providerKey isn't recognized. |

</details>

<details>
<summary><code>syncSchema()</code></summary>

#### `CaptchaAddon syncSchema()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaAddon.php#L158)

```php
private static function syncSchema(ContainerInterface $container, array $appConfig): void
```

Creates/updates the captcha_failed_attempts table via ModuleSchemaBuilder, the same mechanism and config['schema_defaults'] convention core modules use.

| Param | Type | Description |
|---|---|---|
| `$container` | `ContainerInterface` | — |
| `$appConfig` | `array<string, mixed>` | — |

</details>

<details>
<summary><code>attemptKey()</code></summary>

#### `CaptchaAddon attemptKey()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaAddon.php#L197)

```php
private static function attemptKey(?RealIpResolverAdapter $ipAdapter): string
```

Resolves the throttling key for the current request: the same IP Captcha itself would resolve to (real IP when available, otherwise the naive $_SERVER['REMOTE_ADDR']), falling back to the PHP session id only when no IP information exists at all (e.g. a non-HTTP CLI context).

| Param | Type | Description |
|---|---|---|
| `$ipAdapter` | `RealIpResolverAdapter\|null` | — |

**Returns:** `string`

</details>

<details>
<summary><code>wireLogin()</code></summary>

#### `CaptchaAddon wireLogin()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaAddon.php#L222)

```php
private static function wireLogin(ContainerInterface $container, CaptchaService $service, CaptchaWidgetDescriptor $widget, ?RealIpResolverAdapter $ipAdapter, array $loginConfig): void
```

Wire CAPTCHA into the login form. In x_failed mode, the widget is shown/verified only after $threshold consecutive failed attempts within $ttlSeconds. An invisible widget (reCAPTCHA v3) has no checkbox to conditionally show, so it always runs.

| Param | Type | Description |
|---|---|---|
| `$container` | `ContainerInterface` | — |
| `$service` | [`CaptchaService`](#captchaservice) | — |
| `$widget` | `CaptchaWidgetDescriptor` | — |
| `$ipAdapter` | `RealIpResolverAdapter\|null` | — |
| `$loginConfig` | `array<string, mixed>` | 'mode', 'threshold', 'ttl_seconds', 'min_score', 'action'. |

</details>

<details>
<summary><code>wireRegister()</code></summary>

#### `CaptchaAddon wireRegister()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaAddon.php#L314)

```php
private static function wireRegister(ContainerInterface $container, CaptchaService $service, array $registerConfig): void
```

Wire CAPTCHA into the register form. Always shown — there is no x-failed-attempts mode for registration.

| Param | Type | Description |
|---|---|---|
| `$container` | `ContainerInterface` | — |
| `$service` | [`CaptchaService`](#captchaservice) | — |
| `$registerConfig` | `array<string, mixed>` | 'min_score', 'action'. |

</details>

## `CaptchaService`

*final class* — `rafalmasiarek\DashboardKitCaptcha\CaptchaService` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L23)

Reusable CAPTCHA surface for any module — not just login/register. CaptchaAddon::register() builds one instance from the app's single ['captcha'] config and binds it in the container; any module injects it to render a widget and verify a token, without re-deriving provider/ widget/siteKey wiring itself.

**Depends on:** `CaptchaProviderInterface`, `CaptchaWidgetDescriptor`, `HttpClientInterface`, `RemoteIpProviderInterface`

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#captchaservice-__construct) | public | — | — |
| [`captchaFor()`](#captchaservice-captchafor) | public | `Captcha` | A Captcha configured for one usage context. $container labels this instance in log output (e.g. "contactform", "newsletter") — mirrors how wireLogin()/wireRegister() already label theirs "login"/"register". |
| [`widget()`](#captchaservice-widget) | public | `string` | — |
| [`scripts()`](#captchaservice-scripts) | public | `string` | — |
| [`widgetDescriptor()`](#captchaservice-widgetdescriptor) | public | `CaptchaWidgetDescriptor` | — |
| [`siteKey()`](#captchaservice-sitekey) | public | `string` | — |
| [`tokenFieldName()`](#captchaservice-tokenfieldname) | public | `string` | The form field name the token arrives under — read this instead of hardcoding 'g-recaptcha-response'/'cf-turnstile-response'/etc. |

<details>
<summary><code>__construct()</code></summary>

#### `CaptchaService __construct()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L32)

```php
public function __construct(CaptchaProviderInterface $provider, CaptchaWidgetDescriptor $widgetDescriptor, string $siteKey, HttpClientInterface $http, ?RemoteIpProviderInterface $ipProvider)
```

| Param | Type | Description |
|---|---|---|
| `$provider` | `CaptchaProviderInterface` | — |
| `$widgetDescriptor` | `CaptchaWidgetDescriptor` | — |
| `$siteKey` | `string` | — |
| `$http` | `HttpClientInterface` | — |
| `$ipProvider` | `RemoteIpProviderInterface\|null` | — |

</details>

<details>
<summary><code>captchaFor()</code></summary>

#### `CaptchaService captchaFor()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L52)

```php
public function captchaFor(string $container, ?string $expectedAction, ?float $minScore): Captcha
```

A Captcha configured for one usage context. $container labels this instance in log output (e.g. "contactform", "newsletter") — mirrors how wireLogin()/wireRegister() already label theirs "login"/"register".

| Param | Type | Description |
|---|---|---|
| `$container` | `string` | — |
| `$expectedAction` | `string\|null` | — |
| `$minScore` | `float\|null` | — |

**Returns:** `Captcha`

</details>

<details>
<summary><code>widget()</code></summary>

#### `CaptchaService widget()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L72)

```php
public function widget(string $instanceId, ?string $successCallback, ?string $expiredCallback, string $validationMessage): string
```

| Param | Type | Description |
|---|---|---|
| `$instanceId` | `string` | Give each widget on a page a distinct value. |
| `$successCallback` | `string\|null` | — |
| `$expiredCallback` | `string\|null` | — |
| `$validationMessage` | `string` | — |

**Returns:** `string`

</details>

<details>
<summary><code>scripts()</code></summary>

#### `CaptchaService scripts()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L90)

```php
public function scripts(string $action, string $instanceId, ?string $successCallback, ?string $expiredCallback, ?string $nonce): string
```

| Param | Type | Description |
|---|---|---|
| `$action` | `string` | — |
| `$instanceId` | `string` | Must match widget()'s $instanceId for this same widget. |
| `$successCallback` | `string\|null` | — |
| `$expiredCallback` | `string\|null` | — |
| `$nonce` | `string\|null` | CSP nonce, when needed. |

**Returns:** `string`

</details>

<details>
<summary><code>widgetDescriptor()</code></summary>

#### `CaptchaService widgetDescriptor()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L103)

```php
public function widgetDescriptor(): CaptchaWidgetDescriptor
```

**Returns:** `CaptchaWidgetDescriptor`

</details>

<details>
<summary><code>siteKey()</code></summary>

#### `CaptchaService siteKey()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L111)

```php
public function siteKey(): string
```

**Returns:** `string`

</details>

<details>
<summary><code>tokenFieldName()</code></summary>

#### `CaptchaService tokenFieldName()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/CaptchaService.php#L122)

```php
public function tokenFieldName(): string
```

The form field name the token arrives under — read this instead of hardcoding 'g-recaptcha-response'/'cf-turnstile-response'/etc.

**Returns:** `string`

</details>

## `FailedAttemptStoreInterface`

*interface* — `rafalmasiarek\DashboardKitCaptcha\FailedAttemptStoreInterface` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptStoreInterface.php#L18)

Tracks failed attempts (e.g. logins) under an arbitrary key, with TTL-based expiry. Used by CaptchaAddon to decide whether the x-failed-attempts threshold has been reached — deliberately not keyed by PHP session alone, since an attacker who never retains a session cookie trivially resets a session-only counter on every attempt. The default implementation (FailedAttemptTracker) keys by the caller's real IP instead, falling back to the session only when no IP is available.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`increment()`](#failedattemptstoreinterface-increment) | public | `int` | Increments the counter for $key and returns the updated value. A counter that has not been touched for $ttlSeconds is treated as expired and restarts from 1 rather than continuing to accumulate. |
| [`count()`](#failedattemptstoreinterface-count) | public | `int` | — |
| [`reset()`](#failedattemptstoreinterface-reset) | public | `void` | — |

<details>
<summary><code>increment()</code></summary>

#### `FailedAttemptStoreInterface increment()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptStoreInterface.php#L30)

```php
public function increment(string $key, int $ttlSeconds): int
```

Increments the counter for $key and returns the updated value. A counter that has not been touched for $ttlSeconds is treated as expired and restarts from 1 rather than continuing to accumulate.

| Param | Type | Description |
|---|---|---|
| `$key` | `string` | — |
| `$ttlSeconds` | `int` | — |

**Returns:** `int`

</details>

<details>
<summary><code>count()</code></summary>

#### `FailedAttemptStoreInterface count()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptStoreInterface.php#L37)

```php
public function count(string $key): int
```

| Param | Type | Description |
|---|---|---|
| `$key` | `string` | — |

**Returns:** `int` — Current count, 0 when absent or expired.

</details>

<details>
<summary><code>reset()</code></summary>

#### `FailedAttemptStoreInterface reset()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptStoreInterface.php#L44)

```php
public function reset(string $key): void
```

| Param | Type | Description |
|---|---|---|
| `$key` | `string` | — |

</details>

## `FailedAttemptTracker`

*final class* — `rafalmasiarek\DashboardKitCaptcha\FailedAttemptTracker` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptTracker.php#L25)

Database-backed FailedAttemptStoreInterface, via dashboard-kit's Model query builder (Model::on()) — works across whatever PDO driver the app already configured (MySQL/SQLite/Postgres), no addon-specific connection setup needed; CaptchaAddon::syncSchema() creates the table. Two queries (SELECT then INSERT/UPDATE), not one atomic statement — expressing "increment, or reset to 1 if expired" atomically would need per-engine raw SQL (MySQL IF()/SQLite CASE WHEN) that Model::upsert()'s generic VALUES()/excluded. column mapping can't express. The resulting race window (two concurrent attempts from the exact same key reading the same stale count) under-counts by at most one — acceptable for login-attempt throttling, not a correctness-critical counter.

implements [`FailedAttemptStoreInterface`](#failedattemptstoreinterface)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`increment()`](#failedattempttracker-increment) | public | `int` | Increments the counter for $key and returns the updated value. A counter that has not been touched for $ttlSeconds is treated as expired and restarts from 1 rather than continuing to accumulate. |
| [`count()`](#failedattempttracker-count) | public | `int` | — |
| [`reset()`](#failedattempttracker-reset) | public | `void` | — |
| [`isExpired()`](#failedattempttracker-isexpired) | private static | `bool` | — |

<details>
<summary><code>increment()</code></summary>

#### `FailedAttemptTracker increment()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptTracker.php#L33)

```php
public function increment(string $key, int $ttlSeconds): int
```

Increments the counter for $key and returns the updated value. A counter that has not been touched for $ttlSeconds is treated as expired and restarts from 1 rather than continuing to accumulate.

| Param | Type | Description |
|---|---|---|
| `$key` | `string` | — |
| `$ttlSeconds` | `int` | — |

**Returns:** `int`

</details>

<details>
<summary><code>count()</code></summary>

#### `FailedAttemptTracker count()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptTracker.php#L66)

```php
public function count(string $key): int
```

| Param | Type | Description |
|---|---|---|
| `$key` | `string` | — |

**Returns:** `int` — Current count, 0 when absent or expired.

</details>

<details>
<summary><code>reset()</code></summary>

#### `FailedAttemptTracker reset()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptTracker.php#L79)

```php
public function reset(string $key): void
```

| Param | Type | Description |
|---|---|---|
| `$key` | `string` | — |

</details>

<details>
<summary><code>isExpired()</code></summary>

#### `FailedAttemptTracker isExpired()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/FailedAttemptTracker.php#L90)

```php
private static function isExpired(array $row, \DateTimeImmutable $now): bool
```

| Param | Type | Description |
|---|---|---|
| `$row` | `array<string, mixed>` | — |
| `$now` | `\DateTimeImmutable` | — |

**Returns:** `bool`

</details>

## `RealIpResolverAdapter`

*final class* — `rafalmasiarek\DashboardKitCaptcha\RealIpResolverAdapter` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/RealIpResolverAdapter.php#L23)

Bridges rafalmasiarek/real-ip-resolver's RealIpResolver (getIp(): string, never null) to rafalmasiarek/captcha's RemoteIpProviderInterface (getRemoteIp(): ?string) — the two can't satisfy each other directly since RealIpResolver doesn't declare the interface itself (and shouldn't: it's a generic library with no reason to know about CAPTCHA concepts). Used both as Captcha's $defaultIpProvider and as the key source for FailedAttemptTracker's per-IP throttling, so both see the exact same resolved IP.

implements `RemoteIpProviderInterface`

**Depends on:** `RealIpResolver`

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#realipresolveradapter-__construct) | public | — | — |
| [`getRemoteIp()`](#realipresolveradapter-getremoteip) | public | `?string` | {@inheritDoc} |

<details>
<summary><code>__construct()</code></summary>

#### `RealIpResolverAdapter __construct()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/RealIpResolverAdapter.php#L28)

```php
public function __construct(RealIpResolver $resolver)
```

| Param | Type | Description |
|---|---|---|
| `$resolver` | `RealIpResolver` | — |

</details>

<details>
<summary><code>getRemoteIp()</code></summary>

#### `RealIpResolverAdapter getRemoteIp()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-captcha/blob/main/src/RealIpResolverAdapter.php#L36)

```php
public function getRemoteIp(): ?string
```

{@inheritDoc}

**Returns:** `?string`

</details>

