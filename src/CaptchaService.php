<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

use rafalmasiarek\Captcha\Captcha;
use rafalmasiarek\Captcha\CaptchaProviderInterface;
use rafalmasiarek\Captcha\Helpers\HtmlHelper;
use rafalmasiarek\Captcha\Provider\CaptchaWidgetDescriptor;
use rafalmasiarek\Captcha\RemoteIpProviderInterface;
use rafalmasiarek\HttpClient\Http\HttpClientInterface;

/**
 * Reusable CAPTCHA surface for any module — not just login/register.
 * CaptchaAddon::register() builds one instance from the app's single
 * ['captcha'] config and binds it in the container; any module injects it
 * to render a widget and verify a token, without re-deriving provider/
 * widget/siteKey wiring itself.
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
final class CaptchaService
{
    /**
     * @param CaptchaProviderInterface $provider
     * @param CaptchaWidgetDescriptor $widgetDescriptor
     * @param string $siteKey
     * @param HttpClientInterface $http
     * @param RemoteIpProviderInterface|null $ipProvider
     */
    public function __construct(
        private readonly CaptchaProviderInterface $provider,
        private readonly CaptchaWidgetDescriptor $widgetDescriptor,
        private readonly string $siteKey,
        private readonly HttpClientInterface $http,
        private readonly ?RemoteIpProviderInterface $ipProvider = null,
    ) {
    }

    /**
     * A Captcha configured for one usage context. $container labels this
     * instance in log output (e.g. "contactform", "newsletter") — mirrors
     * how wireLogin()/wireRegister() already label theirs "login"/"register".
     *
     * @param string $container
     * @param string|null $expectedAction
     * @param float|null $minScore
     *
     * @return Captcha
     */
    public function captchaFor(string $container, ?string $expectedAction = null, ?float $minScore = null): Captcha
    {
        return new Captcha(
            $this->provider,
            defaultIpProvider: $this->ipProvider,
            http: $this->http,
            minScore: $minScore,
            expectedAction: $expectedAction,
            container: $container,
        );
    }

    /**
     * @param string $instanceId Give each widget on a page a distinct value.
     * @param string|null $successCallback
     * @param string|null $expiredCallback
     * @param string $validationMessage
     *
     * @return string
     */
    public function widget(
        string $instanceId = 'default',
        ?string $successCallback = null,
        ?string $expiredCallback = null,
        string $validationMessage = 'Please complete the CAPTCHA.',
    ): string {
        return HtmlHelper::widget($this->widgetDescriptor, $this->siteKey, $successCallback, $expiredCallback, $instanceId, $validationMessage);
    }

    /**
     * @param string $action
     * @param string $instanceId Must match widget()'s $instanceId for this same widget.
     * @param string|null $successCallback
     * @param string|null $expiredCallback
     * @param string|null $nonce CSP nonce, when needed.
     *
     * @return string
     */
    public function scripts(
        string $action = '',
        string $instanceId = 'default',
        ?string $successCallback = null,
        ?string $expiredCallback = null,
        ?string $nonce = null,
    ): string {
        return HtmlHelper::scripts($this->widgetDescriptor, $this->siteKey, $action, $successCallback, $expiredCallback, $instanceId, $nonce);
    }

    /**
     * @return CaptchaWidgetDescriptor
     */
    public function widgetDescriptor(): CaptchaWidgetDescriptor
    {
        return $this->widgetDescriptor;
    }

    /**
     * @return string
     */
    public function siteKey(): string
    {
        return $this->siteKey;
    }

    /**
     * The form field name the token arrives under — read this instead of
     * hardcoding 'g-recaptcha-response'/'cf-turnstile-response'/etc.
     *
     * @return string
     */
    public function tokenFieldName(): string
    {
        return $this->widgetDescriptor->tokenFieldName;
    }
}
