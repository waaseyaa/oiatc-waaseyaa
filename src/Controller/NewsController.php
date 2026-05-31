<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NewsPost;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Public, themed presentation for the news_post entity.
 *
 * The entity gives us storage + JSON:API CRUD + admin authoring for free;
 * this controller is only the public reading surface: the /news index, the
 * per-post pages, and the RSS feed. Posts are loaded through the repository
 * and filtered/sorted in PHP (custom fields live in the entity's _data blob,
 * so they are not SQL-sortable; volume here is small enough that this is fine).
 */
final class NewsController
{
    private const SITE = 'https://oiatc.ca';

    public function __construct(private readonly EntityRepositoryInterface $repository) {}

    public function index(Request $request): Response
    {
        $posts = $this->publishedPosts();
        $explainer = trim((string) $request->query->get('explainer', ''));

        $visible = $explainer !== ''
            ? array_values(array_filter($posts, static fn(array $p): bool => $p['related_explainer'] === $explainer))
            : $posts;

        return $this->render('news/index.html.twig', [
            'posts' => $visible,
            'active_explainer' => $explainer,
            'explainers' => $this->explainerCounts($posts),
            'total' => count($posts),
        ]);
    }

    public function show(string $slug): Response
    {
        foreach ($this->publishedPosts() as $post) {
            if ($post['slug'] === $slug) {
                return $this->render('news/post.html.twig', ['post' => $post]);
            }
        }

        return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function rss(): Response
    {
        $items = '';
        foreach (array_slice($this->publishedPosts(), 0, 50) as $post) {
            $url = self::SITE . '/news/' . rawurlencode($post['slug']);
            $items .= "    <item>\n"
                . '      <title>' . self::xml($post['title']) . "</title>\n"
                . '      <link>' . self::xml($url) . "</link>\n"
                . '      <guid isPermaLink="true">' . self::xml($url) . "</guid>\n"
                . '      <pubDate>' . gmdate('r', $post['published_at']) . "</pubDate>\n"
                . '      <description>' . self::xml(strip_tags($post['body'])) . "</description>\n"
                . "    </item>\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0"><channel>' . "\n"
            . "    <title>OIATC News</title>\n"
            . '    <link>' . self::SITE . "/news</link>\n"
            . "    <description>Short, time-stamped updates tied to OIATC explainers.</description>\n"
            . '    <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="' . self::SITE . '/news/rss.xml" rel="self" type="application/rss+xml" />' . "\n"
            . $items
            . "</channel></rss>\n";

        return new Response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    /**
     * JSON list of the newest posts for one explainer (drives the "Latest
     * updates" block injected into static explainer pages).
     */
    public function explainerUpdates(Request $request): Response
    {
        $explainer = trim((string) $request->query->get('explainer', ''));
        $items = [];
        if ($explainer !== '') {
            foreach ($this->publishedPosts() as $post) {
                if ($post['related_explainer'] === $explainer) {
                    $items[] = [
                        'title' => $post['title'],
                        'slug' => $post['slug'],
                        'published_at' => $post['published_at'],
                    ];
                    if (count($items) >= 3) {
                        break;
                    }
                }
            }
        }

        return new Response(
            json_encode(['posts' => $items], JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=120'],
        );
    }

    /**
     * All published posts, newest first, as plain arrays. Seeds the example
     * post on first access if the section is empty.
     *
     * @return list<array{title:string, slug:string, body:string, published_at:int, related_explainer:string}>
     */
    private function publishedPosts(): array
    {
        $entities = $this->repository->findBy([]);
        if ($entities === []) {
            $this->seedExample();
            $entities = $this->repository->findBy([]);
        }

        $posts = [];
        foreach ($entities as $entity) {
            if (!$entity instanceof NewsPost || !$entity->isPublished()) {
                continue;
            }
            $body = $entity->getBody();
            $posts[] = [
                'title' => $entity->getTitle(),
                'slug' => $entity->getSlug(),
                'body' => $body,
                'summary' => mb_substr(trim(strip_tags($body)), 0, 200),
                'published_at' => $entity->getPublishedAt(),
                'related_explainer' => $entity->getRelatedExplainer(),
            ];
        }

        usort($posts, static fn(array $a, array $b): int => $b['published_at'] <=> $a['published_at']);

        return $posts;
    }

    /**
     * @param list<array{related_explainer:string}> $posts
     *
     * @return list<array{slug:string, count:int}>
     */
    private function explainerCounts(array $posts): array
    {
        $counts = [];
        foreach ($posts as $post) {
            $slug = $post['related_explainer'];
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }
        $out = [];
        foreach ($counts as $slug => $count) {
            $out[] = ['slug' => $slug, 'count' => $count];
        }

        return $out;
    }

    private function seedExample(): void
    {
        $post = new NewsPost([
            'title' => 'Massey Solar Project clears its IESO contract milestone',
            'slug' => 'massey-solar-ieso-contract-awarded',
            'body' => '<p>This is an example news post. The Massey Solar Project was awarded a 20 year IESO contract on April 10, 2026, moving it from a proposal to a financed project. The environmental review under Regulation 359/09 has not started yet, so the consultation questions are still open.</p><p>Replace this post through the admin, or delete it. See the explainer for the full picture.</p>',
            'published_at' => time(),
            'related_explainer' => 'massey-solar-project',
            'status' => true,
        ]);

        $this->repository->save($post);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('News unavailable: Twig is not initialised.', 500);
        }

        return new Response(
            $twig->render($template, $context),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
