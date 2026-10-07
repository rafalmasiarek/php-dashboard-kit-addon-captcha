<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

/**
 * Widget renderer for reCAPTCHA v2, Cloudflare Turnstile, and hCaptcha — all
 * three deliberately mirror the same visible-checkbox widget API (a div with
 * data-sitekey/data-callback/data-expired-callback that auto-injects its own
 * hidden response field once solved), differing only in CSS class name and
 * script URL, so one implementation covers all three.
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
final class VisibleWidgetRenderer implements CaptchaWidgetRendererInterface
{
    /**
     * @param string $widgetClass CSS class the provider's script looks for, e.g.
     *                            "g-recaptcha", "cf-turnstile", "h-captcha".
     * @param string $scriptUrl Absolute URL of the provider's widget script.
     */
    public function __construct(
        private readonly string $widgetClass,
        private readonly string $scriptUrl,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function isInvisible(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function formFieldsMarkup(string $siteKey, string $action): string
    {
        return '<div class="' . \htmlspecialchars($this->widgetClass, \ENT_QUOTES) . ' mb-3 js-dk-captcha"'
            . ' data-sitekey="' . \htmlspecialchars($siteKey, \ENT_QUOTES) . '"'
            . ' data-callback="dashboardKitCaptchaSuccess"'
            . ' data-expired-callback="dashboardKitCaptchaExpired"></div>';
    }

    /**
     * {@inheritDoc}
     */
    public function scriptsMarkup(string $siteKey, string $action): string
    {
        $script = WidgetScripts::minifyInlineJs(<<<'JS'
        (function () {
            var el     = document.querySelector('.js-dk-captcha');
            var form   = el ? el.closest('form') : null;
            var btn    = form ? form.querySelector('[type="submit"]') : null;
            var solved = false;

            if (btn) btn.disabled = true;

            var hint = null;
            if (el) {
                hint = document.createElement('div');
                hint.className = 'text-danger small mt-1';
                hint.style.display = 'none';
                hint.textContent = 'Please complete the CAPTCHA.';
                el.insertAdjacentElement('afterend', hint);
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (!solved) {
                        e.preventDefault();
                        if (el)   el.style.outline = '2px solid #dc3545';
                        if (hint) hint.style.display = '';
                    }
                });
            }

            window.dashboardKitCaptchaSuccess = function () {
                solved = true;
                if (btn)  btn.disabled = false;
                if (el)   el.style.outline = '';
                if (hint) hint.style.display = 'none';
            };
            window.dashboardKitCaptchaExpired = function () {
                solved = false;
                if (btn)  btn.disabled = true;
                if (el)   el.style.outline = '2px solid #dc3545';
                if (hint) hint.style.display = '';
            };
        }());
        JS);

        return '<script src="' . \htmlspecialchars($this->scriptUrl, \ENT_QUOTES) . '" async defer></script>'
            . '<script>' . $script . '</script>';
    }
}
