<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

/**
 * Widget renderer for reCAPTCHA v3 — invisible, no checkbox. Intercepts the
 * form's submit event, fetches a token via grecaptcha.execute(), fills a
 * hidden field, then resubmits via the raw DOM submit() (which, unlike
 * requestSubmit(), does not re-fire the 'submit' event — no loop guard needed).
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
final class InvisibleWidgetRenderer implements CaptchaWidgetRendererInterface
{
    /**
     * {@inheritDoc}
     */
    public function isInvisible(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function formFieldsMarkup(string $siteKey, string $action): string
    {
        return '<input type="hidden" name="g-recaptcha-response" class="js-dk-captcha-v3">';
    }

    /**
     * {@inheritDoc}
     */
    public function scriptsMarkup(string $siteKey, string $action): string
    {
        $siteKeyJs = \json_encode($siteKey, \JSON_UNESCAPED_SLASHES);
        $actionJs  = \json_encode($action, \JSON_UNESCAPED_SLASHES);

        $script = WidgetScripts::minifyInlineJs(<<<JS
        (function () {
            var el   = document.querySelector('.js-dk-captcha-v3');
            var form = el ? el.closest('form') : null;
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute({$siteKeyJs}, { action: {$actionJs} }).then(function (token) {
                        el.value = token;
                        form.submit();
                    });
                });
            });
        }());
        JS);

        return '<script src="https://www.google.com/recaptcha/api.js?render=' . \rawurlencode($siteKey) . '"></script>'
            . '<script>' . $script . '</script>';
    }
}
