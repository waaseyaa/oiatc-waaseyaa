<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SeedGraphCommand;
use App\Entity\DocChunk;
use App\Entity\GraphEntityBase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class SeedGraphCommandTest extends TestCase
{
    /** @var array<string, EntityRepositoryInterface> */
    private array $repos;

    protected function setUp(): void
    {
        $this->repos = [];
        foreach (['topic', 'place', 'community', 'organization', 'service', 'project', 'doc_chunk'] as $type) {
            $this->repos[$type] = $this->repository();
        }
    }

    #[Test]
    public function it_seeds_the_new_places_topics_and_curated_services(): void
    {
        new SeedGraphCommand($this->repos)->run($this->io());

        $places = $this->bySlug('place');
        self::assertArrayHasKey('mchigeeng', $places, 'exact-coordinate First Nation added');
        self::assertArrayHasKey('serpent-river', $places);
        self::assertArrayHasKey('spanish', $places, 'geocoded place added');
        self::assertArrayHasKey('aundeck-omni-kaning', $places);
        self::assertArrayNotHasKey('zhiibaahaasing', $places, 'unsourceable coordinate is omitted, not approximated');

        $topics = $this->bySlug('topic');
        self::assertArrayHasKey('primary-health', $topics);
        self::assertArrayHasKey('mental-health-addictions', $topics);
        self::assertArrayHasKey('income-support', $topics);
        self::assertArrayNotHasKey('health-wellness', $topics, 'retired topic is not seeded');

        $orgs = $this->bySlug('organization');
        self::assertArrayHasKey('maamwesying', $orgs);
        self::assertArrayHasKey('manitoulin-sudbury-dsb', $orgs);

        $services = $this->bySlug('service');
        self::assertSame('primary-health', $services['nmninoeyaa']->get('has_topic'));
        self::assertSame('serpent-river', $services['nmninoeyaa']->get('located_at'));
        self::assertSame('maamwesying', $services['nmninoeyaa']->get('provided_by'));
        // Province-wide helpline carries no place.
        self::assertSame('', $services['talk4healing-helpline']->get('located_at'));
        self::assertSame('mental-health-addictions', $services['talk4healing-helpline']->get('has_topic'));
    }

    #[Test]
    public function it_retags_sagamok_health_and_adds_a_mental_health_service(): void
    {
        new SeedGraphCommand($this->repos)->run($this->io());

        $services = $this->bySlug('service');
        self::assertSame('primary-health', $services['sagamok-health']->get('has_topic'), 'Community Wellness becomes primary-health');
        self::assertArrayHasKey('sagamok-mental-health', $services);
        self::assertSame('mental-health-addictions', $services['sagamok-mental-health']->get('has_topic'));
        self::assertSame('sagamok', $services['sagamok-mental-health']->get('located_at'));
    }

    #[Test]
    public function it_creates_linked_curated_chunks_each_with_a_source_url(): void
    {
        new SeedGraphCommand($this->repos)->run($this->io());

        $chunks = $this->byChunkKey();
        self::assertArrayHasKey('curated:nmninoeyaa', $chunks);

        $chunk = $chunks['curated:nmninoeyaa'];
        self::assertSame('service', $chunk->get('entity_type'));
        self::assertSame('nmninoeyaa', $chunk->get('entity_id'));
        self::assertStringStartsWith('https://', $chunk->getSourceUrl(), 'every curated chunk carries a source URL');
        self::assertNotSame('', $chunk->getText());
    }

    #[Test]
    public function it_backfills_page_chunks_to_the_matching_sagamok_service_without_touching_curated_chunks(): void
    {
        // A Sagamok page chunk whose text reads as mental health content.
        $this->repos['doc_chunk']->save(DocChunk::make([
            'chunk_key' => '/anokii/sagamok#mh',
            'source_url' => '/anokii/sagamok',
            'title' => 'Sagamok resources',
            'heading' => 'Health and wellness',
            'text' => 'The Community Wellness Department offers mental health and addictions counselling and crisis support.',
            'entity_type' => '',
            'entity_id' => '',
        ]));

        new SeedGraphCommand($this->repos)->run($this->io());

        $chunks = $this->byChunkKey();
        // The page chunk is linked to the Sagamok mental health service (by topic).
        self::assertSame('service', $chunks['/anokii/sagamok#mh']->get('entity_type'));
        self::assertSame('sagamok-mental-health', $chunks['/anokii/sagamok#mh']->get('entity_id'));
        // The curated chunk's own (external) link is untouched by the backfill.
        self::assertSame('nmninoeyaa', $chunks['curated:nmninoeyaa']->get('entity_id'));
        self::assertSame('https://maamwesying.ca/nmninoeyaa-aboriginal-health-access-centre', $chunks['curated:nmninoeyaa']->getSourceUrl());
    }

    #[Test]
    public function seeding_is_idempotent_on_re_run(): void
    {
        new SeedGraphCommand($this->repos)->run($this->io());
        $firstCounts = $this->counts();

        new SeedGraphCommand($this->repos)->run($this->io());
        $secondCounts = $this->counts();

        self::assertSame($firstCounts, $secondCounts, 'A re-run updates in place and never duplicates.');
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $out = [];
        foreach ($this->repos as $type => $repo) {
            $out[$type] = count($repo->findBy([]));
        }

        return $out;
    }

    /**
     * @return array<string, GraphEntityBase>
     */
    private function bySlug(string $type): array
    {
        $out = [];
        foreach ($this->repos[$type]->findBy([]) as $entity) {
            if ($entity instanceof GraphEntityBase) {
                $out[$entity->getSlug()] = $entity;
            }
        }

        return $out;
    }

    /**
     * @return array<string, DocChunk>
     */
    private function byChunkKey(): array
    {
        $out = [];
        foreach ($this->repos['doc_chunk']->findBy([]) as $chunk) {
            if ($chunk instanceof DocChunk) {
                $out[$chunk->getChunkKey()] = $chunk;
            }
        }

        return $out;
    }

    private function io(): SymfonyCommandIO
    {
        // No InputDefinition is bound, so option('dry-run') resolves to null
        // (SymfonyCommandIO catches the lookup error), i.e. a non-dry-run run.
        return new SymfonyCommandIO(new ArrayInput([]), new NullOutput());
    }

    /**
     * In-memory repository keyed by stable identity (chunk_key for doc chunks,
     * slug for graph entities) so the seeder's upserts dedupe across re-runs.
     */
    private function repository(): EntityRepositoryInterface
    {
        return new class implements EntityRepositoryInterface {
            use \App\Tests\Support\RevisionRepositoryStubs;

            /** @var array<string, EntityInterface> */
            private array $store = [];

            private function keyOf(EntityInterface $entity): string
            {
                if ($entity instanceof DocChunk) {
                    return 'chunk:' . $entity->getChunkKey();
                }
                if ($entity instanceof GraphEntityBase) {
                    return 'slug:' . $entity->getSlug();
                }

                return spl_object_hash($entity);
            }

            public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null): array
            {
                return array_values($this->store);
            }

            public function save(EntityInterface $entity, bool $validate = true): int
            {
                $this->store[$this->keyOf($entity)] = $entity;

                return 1;
            }

            public function delete(EntityInterface $entity): void
            {
                unset($this->store[$this->keyOf($entity)]);
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
