<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

use rafalmasiarek\Captcha\RemoteIpProviderInterface;
use rafalmasiarek\RealIpResolver;

/**
 * Bridges rafalmasiarek/real-ip-resolver's RealIpResolver (getIp(): string,
 * never null) to rafalmasiarek/captcha's RemoteIpProviderInterface
 * (getRemoteIp(): ?string) — the two can't satisfy each other directly
 * since RealIpResolver doesn't declare the interface itself (and shouldn't:
 * it's a generic library with no reason to know about CAPTCHA concepts).
 *
 * Used both as Captcha's $defaultIpProvider and as the key source for
 * FailedAttemptTracker's per-IP throttling, so both see the exact same
 * resolved IP.
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
final class RealIpResolverAdapter implements RemoteIpProviderInterface
{
    /**
     * @param RealIpResolver $resolver
     */
    public function __construct(
        private readonly RealIpResolver $resolver,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getRemoteIp(): ?string
    {
        $ip = $this->resolver->getIp();
        return $ip !== '' ? $ip : null;
    }
}
