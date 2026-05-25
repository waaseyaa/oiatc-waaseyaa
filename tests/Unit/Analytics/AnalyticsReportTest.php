<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\AnalyticsReport;
use App\Analytics\AnalyticsSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;

final class AnalyticsReportTest extends TestCase
{
    private DatabaseInterface $db;

    private AnalyticsRecorder $recorder;

    private AnalyticsReport $report;

    private string $today;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite(':memory:');
        (new AnalyticsSchema($this->db))->ensure();
        $this->recorder = new AnalyticsRecorder($this->db, 'test-secret');
        $this->report = new AnalyticsReport($this->db);
        // The recorder stamps created_at with the current UTC day.
        $this->today = gmdate('Y-m-d');
    }

    #[Test]
    public function summary_aggregates_views_visitors_pages_engagement_referrers_and_devices(): void
    {
        $desktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $mobile = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';

        // Visitor A (ip .1, desktop): two views of /home, one of /about.
        $this->assertTrue($this->recorder->record(['t' => 'pageview', 'v' => 'a1', 'p' => '/home', 'r' => 'https://l.facebook.com/x'], '198.51.100.1', $desktop));
        $this->assertTrue($this->recorder->record(['t' => 'pageview', 'v' => 'a2', 'p' => '/home', 'r' => 'https://l.facebook.com/y'], '198.51.100.1', $desktop));
        $this->assertTrue($this->recorder->record(['t' => 'pageview', 'v' => 'a3', 'p' => '/about', 'r' => 'https://www.google.com/'], '198.51.100.1', $desktop));

        // Visitor B (ip .2, mobile): one view of /home.
        $this->assertTrue($this->recorder->record(['t' => 'pageview', 'v' => 'b1', 'p' => '/home', 'r' => 'https://l.facebook.com/z'], '198.51.100.2', $mobile));

        // Engagement rows tied by view_id to /home pageviews.
        $this->assertTrue($this->recorder->record(['t' => 'engagement', 'v' => 'a1', 's' => 40, 'd' => 10000], '198.51.100.1', $desktop));
        $this->assertTrue($this->recorder->record(['t' => 'engagement', 'v' => 'a2', 's' => 80, 'd' => 20000], '198.51.100.1', $desktop));
        $this->assertTrue($this->recorder->record(['t' => 'engagement', 'v' => 'b1', 's' => 60, 'd' => 30000], '198.51.100.2', $mobile));

        $summary = $this->report->summary($this->today, $this->today);

        // Totals: 4 pageviews, 2 distinct visitors.
        $this->assertSame(4, $summary['totals']['views']);
        $this->assertSame(2, $summary['totals']['visitors']);

        // Pages keyed by path for assertions.
        $pages = [];
        foreach ($summary['pages'] as $page) {
            $pages[$page['path']] = $page;
        }

        $this->assertArrayHasKey('/home', $pages);
        $this->assertArrayHasKey('/about', $pages);

        // /home: 3 views from 2 distinct visitors.
        $this->assertSame(3, $pages['/home']['views']);
        $this->assertSame(2, $pages['/home']['visitors']);
        // avg_scroll over engagement rows for /home: (40 + 80 + 60) / 3 = 60.0
        $this->assertSame(60.0, $pages['/home']['avg_scroll']);
        // avg_dwell_ms over engagement rows for /home: (10000 + 20000 + 30000) / 3 = 20000
        $this->assertSame(20000.0, $pages['/home']['avg_dwell_ms']);

        // /about: 1 view from 1 distinct visitor, no engagement.
        $this->assertSame(1, $pages['/about']['views']);
        $this->assertSame(1, $pages['/about']['visitors']);
        $this->assertSame(0.0, $pages['/about']['avg_scroll']);
        $this->assertSame(0.0, $pages['/about']['avg_dwell_ms']);

        // Pages ordered by views DESC: /home (3) before /about (1).
        $this->assertSame('/home', $summary['pages'][0]['path']);

        // Referrers: l.facebook.com (3) then www.google.com (1), ordered by count DESC.
        $this->assertSame('l.facebook.com', $summary['referrers'][0]['host']);
        $this->assertSame(3, $summary['referrers'][0]['count']);
        $this->assertSame('www.google.com', $summary['referrers'][1]['host']);
        $this->assertSame(1, $summary['referrers'][1]['count']);

        // Devices: desktop (3) then mobile (1).
        $devices = [];
        foreach ($summary['devices'] as $device) {
            $devices[$device['device']] = $device['count'];
        }
        $this->assertSame(3, $devices['desktop']);
        $this->assertSame(1, $devices['mobile']);

        // Range echoed back.
        $this->assertSame($this->today, $summary['from']);
        $this->assertSame($this->today, $summary['to']);
    }

    #[Test]
    public function summary_is_empty_for_a_range_with_no_events(): void
    {
        $summary = $this->report->summary('2000-01-01', '2000-01-02');

        $this->assertSame(0, $summary['totals']['views']);
        $this->assertSame(0, $summary['totals']['visitors']);
        $this->assertSame([], $summary['pages']);
        $this->assertSame([], $summary['referrers']);
        $this->assertSame([], $summary['devices']);
    }
}
