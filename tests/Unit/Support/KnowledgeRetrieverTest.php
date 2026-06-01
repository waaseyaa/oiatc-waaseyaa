<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Entity\DocChunk;
use App\Support\KnowledgeRetriever;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class KnowledgeRetrieverTest extends TestCase
{
    #[Test]
    public function housing_question_returns_the_housing_chunk_first(): void
    {
        $top = $this->retriever()->retrieve('How do I apply for housing?', 3);

        self::assertNotSame([], $top);
        self::assertSame('Apply for housing', $top[0]->heading);
        self::assertSame('/resources/sagamok', $top[0]->sourceUrl);
    }

    #[Test]
    public function business_question_returns_the_business_chunk_first(): void
    {
        $top = $this->retriever()->retrieve('I want to start a business', 3);

        self::assertSame('Start or grow a business', $top[0]->heading);
    }

    #[Test]
    public function per_capita_question_returns_the_finance_chunk_first(): void
    {
        $top = $this->retriever()->retrieve('when is per capita paid out', 3);

        self::assertStringContainsString('per capita', strtolower($top[0]->heading));
    }

    #[Test]
    public function off_corpus_question_returns_nothing(): void
    {
        self::assertSame([], $this->retriever()->retrieve('what is the weather forecast tomorrow'));
    }

    #[Test]
    public function a_query_of_only_stopwords_returns_nothing(): void
    {
        self::assertSame([], $this->retriever()->retrieve('how do i'));
    }

    private function retriever(): KnowledgeRetriever
    {
        return new KnowledgeRetriever($this->repository([
            $this->chunk('Apply for housing', 'The Housing Department handles housing applications, rentals, rent-to-own, and self-help loans. To apply for housing, contact the Housing Department; the Housing Director is William Caldwell.'),
            $this->chunk('Start or grow a business', "Business support sits in three doors: Sagamok Development Corporation offers entrepreneurial support for starting a business, band Economic Development, and Z'gamok Enterprises for the resource sector."),
            $this->chunk('Ask about per capita or pre-authorized deposit', 'Per capita distribution and pre-authorized deposit are handled by Finance. Contact Finance directly rather than the online forms.'),
            $this->chunk('Health and wellness', 'The Community Wellness Department runs out of the health centre. See a nurse, mental health and addictions support, and medical transportation.'),
        ]));
    }

    private function chunk(string $heading, string $text): DocChunk
    {
        return new DocChunk([
            'chunk_key' => '/resources/sagamok#' . strtolower(str_replace(' ', '-', $heading)),
            'source_url' => '/resources/sagamok',
            'title' => 'Sagamok member resources',
            'heading' => $heading,
            'text' => $text,
        ]);
    }

    /**
     * @param list<DocChunk> $chunks
     */
    private function repository(array $chunks): EntityRepositoryInterface
    {
        return new class ($chunks) implements EntityRepositoryInterface {
            /** @param list<DocChunk> $chunks */
            public function __construct(private array $chunks) {}

            public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null): array
            {
                return $this->chunks;
            }

            public function find(string $id, ?string $langcode = null, bool $fallback = false): ?EntityInterface
            {
                return null;
            }

            public function findMany(array $ids, ?string $langcode = null, bool $fallback = false): array
            {
                return [];
            }

            public function save(EntityInterface $entity, bool $validate = true): int
            {
                return 1;
            }

            public function delete(EntityInterface $entity): void {}

            public function exists(string $id): bool
            {
                return false;
            }

            public function count(array $criteria = []): int
            {
                return count($this->chunks);
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
