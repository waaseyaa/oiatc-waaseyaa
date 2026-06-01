<?php

declare(strict_types=1);

namespace App\Analytics;

/**
 * Records one anonymous Co-Intelligence query for content-gap mining.
 *
 * Strictly OCAP-aligned and anonymous: implementations must store only the
 * question content and its outcome, never an IP, session/visitor id, or
 * anything that links a question to a person.
 */
interface ChatQueryLogInterface
{
    /**
     * @param string       $community vantage community slug
     * @param string       $question  the question text (content only)
     * @param string       $outcome   answered | refused | no_match | error | unavailable
     * @param string|null  $topic     inferred topic slug, or null when none matched
     * @param list<string> $sources   cited source URLs (empty when none)
     */
    public function record(string $community, string $question, string $outcome, ?string $topic, array $sources): void;
}
