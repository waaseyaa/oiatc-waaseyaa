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

        // The ensured announcements link to four different sections; each back-link
        // resolves to the right base path, never to /explainers/ for a non-explainer.
        self::assertStringContainsString('href="/disclosure/sagamok-portal"', $html);
        self::assertStringContainsString('href="/anokii"', $html);
        self::assertStringContainsString('href="/positions/counter-disinformation"', $html);
        self::assertStringContainsString('href="/explainers/robinson-huron-treaty"', $html);
        self::assertStringNotContainsString('href="/explainers/sagamok-portal"', $html);
        self::assertStringNotContainsString('href="/explainers/anokii"', $html);
        self::assertStringNotContainsString('href="/anokii/anokii"', $html);
    }

    #[Test]
    public function the_potentia_post_renders_long_form_and_reconciles_an_existing_row(): void
    {
        // A pre-existing short row, as ensure-by-slug first created it.
        $short = new NewsPost([
            'title' => 'Potentia responds on the record to our Massey questions',
            'slug' => 'potentia-responds-massey',
            'body' => '<p>Short prior body that must be replaced.</p>',
            'published_at' => 1780358400,
            'related_explainer' => 'massey-solar-project',
            'status' => true,
        ]);
        $repo = $this->repository([$short]);

        $html = (string) new NewsController($repo)->show('potentia-responds-massey')->getContent();

        // Long-form: multiple paragraphs with bolded section labels.
        self::assertStringContainsString('<strong>Ownership.</strong>', $html);
        self::assertStringContainsString('<strong>Sagamok.</strong>', $html);
        self::assertStringContainsString('<strong>Timeline.</strong>', $html);
        self::assertStringContainsString('community drop-in sessions', $html);
        // Corrected copy: the perspective version of the Power Corporation point.
        self::assertStringContainsString('puts the common "Power Corporation project" description in perspective', $html);
        self::assertStringContainsString('wholly owned subsidiary of Power Corporation of Canada', $html);
        // Lead includes the one-sentence recap of the five questions.
        self::assertStringContainsString('The questions covered ownership and equity', $html);
        // Reported speech ("said"), and the "on the record" phrasing is gone.
        self::assertStringContainsString('Here is what the company said', $html);
        self::assertStringNotContainsString('Potentia says', $html);
        // "on the record" is gone from both the title and the body now.
        self::assertStringNotContainsString('on the record', $html);
        // The new "not addressed" paragraph and the Sagamok-no-position line.
        self::assertStringContainsString('A few things were not addressed', $html);
        self::assertStringContainsString('Sagamok has not published a formal public position', $html);
        // Meta description is the first sentence only (not the recap).
        self::assertStringContainsString('name="description" content="Potentia Renewables responded in writing to OIATC&#039;s five questions about the Massey Solar Project.">', $html);
        // The stored short row was reconciled in place, not left stale.
        self::assertStringNotContainsString('Short prior body', $html);
        // The explainer back-link CTA is preserved, with the label and title
        // separated (not run together as "explainerMassey").
        self::assertStringContainsString('href="/explainers/massey-solar-project"', $html);
        self::assertStringContainsString('>Read the full explainer</span><br>', $html);
        // The new headline reads in the H1, the <title>, and og:title; the stale
        // old title was reconciled away.
        self::assertStringContainsString('<h1>Potentia responds to our Massey questions</h1>', $html);
        self::assertStringContainsString('<title>Potentia responds to our Massey questions ', $html);
        self::assertStringContainsString('<meta property="og:title" content="Potentia responds to our Massey questions">', $html);
        self::assertStringNotContainsString('responds on the record', $html);
        // No em dashes anywhere in the rendered post.
        self::assertStringNotContainsString("\u{2014}", $html);
    }

    #[Test]
    public function reconcile_updates_the_title_in_place_when_only_the_title_differs(): void
    {
        // Seed the canonical post, then simulate a live row that has the current
        // body but the old title (the body-only reconcile check would miss this).
        $repo = $this->repository([]);
        $controller = new NewsController($repo);
        $controller->rss();
        foreach ($repo->findBy([]) as $entity) {
            if ($entity instanceof NewsPost && $entity->getSlug() === 'potentia-responds-massey') {
                $entity->set('title', 'Potentia responds on the record to our Massey questions');
            }
        }

        $controller->rss();

        $titles = [];
        foreach ($repo->findBy([]) as $entity) {
            if ($entity instanceof NewsPost && $entity->getSlug() === 'potentia-responds-massey') {
                $titles[] = $entity->getTitle();
            }
        }
        self::assertContains('Potentia responds to our Massey questions', $titles);
        self::assertNotContains('Potentia responds on the record to our Massey questions', $titles);
    }

    #[Test]
    public function the_legacy_example_post_is_replaced_in_place_with_real_copy(): void
    {
        $legacy = new NewsPost([
            'title' => 'Massey Solar Project clears its IESO contract milestone',
            'slug' => 'massey-solar-ieso-contract-awarded',
            'body' => '<p>This is an example news post. Replace this post through the admin, or delete it.</p>',
            'published_at' => 100,
            'related_explainer' => 'massey-solar-project',
            'status' => true,
        ]);
        $repo = $this->repository([$legacy]);

        new NewsController($repo)->rss();

        $found = null;
        foreach ($repo->findBy([]) as $entity) {
            if ($entity instanceof NewsPost && $entity->getSlug() === 'massey-solar-ieso-contract-awarded') {
                $found = $entity;
            }
        }
        self::assertNotNull($found);
        self::assertSame('Massey Solar clears its IESO contract milestone', $found->getTitle());
        self::assertStringNotContainsString('example', $found->getBody());
        self::assertStringContainsString('20-year IESO contract on April 10, 2026', $found->getBody());
        self::assertSame(1775779200, $found->getPublishedAt());
    }

    #[Test]
    public function a_disclosure_post_page_links_back_to_the_disclosure_section(): void
    {
        $html = (string) new NewsController($this->repository([]))->show('sagamok-portal-disclosure')->getContent();

        self::assertStringContainsString('href="/disclosure/sagamok-portal"', $html);
        self::assertStringContainsString('Read the disclosure', $html);
        self::assertStringNotContainsString('href="/explainers/sagamok-portal"', $html);
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
            $this->post('RHT post', 'rht-post', 'robinson-huron-treaty', 500),
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
            $this->post('RHT', 'rht', 'robinson-huron-treaty', 100),
        ]));

        $response = $controller->explainerUpdates(Request::create('/api/explainer-updates?explainer=no-such-explainer'));

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
        $repo = $this->repository([$this->post('Existing', 'existing', 'massey-solar-project', 100)]);
        $controller = new NewsController($repo);

        $xml = (string) $controller->rss()->getContent();
        self::assertStringContainsString('A $300-million lesson in who governs the system', $xml, 'announcement seeded into a non-empty section');

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
