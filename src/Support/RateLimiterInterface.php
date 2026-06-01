<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A per-key request limiter. Decoupled from storage so the controller can be
 * tested without a database and so the backing store can change.
 */
interface RateLimiterInterface
{
    /**
     * Record a hit for $key and report whether it is over the limit.
     *
     * @return int|null seconds to wait if the limit is exceeded, or null if the
     *                  request is allowed
     */
    public function retryAfter(string $key): ?int;
}
