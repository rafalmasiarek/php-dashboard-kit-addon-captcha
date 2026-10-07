<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use rafalmasiarek\Captcha\Captcha;
use rafalmasiarek\Captcha\CaptchaProviderInterface;
use rafalmasiarek\Captcha\Provider\HCaptchaProvider;
use rafalmasiarek\Captcha\Provider\RecaptchaProvider;
use rafalmasiarek\Captcha\Provider\TurnstileProvider;
use rafalmasiarek\Captcha\SystemRemoteIpProvider;
use rafalmasiarek\DashboardKit\Extension\FormSlotRegistry;
use rafalmasiarek\DashboardKit\Hook\HookRegistry;
use rafalmasiarek\DashboardKit\Schema\ModuleSchemaBuilder;
use rafalmasiarek\DashboardKit\Schema\SchemaInspector;
use rafalmasiarek\DashboardKit\Schema\SchemaStateManager;
use rafalmasiarek\DnsResolver\SystemDnsResolver;
use rafalmasiarek\HttpClient\Http\CurlHttpClient;
use rafalmasiarek\HttpClient\Http\HttpClientInterface;
use rafalmasiarek\RealIpResolver;
use Slim\App;

/**
 * Wires CAPTCHA (reCAPTCHA v2/v3, Cloudflare Turnstile, or hCaptcha — via
 * rafalmasiarek/captcha) into dashboard-kit login and register forms.
 *
 * Supersedes rafalmasiarek/dashboard-kit-addon-recaptcha: same wiring
 * mechanism (FormSlotRegistry slots, auth.before_login/before_register hook
 * chaining, HookRegistry login/login_failed events), generalized to any
 * provider and backed by a DB-persisted, IP-keyed failed-attempt counter
 * instead of a PHP-session counter — a session-only counter is trivially
 * reset by an attacker who simply never retains a session cookie between
 * attempts, which defeats it as an anti-automation measure (it only ever
 * throttled a human repeatedly mistyping their password in one browser tab).
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
final class CaptchaAddon
{
    /** @var int Default failed-attempt counter lifetime, in seconds. */
    private const DEFAULT_TTL_SECONDS = 900;

    /**
     * Register the CAPTCHA addon with the given application and container.
     *
     * @param App $app Slim application instance.
     * @param ContainerInterface $container PHP-DI container.
     * @param array<string, mixed> $config Addon configuration: 'provider'
     *        ('recaptcha_v2'|'recaptcha_v3'|'turnstile'|'hcaptcha'), 'site_key',
     *        'secret_key', 'login', 'register'. login/register may be
     *        `true`/`false` or an array with 'mode'/'threshold'/'ttl_seconds'
     *        (x-failed-attempts, login only), 'min_score' and 'action'
     *        (reCAPTCHA v3 / hCaptcha Enterprise only, both optional).
     *
     * @return void
     */
    public static function register(App $app, ContainerInterface $container, array $config = []): void
    {
        if (!\class_exists(\rafalmasiarek\DashboardKit\Dashboard::class)) {
            throw new \LogicException(
                static::class . ' is a dashboard-kit addon and requires rafalmasiarek/dashboard-kit. '
                . 'Run: composer require rafalmasiarek/dashboard-kit'
            );
        }

        $appConfig = $container->has('app.config') ? (array) $container->get('app.config') : [];
        $config    = \array_merge((array) ($appConfig['captcha'] ?? []), $config);

        $providerKey = (string) ($config['provider']   ?? 'recaptcha_v2');
        $siteKey     = (string) ($config['site_key']   ?? '');
        $secretKey   = (string) ($config['secret_key'] ?? '');

        if ($siteKey === '' || $secretKey === '') {
            throw new \InvalidArgumentException('CaptchaAddon requires both site_key and secret_key to be set.');
        }

        $http = $container->has(HttpClientInterface::class)
            ? $container->get(HttpClientInterface::class)
            : new CurlHttpClient(new SystemDnsResolver());

        [$provider, $widget] = self::buildProvider($providerKey, $secretKey, $siteKey);

        $ipAdapter = $container->has(RealIpResolver::class)
            ? new RealIpResolverAdapter($container->get(RealIpResolver::class))
            : null;

        $loginConfig    = $config['login']    ?? false;
        $registerConfig = $config['register'] ?? false;

        if ($loginConfig !== false) {
            $loginConfig = $loginConfig === true ? [] : (array) $loginConfig;
            self::syncSchema($container, $appConfig);
            self::wireLogin($container, $provider, $widget, $http, $ipAdapter, $siteKey, $loginConfig);
        }

        if ($registerConfig !== false) {
            $registerConfig = $registerConfig === true ? [] : (array) $registerConfig;
            self::wireRegister($container, $provider, $widget, $http, $ipAdapter, $siteKey, $registerConfig);
        }
    }

    /**
     * Builds the verifier provider + widget renderer pair for a provider key.
     *
     * @param string $providerKey 'recaptcha_v2'|'recaptcha_v3'|'turnstile'|'hcaptcha'.
     * @param string $secretKey
     * @param string $siteKey
     *
     * @throws \InvalidArgumentException When $providerKey isn't recognized.
     *
     * @return array{0: CaptchaProviderInterface, 1: CaptchaWidgetRendererInterface}
     */
    private static function buildProvider(string $providerKey, string $secretKey, string $siteKey): array
    {
        return match ($providerKey) {
            'recaptcha_v2' => [
                new RecaptchaProvider($secretKey),
                new VisibleWidgetRenderer('g-recaptcha', 'https://www.google.com/recaptcha/api.js'),
            ],
            'recaptcha_v3' => [
                new RecaptchaProvider($secretKey),
                new InvisibleWidgetRenderer(),
            ],
            'turnstile' => [
                new TurnstileProvider($secretKey),
                new VisibleWidgetRenderer('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js'),
            ],
            'hcaptcha' => [
                new HCaptchaProvider($secretKey, $siteKey),
                new VisibleWidgetRenderer('h-captcha', 'https://js.hcaptcha.com/1/api.js'),
            ],
            default => throw new \InvalidArgumentException("CaptchaAddon: unknown provider \"{$providerKey}\"."),
        };
    }

    /**
     * Creates/updates the captcha_failed_attempts table via ModuleSchemaBuilder,
     * the same mechanism and config['schema_defaults'] convention core modules use.
     *
     * @param ContainerInterface $container
     * @param array<string, mixed> $appConfig
     *
     * @return void
     */
    private static function syncSchema(ContainerInterface $container, array $appConfig): void
    {
        $builder = new ModuleSchemaBuilder(
            (bool) ($appConfig['schema_defaults']['timestamps']   ?? false),
            (bool) ($appConfig['schema_defaults']['soft_deletes'] ?? false),
        );

        $manager = new SchemaStateManager(
            $container->get('pdo.raw'),
            $builder,
            new SchemaInspector(),
        );

        $manager->sync(['captcha_failed_attempts' => ['schema' => [
            'captcha_failed_attempts' => [
                'columns' => [
                    'id'          => ['type' => 'int', 'unsigned' => true, 'auto_increment' => true, 'null' => false],
                    'attempt_key' => ['type' => 'varchar(255)', 'null' => false],
                    'count'       => ['type' => 'int', 'unsigned' => true, 'null' => false, 'default' => 0],
                    'expires_at'  => ['type' => 'datetime', 'null' => false],
                ],
                'primary' => 'id',
                'indexes' => [
                    'uniq_attempt_key' => ['columns' => ['attempt_key'], 'unique' => true],
                ],
            ],
        ]]]);
    }

    /**
     * Resolves the throttling key for the current request: the same IP
     * Captcha itself would resolve to (real IP when available, otherwise the
     * naive $_SERVER['REMOTE_ADDR']), falling back to the PHP session id only
     * when no IP information exists at all (e.g. a non-HTTP CLI context).
     *
     * @param RealIpResolverAdapter|null $ipAdapter
     *
     * @return string
     */
    private static function attemptKey(?RealIpResolverAdapter $ipAdapter): string
    {
        $ip = $ipAdapter?->getRemoteIp() ?? (new SystemRemoteIpProvider())->getRemoteIp();
        if ($ip !== null) {
            return 'ip:' . $ip;
        }

        return 'session:' . (\session_id() ?: 'unknown');
    }

    /**
     * Wire CAPTCHA into the login form.
     *
     * In x_failed mode, the widget is shown/verified only after $threshold
     * consecutive failed attempts within $ttlSeconds. An invisible widget
     * (reCAPTCHA v3) has no checkbox to conditionally show, so it always runs.
     *
     * @param ContainerInterface $container
     * @param CaptchaProviderInterface $provider
     * @param CaptchaWidgetRendererInterface $widget
     * @param HttpClientInterface $http
     * @param RealIpResolverAdapter|null $ipAdapter
     * @param string $siteKey
     * @param array<string, mixed> $loginConfig 'mode', 'threshold', 'ttl_seconds', 'min_score', 'action'.
     *
     * @return void
     */
    private static function wireLogin(
        ContainerInterface $container,
        CaptchaProviderInterface $provider,
        CaptchaWidgetRendererInterface $widget,
        HttpClientInterface $http,
        ?RealIpResolverAdapter $ipAdapter,
        string $siteKey,
        array $loginConfig,
    ): void {
        $mode       = (string) ($loginConfig['mode'] ?? 'always');
        $threshold  = (int) ($loginConfig['threshold']  ?? 3);
        $ttlSeconds = (int) ($loginConfig['ttl_seconds'] ?? self::DEFAULT_TTL_SECONDS);
        $always     = $widget->isInvisible() || $mode !== 'x_failed';
        $minScore   = isset($loginConfig['min_score']) ? (float) $loginConfig['min_score'] : null;
        $action     = (string) ($loginConfig['action'] ?? 'login');

        $tracker = new FailedAttemptTracker();
        $key     = self::attemptKey($ipAdapter);

        $captcha = new Captcha(
            $provider,
            defaultIpProvider: $ipAdapter,
            http: $http,
            minScore: $minScore,
            expectedAction: $minScore !== null ? $action : null,
            container: 'login',
        );

        $formSlots = $container->get(FormSlotRegistry::class);

        $formSlots->register('login', 'form_fields', static function () use ($widget, $siteKey, $action, $always, $threshold, $tracker, $key): string {
            if (!$always && $tracker->count($key) < $threshold) {
                return '';
            }
            return $widget->formFieldsMarkup($siteKey, $action);
        });

        $formSlots->register('login', 'scripts', static function () use ($widget, $siteKey, $action, $always, $threshold, $tracker, $key): string {
            if (!$always && $tracker->count($key) < $threshold) {
                return '';
            }
            return $widget->scriptsMarkup($siteKey, $action);
        });

        $hooks = $container->get(HookRegistry::class);

        // Increment counter on each failed login attempt.
        $hooks->on('login_failed', static function () use ($tracker, $key, $ttlSeconds): void {
            $tracker->increment($key, $ttlSeconds);
        });

        // Reset counter on successful login.
        $hooks->on('login', static function () use ($tracker, $key): void {
            $tracker->reset($key);
        });

        // Wrap before_login: chain existing callable, then verify CAPTCHA when required.
        // PHP-DI treats plain closures set via set() as factory definitions, so the callable
        // is wrapped in an outer factory that returns the actual hook callable as a value.
        $existing = $container->get('auth.before_login');
        $container->set('auth.before_login', static fn() => static function (ServerRequestInterface $request) use (
            $existing,
            $captcha,
            $tracker,
            $key,
            $always,
            $threshold,
        ): ?string {
            if ($existing !== null) {
                $blockReason = $existing($request);
                if ($blockReason !== null) {
                    return $blockReason;
                }
            }

            $required = $always || $tracker->count($key) >= $threshold;
            if (!$required) {
                return null;
            }

            $body  = (array) $request->getParsedBody();
            $token = (string) ($body[$captcha->provider()->defaultTokenFieldName()] ?? '');

            if ($token === '' || !$captcha->verify($token)->success) {
                return 'Please complete the CAPTCHA verification.';
            }

            return null;
        });
    }

    /**
     * Wire CAPTCHA into the register form. Always shown — there is no
     * x-failed-attempts mode for registration.
     *
     * @param ContainerInterface $container
     * @param CaptchaProviderInterface $provider
     * @param CaptchaWidgetRendererInterface $widget
     * @param HttpClientInterface $http
     * @param RealIpResolverAdapter|null $ipAdapter
     * @param string $siteKey
     * @param array<string, mixed> $registerConfig 'min_score', 'action'.
     *
     * @return void
     */
    private static function wireRegister(
        ContainerInterface $container,
        CaptchaProviderInterface $provider,
        CaptchaWidgetRendererInterface $widget,
        HttpClientInterface $http,
        ?RealIpResolverAdapter $ipAdapter,
        string $siteKey,
        array $registerConfig,
    ): void {
        $minScore = isset($registerConfig['min_score']) ? (float) $registerConfig['min_score'] : null;
        $action   = (string) ($registerConfig['action'] ?? 'register');

        $captcha = new Captcha(
            $provider,
            defaultIpProvider: $ipAdapter,
            http: $http,
            minScore: $minScore,
            expectedAction: $minScore !== null ? $action : null,
            container: 'register',
        );

        $formSlots = $container->get(FormSlotRegistry::class);
        $formSlots->register('register', 'form_fields', $widget->formFieldsMarkup($siteKey, $action));
        $formSlots->register('register', 'scripts', $widget->scriptsMarkup($siteKey, $action));

        $existing = $container->get('auth.before_register');
        $container->set('auth.before_register', static fn() => static function (ServerRequestInterface $request) use (
            $existing,
            $captcha,
        ): ?string {
            if ($existing !== null) {
                $blockReason = $existing($request);
                if ($blockReason !== null) {
                    return $blockReason;
                }
            }

            $body  = (array) $request->getParsedBody();
            $token = (string) ($body[$captcha->provider()->defaultTokenFieldName()] ?? '');

            if ($token === '' || !$captcha->verify($token)->success) {
                return 'Please complete the CAPTCHA verification.';
            }

            return null;
        });
    }
}
