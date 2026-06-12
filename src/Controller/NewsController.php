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
        $changed = $this->healLegacyExample($entities);
        $changed = $this->reconcileManagedPost($entities, $this->potentiaPost()) || $changed;
        $changed = $this->reconcileManagedPost($entities, $this->prescribeitPost()) || $changed;
        $changed = $this->reconcileManagedPost($entities, $this->masseyConsultationPost()) || $changed;
        $changed = $this->healRenamedLanguagePost($entities) || $changed;
        $changed = $this->reconcileManagedPost($entities, $this->languageProjectPost()) || $changed;
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
     * One-time, self-healing replacement of the retired bootstrap example post.
     * The old row shares the massey-solar-ieso-contract-awarded slug, which the
     * ensure-by-slug pass will not overwrite, so where that placeholder row still
     * exists we update it in place to the real, permanent copy. After this runs
     * once the placeholder is gone and the check is a no-op.
     *
     * @param list<EntityInterface> $entities
     */
    private function healLegacyExample(array $entities): bool
    {
        foreach ($entities as $entity) {
            if ($entity instanceof NewsPost && str_contains($entity->getBody(), 'This is an example news post')) {
                foreach ($this->masseyContractPost() as $field => $value) {
                    $entity->set($field, $value);
                }
                $this->repository->save($entity);

                return true;
            }
        }

        return false;
    }

    /**
     * The real, permanent Massey IESO contract post (stable slug).
     *
     * @return array<string, mixed>
     */
    private function masseyContractPost(): array
    {
        return [
            'title' => 'Massey Solar clears its IESO contract milestone',
            'slug' => 'massey-solar-ieso-contract-awarded',
            'body' => '<p>The Massey Solar Project was awarded a 20-year IESO contract on April 10, 2026, moving it from a proposal to a financed project. The environmental review under Regulation 359/09 still has to happen before construction can begin.</p>',
            // 2026-04-10 00:00:00 UTC
            'published_at' => 1775779200,
            'related_explainer' => 'massey-solar-project',
            'status' => true,
        ];
    }

    /**
     * The comprehensive, self-contained Potentia response post (long-form).
     * Body is HTML so it renders as paragraphs through {{ post.body|raw }}.
     *
     * @return array<string, mixed>
     */
    private function potentiaPost(): array
    {
        return [
            'title' => 'Potentia responds to our Massey questions',
            'slug' => 'potentia-responds-massey',
            'body' => '<p>Potentia Renewables responded in writing to OIATC\'s five questions about the Massey Solar Project, a proposed 141-megawatt solar farm about 14 kilometres from Massey that holds a 20-year provincial electricity contract but still needs Ontario\'s environmental approval before construction. The questions covered ownership and equity, how the two First Nation partners were chosen and whether Sagamok was approached, the consultation plan for the provincial review, the response to local water and wildlife concerns, and the construction timeline. Patrick Russell, a project manager at Potentia, sent the response on June 2, 2026. Here is what the company said.</p>'
                . '<p><strong>Ownership.</strong> Massey Solar Inc. is an Ontario corporation. 51 per cent is held collectively through subsidiaries of Wahnapitae First Nation and Atikameksheng Anishnawbek. 49 per cent is held through subsidiaries of Power Sustainable Energy Infrastructure Partnership (PSEIP), a private renewable energy fund. Potentia is an affiliate of PSEIP and acts as the developer and construction-services provider, not the 49 per cent owner. The split between the two First Nations inside the 51 per cent was not disclosed. This puts the common "Power Corporation project" description in perspective. The project company is majority First Nations held, while the 49 per cent minority sits with PSEIP, which is managed by Power Sustainable, a wholly owned subsidiary of Power Corporation of Canada.</p>'
                . '<p><strong>Why these two Nations.</strong> Potentia said Atikameksheng and Wahnapitae were chosen as Robinson Huron Treaty signatories for the treaty area where the project sits.</p>'
                . '<p><strong>Sagamok.</strong> Potentia said it has been in discussions with Sagamok Anishnawbek since the fall of 2025 about economic benefit opportunities, and is exploring all forms of economic benefit, which can include equity in the project. It said it intends to continue through the approval process. Sagamok\'s reserve and territory sit closer to the site than either equity partner\'s.</p>'
                . '<p><strong>The environmental review.</strong> Potentia said specialist consultants have begun the studies required for the Renewable Energy Approval: natural environment surveys, species-at-risk screening, wetland confirmation, noise modelling, water and drainage assessments, and archaeological and cultural heritage work. The application itself has not been filed.</p>'
                . '<p><strong>On local concerns.</strong> Potentia said it will run a detailed hydrogeological study, that panel materials are non-hazardous, and that solar facilities pose minimal risk to drinking water. It said vegetation will be managed mainly by mechanical methods such as mowing and trimming, with low-impact approaches that may include native plantings and managed grazing. Potentia said it does not intend to use herbicides at the site, and would turn to them only if invasive species required control and only in agreement with the municipality and landowners.</p>'
                . '<p><strong>Timeline.</strong> Potentia said the timeline runs studies through 2026 into 2027, permitting to 2028, construction beginning in 2028, and operation in 2029, if all approvals are received.</p>'
                . '<p>A few things were not addressed. Potentia did not detail the public and Indigenous consultation plan for the approval, or say whether pre-application discussions with the ministry have begun. Sagamok has not published a formal public position on the project.</p>'
                . '<p>Potentia declined a live interview for now, but welcomed talking with OIATC at the upcoming community drop-in sessions.</p>',
            // 2026-06-02 00:00:00 UTC
            'published_at' => 1780358400,
            'related_explainer' => 'massey-solar-project',
            'status' => true,
        ];
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
     * The Massey Solar consultation venue pause post (long-form, flowing
     * paragraphs). Body is HTML so it renders through {{ post.body|raw }}.
     *
     * @return array<string, mixed>
     */
    private function masseyConsultationPost(): array
    {
        return [
            'title' => 'Massey Solar\'s first public open houses are paused over the venue',
            'slug' => 'massey-solar-open-houses-paused',
            'body' => '<p>The public consultation for the Massey Solar Project was set to begin this month, and its first step is already on hold. Potentia Renewables scheduled two open houses, on June 10 and 11 at the Massey Public Library, as the start of public consultation for the proposed 141-megawatt solar farm near Massey. Those sessions have been paused while the company looks for a new venue.</p>'
                . '<p>As reported by <a href="https://www.myespanolanow.com/author/rosalind/" target="_blank" rel="noopener">Rosalind Russell</a>, the library was considered an unsuitable location because the sessions could disrupt library services, and Potentia is now seeking another venue.</p>'
                . '<p>The open houses are part of the Renewable Energy Approval, the provincial environmental review that OIATC\'s explainer has pointed to as the stage where the groundwater, wildlife, and consultation questions are formally studied. The project holds a 20-year provincial electricity contract but still needs that approval before it can be built.</p>'
                . '<p>Opposition has been steady. The Massey Wildlife Conservation Committee says it gathered more than 2,000 signatures asking the township to withdraw its support, and the Ontario Federation of Agriculture, the Manitoulin North Shore Federation of Agriculture, and the Massey Agricultural Society have raised concerns. Council voted in March against rescinding its support. Some residents have also questioned why the library was chosen in the first place, reading it as a way to manage the setting; the reported reason for the pause is the disruption to library services.</p>'
                . '<p>OIATC will keep tracking the consultation as it proceeds.</p>',
            // 2026-06-02 00:00:00 UTC
            'published_at' => 1780358400,
            'related_explainer' => 'massey-solar-project',
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
                . '<p>This is not a plan waiting for funding. A corpus pipeline, a first lesson app, transcription and translation helpers, and a suite of Anishinaabemowin learning games on minoo.live are already running. The work is organized into four streams on one principle, that the technology serves the relationships and not the other way around: record fluent speakers now, build low-pressure places to hear the language, ship a structured course on a shared open core, and keep the language content community-controlled and gated even as the code stays open source. It is led by Russell Jones in Sagamok Anishnawbek and built on Waaseyaa, the framework OIATC stewards.</p>'
                . '<p>The page is the project\'s public home, not a finished statement. It carries a dated progress log, newest first, that will be extended as the work proceeds, so anyone can follow what has shipped and what is still being decided. Read it at <a href="/anishinaabemowin">/anishinaabemowin</a>.</p>',
            // 2026-06-12 00:00:00 UTC
            'published_at' => 1781222400,
            'related_explainer' => 'anishinaabemowin',
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
            'add-your-voice' => 'OIATC has built a simple way for members to weigh in on the questions that matter. The first one: where our data actually lives.',
            'prescribeit-governance-failure' => 'Ottawa has shut down PrescribeIT, its federal e-prescribing program, after spending close to $300-million.',
            'massey-solar-open-houses-paused' => 'Potentia\'s first public open houses for the Massey Solar Project, set for June 10 and 11, have been paused while the company seeks a new venue.',
            'massey-solar-drop-in-sessions-fire-hall' => 'Potentia has moved the Massey Solar Project\'s community drop-in sessions to the Massey Fire Hall, with dates in June and July 2026.',
            'anishinaabemowin-project-published' => 'OIATC has published a project section for Anishinaabemowin, a live, community-owned effort to record fluent speakers and keep the language alive.',
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
            $this->masseyContractPost(),
            [
                'title' => 'A member resource on the Robinson Huron Treaty',
                'slug' => 'robinson-huron-treaty-explainer',
                'body' => '<p>OIATC has published a plain-language explainer on the Robinson Huron Treaty, the annuity settlement, and what it means for members. It is a living resource, updated as the picture changes.</p>',
                // 2026-05-14 00:00:00 UTC
                'published_at' => 1778716800,
                'related_explainer' => 'robinson-huron-treaty',
                'status' => true,
            ],
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
                'title' => 'A responsible disclosure on the Sagamok members portal',
                'slug' => 'sagamok-portal-disclosure',
                'body' => '<p>OIATC has published a responsible disclosure. The Sagamok members-only portal serves member content to anyone, because the login gate is client-side only. The public page carries no URLs, passwords, or member content.</p>',
                // 2026-05-31 00:00:00 UTC
                'published_at' => 1780185600,
                'related_explainer' => 'sagamok-portal',
                'status' => true,
            ],
            [
                'title' => "Where does your community's data actually live?",
                'slug' => 'where-your-data-lives-explainer',
                'body' => '<p>A new explainer maps where community data physically goes, from origin servers to global copies, and what the US CLOUD Act and OCAP mean for it. It is a companion to the Sagamok portal disclosure.</p>',
                // 2026-05-31 01:00:00 UTC (after the disclosure post on the same day)
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
            $this->potentiaPost(),
            $this->prescribeitPost(),
            $this->masseyConsultationPost(),
            $this->languageProjectPost(),
            [
                'title' => 'Add your voice, and a tool built to keep your data home',
                'slug' => 'add-your-voice',
                'body' => '<p>Understanding how a community is run is the first step. Acting on it is the next. OIATC has built a simple way for members to do that: add your voice to a question and have it counted.</p>'
                    . '<p>The first campaign is the one that prompted the tool, our own data. When you sign up to a community website, where does your information actually go, whose laws can reach it, and who controls it? Our plain-language explainer walks through <a href="/explainers/where-your-data-lives">where your data lives</a>.</p>'
                    . '<p>The tool itself is built the way that explainer argues data should be handled. It runs on OIATC\'s own server, in Canada, under our control, and it asks for the least it can. No US platform sits in the middle, and nothing tracks you. The data stays home.</p>'
                    . '<p>Read the explainer, and when you are ready, <a href="/explainers/where-your-data-lives">add your voice</a>.</p>',
                // 2026-06-04 00:00:00 UTC
                'published_at' => 1780531200,
                'related_explainer' => 'where-your-data-lives',
                'status' => true,
            ],
            [
                'title' => 'Massey Solar\'s drop-in sessions move to the Massey Fire Hall',
                'slug' => 'massey-solar-drop-in-sessions-fire-hall',
                'body' => '<p>The Massey Solar Project\'s community drop-in sessions, paused in early June over the venue, now have a new home. Potentia Renewables has notified the community that the sessions will take place at the Massey Fire Hall (Imperial St N, Massey), moved from the Massey Public Library to accommodate the number of community members interested in attending.</p>'
                    . '<p>The schedule: Wednesday June 10 and Wednesday July 22 from 4:00pm to 7:00pm, and Thursday June 11 and Thursday July 23 from 11:00am to 2:00pm. The company says the times are unchanged from the original notice; only the venue has moved. Questions can go to info@masseysolar.ca.</p>'
                    . '<p>These drop-in sessions are part of the project\'s public engagement. The Massey Solar Project holds a 20-year provincial electricity contract but still needs Ontario\'s Renewable Energy Approval, the environmental review where the groundwater, wildlife, wetland, and consultation questions are formally studied, before it can be built.</p>',
                // 2026-06-09 00:00:00 UTC
                'published_at' => 1780963200,
                'related_explainer' => 'massey-solar-project',
                'status' => true,
            ],
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
