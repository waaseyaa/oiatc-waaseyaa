<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\AnalyticsReport;
use App\Analytics\AnalyticsSchema;
use App\Analytics\SqliteChatQueryLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;

final class ChatQueryLogTest extends TestCase
{
    #[Test]
    public function it_records_queries_and_reports_per_community_gaps(): void
    {
        $db = DBALDatabase::createSqlite(':memory:');
        new AnalyticsSchema($db)->ensure();
        $log = new SqliteChatQueryLog($db);

        $log->record('sagamok', 'How do I apply for housing?', 'answered', 'housing', ['/anokii/sagamok']);
        $log->record('sagamok', 'fishing license in toronto', 'no_match', 'lands-environment', []);
        $log->record('sagamok', 'do you sell concert tickets', 'refused', null, []);
        $log->record('massey', 'what is the solar project', 'answered', 'energy-solar', ['/explainers/massey-solar-project']);

        $today = gmdate('Y-m-d');
        $report = new AnalyticsReport($db)->chatGaps($today, $today);

        self::assertArrayHasKey('sagamok', $report['communities']);
        self::assertArrayHasKey('massey', $report['communities']);

        $sag = $report['communities']['sagamok'];
        self::assertSame(3, $sag['totals']['total']);
        self::assertSame(2, $sag['totals']['unanswered'], 'no_match + refused are the gap backlog');
        self::assertCount(2, $sag['unanswered']);
        $questions = array_column($sag['unanswered'], 'question');
        self::assertContains('fishing license in toronto', $questions);
        self::assertContains('do you sell concert tickets', $questions);

        $topicCounts = [];
        foreach ($sag['topics'] as $t) {
            $topicCounts[$t['topic']] = $t['count'];
        }
        self::assertSame(1, $topicCounts['housing'] ?? null);
        self::assertSame(1, $topicCounts['lands-environment'] ?? null);
        self::assertSame(1, $topicCounts['none'] ?? null, 'A null topic is reported as "none".');

        $massey = $report['communities']['massey'];
        self::assertSame(1, $massey['totals']['total']);
        self::assertSame(0, $massey['totals']['unanswered']);
        self::assertSame([], $massey['unanswered']);
    }

    #[Test]
    public function the_log_table_stores_no_identifying_columns(): void
    {
        // OCAP / anonymity: the table must carry no IP, visitor, session, or
        // user identifier, only the question content and its outcome.
        $db = DBALDatabase::createSqlite(':memory:');
        new AnalyticsSchema($db)->ensure();

        $columns = [];
        foreach ($db->query('PRAGMA table_info(' . AnalyticsSchema::TABLE_CHAT . ')') as $row) {
            $columns[] = strtolower((string) ($row['name'] ?? ''));
        }

        foreach (['ip', 'visitor_hash', 'view_id', 'session', 'session_id', 'user_id', 'user_agent'] as $forbidden) {
            self::assertNotContains($forbidden, $columns, "chat_query_log must not have a '{$forbidden}' column.");
        }
        self::assertContains('question', $columns);
        self::assertContains('outcome', $columns);
        self::assertContains('community', $columns);
    }
}
