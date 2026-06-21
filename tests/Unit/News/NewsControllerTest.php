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
    public static function setUpBeforeClass(): void
    {
        $provider = new \Waaseyaa\SSR\SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 3), [], []);
        $provider->boot();
    }

    #[Test]
    public function news_index_resolves_section_back_links_for_each_section(): void
    {
        $html = (string) new NewsController($this->repository([]))->index(Request::create('/news'))->getContent();

        // The ensured announcements link to several sections; each back-link
        // resolves to the right base path, never to /explainers/ for a non-explainer.
        self::assertStringContainsString('href="/anokii"', $html);
        self::assertStringContainsString('href="/positions/counter-disinformation"', $html);
        self::assertStringContainsString('href="/explainers/where-your-data-lives"', $html);
        self::assertStringNotContainsString('href="/explainers/anokii"', $html);
        self::assertStringNotContainsString('href="/anokii/anokii"', $html);
        // Retired/migrated posts no longer appear or link internally.
        self::assertStringNotContainsString('href="/disclosure/sagamok-portal"', $html);
        self::assertStringNotContainsString('href="/explainers/robinson-huron-treaty"', $html);
        self::assertStringNotContainsString('href="/explainers/massey-solar-project"', $html);
    }

    #[Test]
    public function reconcile_updates_the_title_in_place_when_only_the_title_differs(): void
    {
        // Seed the canonical posts, then simulate a live row that has the current
        // body but the old title (the body-only reconcile check would miss this).
        $repo = $this->repository([]);
        $controller = new NewsController($repo);
        $controller->rss();
        foreach ($repo->findBy([]) as $entity) {
            if ($entity instanceof NewsPost && $entity->getSlug() === 'prescribeit-governance-failure') {
                $entity->set('title', 'A $300-million lesson in who governs the system');
            }
        }

        $controller->rss();

        $titles = [];
        foreach ($repo->findBy([]) as $entity) {
            if ($entity instanceof NewsPost && $entity->getSlug() === 'prescribeit-governance-failure') {
                $titles[] = $entity->getTitle();
            }
        }
        self::assertContains('Ottawa shut down its $298-million e-prescribing program', $titles);
        self::assertNotContains('A $300-million lesson in who governs the system', $titles);
    }

    #[Test]
    public function a_post_without_a_generated_card_falls_back_to_the_default_og_image(): void
    {
        $repo = $this->repository([$this->post('No card', 'totally-unknown-card-slug', 'where-your-data-lives', 1)]);

        $html = (string) new NewsController($repo)->show('totally-unknown-card-slug')->getContent();

        self::assertStringContainsString('<meta property="og:image" content="https://oiatc.ca/images/og-default.png">', $html);
    }

    #[Test]
    public function explainer_updates_returns_newest_three_published_posts_for_that_explainer(): void
    {
        // Use a synthetic explainer slug so the always-ensured product
        // announcements (which target real sections) do not enter this fixture.
        $controller = new NewsController($this->repository([
            $this->post('Old demo', 'old-demo', 'demo-explainer', 100),
            $this->post('New demo', 'new-demo', 'demo-explainer', 400),
            $this->post('Mid demo', 'mid-demo', 'demo-explainer', 300),
            $this->post('Oldest demo', 'oldest-demo', 'demo-explainer', 50),
            $this->post('Other post', 'other-post', 'where-your-data-lives', 500),
            $this->post('Unpublished demo', 'hidden', 'demo-explainer', 999, false),
        ]));

        $response = $controller->explainerUpdates(Request::create('/api/explainer-updates?explainer=demo-explainer'));
        $data = json_decode($response->getContent(), true);

        $slugs = array_column($data['posts'], 'slug');

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(3, $data['posts'], 'capped at three');
        self::assertSame(['new-demo', 'mid-demo', 'old-demo'], $slugs, 'newest first, excludes other explainer and unpublished');
    }

    #[Test]
    public function explainer_updates_is_empty_without_a_match(): void
    {
        $controller = new NewsController($this->repository([
            $this->post('Other', 'other', 'where-your-data-lives', 100),
        ]));

        $response = $controller->explainerUpdates(Request::create('/api/explainer-updates?explainer=no-such-explainer'));

        self::assertSame(['posts' => []], json_decode($response->getContent(), true));
    }

    #[Test]
    public function rss_lists_published_posts_newest_first_with_escaping(): void
    {
        $controller = new NewsController($this->repository([
            $this->post('Older', 'older', 'where-your-data-lives', 100),
            $this->post('Tom & Jerry', 'amp', 'where-your-data-lives', 200),
            $this->post('Hidden', 'hidden', 'where-your-data-lives', 300, false),
        ]));

        $response = $controller->rss();
        $xml = (string) $response->getContent();

        self::assertStringContainsString('application/rss+xml', (string) $response->headers->get('Content-Type'));
        // The controller always ensures the editorial announcement exists, so the
        // feed carries the two published fixtures plus that announcement (the
        // unpublished fixture is still excluded).
        self::assertStringNotContainsString('<title>Hidden</title>', $xml, 'unpublished excluded');
        self::assertStringContainsString('Tom &amp; Jerry', $xml, 'XML-escaped title');
        self::assertStringContainsString('<title>Older</title>', $xml);
        self::assertLessThan(strpos($xml, 'Older'), strpos($xml, 'Tom'), 'newest first');
    }

    #[Test]
    public function the_prescribeit_announcement_is_ensured_by_slug_even_when_other_posts_exist(): void
    {
        $repo = $this->repository([$this->post('Existing', 'existing', 'where-your-data-lives', 100)]);
        $controller = new NewsController($repo);

        $xml = (string) $controller->rss()->getContent();
        self::assertStringContainsString('Ottawa shut down its $298-million e-prescribing program', $xml, 'announcement seeded into a non-empty section');

        // Persisted, links to the position, and idempotent (a second pass does not duplicate it).
        $controller->rss();
        $announcements = [];
        foreach ($repo->findBy([]) as $entity) {
            if ($entity instanceof NewsPost && $entity->getSlug() === 'prescribeit-governance-failure') {
                $announcements[] = $entity;
            }
        }
        self::assertCount(1, $announcements, 'seeded once, not duplicated');
        self::assertStringContainsString('/positions/prescribeit', $announcements[0]->getBody(), 'body links to the position');
    }

    #[Test]
    public function the_prescribeit_post_renders_long_form_and_reconciles_an_existing_row(): void
    {
        $short = new NewsPost([
            'title' => 'A $300-million lesson in who governs the system',
            'slug' => 'prescribeit-governance-failure',
            'body' => '<p>Short prior prescribeit body.</p>',
            'published_at' => 1780358400,
            'related_explainer' => 'prescribeit',
            'status' => true,
        ]);
        $repo = $this->repository([$short]);

        $html = (string) new NewsController($repo)->show('prescribeit-governance-failure')->getContent();

        // Long-form body with bolded section labels.
        self::assertStringContainsString('<strong>The program.</strong>', $html);
        self::assertStringContainsString('<strong>The money.</strong>', $html);
        self::assertStringContainsString('<strong>The First Nations layer.</strong>', $html);
        self::assertStringContainsString('<strong>How OIATC reads it.</strong>', $html);
        // New headline in H1, <title>, og:title.
        self::assertStringContainsString('<h1>Ottawa shut down its $298-million e-prescribing program</h1>', $html);
        self::assertStringContainsString('<title>Ottawa shut down its $298-million e-prescribing program ', $html);
        self::assertStringContainsString('<meta property="og:title" content="Ottawa shut down its $298-million e-prescribing program">', $html);
        // The short body and old title were reconciled away.
        self::assertStringNotContainsString('Short prior prescribeit body', $html);
        self::assertStringNotContainsString('A $300-million lesson in who governs the system', $html);
        // CTA to the position.
        self::assertStringContainsString('href="/positions/prescribeit"', $html);
        self::assertStringContainsString('Read the OIATC position', $html);
        // Meta description is the explicit short sentence (not the long lead).
        self::assertStringContainsString('name="description" content="Ottawa has shut down PrescribeIT, its federal e-prescribing program, after spending close to $300-million.">', $html);
        // Past tense, no "on the record", no em dashes.
        self::assertStringNotContainsString('on the record', $html);
        self::assertStringNotContainsString("\u{2014}", $html);
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
        return new class ($entities) implements EntityRepositoryInterface {
            use \App\Tests\Support\RevisionRepositoryStubs;

            /** @param list<EntityInterface> $entities */
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
