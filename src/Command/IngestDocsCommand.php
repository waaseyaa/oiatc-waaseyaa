<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\DocChunk;
use App\Entity\NewsPost;
use App\Support\ChunkData;
use App\Support\DocChunker;
use Twig\Environment;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * `bin/waaseyaa app:ingest-docs [--dry-run] [--no-prune]` — extract heading-
 * delimited passages from our published pages (and optionally published news)
 * and upsert them into the `doc_chunk` entity as the RAG content source.
 *
 * Idempotent: chunks are keyed by a stable `chunk_key`; a re-run updates rows
 * whose key is unchanged, inserts new ones, and (unless --no-prune) deletes
 * stored chunks that were not regenerated this run, so the index converges to
 * exactly the current published content. No embeddings are produced here.
 */
final class IngestDocsCommand
{
    /**
     * Published pages to ingest, as source URL => Twig template. Add rows here
     * to widen the knowledge base (e.g. the Massey sub-pages or the disclosure).
     *
     * @var array<string, string>
     */
    private const PAGES = [
        // Sagamok resources now live at the Anokii Sagamok lens; ingest the content
        // partial directly so chunks carry the canonical /anokii/sagamok source_url
        // without the shell chrome.
        '/anokii/sagamok' => 'anokii/_sagamok-resources.html.twig',
        '/explainers/robinson-huron-treaty' => 'explainers/robinson-huron-treaty.html.twig',
        // Massey corpus: the explainer cluster only (no news post in this environment).
        '/explainers/massey-solar-project' => 'explainers/massey-solar-project.html.twig',
        '/explainers/massey-solar-project-voices' => 'explainers/massey-solar-project-voices.html.twig',
        '/explainers/massey-solar-project-what-youve-heard' => 'explainers/massey-solar-project-what-youve-heard.html.twig',
        // Neutral climate/environment companion. Slash source_url matches its live
        // route and canonical, and still starts with /explainers/massey-solar-project
        // so app:seed-graph links its chunks to the shared Massey Solar project
        // (reachable from the Massey vantage).
        '/explainers/massey-solar-project/climate-and-environment' => 'explainers/massey-solar-project-climate-and-environment.html.twig',
        '/explainers/where-your-data-lives' => 'explainers/where-your-data-lives.html.twig',
        '/explainers/how-sagamok-is-organized' => 'explainers/how-sagamok-is-organized.html.twig',
        '/positions/counter-disinformation' => 'positions/counter-disinformation.html.twig',
        '/positions/prescribeit' => 'positions/prescribeit.html.twig',
        '/practice/ai-in-coursework' => 'practice/ai-in-coursework.html.twig',
        '/anishinaabemowin' => 'anishinaabemowin/home.html.twig',
        '/anishinaabemowin/doll' => 'anishinaabemowin/doll.html.twig',
        '/anishinaabemowin/doll/build' => 'anishinaabemowin/doll-build.html.twig',
        '/anishinaabemowin/doll/process' => 'anishinaabemowin/doll-process.html.twig',
    ];

    public function __construct(
        private readonly EntityRepositoryInterface $chunks,
        private readonly Environment $twig,
        private readonly DocChunker $chunker = new DocChunker(),
        private readonly ?EntityRepositoryInterface $news = null,
    ) {}

    public function run(CliIO $io): int
    {
        $dryRun = (bool) $io->option('dry-run');
        $pruneOption = $io->option('prune');
        $prune = $pruneOption === null ? true : (bool) $pruneOption;

        [$chunks, $pageCount] = $this->collectChunks($io);
        $io->writeln(sprintf('Extracted %d chunks from %d sources.', count($chunks), $pageCount));

        if ($dryRun) {
            foreach (array_slice($chunks, 0, 8) as $c) {
                $io->writeln(sprintf(
                    '  [%s] %s — "%s" (%d chars)',
                    $c->chunkKey,
                    $c->sourceUrl,
                    $c->heading !== '' ? $c->heading : '(intro)',
                    mb_strlen($c->text),
                ));
            }
            $io->writeln('Dry run: no changes written.');

            return 0;
        }

        $result = self::syncChunks($this->chunks, $chunks, $prune);
        $io->writeln(sprintf(
            'doc_chunk sync: %d created, %d updated, %d deleted (%d chunks total).',
            $result['created'],
            $result['updated'],
            $result['deleted'],
            $result['total'],
        ));

        return 0;
    }

    /**
     * Render and chunk every configured source. Returns the flat chunk list and
     * the number of sources that produced content.
     *
     * @return array{0: list<ChunkData>, 1: int}
     */
    private function collectChunks(CliIO $io): array
    {
        $chunks = [];
        $sources = 0;

        foreach (self::PAGES as $sourceUrl => $template) {
            try {
                $html = $this->twig->render($template);
            } catch (\Throwable $e) {
                $io->error(sprintf('Skipped %s: could not render %s (%s).', $sourceUrl, $template, $e->getMessage()));
                continue;
            }
            $pageChunks = $this->chunker->chunkHtml($html, $sourceUrl);
            if ($pageChunks !== []) {
                $sources++;
            }
            foreach ($pageChunks as $c) {
                $chunks[] = $c;
            }
        }

        foreach ($this->newsChunks() as $c) {
            $chunks[] = $c;
            $sources++;
        }

        return [$chunks, $sources];
    }

    /**
     * Chunk published news posts (optional source). One source per post.
     *
     * @return list<ChunkData>
     */
    private function newsChunks(): array
    {
        if ($this->news === null) {
            return [];
        }

        $chunks = [];
        foreach ($this->news->findBy([]) as $post) {
            if (!$post instanceof NewsPost || !$post->isPublished()) {
                continue;
            }
            $url = '/news/' . $post->getSlug();
            foreach ($this->chunker->chunkText($post->getBody(), $url, $post->getTitle(), $post->getTitle()) as $c) {
                $chunks[] = $c;
            }
        }

        return $chunks;
    }

    /**
     * Upsert chunks by stable key and (optionally) prune stored chunks not seen
     * this run. Loads all existing chunks once and indexes in PHP, because
     * #[Field] values live in the entity's _data blob and are not SQL-filterable.
     *
     * @param list<ChunkData> $chunks
     *
     * @return array{created: int, updated: int, deleted: int, total: int}
     */
    public static function syncChunks(EntityRepositoryInterface $repo, array $chunks, bool $prune): array
    {
        $byKey = [];
        foreach ($repo->findBy([]) as $existing) {
            if ($existing instanceof DocChunk) {
                $byKey[$existing->getChunkKey()] = $existing;
            }
        }

        $seen = [];
        $created = 0;
        $updated = 0;

        foreach ($chunks as $c) {
            $seen[$c->chunkKey] = true;
            $existing = $byKey[$c->chunkKey] ?? null;
            if ($existing instanceof DocChunk) {
                $existing->set('source_url', $c->sourceUrl);
                $existing->set('title', $c->title);
                $existing->set('heading', $c->heading);
                $existing->set('text', $c->text);
                $repo->save($existing);
                $updated++;
                continue;
            }
            $repo->save(new DocChunk([
                'chunk_key' => $c->chunkKey,
                'source_url' => $c->sourceUrl,
                'title' => $c->title,
                'heading' => $c->heading,
                'text' => $c->text,
            ]));
            $created++;
        }

        $deleted = 0;
        if ($prune) {
            foreach ($byKey as $key => $existing) {
                // Curated chunks are owned by app:seed-graph, not extracted from a
                // published page, so a page-ingest run must not prune them.
                if (str_starts_with($key, SeedGraphCommand::CURATED_KEY_PREFIX)) {
                    continue;
                }
                if (!isset($seen[$key])) {
                    $repo->delete($existing);
                    $deleted++;
                }
            }
        }

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted, 'total' => count($chunks)];
    }
}
