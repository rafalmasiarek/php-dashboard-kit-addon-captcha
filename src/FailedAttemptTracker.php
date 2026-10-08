<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCaptcha;

use rafalmasiarek\DashboardKit\Model\Model;

/**
 * Database-backed FailedAttemptStoreInterface, via dashboard-kit's Model
 * query builder (Model::on()) — works across whatever PDO driver the app
 * already configured (MySQL/SQLite/Postgres), no addon-specific connection
 * setup needed; CaptchaAddon::syncSchema() creates the table.
 *
 * Two queries (SELECT then INSERT/UPDATE), not one atomic statement —
 * expressing "increment, or reset to 1 if expired" atomically would need
 * per-engine raw SQL (MySQL IF()/SQLite CASE WHEN) that Model::upsert()'s
 * generic VALUES()/excluded. column mapping can't express. The resulting
 * race window (two concurrent attempts from the exact same key reading the
 * same stale count) under-counts by at most one — acceptable for
 * login-attempt throttling, not a correctness-critical counter.
 *
 * @package rafalmasiarek\DashboardKitCaptcha
 */
final class FailedAttemptTracker implements FailedAttemptStoreInterface
{
    /** @var string Table created by CaptchaAddon::syncSchema(). */
    private const TABLE = 'captcha_failed_attempts';

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $ttlSeconds): int
    {
        $now = Model::getClock()->now();
        $row = Model::on(self::TABLE)->where('attempt_key', $key)->first();

        if ($row === null || self::isExpired($row, $now)) {
            $expiresAt = $now->modify("+{$ttlSeconds} seconds")->format('Y-m-d H:i:s');

            if ($row === null) {
                Model::on(self::TABLE)->insert([
                    'attempt_key' => $key,
                    'count'       => 1,
                    'expires_at'  => $expiresAt,
                ]);
            } else {
                Model::on(self::TABLE)->where('attempt_key', $key)->update([
                    'count'      => 1,
                    'expires_at' => $expiresAt,
                ]);
            }

            return 1;
        }

        $newCount = ((int) $row['count']) + 1;
        Model::on(self::TABLE)->where('attempt_key', $key)->update(['count' => $newCount]);

        return $newCount;
    }

    /**
     * {@inheritDoc}
     */
    public function count(string $key): int
    {
        $row = Model::on(self::TABLE)->where('attempt_key', $key)->first();
        if ($row === null || self::isExpired($row, Model::getClock()->now())) {
            return 0;
        }

        return (int) $row['count'];
    }

    /**
     * {@inheritDoc}
     */
    public function reset(string $key): void
    {
        Model::on(self::TABLE)->where('attempt_key', $key)->delete();
    }

    /**
     * @param array<string, mixed> $row
     * @param \DateTimeImmutable $now
     *
     * @return bool
     */
    private static function isExpired(array $row, \DateTimeImmutable $now): bool
    {
        $expiresAt = $row['expires_at'] ?? null;
        if ($expiresAt === null) {
            return true;
        }

        return new \DateTimeImmutable((string) $expiresAt) < $now;
    }
}
