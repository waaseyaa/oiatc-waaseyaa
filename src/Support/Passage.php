<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One retrieved passage: a doc_chunk plus the score the retriever gave it.
 * Plain value object so retrieval can be tested and so the answer layer has a
 * stable shape regardless of which retriever (keyword now, vector later)
 * produced it.
 */
final readonly class Passage
{
    public function __construct(
        public string $sourceUrl,
        public string $title,
        public string $heading,
        public string $text,
        public float $score,
    ) {}
}
