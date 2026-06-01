<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\AnalyticsReport;
use App\Analytics\AnalyticsSchema;
use App\Controller\CollectController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Database\DBALDatabase;

/**
 * Guards the fix for upstream note #018: analytics components must be pinned to
 * the persistent SQLite *file*, not the ephemeral connection that
 * resolve(DatabaseInterface) hands back at route-build time.
 *
 * The defining property of the fix is cross-connection persistence: a beacon
 * written through one file-backed connection (the request that handles
 * /api/collect) must be visible to a *separate* connection opened later (the
 * request that renders the dashboard via AnalyticsReport). An ephemeral or
 * in-memory connection — what the buggy wiring captured — would lose the write
 * entirely, exactly as verified live (POST returned 204, 0 rows persisted).
 *
 * Each connection here is opened independently against the same temp file, so
 * the test fails if the recorder/report are ever rewired to a connection that
 * does not durably persist.
 */
final class AnalyticsPersistenceTest extends TestCase
{
    private string $dbFile = '';

    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'oiatc-analytics-') ?: '';
        self::assertNotSame('', $file, 'Could not allocate a temp database file.');
        $this->dbFile = $file . '.sqlite';
        @unlink($file);

        // Create the schema on its own connection, then drop it — mirroring boot().
        new AnalyticsSchema(DBALDatabase::createSqlite($this->dbFile))->ensure();
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbFile, $this->dbFile . '-wal', $this->dbFile . '-shm'] as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    #[Test]
    public function a_beacon_collected_on_one_connection_is_visible_to_a_later_report_connection(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

        // Connection #1: the /api/collect request writes a pageview + engagement.
        $writeDb = DBALDatabase::createSqlite($this->dbFile);
        $collect = new CollectController(new AnalyticsRecorder($writeDb, 'test-secret'));

        $collect->collect($this->beacon(
            ['t' => 'pageview', 'v' => 'persist-1', 'p' => '/explainers/massey-solar', 'r' => 'https://l.facebook.com/x'],
            $ua,
        ));
        $collect->collect($this->beacon(
            ['t' => 'engagement', 'v' => 'persist-1', 's' => 80, 'd' => 9000],
            $ua,
        ));

        // Connection #2: a *separate* later request renders the dashboard. If the
        // write above went to an ephemeral/in-memory connection, this sees nothing.
        $today = gmdate('Y-m-d');
        $report = new AnalyticsReport(DBALDatabase::createSqlite($this->dbFile));
        $summary = $report->summary($today, $today);

        self::assertSame(1, $summary['totals']['views'], 'The pageview must persist to the file and be readable by a fresh connection.');
        self::assertSame(1, $summary['totals']['visitors']);
        self::assertCount(1, $summary['pages']);
        self::assertSame('/explainers/massey-solar', $summary['pages'][0]['path']);
        self::assertSame(80.0, $summary['pages'][0]['avg_scroll'], 'Engagement joined to its pageview by view_id must persist too.');
        self::assertSame(9000.0, $summary['pages'][0]['avg_dwell_ms']);

        // All-time per-path count uses its own query path; assert it too.
        self::assertSame(1, $report->viewsForPath('/explainers/massey-solar'));
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function beacon(array $payload, string $userAgent): Request
    {
        return Request::create(
            '/api/collect',
            'POST',
            [],
            [],
            [],
            ['HTTP_USER_AGENT' => $userAgent],
            (string) json_encode($payload),
        );
    }
}
