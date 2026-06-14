<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Waaseyaa\Entity\EntityInterface;

/**
 * Stubs for the revision and translation methods on EntityRepositoryInterface
 * that the in-memory test doubles do not exercise. OIATC's entities are
 * non-revisionable and untranslated, so these are never called; centralising
 * them here lets the doubles track interface growth in one place.
 */
trait RevisionRepositoryStubs
{
    public function listRevisions(string $entityId): array
    {
        return [];
    }

    public function setCurrentRevision(string $entityId, int $revisionId): EntityInterface
    {
        throw new \LogicException('setCurrentRevision not used in tests');
    }

    public function loadPublishedRevision(string $entityId): ?EntityInterface
    {
        return null;
    }

    public function setPublishedRevision(string $entityId, int $revisionId): EntityInterface
    {
        throw new \LogicException('setPublishedRevision not used in tests');
    }

    public function saveTranslation(string $entityId, string $langcode, array $values, ?string $log = null): int
    {
        return 0;
    }

    public function loadTranslation(string $entityId, string $langcode): ?EntityInterface
    {
        return null;
    }

    public function listTranslationRevisions(string $entityId, string $langcode): array
    {
        return [];
    }
}
