<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Returns the passages most relevant to a question, best first.
 *
 * Keyword scoring backs this today ({@see KnowledgeRetriever}); a vector index
 * over the same doc_chunk rows can implement this interface later without the
 * chat layer changing.
 */
interface RetrieverInterface
{
    /**
     * @return list<Passage> up to $k passages, highest score first
     */
    public function retrieve(string $query, int $k = 6): array;
}
