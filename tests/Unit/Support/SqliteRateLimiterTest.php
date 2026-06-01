<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\SqliteRateLimiter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;

final class SqliteRateLimiterTest extends TestCase
{
    #[Test]
    public function it_allows_up_to_the_cap_then_blocks(): void
    {
        $limiter = new SqliteRateLimiter(DBALDatabase::createSqlite(':memory:'), maxRequests: 3, windowSeconds: 60);

        $results = [];
        for ($i = 0; $i < 4; $i++) {
            $results[] = $limiter->retryAfter('client-a');
        }

        self::assertNull($results[0]);
        self::assertNull($results[1]);
        self::assertNull($results[2]);
        self::assertNotNull($results[3], 'The 4th request in the window is blocked.');
        self::assertGreaterThan(0, $results[3]);
    }

    #[Test]
    public function separate_clients_have_independent_windows(): void
    {
        $limiter = new SqliteRateLimiter(DBALDatabase::createSqlite(':memory:'), maxRequests: 1, windowSeconds: 60);

        $a = [$limiter->retryAfter('client-a'), $limiter->retryAfter('client-a')];
        $b = $limiter->retryAfter('client-b');

        self::assertNull($a[0]);
        self::assertNotNull($a[1], 'client-a is now over its cap.');
        self::assertNull($b, 'client-b is unaffected by client-a.');
    }
}
