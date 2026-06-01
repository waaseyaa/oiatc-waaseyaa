<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Analytics\ChatQueryLogInterface;

/**
 * Test double for the anonymous chat query log: captures every record so tests
 * can assert the logged fields and outcome.
 */
final class CapturingChatQueryLog implements ChatQueryLogInterface
{
    /** @var list<array{community:string, question:string, outcome:string, topic:?string, sources:list<string>}> */
    public array $records = [];

    public function record(string $community, string $question, string $outcome, ?string $topic, array $sources): void
    {
        $this->records[] = [
            'community' => $community,
            'question' => $question,
            'outcome' => $outcome,
            'topic' => $topic,
            'sources' => $sources,
        ];
    }
}
