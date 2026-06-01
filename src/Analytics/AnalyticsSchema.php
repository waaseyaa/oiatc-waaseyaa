<?php

declare(strict_types=1);

namespace App\Analytics;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Creates the append-only analytics event table on demand.
 *
 * The framework has no migration CLI, so the table is ensured at boot,
 * guarded by tableExists(). This is a non-entity, audit-log-style table and
 * therefore uses DatabaseInterface directly (per framework convention).
 */
final class AnalyticsSchema
{
    public const TABLE = 'analytics_event';
    public const TABLE_CHAT = 'chat_query_log';

    public function __construct(private readonly DatabaseInterface $db) {}

    public function ensure(): void
    {
        $this->ensureEventTable();
        $this->ensureChatLogTable();
    }

    private function ensureEventTable(): void
    {
        $schema = $this->db->schema();
        if ($schema->tableExists(self::TABLE)) {
            return;
        }

        $schema->createTable(self::TABLE, [
            'fields' => [
                'id' => ['type' => 'serial', 'not null' => true],
                'event_type' => ['type' => 'varchar', 'length' => 20, 'not null' => true],
                'path' => ['type' => 'varchar', 'length' => 255],
                'referrer_host' => ['type' => 'varchar', 'length' => 255],
                'view_id' => ['type' => 'varchar', 'length' => 64],
                'visitor_hash' => ['type' => 'varchar', 'length' => 64],
                'device' => ['type' => 'varchar', 'length' => 20],
                'scroll_pct' => ['type' => 'int'],
                'dwell_ms' => ['type' => 'int'],
                'created_at' => ['type' => 'varchar', 'length' => 19, 'not null' => true],
            ],
            'primary key' => ['id'],
            'indexes' => [
                'idx_ae_created' => ['created_at'],
                'idx_ae_type' => ['event_type'],
                'idx_ae_view' => ['view_id'],
                'idx_ae_visitor' => ['visitor_hash', 'created_at'],
            ],
        ]);
    }

    /**
     * Anonymous Co-Intelligence query log, for mining content gaps. Strictly
     * OCAP-aligned: it records the question content and outcome only, with NO
     * IP, visitor hash, view/session id, or any other identifier — nothing that
     * links a question to a person.
     */
    private function ensureChatLogTable(): void
    {
        $schema = $this->db->schema();
        if ($schema->tableExists(self::TABLE_CHAT)) {
            return;
        }

        $schema->createTable(self::TABLE_CHAT, [
            'fields' => [
                'id' => ['type' => 'serial', 'not null' => true],
                'created_at' => ['type' => 'varchar', 'length' => 19, 'not null' => true],
                'community' => ['type' => 'varchar', 'length' => 32, 'not null' => true],
                'question' => ['type' => 'varchar', 'length' => 512, 'not null' => true],
                'outcome' => ['type' => 'varchar', 'length' => 16, 'not null' => true],
                'topic' => ['type' => 'varchar', 'length' => 64],
                'sources' => ['type' => 'varchar', 'length' => 512],
            ],
            'primary key' => ['id'],
            'indexes' => [
                'idx_cq_created' => ['created_at'],
                'idx_cq_community' => ['community', 'created_at'],
                'idx_cq_outcome' => ['outcome'],
                'idx_cq_topic' => ['topic'],
            ],
        ]);
    }
}
