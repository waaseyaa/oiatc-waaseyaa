<?php

declare(strict_types=1);

namespace App\Support;

use App\Entity\DocChunk;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Keyword retrieval over the doc_chunk rows. No embeddings.
 *
 * Loads all chunks (small N; custom #[Field]s aren't SQL-filterable anyway),
 * tokenizes the question (lower-cased, punctuation-stripped, stop-worded), and
 * scores each chunk by weighted term frequency over title (x3), heading (x2),
 * and text (x1). Returns the top-k passages with score > 0.
 *
 * Deliberately simple and dependency-free so a vector index can replace it
 * behind {@see RetrieverInterface} later.
 */
final class KnowledgeRetriever implements RetrieverInterface
{
    private const TITLE_WEIGHT = 3;
    private const HEADING_WEIGHT = 2;
    private const TEXT_WEIGHT = 1;

    /** Common words that should never drive a match. */
    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'can', 'do', 'does', 'for', 'from',
        'how', 'i', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'per', 'so', 'the', 'to',
        'we', 'what', 'when', 'where', 'which', 'who', 'why', 'with', 'you', 'your', 'about',
        'get', 'got', 'this', 'that', 'there', 'their', 'them', 'they', 'will', 'would', 'should',
        'if', 'but', 'not', 'no', 'yes', 'any', 'all', 'some', 'our', 'us', 'am',
    ];

    public function __construct(private readonly EntityRepositoryInterface $chunks) {}

    public function retrieve(string $query, int $k = 6): array
    {
        $terms = $this->tokenize($query);
        if ($terms === []) {
            return [];
        }

        $scored = [];
        foreach ($this->chunks->findBy([]) as $chunk) {
            if (!$chunk instanceof DocChunk) {
                continue;
            }
            $score = $this->score($terms, $chunk);
            if ($score > 0.0) {
                $scored[] = new Passage(
                    sourceUrl: $chunk->getSourceUrl(),
                    title: $chunk->getTitle(),
                    heading: $chunk->getHeading(),
                    text: $chunk->getText(),
                    score: $score,
                );
            }
        }

        usort($scored, static fn(Passage $a, Passage $b): int => $b->score <=> $a->score);

        return array_slice($scored, 0, max(1, $k));
    }

    /**
     * @param list<string> $terms
     */
    private function score(array $terms, DocChunk $chunk): float
    {
        $titleTf = $this->termCounts($this->tokenize($chunk->getTitle()));
        $headingTf = $this->termCounts($this->tokenize($chunk->getHeading()));
        $textTf = $this->termCounts($this->tokenize($chunk->getText()));

        $score = 0.0;
        foreach (array_unique($terms) as $term) {
            $hits = ($titleTf[$term] ?? 0) * self::TITLE_WEIGHT
                + ($headingTf[$term] ?? 0) * self::HEADING_WEIGHT
                + ($textTf[$term] ?? 0) * self::TEXT_WEIGHT;
            if ($hits > 0) {
                // Damp raw frequency so one term repeated many times can't bury
                // a passage that matches several distinct query terms.
                $score += 1.0 + log((float) $hits);
            }
        }

        return $score;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        $tokens = [];
        foreach (explode(' ', $text) as $token) {
            if (strlen($token) < 2) {
                continue;
            }
            if (in_array($token, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @param list<string> $tokens
     *
     * @return array<string, int>
     */
    private function termCounts(array $tokens): array
    {
        $counts = [];
        foreach ($tokens as $token) {
            $counts[$token] = ($counts[$token] ?? 0) + 1;
        }

        return $counts;
    }
}
