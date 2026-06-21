<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NewsPost;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityInterface;
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
            . "    <description>Time-stamped updates from OIATC, newest first.</description>\n"
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
     * All published posts, newest first, as plain arrays. Posts are managed
     * through the admin or the ensured editorial set below; there is no
     * empty-section bootstrap example.
     *
     * @return list<array{title:string, slug:string, body:string, summary:string, meta_description:string, og_image:string, published_at:int, related_explainer:string}>
     */
    private function publishedPosts(): array
    {
        $entities = $this->repository->findBy([]);
        $changed = $this->reconcileManagedPost($entities, $this->prescribeitPost());
        $changed = $this->healRenamedLanguagePost($entities) || $changed;
        $changed = $this->reconcileManagedPost($entities, $this->languageProjectPost()) || $changed;
        $changed = $this->reconcileManagedPost($entities, $this->languageDollPost()) || $changed;
        $changed = $this->reconcileManagedPost($entities, $this->sovereignAiPositionPost()) || $changed;
        $changed = $this->reconcileManagedPost($entities, $this->councilMembersPost()) || $changed;
        $changed = $this->ensureAnnouncements($entities) || $changed;
        if ($changed) {
            $entities = $this->repository->findBy([]);
        }

        $posts = [];
        foreach ($entities as $entity) {
            if (!$entity instanceof NewsPost || !$entity->isPublished()) {
                continue;
            }
            $body = $entity->getBody();
            $plain = trim(strip_tags($body));
            $posts[] = [
                'title' => $entity->getTitle(),
                'slug' => $entity->getSlug(),
                'body' => $body,
                // List excerpt: a short truncation. Meta description: an explicit
                // short sentence where a managed post sets one, else the first
                // sentence (length-guarded).
                'summary' => mb_substr($plain, 0, 200),
                'meta_description' => $this->explicitMeta($entity->getSlug()) ?? $this->metaDescription($plain),
                'og_image' => $this->ogImageFor($entity->getSlug()),
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

    /**
     * The comprehensive, self-contained PrescribeIT post (long-form). Body is
     * HTML so it renders as paragraphs through {{ post.body|raw }}.
     *
     * @return array<string, mixed>
     */
    private function prescribeitPost(): array
    {
        return [
            'title' => 'Ottawa shut down its $298-million e-prescribing program',
            'slug' => 'prescribeit-governance-failure',
            'body' => '<p>Ottawa has shut down PrescribeIT, the federal "axe the fax" program built to replace fax machines for sending prescriptions between doctors and pharmacies, after spending close to $300-million on a service that never carried more than five per cent of the country\'s prescriptions. Here is what happened, and how OIATC reads it.</p>'
                . '<p><strong>The program.</strong> PrescribeIT was run by Canada Health Infoway, a government-funded non-profit, from 2017, and built by Telus Health. It went offline on May 29, 2026. Adoption never arrived: fewer than five per cent of prescriptions in Canada were sent through it. A fee of twenty cents per prescription, introduced in 2025 to help fund the service, pushed some pharmacists to drop it.</p>'
                . '<p><strong>The money.</strong> Early estimates put federal spending around $250-million. Health Canada told a House of Commons committee the actual figure was more than $298-million, of which about $98-million went to Telus.</p>'
                . '<p><strong>The intellectual property.</strong> A Telus executive said the company kept about 85 per cent of the intellectual property behind PrescribeIT and is considering relaunching it. Health Canada confirmed the government holds no intellectual property in the program it paid to build.</p>'
                . '<p><strong>The fallout.</strong> The executive who led Canada Health Infoway was later removed from the role, and Conservative MPs asked the Auditor General to investigate the spending. Ottawa said PrescribeIT will be replaced by a national e-prescribing standard it hopes private health-technology companies will adopt.</p>'
                . '<p><strong>The First Nations layer.</strong> Canada Health Infoway\'s own Indigenous health program listed First Nations-led deployment of PrescribeIT among its partnership lines. So a system chosen federally, built by a vendor that kept the rights, and switched off from the centre was, in part, being deployed into First Nations settings.</p>'
                . '<p><strong>How OIATC reads it.</strong> OIATC has published a <a href="/positions/prescribeit">position</a> arguing the failure was one of governance, not technology. Adoption was assumed and mandated from outside rather than grown from the people who had to use it, a pricing change made in Ottawa drove users away, and the rights to a public investment ended up in private hands. The question OIATC keeps asking is who chose the system, and who can change it.</p>'
                . '<p>What replaces PrescribeIT, and whether the communities a system serves get any say in choosing and governing it, is the thing to watch.</p>',
            // 2026-06-02 00:00:00 UTC
            'published_at' => 1780358400,
            'related_explainer' => 'prescribeit',
            'status' => true,
        ];
    }

    /**
     * The Anishinaabemowin project announcement (long-form, flowing paragraphs).
     * Body is HTML so it renders through {{ post.body|raw }}; related_explainer
     * 'anishinaabemowin' points the post CTA at the top-level /anishinaabemowin
     * project home.
     *
     * @return array<string, mixed>
     */
    private function languageProjectPost(): array
    {
        return [
            'title' => 'A working project to keep Anishinaabemowin alive',
            'slug' => 'anishinaabemowin-project-published',
            'body' => '<p>OIATC has published a project section for Anishinaabemowin, a live, community-owned effort to keep the language alive through the next twenty years. The stakes are in the numbers. The 2021 Census put the average age of the Ojibway mother-tongue population at 48, the second oldest among the major Algonquian languages, and fluent speakers are concentrated among Elders on both sides of the border.</p>'
                . '<p>This is not a plan waiting for funding. A corpus pipeline, a first lesson app, and transcription and translation helpers are already running, and a suite of Anishinaabemowin learning games shipped on Minoo, the community platform built on Waaseyaa. The work is organized into four streams on one principle, that the technology serves the relationships and not the other way around: record fluent speakers now, build low-pressure places to hear the language, ship a structured course on a shared open core, and keep the language content community-controlled and gated even as the code stays open source. It is led by Russell Jones in Sagamok Anishnawbek and built on Waaseyaa, the framework OIATC stewards.</p>'
                . '<p>The page is the project\'s public home, not a finished statement. It carries a dated progress log, newest first, that will be extended as the work proceeds, so anyone can follow what has shipped and what is still being decided. Read it at <a href="/anishinaabemowin">/anishinaabemowin</a>.</p>',
            // 2026-06-12 00:00:00 UTC
            'published_at' => 1781222400,
            'related_explainer' => 'anishinaabemowin',
            'status' => true,
        ];
    }

    /**
     * The talking-doll announcement (short, two paragraphs). Body is HTML so it
     * renders through {{ post.body|raw }}; related_explainer 'anishinaabemowin-doll'
     * points the post CTA at /anishinaabemowin/doll. The Elder and the commenter
     * who raised the idea both stay unnamed here.
     *
     * @return array<string, mixed>
     */
    private function languageDollPost(): array
    {
        return [
            'title' => 'A doll that speaks Anishinaabemowin',
            'slug' => 'anishinaabemowin-doll-plan',
            'body' => '<p>The idea arrived twice on the same day. In a community thread about the future of the language, a commenter suggested putting fluent speakers\' voices into a learning doll, with the recordings kept by the Nations themselves. Russell Jones had been carrying the same idea. When two people who have never met describe the same object, you build it.</p>'
                . '<p>OIATC has published the plan, from idea to a child holding one: a soft doll sewn in community with a fluent Elder\'s voice inside, Anishinaabemowin on the first squeeze and English on the second, no screen, no app, nothing online. The page carries the whole build, parts list and costs included, and the consent process that puts the Elder\'s agreement at the centre of every step. Read it at <a href="/anishinaabemowin/doll">/anishinaabemowin/doll</a>.</p>',
            // 2026-06-12 12:00:00 UTC (after the project-section post on the same day)
            'published_at' => 1781265600,
            'related_explainer' => 'anishinaabemowin-doll',
            'status' => true,
        ];
    }

    /**
     * The sovereign-AI position announcement (short, two paragraphs). Body is
     * HTML so it renders through {{ post.body|raw }}; related_explainer
     * 'sovereign-ai' is in the positions set, so the CTA points at
     * /positions/sovereign-ai. The access loss is kept structural (no individual
     * named as the person who lost access).
     *
     * @return array<string, mixed>
     */
    private function sovereignAiPositionPost(): array
    {
        return [
            'title' => 'When a foreign order can switch off your AI',
            'slug' => 'sovereign-ai-position',
            'body' => '<p>On the evening of June 12, 2026, a United States export order forced a major AI company to cut off its two most capable models to every foreign national, worldwide, overnight. Because the company could not separate foreign users from the rest in real time, it shut the models off for everyone. People in Canada who were using the tool an hour earlier lost it the same night.</p>'
                . '<p>OIATC has published its position on what that means for First Nations. The lesson is not which model to use. The model is the swappable layer, and sovereignty lives in the data a community governs under OCAP and the infrastructure it controls. Read it at <a href="/positions/sovereign-ai">/positions/sovereign-ai</a>.</p>',
            // 2026-06-13 00:00:00 UTC
            'published_at' => 1781308800,
            'related_explainer' => 'sovereign-ai',
            'status' => true,
        ];
    }

    /**
     * The council-members announcement (short, flowing paragraphs). Body is HTML
     * so it renders through {{ post.body|raw }}; related_explainer 'council'
     * points the post CTA at /about (the council page). Two bold lead-ins, one
     * per new member; Steven Bennett's first mention links to his public page.
     *
     * @return array<string, mixed>
     */
    private function councilMembersPost(): array
    {
        return [
            'title' => 'OIATC welcomes an Elder and a director to the council',
            'slug' => 'council-elder-and-director',
            'body' => '<p>OIATC has added two people to the council as it incorporates as a not-for-profit: an Elder and Knowledge Keeper, and a director. The council stays small, and grows on fit rather than urgency.</p>'
                . '<p><strong><a href="https://www.facebook.com/profile.php?id=61582894730998" target="_blank" rel="noopener">Steven Bennett</a></strong> joins as the council\'s Elder and Knowledge Keeper. He is a fluent speaker who shares his teachings of Anishinaabemowin publicly, and his recorded teachings are the foundation of OIATC\'s language program, used with his agreement. He guides the council on language, on protocol, and on how community knowledge is used.</p>'
                . '<p><strong>Oliver Zielke</strong> joins as a director. A former Director at Web Networks, the non-profit worker co-op that provides OIATC\'s hosting foundation, he brings a background in non-profit and open-source technology, and governance experience as OIATC incorporates.</p>'
                . '<p>Full profiles are on the <a href="/about">council page</a>.</p>',
            // 2026-06-15 00:00:00 UTC
            'published_at' => 1781481600,
            'related_explainer' => 'council',
            'status' => true,
        ];
    }

    /**
     * One-time, self-healing rename of the first-ship announcement. The project
     * shipped first as a practice case study with slug
     * `anishinaabemowin-program-published`; it is now a top-level project. Where
     * that old row still exists, rename it in place to the new slug and copy the
     * canonical project fields, so ensure-by-slug does not create a duplicate.
     * After this runs once the old slug is gone and the check is a no-op.
     *
     * @param list<EntityInterface> $entities
     */
    private function healRenamedLanguagePost(array $entities): bool
    {
        foreach ($entities as $entity) {
            if ($entity instanceof NewsPost && $entity->getSlug() === 'anishinaabemowin-program-published') {
                foreach ($this->languageProjectPost() as $field => $value) {
                    $entity->set($field, $value);
                }
                $this->repository->save($entity);

                return true;
            }
        }

        return false;
    }

    /**
     * Update a managed post in place when its stored body differs from the
     * canonical definition. ensure-by-slug will not overwrite an existing row,
     * so this is how a definition change reaches a live row on next read.
     *
     * @param list<EntityInterface> $entities
     * @param array<string, mixed> $canonical
     */
    private function reconcileManagedPost(array $entities, array $canonical): bool
    {
        foreach ($entities as $entity) {
            if ($entity instanceof NewsPost
                && $entity->getSlug() === (string) $canonical['slug']
                && ($entity->getBody() !== (string) $canonical['body']
                    || $entity->getTitle() !== (string) $canonical['title'])
            ) {
                foreach ($canonical as $field => $value) {
                    $entity->set($field, $value);
                }
                $this->repository->save($entity);

                return true;
            }
        }

        return false;
    }

    /**
     * The first sentence of a plain-text string, for a one-line meta description.
     */
    private function firstSentence(string $plain): string
    {
        $end = mb_strpos($plain, '. ');

        return $end !== false ? mb_substr($plain, 0, $end + 1) : $plain;
    }

    /**
     * A meta description: the first sentence, but if that sentence runs long
     * (over 160 characters) fall back to its first clause so the description
     * stays short. Keeps the description usable when a lead sentence carries a
     * long trailing qualifier.
     */
    private function metaDescription(string $plain): string
    {
        $sentence = $this->firstSentence($plain);
        if (mb_strlen($sentence) <= 160) {
            return $sentence;
        }

        $comma = mb_strpos($sentence, ', ');

        return $comma !== false ? mb_substr($sentence, 0, $comma) . '.' : mb_substr($sentence, 0, 157) . '...';
    }

    /**
     * An explicit meta description for a managed post whose lead sentence does
     * not make a good standalone description. Null means derive it instead.
     */
    private function explicitMeta(string $slug): ?string
    {
        return match ($slug) {
            'prescribeit-governance-failure' => 'Ottawa has shut down PrescribeIT, its federal e-prescribing program, after spending close to $300-million.',
            'anishinaabemowin-project-published' => 'OIATC has published a project section for Anishinaabemowin, a live, community-owned effort to record fluent speakers and keep the language alive.',
            'anishinaabemowin-doll-plan' => 'OIATC has published the plan for a doll that speaks Anishinaabemowin: a fluent Elder\'s voice in a child\'s hands, offline, recordings held by the community.',
            'sovereign-ai-position' => 'OIATC\'s position on a US export order that switched off two major AI models worldwide overnight, and what it means for First Nations sovereignty.',
            'council-elder-and-director' => 'OIATC has added two people to the council as it incorporates: Steven Bennett as Elder and Knowledge Keeper, and Oliver Zielke as a director.',
            default => null,
        };
    }

    /**
     * Absolute URL of the social card for a post: the per-post card generated by
     * scripts/generate-og.js if it has been rendered and committed, otherwise the
     * branded default.
     */
    private function ogImageFor(string $slug): string
    {
        $relative = '/images/og/news/' . $slug . '.png';
        $absolute = dirname(__DIR__, 2) . '/public' . $relative;

        return is_file($absolute) ? self::SITE . $relative : self::SITE . '/images/og-default.png';
    }

    /**
     * Published posts as a plain list (title, slug, meta description), for the
     * app:news-og-manifest command that feeds the OG card generator.
     *
     * @return list<array{title: string, slug: string, meta_description: string}>
     */
    public function publishedList(): array
    {
        return array_map(
            static fn(array $post): array => [
                'title' => $post['title'],
                'slug' => $post['slug'],
                'meta_description' => $post['meta_description'],
            ],
            $this->publishedPosts(),
        );
    }

    /**
     * Ensure the editorial announcement posts exist, seeding any whose slug is
     * absent. Unlike the bootstrap example (seeded only when the section is
     * empty), these are real posts published from the repo, so they are ensured
     * by slug even when other posts already exist.
     *
     * @param list<EntityInterface> $entities
     */
    private function ensureAnnouncements(array $entities): bool
    {
        $have = [];
        foreach ($entities as $entity) {
            if ($entity instanceof NewsPost) {
                $have[$entity->getSlug()] = true;
            }
        }

        $saved = false;
        foreach ($this->announcementPosts() as $row) {
            if (!isset($have[(string) $row['slug']])) {
                $this->repository->save(new NewsPost($row));
                $saved = true;
            }
        }

        return $saved;
    }

    /**
     * Editorial announcement posts published from the repo.
     *
     * @return list<array<string, mixed>>
     */
    private function announcementPosts(): array
    {
        return [
            [
                'title' => 'Our position on counter-disinformation',
                'slug' => 'counter-disinformation-position',
                'body' => '<p>OIATC has published its position on countering disinformation in and about First Nations communities. It sets out the stance and the principles behind it.</p>',
                // 2026-05-28 00:00:00 UTC
                'published_at' => 1779926400,
                'related_explainer' => 'counter-disinformation',
                'status' => true,
            ],
            [
                'title' => "Where does your community's data actually live?",
                'slug' => 'where-your-data-lives-explainer',
                'body' => '<p>A new explainer maps where community data physically goes, from origin servers to global copies, and what the US CLOUD Act and OCAP mean for it.</p>',
                // 2026-05-31 01:00:00 UTC
                'published_at' => 1780189200,
                'related_explainer' => 'where-your-data-lives',
                'status' => true,
            ],
            [
                'title' => 'Anokii is live on oiatc.ca',
                'slug' => 'anokii-launch',
                'body' => '<p>OIATC has launched Anokii, a geography-aware resource instance with a grounded, cited assistant. Communities are vantage points on one shared map, not silos. It is live for Sagamok and Massey.</p>',
                // 2026-06-01 00:00:00 UTC
                'published_at' => 1780272000,
                'related_explainer' => 'anokii',
                'status' => true,
            ],
            $this->prescribeitPost(),
            $this->languageProjectPost(),
            $this->languageDollPost(),
            $this->sovereignAiPositionPost(),
            $this->councilMembersPost(),
        ];
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
