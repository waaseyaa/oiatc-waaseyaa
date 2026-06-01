<?php

declare(strict_types=1);

namespace App\Analytics;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Append-only, anonymous Co-Intelligence query log backed by the first-party
 * analytics SQLite store (table {@see AnalyticsSchema::TABLE_CHAT}).
 *
 * Records timestamp, vantage community, question text, outcome, inferred topic,
 * and cited sources only. No IP, visitor hash, view/session id, or anything that
 * links a question to a person. A write failure is swallowed so logging never
 * breaks the user-facing chat response.
 */
final class SqliteChatQueryLog implements ChatQueryLogInterface
{
    private const MAX_SOURCES = 10;

    public function __construct(private readonly DatabaseInterface $db) {}

    public function record(string $community, string $question, string $outcome, ?string $topic, array $sources): void
    {
        $uniqueSources = array_values(array_unique(array_filter($sources, static fn(string $s): bool => $s !== '')));
        $sourcesCsv = implode(',', array_slice($uniqueSources, 0, self::MAX_SOURCES));

        try {
            $this->db->query(
                'INSERT INTO ' . AnalyticsSchema::TABLE_CHAT
                . ' (created_at, community, question, outcome, topic, sources) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    gmdate('Y-m-d H:i:s'),
                    substr($community, 0, 32),
                    substr($question, 0, 500),
                    substr($outcome, 0, 16),
                    $topic !== null ? substr($topic, 0, 64) : null,
                    substr($sourcesCsv, 0, 512),
                ],
            );
        } catch (\Throwable) {
            // Analytics logging must never break the chat response.
        }
    }
}
