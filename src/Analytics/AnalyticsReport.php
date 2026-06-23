<?php

declare(strict_types=1);

namespace App\Analytics;

use Anokii\CoIntelligence\ChatQueryLogSchema;
use Waaseyaa\Database\DatabaseInterface;

/**
 * Aggregates raw analytics events into dashboard-ready summaries.
 *
 * Uses raw SELECT queries (GROUP BY / aggregates) via DatabaseInterface::query,
 * which returns associative rows. All counts are cast explicitly because the
 * SQLite driver may return numeric columns as strings.
 */
final class AnalyticsReport
{
    public function __construct(private readonly DatabaseInterface $db) {}

    /**
     * @return array{
     *   totals: array{views:int, visitors:int},
     *   pages: list<array{path:string, views:int, visitors:int, avg_scroll:float, avg_dwell_ms:float}>,
     *   referrers: list<array{host:string, count:int}>,
     *   devices: list<array{device:string, count:int}>,
     *   from:string, to:string
     * }
     */
    public function summary(string $fromDate, string $toDate): array
    {
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';
        $table = AnalyticsSchema::TABLE;

        $totalsRow = $this->one(
            'SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors'
            . " FROM {$table} WHERE event_type = 'pageview' AND created_at BETWEEN ? AND ?",
            [$from, $to],
        );
        $totals = [
            'views' => (int) ($totalsRow['views'] ?? 0),
            'visitors' => (int) ($totalsRow['visitors'] ?? 0),
        ];

        $pages = [];
        $rows = $this->db->query(
            'SELECT path, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors'
            . " FROM {$table} WHERE event_type = 'pageview' AND created_at BETWEEN ? AND ?"
            . ' GROUP BY path ORDER BY views DESC',
            [$from, $to],
        );
        foreach ($rows as $r) {
            $path = (string) ($r['path'] ?? '');
            $pages[$path] = [
                'path' => $path,
                'views' => (int) $r['views'],
                'visitors' => (int) $r['visitors'],
                'avg_scroll' => 0.0,
                'avg_dwell_ms' => 0.0,
            ];
        }

        // Engagement rows carry no path; join them to their pageview via view_id.
        $engagement = $this->db->query(
            'SELECT p.path AS path, AVG(e.scroll_pct) AS avg_scroll, AVG(e.dwell_ms) AS avg_dwell'
            . " FROM {$table} e JOIN {$table} p ON p.view_id = e.view_id AND p.event_type = 'pageview'"
            . " WHERE e.event_type = 'engagement' AND e.created_at BETWEEN ? AND ?"
            . ' GROUP BY p.path',
            [$from, $to],
        );
        foreach ($engagement as $r) {
            $path = (string) ($r['path'] ?? '');
            if (isset($pages[$path])) {
                $pages[$path]['avg_scroll'] = round((float) $r['avg_scroll'], 1);
                $pages[$path]['avg_dwell_ms'] = round((float) $r['avg_dwell'], 0);
            }
        }

        $referrers = [];
        $rows = $this->db->query(
            "SELECT referrer_host AS host, COUNT(*) AS count FROM {$table}"
            . " WHERE event_type = 'pageview' AND referrer_host IS NOT NULL AND created_at BETWEEN ? AND ?"
            . ' GROUP BY referrer_host ORDER BY count DESC LIMIT 20',
            [$from, $to],
        );
        foreach ($rows as $r) {
            $referrers[] = ['host' => (string) $r['host'], 'count' => (int) $r['count']];
        }

        $devices = [];
        $rows = $this->db->query(
            "SELECT COALESCE(device, 'unknown') AS device, COUNT(*) AS count FROM {$table}"
            . " WHERE event_type = 'pageview' AND created_at BETWEEN ? AND ?"
            . ' GROUP BY device ORDER BY count DESC',
            [$from, $to],
        );
        foreach ($rows as $r) {
            $devices[] = ['device' => (string) $r['device'], 'count' => (int) $r['count']];
        }

        return [
            'totals' => $totals,
            'pages' => array_values($pages),
            'referrers' => $referrers,
            'devices' => $devices,
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    /**
     * Co-Intelligence content-gap report: per vantage community, the questions
     * that refused or matched nothing (the gap backlog) and the most-asked
     * topics (demand). Reads the anonymous chat_query_log; no identifiers exist
     * in that table to surface. Returns empty if the table is absent.
     *
     * @return array{
     *   communities: array<string, array{
     *     totals: array{total:int, unanswered:int},
     *     unanswered: list<array{question:string, outcome:string, topic:string, created_at:string}>,
     *     topics: list<array{topic:string, count:int}>
     *   }>,
     *   from:string, to:string
     * }
     */
    public function chatGaps(string $fromDate, string $toDate): array
    {
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';
        $table = ChatQueryLogSchema::TABLE;
        $communities = [];

        try {
            $names = [];
            foreach ($this->db->query("SELECT DISTINCT community FROM {$table} WHERE created_at BETWEEN ? AND ? ORDER BY community", [$from, $to]) as $r) {
                $names[] = (string) ($r['community'] ?? '');
            }

            foreach ($names as $community) {
                if ($community === '') {
                    continue;
                }
                $totalsRow = $this->one(
                    'SELECT COUNT(*) AS total,'
                    . " SUM(CASE WHEN outcome IN ('refused','no_match') THEN 1 ELSE 0 END) AS unanswered"
                    . " FROM {$table} WHERE community = ? AND created_at BETWEEN ? AND ?",
                    [$community, $from, $to],
                );

                $unanswered = [];
                foreach ($this->db->query(
                    "SELECT question, outcome, COALESCE(NULLIF(topic, ''), 'none') AS topic, created_at FROM {$table}"
                    . " WHERE community = ? AND outcome IN ('refused','no_match') AND created_at BETWEEN ? AND ?"
                    . ' ORDER BY created_at DESC LIMIT 100',
                    [$community, $from, $to],
                ) as $r) {
                    $unanswered[] = [
                        'question' => (string) ($r['question'] ?? ''),
                        'outcome' => (string) ($r['outcome'] ?? ''),
                        'topic' => (string) ($r['topic'] ?? 'none'),
                        'created_at' => (string) ($r['created_at'] ?? ''),
                    ];
                }

                $topics = [];
                foreach ($this->db->query(
                    "SELECT COALESCE(NULLIF(topic, ''), 'none') AS topic, COUNT(*) AS count FROM {$table}"
                    . ' WHERE community = ? AND created_at BETWEEN ? AND ?'
                    . " GROUP BY COALESCE(NULLIF(topic, ''), 'none') ORDER BY count DESC, topic LIMIT 15",
                    [$community, $from, $to],
                ) as $r) {
                    $topics[] = ['topic' => (string) ($r['topic'] ?? 'none'), 'count' => (int) $r['count']];
                }

                $communities[$community] = [
                    'totals' => [
                        'total' => (int) ($totalsRow['total'] ?? 0),
                        'unanswered' => (int) ($totalsRow['unanswered'] ?? 0),
                    ],
                    'unanswered' => $unanswered,
                    'topics' => $topics,
                ];
            }
        } catch (\Throwable) {
            $communities = [];
        }

        return ['communities' => $communities, 'from' => $fromDate, 'to' => $toDate];
    }

    /**
     * All-time pageview count for a single path (for public per-page social proof).
     */
    public function viewsForPath(string $path): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS views FROM ' . AnalyticsSchema::TABLE
            . " WHERE event_type = 'pageview' AND path = ?",
            [$path],
        );

        return (int) ($row['views'] ?? 0);
    }

    /**
     * @param list<mixed> $args
     *
     * @return array<string,mixed>
     */
    private function one(string $sql, array $args): array
    {
        foreach ($this->db->query($sql, $args) as $row) {
            return $row;
        }

        return [];
    }
}
