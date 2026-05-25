<?php

declare(strict_types=1);

namespace App\Tests\Unit\News;

use App\Controller\NewsController;
use App\Entity\NewsPost;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class NewsControllerTest extends TestCase
{
    #[Test]
    public function explainer_updates_returns_newest_three_published_posts_for_that_explainer(): void
    {
        $controller = new NewsController($this->repository([
            $this->post('Old massey', 'old-massey', 'massey-solar-project', 100),
            $this->post('New massey', 'new-massey', 'massey-solar-project', 400),
            $this->post('Mid massey', 'mid-massey', 'massey-solar-project', 300),
            $this->post('Oldest massey', 'oldest-massey', 'massey-solar-project', 50),
            $this->post('RHT post', 'rht-post', 'robinson-huron-treaty', 500),
            $this->post('Unpublished massey', 'hidden', 'massey-solar-project', 999, false),
        ]));

        $response = $controller->explainerUpdates(Request::create('/api/explainer-updates?explainer=massey-solar-project'));
        $data = json_decode($response->getContent(), true);

        $slugs = array_column($data['posts'], 'slug');

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(3, $data['posts'], 'capped at three');
        self::assertSame(['new-massey', 'mid-massey', 'old-massey'], $slugs, 'newest first, excludes other explainer and unpublished');
    }

    #[Test]
    public function explainer_updates_is_empty_without_a_match(): void
    {
        $controller = new NewsController($this->repository([
            $this->post('RHT', 'rht', 'robinson-huron-treaty', 100),
        ]));

        $response = $controller->explainerUpdates(Request::create('/api/explainer-updates?explainer=massey-solar-project'));

        self::assertSame(['posts' => []], json_decode($response->getContent(), true));
    }

    #[Test]
    public function rss_lists_published_posts_newest_first_with_escaping(): void
    {
        $controller = new NewsController($this->repository([
            $this->post('Older', 'older', 'massey-solar-project', 100),
            $this->post('Tom & Jerry', 'amp', 'massey-solar-project', 200),
            $this->post('Hidden', 'hidden', 'massey-solar-project', 300, false),
        ]));

        $response = $controller->rss();
        $xml = $response->getContent();

        self::assertStringContainsString('application/rss+xml', (string) $response->headers->get('Content-Type'));
        self::assertSame(2, substr_count($xml, '<item>'), 'only published posts');
        self::assertStringContainsString('Tom &amp; Jerry', $xml, 'XML-escaped title');
        self::assertLessThan(strpos($xml, 'Older'), strpos($xml, 'Tom'), 'newest first');
    }

    private function post(string $title, string $slug, string $explainer, int $publishedAt, bool $published = true): NewsPost
    {
        return new NewsPost([
            'title' => $title,
            'slug' => $slug,
            'body' => '<p>' . $title . '</p>',
            'published_at' => $publishedAt,
            'related_explainer' => $explainer,
            'status' => $published,
        ]);
    }

    /**
     * @param list<NewsPost> $entities
     */
    private function repository(array $entities): EntityRepositoryInterface
    {
        return new class($entities) implements EntityRepositoryInterface {
            /** @param list<NewsPost> $entities */
            public function __construct(private array $entities) {}

            public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null): array
            {
                return $this->entities;
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
                $this->entities[] = $entity;

                return 1;
            }

            public function delete(EntityInterface $entity): void {}

            public function exists(string $id): bool
            {
                return false;
            }

            public function count(array $criteria = []): int
            {
                return count($this->entities);
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
