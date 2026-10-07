<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

/**
 * Tracks failed attempts (e.g. logins) under an arbitrary key, with TTL-based
 * expiry. Used by CaptchaAddon to decide whether the x-failed-attempts
 * threshold has been reached — deliberately not keyed by PHP session alone,
 * since an attacker who never retains a session cookie trivially resets a
 * session-only counter on every attempt. The default implementation
 * (FailedAttemptTracker) keys by the caller's real IP instead, falling back
 * to the session only when no IP is available.
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
interface FailedAttemptStoreInterface
{
    /**
     * Increments the counter for $key and returns the updated value. A
     * counter that has not been touched for $ttlSeconds is treated as
     * expired and restarts from 1 rather than continuing to accumulate.
     *
     * @param string $key
     * @param int $ttlSeconds
     *
     * @return int
     */
    public function increment(string $key, int $ttlSeconds): int;

    /**
     * @param string $key
     *
     * @return int Current count, 0 when absent or expired.
     */
    public function count(string $key): int;

    /**
     * @param string $key
     *
     * @return void
     */
    public function reset(string $key): void;
}
