<?php

declare(strict_types=1);

namespace App\Support;

/**
 * An extracted, not-yet-persisted retrieval passage. Plain value object so the
 * chunker can be unit-tested without touching storage.
 */
final readonly class ChunkData
{
    public function __construct(
        public string $chunkKey,
        public string $sourceUrl,
        public string $title,
        public string $heading,
        public string $text,
    ) {}
}
