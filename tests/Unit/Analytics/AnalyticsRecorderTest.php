<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\AnalyticsSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;

final class AnalyticsRecorderTest extends TestCase
{
    private DatabaseInterface $db;

    private AnalyticsRecorder $recorder;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite(':memory:');
        new AnalyticsSchema($this->db)->ensure();
        $this->recorder = new AnalyticsRecorder($this->db, 'test-secret');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rows(): array
    {
        $rows = [];
        foreach ($this->db->query('SELECT * FROM ' . AnalyticsSchema::TABLE, []) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    #[Test]
    public function it_records_a_valid_pageview(): void
    {
        $stored = $this->recorder->record(
            ['t' => 'pageview', 'v' => 'view-1', 'p' => '/explainers/massey-solar', 'r' => 'https://l.facebook.com/abc?ref=1'],
            '203.0.113.7',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        );

        $this->assertTrue($stored);

        $rows = $this->rows();
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame('pageview', $row['event_type']);
        $this->assertSame('/explainers/massey-solar', $row['path']);
        $this->assertSame('l.facebook.com', $row['referrer_host']);
        $this->assertSame('view-1', $row['view_id']);
        $this->assertSame('desktop', $row['device']);
        $this->assertNotNull($row['visitor_hash']);
        $this->assertNotSame('', $row['visitor_hash']);
        $this->assertNull($row['scroll_pct']);
        $this->assertNull($row['dwell_ms']);
    }

    #[Test]
    public function it_records_a_valid_engagement(): void
    {
        $stored = $this->recorder->record(
            ['t' => 'engagement', 'v' => 'view-2', 's' => 55, 'd' => 12000],
            '203.0.113.7',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148',
        );

        $this->assertTrue($stored);

        $rows = $this->rows();
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame('engagement', $row['event_type']);
        $this->assertSame('view-2', $row['view_id']);
        $this->assertSame(55, (int) $row['scroll_pct']);
        $this->assertSame(12000, (int) $row['dwell_ms']);
        $this->assertNull($row['path']);
        $this->assertNull($row['visitor_hash']);
    }

    #[Test]
    public function it_rejects_an_unknown_event_type(): void
    {
        $stored = $this->recorder->record(
            ['t' => 'click', 'v' => 'view-3', 'p' => '/'],
            '203.0.113.7',
            'Mozilla/5.0',
        );

        $this->assertFalse($stored);
        $this->assertCount(0, $this->rows());
    }

    #[Test]
    public function it_rejects_a_missing_or_empty_view_id(): void
    {
        $missing = $this->recorder->record(
            ['t' => 'pageview', 'p' => '/'],
            '203.0.113.7',
            'Mozilla/5.0',
        );
        $this->assertFalse($missing);

        $empty = $this->recorder->record(
            ['t' => 'pageview', 'v' => '', 'p' => '/'],
            '203.0.113.7',
            'Mozilla/5.0',
        );
        $this->assertFalse($empty);

        $this->assertCount(0, $this->rows());
    }

    #[Test]
    public function it_rejects_a_bot_user_agent(): void
    {
        $curl = $this->recorder->record(
            ['t' => 'pageview', 'v' => 'view-4', 'p' => '/'],
            '203.0.113.7',
            'curl/8.0',
        );
        $this->assertFalse($curl);

        $googlebot = $this->recorder->record(
            ['t' => 'pageview', 'v' => 'view-5', 'p' => '/'],
            '203.0.113.7',
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        );
        $this->assertFalse($googlebot);

        $this->assertCount(0, $this->rows());
    }

    #[Test]
    public function visitor_hash_is_stable_for_same_ip_and_ua_and_differs_for_a_different_ip(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36';

        $this->assertTrue($this->recorder->record(['t' => 'pageview', 'v' => 'a', 'p' => '/'], '198.51.100.1', $ua));
        $this->assertTrue($this->recorder->record(['t' => 'pageview', 'v' => 'b', 'p' => '/'], '198.51.100.1', $ua));
        $this->assertTrue($this->recorder->record(['t' => 'pageview', 'v' => 'c', 'p' => '/'], '198.51.100.2', $ua));

        $rows = $this->rows();
        $this->assertCount(3, $rows);

        // Same ip + ua on the same day collapse to one hash.
        $this->assertSame($rows[0]['visitor_hash'], $rows[1]['visitor_hash']);
        // A different ip produces a different hash.
        $this->assertNotSame($rows[0]['visitor_hash'], $rows[2]['visitor_hash']);
    }

    #[Test]
    public function it_clamps_scroll_pct_and_ignores_non_numeric_engagement_values(): void
    {
        // scroll_pct above 100 is clamped to 100; non-numeric dwell is dropped to null.
        $this->assertTrue($this->recorder->record(
            ['t' => 'engagement', 'v' => 'over', 's' => 250, 'd' => 'not-a-number'],
            null,
            null,
        ));

        // negative scroll_pct is clamped to 0.
        $this->assertTrue($this->recorder->record(
            ['t' => 'engagement', 'v' => 'under', 's' => -10, 'd' => 5000],
            null,
            null,
        ));

        $rows = $this->rows();
        $this->assertCount(2, $rows);

        $this->assertSame(100, (int) $rows[0]['scroll_pct']);
        $this->assertNull($rows[0]['dwell_ms']);

        $this->assertSame(0, (int) $rows[1]['scroll_pct']);
        $this->assertSame(5000, (int) $rows[1]['dwell_ms']);
    }
}
