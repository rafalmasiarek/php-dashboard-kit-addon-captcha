<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

/**
 * Renders the client-side widget (HTML + script tags) for one CAPTCHA
 * provider variant. Deliberately separate from rafalmasiarek/captcha's
 * CaptchaProviderInterface — that package verifies server-side only and
 * never touches widget rendering, since script URLs/data-* attributes/
 * callback wiring differ per provider *and* per consuming framework.
 *
 * reCAPTCHA v2, Turnstile, and hCaptcha all deliberately mirror reCAPTCHA
 * v2's widget API (data-callback/data-expired-callback, an auto-injected
 * hidden response field) for drop-in compatibility — see VisibleWidgetRenderer,
 * the one implementation all three share. reCAPTCHA v3 has no visible
 * widget at all — see InvisibleWidgetRenderer.
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
interface CaptchaWidgetRendererInterface
{
    /**
     * Whether this variant has no visible widget to show/hide — an
     * invisible variant always runs regardless of the x-failed-attempts
     * threshold, since there is nothing to conditionally display.
     *
     * @return bool
     */
    public function isInvisible(): bool;

    /**
     * @param string $siteKey
     * @param string $action
     *
     * @return string HTML to inject into the form_fields slot.
     */
    public function formFieldsMarkup(string $siteKey, string $action): string;

    /**
     * @param string $siteKey
     * @param string $action
     *
     * @return string HTML to inject into the scripts slot.
     */
    public function scriptsMarkup(string $siteKey, string $action): string;
}
