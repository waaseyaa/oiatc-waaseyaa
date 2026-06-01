<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Community;
use App\Entity\DocChunk;
use App\Entity\GraphEntityBase;
use App\Entity\Organization;
use App\Entity\Place;
use App\Entity\Project;
use App\Entity\Service;
use App\Entity\Topic;
use App\Support\TopicVocabulary;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * `bin/waaseyaa app:seed-graph [--dry-run]` — seed the Anokii relational graph
 * (Places with coordinates, Communities with curated regions, Topics, the
 * Sagamok organization and its topic Services, and the shared Massey Solar
 * Project) and backfill each doc_chunk's link to its source entity.
 *
 * Idempotent: every entity is keyed by a stable slug, so a re-run updates rather
 * than duplicates. All data is public and sourced; no member data. Coordinates
 * are a ranking signal only; the one sourced travel note (Elliot Lake) is stored
 * verbatim and never computed. Run after `app:ingest-docs` so the chunks exist.
 */
final class SeedGraphCommand
{
    /**
     * @param array<string, EntityRepositoryInterface> $repos keyed by entity type id
     */
    public function __construct(
        private readonly array $repos,
        private readonly TopicVocabulary $vocabulary = new TopicVocabulary(),
    ) {}

    public function run(CliIO $io): int
    {
        $dryRun = (bool) $io->option('dry-run');

        $places = $this->placeRows();
        $communities = $this->communityRows();
        $topics = $this->topicRows();
        $organizations = $this->organizationRows();
        $services = $this->serviceRows();
        $projects = $this->projectRows();

        if ($dryRun) {
            $io->writeln(sprintf(
                'Dry run: would seed %d places, %d communities, %d topics, %d organizations, %d services, %d projects, then backfill chunk links.',
                count($places),
                count($communities),
                count($topics),
                count($organizations),
                count($services),
                count($projects),
            ));

            return 0;
        }

        $io->writeln('Seeding graph...');
        $io->writeln(sprintf('  topics:        %s', $this->sync('topic', $topics, static fn(array $v): Topic => Topic::make($v))));
        $io->writeln(sprintf('  places:        %s', $this->sync('place', $places, static fn(array $v): Place => Place::make($v))));
        $io->writeln(sprintf('  communities:   %s', $this->sync('community', $communities, static fn(array $v): Community => Community::make($v))));
        $io->writeln(sprintf('  organizations: %s', $this->sync('organization', $organizations, static fn(array $v): Organization => Organization::make($v))));
        $io->writeln(sprintf('  services:      %s', $this->sync('service', $services, static fn(array $v): Service => Service::make($v))));
        $io->writeln(sprintf('  projects:      %s', $this->sync('project', $projects, static fn(array $v): Project => Project::make($v))));

        $linked = $this->backfillChunks($services);
        $io->writeln(sprintf('Linked %d doc_chunk rows to source entities (%d to services, %d to the Massey project, %d left general).', $linked['total'], $linked['service'], $linked['project'], $linked['general']));

        return 0;
    }

    /**
     * Upsert a list of slug-keyed rows into one entity type's repository.
     *
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): GraphEntityBase $make
     */
    private function sync(string $type, array $rows, callable $make): string
    {
        $repo = $this->repos[$type];
        $existing = [];
        foreach ($repo->findBy([]) as $entity) {
            if ($entity instanceof GraphEntityBase) {
                $existing[$entity->getSlug()] = $entity;
            }
        }

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $current = $existing[$slug] ?? null;
            if ($current instanceof GraphEntityBase) {
                foreach ($row as $field => $value) {
                    $current->set($field, $value);
                }
                $repo->save($current);
                $updated++;
                continue;
            }
            $repo->save($make($row));
            $created++;
        }

        return sprintf('%d created, %d updated', $created, $updated);
    }

    /**
     * Link each doc_chunk to its source entity by slug: Sagamok resource chunks
     * to the topic-matched Service (generic fallback otherwise), Massey explainer
     * chunks to the shared Massey Solar Project, everything else left general.
     *
     * @param list<array<string, mixed>> $services
     *
     * @return array{total: int, service: int, project: int, general: int}
     */
    private function backfillChunks(array $services): array
    {
        $topicToService = [];
        foreach ($services as $svc) {
            $topic = (string) $svc['has_topic'];
            if ($topic !== '') {
                $topicToService[$topic] = (string) $svc['slug'];
            }
        }

        $repo = $this->repos['doc_chunk'];
        $counts = ['total' => 0, 'service' => 0, 'project' => 0, 'general' => 0];

        foreach ($repo->findBy([]) as $chunk) {
            if (!$chunk instanceof DocChunk) {
                continue;
            }
            $url = $chunk->getSourceUrl();
            [$type, $id] = $this->linkFor($url, $chunk->getHeading() . ' ' . $chunk->getText(), $topicToService);

            $chunk->set('entity_type', $type);
            $chunk->set('entity_id', $id);
            $repo->save($chunk);

            $counts['total']++;
            $counts[$type === 'service' ? 'service' : ($type === 'project' ? 'project' : 'general')]++;
        }

        return $counts;
    }

    /**
     * @param array<string, string> $topicToService
     *
     * @return array{0: string, 1: string} [entity_type, entity_id]
     */
    private function linkFor(string $url, string $text, array $topicToService): array
    {
        if ($url === '/anokii/sagamok') {
            $topic = $this->vocabulary->infer($text);
            $service = ($topic !== null && isset($topicToService[$topic])) ? $topicToService[$topic] : 'sagamok-resources';

            return ['service', $service];
        }

        if (str_starts_with($url, '/explainers/massey-solar-project')) {
            return ['project', 'massey-solar'];
        }

        return ['', '']; // general OIATC content
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placeRows(): array
    {
        // Public coordinates; distance is a ranking signal only, never shown as
        // travel time. The single sourced travel note (Elliot Lake) is verbatim.
        return [
            ['slug' => 'sagamok', 'name' => 'Sagamok', 'lat' => '46.1575', 'lng' => '-82.1102', 'travel_note' => ''],
            ['slug' => 'massey', 'name' => 'Massey', 'lat' => '46.2126', 'lng' => '-82.0776', 'travel_note' => ''],
            ['slug' => 'espanola', 'name' => 'Espanola', 'lat' => '46.2584', 'lng' => '-81.7665', 'travel_note' => ''],
            ['slug' => 'elliot-lake', 'name' => 'Elliot Lake', 'lat' => '46.3833', 'lng' => '-82.6500', 'travel_note' => 'about 45 minutes by road'],
            ['slug' => 'blind-river', 'name' => 'Blind River', 'lat' => '46.1884', 'lng' => '-82.9564', 'travel_note' => ''],
            ['slug' => 'greater-sudbury', 'name' => 'Greater Sudbury', 'lat' => '46.4900', 'lng' => '-80.9900', 'travel_note' => ''],
            ['slug' => 'sault-ste-marie', 'name' => 'Sault Ste. Marie', 'lat' => '46.5168', 'lng' => '-84.3333', 'travel_note' => ''],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function communityRows(): array
    {
        return [
            [
                'slug' => 'sagamok',
                'name' => 'Sagamok',
                'located_at' => 'sagamok',
                'region' => json_encode(['massey', 'espanola', 'elliot-lake', 'blind-river', 'greater-sudbury', 'sault-ste-marie'], JSON_THROW_ON_ERROR),
            ],
            [
                'slug' => 'massey',
                'name' => 'Massey',
                'located_at' => 'massey',
                'region' => json_encode(['espanola', 'sagamok', 'elliot-lake', 'greater-sudbury', 'sault-ste-marie'], JSON_THROW_ON_ERROR),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topicRows(): array
    {
        $rows = [];
        foreach ($this->vocabulary->all() as $slug => $topic) {
            $rows[] = ['slug' => $slug, 'name' => $topic['name'], 'keywords' => implode(' ', $topic['keywords'])];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function organizationRows(): array
    {
        return [
            ['slug' => 'sagamok-anishnawbek', 'name' => 'Sagamok Anishnawbek', 'source_url' => '/anokii/sagamok'],
        ];
    }

    /**
     * Sagamok services, one per topic area plus a generic fallback. Names mirror
     * the public Sagamok resources page; contacts/figures live in the chunk text,
     * never here, so nothing is invented.
     *
     * @return list<array<string, mixed>>
     */
    private function serviceRows(): array
    {
        $org = 'sagamok-anishnawbek';
        $place = 'sagamok';
        $url = '/anokii/sagamok';
        $defs = [
            ['sagamok-housing', 'Housing Department', 'housing'],
            ['sagamok-employment', 'Lifelong Learning Centre', 'employment-training'],
            ['sagamok-business', 'Economic Development', 'business'],
            ['sagamok-health', 'Community Wellness Department', 'health-wellness'],
            ['sagamok-finance', 'Finance Department', 'finance'],
            ['sagamok-membership', 'Membership', 'membership-status'],
            ['sagamok-lands', 'Lands, Resources and Environment', 'lands-environment'],
            ['sagamok-education', 'Education', 'education-youth'],
            ['sagamok-resources', 'Sagamok member resources', ''],
        ];
        $rows = [];
        foreach ($defs as [$slug, $name, $topic]) {
            $rows[] = ['slug' => $slug, 'name' => $name, 'provided_by' => $org, 'located_at' => $place, 'has_topic' => $topic, 'source_url' => $url];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectRows(): array
    {
        return [
            [
                'slug' => 'massey-solar',
                'name' => 'Massey Solar Project',
                'relates_to' => json_encode(['sagamok', 'massey'], JSON_THROW_ON_ERROR),
                'located_at' => 'massey',
                'has_topic' => 'energy-solar',
                'source_url' => '/explainers/massey-solar-project',
            ],
        ];
    }
}
