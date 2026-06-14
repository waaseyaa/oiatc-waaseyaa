<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\IngestDocsCommand;
use App\Command\SeedGraphCommand;
use App\Entity\DocChunk;
use App\Support\ChunkData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class IngestDocsSyncTest extends TestCase
{
    #[Test]
    public function first_sync_creates_every_chunk(): void
    {
        $repo = $this->repository();
        $result = IngestDocsCommand::syncChunks($repo, $this->chunks(['a', 'b', 'c']), prune: true);

        self::assertSame(['created' => 3, 'updated' => 0, 'deleted' => 0, 'total' => 3], $result);
        self::assertCount(3, $repo->findBy([]));
    }

    #[Test]
    public function re_running_the_same_input_updates_in_place_without_duplicating(): void
    {
        $repo = $this->repository();
        IngestDocsCommand::syncChunks($repo, $this->chunks(['a', 'b', 'c']), prune: true);

        $result = IngestDocsCommand::syncChunks($repo, $this->chunks(['a', 'b', 'c']), prune: true);

        self::assertSame(['created' => 0, 'updated' => 3, 'deleted' => 0, 'total' => 3], $result);
        self::assertCount(3, $repo->findBy([]), 'Re-run must not duplicate rows.');
    }

    #[Test]
    public function changed_input_creates_updates_and_prunes(): void
    {
        $repo = $this->repository();
        IngestDocsCommand::syncChunks($repo, $this->chunks(['a', 'b', 'c']), prune: true);

        // Drop 'c', change the text of 'b', add 'd'.
        $next = [
            $this->chunk('a'),
            $this->chunk('b', 'updated body text for chunk b that is long enough to keep'),
            $this->chunk('d'),
        ];
        $result = IngestDocsCommand::syncChunks($repo, $next, prune: true);

        self::assertSame(['created' => 1, 'updated' => 2, 'deleted' => 1, 'total' => 3], $result);

        $byKey = $this->byKey($repo);
        self::assertArrayNotHasKey('/p#h-c', $byKey, "Pruned chunk 'c' must be gone.");
        self::assertArrayHasKey('/p#h-d', $byKey, "New chunk 'd' must be present.");
        self::assertStringContainsString('updated body text', $byKey['/p#h-b']->getText());
    }

    #[Test]
    public function no_prune_keeps_chunks_that_were_not_regenerated(): void
    {
        $repo = $this->repository();
        IngestDocsCommand::syncChunks($repo, $this->chunks(['a', 'b', 'c']), prune: true);

        $result = IngestDocsCommand::syncChunks($repo, [$this->chunk('a')], prune: false);

        self::assertSame(0, $result['deleted']);
        self::assertCount(3, $repo->findBy([]), 'With --no-prune, stale chunks remain.');
    }

    #[Test]
    public function pruning_never_deletes_curated_chunks_owned_by_the_seeder(): void
    {
        $repo = $this->repository();
        // A curated chunk created by app:seed-graph (external source, not a page).
        $repo->save(DocChunk::make([
            'chunk_key' => SeedGraphCommand::CURATED_KEY_PREFIX . 'nmninoeyaa',
            'source_url' => 'https://maamwesying.ca/nmninoeyaa-aboriginal-health-access-centre',
            'title' => 'Maamwesying',
            'heading' => 'Primary care',
            'text' => 'Sourced curated text.',
            'entity_type' => 'service',
            'entity_id' => 'nmninoeyaa',
        ]));

        // A page-ingest run that knows nothing about the curated chunk.
        $result = IngestDocsCommand::syncChunks($repo, [$this->chunk('a')], prune: true);

        self::assertSame(0, $result['deleted'], 'The curated chunk must not be pruned.');
        self::assertArrayHasKey(SeedGraphCommand::CURATED_KEY_PREFIX . 'nmninoeyaa', $this->byKey($repo));
    }

    /**
     * @param list<string> $suffixes
     *
     * @return list<ChunkData>
     */
    private function chunks(array $suffixes): array
    {
        return array_map(fn(string $s): ChunkData => $this->chunk($s), $suffixes);
    }

    private function chunk(string $suffix, ?string $text = null): ChunkData
    {
        return new ChunkData(
            chunkKey: '/p#h-' . $suffix,
            sourceUrl: '/p',
            title: 'Page',
            heading: 'H',
            text: $text ?? ('Body text for chunk ' . $suffix . ' with enough length to be retained.'),
        );
    }

    /**
     * @return array<string, DocChunk>
     */
    private function byKey(EntityRepositoryInterface $repo): array
    {
        $out = [];
        foreach ($repo->findBy([]) as $chunk) {
            if ($chunk instanceof DocChunk) {
                $out[$chunk->getChunkKey()] = $chunk;
            }
        }

        return $out;
    }

    /**
     * In-memory doc_chunk repository keyed by chunk_key. Implements only the
     * methods syncChunks touches (findBy/save/delete); the rest are stubs.
     */
    private function repository(): EntityRepositoryInterface
    {
        return new class implements EntityRepositoryInterface {
            use \App\Tests\Support\RevisionRepositoryStubs;

            /** @var array<string, DocChunk> */
            private array $store = [];

            public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null): array
            {
                return array_values($this->store);
            }

            public function save(EntityInterface $entity, bool $validate = true): int
            {
                if ($entity instanceof DocChunk) {
                    $this->store[$entity->getChunkKey()] = $entity;
                }

                return 1;
            }

            public function delete(EntityInterface $entity): void
            {
                if ($entity instanceof DocChunk) {
                    unset($this->store[$entity->getChunkKey()]);
                }
            }

            public function find(string $id, ?string $langcode = null, bool $fallback = false): ?EntityInterface
            {
                return null;
            }

            public function findMany(array $ids, ?string $langcode = null, bool $fallback = false): array
            {
                return [];
            }

            public function exists(string $id): bool
            {
                return false;
            }

            public function count(array $criteria = []): int
            {
                return count($this->store);
            }

            public function loadRevision(string $entityId, int $revisionId): ?EntityInterface
            {
                return null;
            }

            public function rollback(string $entityId, int $targetRevisionId): EntityInterface
            {
                throw new \LogicException('not used in tests');
            }

            public function saveMany(array $entities, bool $validate = true): array
            {
                return [];
            }

            public function deleteMany(array $entities): int
            {
                return 0;
            }

            public function findTranslations(EntityInterface $entity): array
            {
                return [];
            }
        };
    }
}
