<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

/**
 * Shared helper for the widget renderers.
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
final class WidgetScripts
{
    /**
     * Collapses a multi-line JavaScript snippet to a single line.
     *
     * Trims each line, collapses internal whitespace, drops blank lines.
     * Safe only for simple scripts — no regex literals, no template strings.
     *
     * @param string $js Multi-line JS source.
     *
     * @return string Single-line output.
     */
    public static function minifyInlineJs(string $js): string
    {
        $out = [];
        foreach (\explode("\n", $js) as $line) {
            $line = \preg_replace('/\s+/', ' ', \trim($line));
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return \implode(' ', $out);
    }
}
