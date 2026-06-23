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
use Waaseyaa\CLI\Command\SymfonyCommandIO;
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
     * Idempotency-key prefix for chunks the seeder creates directly (the curated
     * regional tier), as opposed to chunks that app:ingest-docs extracts from
     * published pages. app:ingest-docs leaves these alone when pruning, and this
     * command's page-chunk backfill skips them.
     */
    public const CURATED_KEY_PREFIX = 'curated:';

    /**
     * @param array<string, EntityRepositoryInterface> $repos keyed by entity type id
     */
    public function __construct(
        private readonly array $repos,
        private readonly TopicVocabulary $vocabulary = new TopicVocabulary(),
    ) {}

    public function run(SymfonyCommandIO $io): int
    {
        $dryRun = (bool) $io->option('dry-run');

        $places = $this->placeRows();
        $communities = $this->communityRows();
        $topics = $this->topicRows();
        $organizations = $this->organizationRows();
        $services = $this->serviceRows();
        $projects = $this->projectRows();

        $curatedChunks = $this->curatedChunkRows();

        if ($dryRun) {
            $io->writeln(sprintf(
                'Dry run: would seed %d places, %d communities, %d topics, %d organizations, %d services, %d projects, %d curated chunks, then backfill page-chunk links.',
                count($places),
                count($communities),
                count($topics),
                count($organizations),
                count($services),
                count($projects),
                count($curatedChunks),
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

        $curated = $this->syncCuratedChunks($curatedChunks);
        $io->writeln(sprintf('  curated chunks: %s', $curated));

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
        // Map only Sagamok's own services by topic: the /anokii/sagamok page
        // chunks must link to Sagamok services, never to a regional service that
        // happens to share a topic (e.g. another primary-health provider).
        $topicToService = [];
        foreach ($services as $svc) {
            if ((string) ($svc['provided_by'] ?? '') !== 'sagamok-anishnawbek') {
                continue;
            }
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
            // Curated chunks are seeded already linked; the page-chunk backfill
            // must not relabel them (their source_url is external).
            if (str_starts_with($chunk->getChunkKey(), self::CURATED_KEY_PREFIX)) {
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

        return ['', '']; // general OIATC content
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placeRows(): array
    {
        // Public coordinates; distance is a ranking signal only, never shown as
        // travel time. The single sourced travel note (Elliot Lake) is verbatim.
        // Towns and First Nations with caller-supplied exact coordinates; the
        // address-geocoded First Nations are appended by geocodedPlaceRows().
        return array_merge([
            // Towns
            ['slug' => 'sagamok', 'name' => 'Sagamok', 'lat' => '46.1575', 'lng' => '-82.1102', 'travel_note' => ''],
            ['slug' => 'massey', 'name' => 'Massey', 'lat' => '46.2126', 'lng' => '-82.0776', 'travel_note' => ''],
            ['slug' => 'espanola', 'name' => 'Espanola', 'lat' => '46.2584', 'lng' => '-81.7665', 'travel_note' => ''],
            ['slug' => 'elliot-lake', 'name' => 'Elliot Lake', 'lat' => '46.3833', 'lng' => '-82.6500', 'travel_note' => 'about 45 minutes by road'],
            ['slug' => 'blind-river', 'name' => 'Blind River', 'lat' => '46.1884', 'lng' => '-82.9564', 'travel_note' => ''],
            ['slug' => 'greater-sudbury', 'name' => 'Greater Sudbury', 'lat' => '46.4900', 'lng' => '-80.9900', 'travel_note' => ''],
            ['slug' => 'sault-ste-marie', 'name' => 'Sault Ste. Marie', 'lat' => '46.5168', 'lng' => '-84.3333', 'travel_note' => ''],
            ['slug' => 'thessalon', 'name' => 'Thessalon', 'lat' => '46.2560', 'lng' => '-83.5564', 'travel_note' => ''],
            ['slug' => 'little-current', 'name' => 'Little Current', 'lat' => '45.9805', 'lng' => '-81.9278', 'travel_note' => ''],
            ['slug' => 'gore-bay', 'name' => 'Gore Bay', 'lat' => '45.9114', 'lng' => '-82.4598', 'travel_note' => ''],
            ['slug' => 'mindemoya', 'name' => 'Mindemoya', 'lat' => '45.7328', 'lng' => '-82.1663', 'travel_note' => ''],
            ['slug' => 'manitowaning', 'name' => 'Manitowaning', 'lat' => '45.7333', 'lng' => '-81.8000', 'travel_note' => ''],
            // First Nations with caller-supplied exact coordinates
            ['slug' => 'serpent-river', 'name' => 'Serpent River First Nation', 'lat' => '46.2021', 'lng' => '-82.4681', 'travel_note' => ''],
            ['slug' => 'atikameksheng', 'name' => 'Atikameksheng Anishnawbek', 'lat' => '46.3012', 'lng' => '-81.2567', 'travel_note' => ''],
            ['slug' => 'thessalon-first-nation', 'name' => 'Thessalon First Nation', 'lat' => '46.2607', 'lng' => '-83.4174', 'travel_note' => ''],
            ['slug' => 'mchigeeng', 'name' => "M'Chigeeng First Nation", 'lat' => '45.8232', 'lng' => '-82.1567', 'travel_note' => ''],
        ], $this->geocodedPlaceRows());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function communityRows(): array
    {
        // Region place-lists are the catchment gate: a regional service surfaces
        // from a vantage only if its Place is listed here. These lists cover the
        // North Shore and reach across to Manitoulin so the curated regional
        // services (located at serpent-river/Cutler, espanola, elliot-lake,
        // greater-sudbury, sault-ste-marie, aundeck-omni-kaning, mchigeeng) are
        // reachable. Distance still orders nearer options first.
        $northShoreAndManitoulin = ['espanola', 'spanish', 'serpent-river', 'elliot-lake', 'blind-river', 'greater-sudbury', 'sault-ste-marie', 'little-current', 'aundeck-omni-kaning', 'mchigeeng'];

        return [
            [
                'slug' => 'sagamok',
                'name' => 'Sagamok',
                'located_at' => 'sagamok',
                'region' => json_encode(array_merge(['massey'], $northShoreAndManitoulin), JSON_THROW_ON_ERROR),
            ],
            [
                'slug' => 'massey',
                'name' => 'Massey',
                'located_at' => 'massey',
                'region' => json_encode(array_merge(['sagamok'], $northShoreAndManitoulin), JSON_THROW_ON_ERROR),
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
            // Curated regional tier. Each is an independent organization; listing
            // them implies no affiliation between them or with OIATC.
            ['slug' => 'maamwesying', 'name' => 'Maamwesying North Shore Community Health Services', 'source_url' => 'https://maamwesying.ca/about-us/'],
            ['slug' => 'noojmowin-teg', 'name' => 'Noojmowin Teg Health Centre', 'source_url' => 'https://www.noojmowin-teg.ca/'],
            ['slug' => 'mamaweswen', 'name' => 'Mamaweswen, The North Shore Tribal Council', 'source_url' => 'https://mamaweswen.com/'],
            ['slug' => 'uccmm', 'name' => 'United Chiefs and Councils of Mnidoo Mnising', 'source_url' => 'http://www.uccmm.ca/'],
            ['slug' => 'manitoulin-sudbury-dsb', 'name' => 'Manitoulin-Sudbury District Services Board', 'source_url' => 'https://www.msdsb.net/'],
            ['slug' => 'adsab', 'name' => 'Algoma District Services Administration Board', 'source_url' => 'https://www.adsab.on.ca/'],
            ['slug' => 'phsd', 'name' => 'Public Health Sudbury and Districts', 'source_url' => 'https://www.phsd.ca/'],
            ['slug' => 'algoma-public-health', 'name' => 'Algoma Public Health', 'source_url' => 'https://www.algomapublichealth.com/'],
            ['slug' => 'talk4healing', 'name' => 'Talk4Healing', 'source_url' => 'https://www.talk4healing.com/'],
            ['slug' => 'hope-for-wellness', 'name' => 'Hope for Wellness Help Line', 'source_url' => 'https://www.hopeforwellness.ca/'],
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
            // Health splits into primary care and mental health/addictions. Both
            // point at the Sagamok page; the on-reserve option stays first by
            // closeness for both a primary-care and a mental-health question.
            ['sagamok-health', 'Community Wellness Department', 'primary-health'],
            ['sagamok-mental-health', 'Community Wellness (mental health and addictions)', 'mental-health-addictions'],
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

        return array_merge($rows, $this->curatedServiceRows());
    }

    /**
     * Curated regional services. Each is provided_by a regional Organization,
     * located_at a real Place (or empty for a province-wide helpline), tagged with
     * a Topic, and carries its official source URL. Province-wide helplines use an
     * empty located_at: the retriever surfaces them from any vantage as broader,
     * honestly labelled, never pinned to a town.
     *
     * @return list<array<string, mixed>>
     */
    private function curatedServiceRows(): array
    {
        $defs = [
            // [slug, name, provided_by, located_at, has_topic, source_url]
            ['nmninoeyaa', "N'Mninoeyaa Aboriginal Health Access Centre", 'maamwesying', 'serpent-river', 'primary-health', 'https://maamwesying.ca/nmninoeyaa-aboriginal-health-access-centre'],
            ['baawaating-fht', 'Baawaating Family Health Team', 'maamwesying', 'sault-ste-marie', 'primary-health', 'https://maamwesying.ca/baawaating-fht/'],
            ['maamwesying-mental-wellness', 'Maamwesying Mental Wellness and Addictions', 'maamwesying', 'serpent-river', 'mental-health-addictions', 'https://maamwesying.ca/about-us/'],
            ['noojmowin-teg-primary-care', 'Noojmowin Teg primary health care clinic', 'noojmowin-teg', 'aundeck-omni-kaning', 'primary-health', 'https://211north.ca/record/65284890/'],
            ['noojmowin-teg-mental-wellness', 'Noojmowin Teg mental wellness and counselling', 'noojmowin-teg', 'aundeck-omni-kaning', 'mental-health-addictions', 'https://211north.ca/record/65284865/agency/'],
            ['mamaweswen-niigaaniin', 'Niigaaniin social assistance (Mamaweswen)', 'mamaweswen', 'serpent-river', 'income-support', 'https://mamaweswen.com/'],
            ['mamaweswen-koognaasewin', 'Koognaasewin child and family services (Mamaweswen)', 'mamaweswen', 'serpent-river', 'child-and-family', 'https://mamaweswen.com/'],
            ['mamaweswen-isetp', 'Mamaweswen employment and training (ISETP)', 'mamaweswen', 'serpent-river', 'employment-training', 'https://mamaweswen.com/'],
            ['uccmm-justice', 'UCCMM community justice programs', 'uccmm', 'mchigeeng', 'legal-aid', 'https://211north.ca/record/65285057/'],
            ['msdsb-ontario-works', 'Manitoulin-Sudbury DSB Ontario Works', 'manitoulin-sudbury-dsb', 'espanola', 'income-support', 'https://211ontario.ca/service/71647062/manitoulin-sudbury-district-services-board-ontario-works/'],
            ['msdsb-housing', 'Manitoulin-Sudbury DSB community housing', 'manitoulin-sudbury-dsb', 'espanola', 'housing', 'https://www.msdsb.net/'],
            ['msdsb-earlyon', 'Manitoulin-Sudbury DSB EarlyON child and family', 'manitoulin-sudbury-dsb', 'espanola', 'child-and-family', 'https://211north.ca/record/65277460/'],
            ['adsab-ontario-works', 'Algoma DSAB Ontario Works', 'adsab', 'elliot-lake', 'income-support', 'https://www.adsab.on.ca/en/about-us/'],
            ['adsab-housing', 'Algoma DSAB social housing', 'adsab', 'elliot-lake', 'housing', 'https://www.adsab.on.ca/en/social-services/housing-services/'],
            ['adsab-children', "Algoma DSAB children's services", 'adsab', 'elliot-lake', 'child-and-family', 'https://www.adsab.on.ca/en/about-us/'],
            ['phsd-public-health', 'Public Health Sudbury and Districts programs', 'phsd', 'greater-sudbury', 'primary-health', 'https://www.phsd.ca/'],
            ['aph-public-health', 'Algoma Public Health programs', 'algoma-public-health', 'sault-ste-marie', 'primary-health', 'https://www.algomapublichealth.com/about-us/'],
            // Province-wide helplines: empty located_at (no single town).
            ['talk4healing-helpline', 'Talk4Healing helpline', 'talk4healing', '', 'mental-health-addictions', 'https://www.talk4healing.com/'],
            ['hope-for-wellness-line', 'Hope for Wellness Help Line', 'hope-for-wellness', '', 'mental-health-addictions', 'https://www.hopeforwellness.ca/'],
        ];

        $rows = [];
        foreach ($defs as [$slug, $name, $providedBy, $locatedAt, $topic, $sourceUrl]) {
            $rows[] = ['slug' => $slug, 'name' => $name, 'provided_by' => $providedBy, 'located_at' => $locatedAt, 'has_topic' => $topic, 'source_url' => $sourceUrl];
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
                // The Massey explainers moved to the independent RHT Members'
                // Transparency Circle; cite their live home there, not the old
                // (now 301) oiatc path. No Massey pages are ingested here anymore.
                'source_url' => 'https://rhtcircle.ca/land/massey-solar-project',
            ],
        ];
    }

    /**
     * Places geocoded from each community's official band-office or municipal
     * address (coordinates are a ranking signal only). Zhiibaahaasing First Nation
     * is intentionally omitted: its 36 Sagon Road address could not be tied to a
     * defensible office coordinate (the only centroid available resolved to
     * Cockburn Island, not the inhabited mainland community), so per the
     * accuracy-over-coverage rule it is left out rather than approximated.
     *
     * @return list<array<string, mixed>>
     */
    private function geocodedPlaceRows(): array
    {
        return [
            ['slug' => 'spanish', 'name' => 'Spanish', 'lat' => '46.1923', 'lng' => '-82.3461', 'travel_note' => ''],
            // The Township of Sables-Spanish Rivers has one municipal office, in
            // Massey; this point represents the governing office, not the Webbwood
            // locality.
            ['slug' => 'webbwood', 'name' => 'Webbwood', 'lat' => '46.2371', 'lng' => '-82.0663', 'travel_note' => ''],
            ['slug' => 'nairn-centre', 'name' => 'Nairn Centre', 'lat' => '46.3335', 'lng' => '-81.5829', 'travel_note' => ''],
            ['slug' => 'mississauga-first-nation', 'name' => 'Mississauga First Nation', 'lat' => '46.2864', 'lng' => '-83.0774', 'travel_note' => ''],
            ['slug' => 'garden-river', 'name' => 'Garden River First Nation', 'lat' => '46.5344', 'lng' => '-84.1502', 'travel_note' => ''],
            ['slug' => 'batchewana', 'name' => 'Batchewana First Nation', 'lat' => '46.5610', 'lng' => '-84.2493', 'travel_note' => ''],
            ['slug' => 'wiikwemkoong', 'name' => 'Wiikwemkoong Unceded Territory', 'lat' => '45.7973', 'lng' => '-81.7285', 'travel_note' => ''],
            ['slug' => 'sheguiandah', 'name' => 'Sheguiandah First Nation', 'lat' => '45.8641', 'lng' => '-81.9304', 'travel_note' => ''],
            ['slug' => 'aundeck-omni-kaning', 'name' => 'Aundeck Omni Kaning First Nation', 'lat' => '45.9601', 'lng' => '-81.9959', 'travel_note' => ''],
            ['slug' => 'sheshegwaning', 'name' => 'Sheshegwaning First Nation', 'lat' => '45.9334', 'lng' => '-82.8371', 'travel_note' => ''],
            ['slug' => 'whitefish-river', 'name' => 'Whitefish River First Nation', 'lat' => '46.0647', 'lng' => '-81.7822', 'travel_note' => ''],
        ];
    }

    /**
     * One short, sourced doc_chunk per curated service, created directly here (not
     * extracted from a published page). Keyed by CURATED_KEY_PREFIX so re-runs
     * update rather than duplicate and app:ingest-docs leaves them alone. Each
     * carries its official source_url and is linked to its Service via
     * (entity_type='service', entity_id=<service slug>). Text is public, sourced,
     * plain language, and implies no affiliation between the organizations.
     *
     * @return list<array<string, mixed>>
     */
    private function curatedChunkRows(): array
    {
        $defs = [
            // [service slug, organization (title), heading, text]
            ['nmninoeyaa', 'Maamwesying North Shore Community Health Services', "N'Mninoeyaa Aboriginal Health Access Centre", "N'Mninoeyaa Aboriginal Health Access Centre brings together nurse practitioners, physicians, and client care coordinators to provide primary health care, including preventative care, chronic disease management, medical assessments, pre and post natal care, smoking cessation, and immunizations. Its main office is at 473B Highway 17 West in Cutler, on Serpent River First Nation, and it works through community health centres across several North Shore First Nations."],
            ['baawaating-fht', 'Maamwesying North Shore Community Health Services', 'Baawaating Family Health Team', 'The Baawaating Family Health Team provides primary care to Indigenous and non Indigenous patients and families in the Sault Ste. Marie catchment area, with a focus on the Indigenous population. Its team includes physicians, nurse practitioners, registered nurses, social workers, dietitians, pharmacists, and occupational therapists, offering episodic care, chronic disease management, traditional health, mental health services, dietary counselling, immunizations, telemedicine, and pharmacy services.'],
            ['maamwesying-mental-wellness', 'Maamwesying North Shore Community Health Services', 'Mental wellness and addictions', "Maamwesying North Shore Community Health Services delivers mental wellness and addictions support across its member communities, including addiction services, mental wellness counselling, crisis counselling, structured psychotherapy, and access to traditional health and Elders. It is one of Maamwesying's main program areas alongside primary health care and home and community support."],
            ['noojmowin-teg-primary-care', 'Noojmowin Teg Health Centre', 'Primary health care clinic', 'Noojmowin Teg Health Centre runs a primary health care clinic for Indigenous individuals and families of Manitoulin Island and surrounding areas, combining traditional healing with Western medicine. Care is delivered by physicians, nurse practitioners, and traditional healers and includes chronic disease prevention and management, pre and post natal care, nutrition, and health education. The centre is at 48 Hillside Road on Aundeck Omni Kaning First Nation, near Little Current, open Monday to Friday.'],
            ['noojmowin-teg-mental-wellness', 'Noojmowin Teg Health Centre', 'Mental wellness and counselling', 'Noojmowin Teg Health Centre offers counselling and mental wellness services as part of its outreach programs for Manitoulin Island and surrounding areas, alongside traditional health services and supports for individuals and families.'],
            ['mamaweswen-niigaaniin', 'Mamaweswen, The North Shore Tribal Council', 'Niigaaniin social assistance', 'Niigaaniin is the social assistance program of Mamaweswen, The North Shore Tribal Council, supporting member First Nation communities across the North Shore. The tribal council is based at 473 Highway 17 West in Cutler.'],
            ['mamaweswen-koognaasewin', 'Mamaweswen, The North Shore Tribal Council', 'Koognaasewin child and family services', 'Koognaasewin is the child and family service of Mamaweswen, The North Shore Tribal Council, focused on community based child and family care, including child well being and family supports for the member First Nations.'],
            ['mamaweswen-isetp', 'Mamaweswen, The North Shore Tribal Council', 'Employment and training', 'Mamaweswen, The North Shore Tribal Council delivers employment and training programming through its Indigenous skills and employment training strategy and labour market services across the member First Nation communities.'],
            ['uccmm-justice', 'United Chiefs and Councils of Mnidoo Mnising', 'Community justice programs', "United Chiefs and Councils of Mnidoo Mnising runs Indigenous community justice programs serving member communities on Manitoulin Island, including Gladue and Gladue aftercare, bail verification and supervision, diversion, an intimate partner violence program, FASD programming, and youth prevention and re integration supports. UCCMM is based at M'Chigeeng First Nation."],
            ['msdsb-ontario-works', 'Manitoulin-Sudbury District Services Board', 'Ontario Works', 'The Manitoulin-Sudbury District Services Board delivers Ontario Works, the provincial social assistance and employment assistance program, across the Manitoulin and Sudbury districts, with offices including Espanola and Little Current.'],
            ['msdsb-housing', 'Manitoulin-Sudbury District Services Board', 'Community housing', 'The Manitoulin-Sudbury District Services Board administers community housing as one of its integrated human services for the Manitoulin and Sudbury districts.'],
            ['msdsb-earlyon', 'Manitoulin-Sudbury District Services Board', 'EarlyON child and family programs', "The Manitoulin-Sudbury District Services Board runs EarlyON child and family programs and administers subsidized child care for eligible families in the Manitoulin and Sudbury districts. Its children's services office is in Espanola, and it does not serve the City of Greater Sudbury."],
            ['adsab-ontario-works', 'Algoma District Services Administration Board', 'Ontario Works', 'The Algoma District Services Administration Board delivers Ontario Works for the District of Algoma, excluding the City of Sault Ste. Marie, through social services offices in Elliot Lake, Blind River, Thessalon, and Wawa.'],
            ['adsab-housing', 'Algoma District Services Administration Board', 'Social housing', 'The Algoma District Services Administration Board owns and manages rent geared to income social housing across the Algoma district, with rent generally set at about 30 percent of household gross monthly income, plus affordable housing and portable subsidy programs. Housing units are located in municipalities including Elliot Lake, Blind River, and Thessalon. Eligibility includes a household member aged 18 or older, legal status in Canada, and income and asset limits.'],
            ['adsab-children', 'Algoma District Services Administration Board', "Children's services", "The Algoma District Services Administration Board manages children's services, including child care and early learning, for the District of Algoma, excluding the City of Sault Ste. Marie, through offices in Elliot Lake, Blind River, Thessalon, and Wawa."],
            ['phsd-public-health', 'Public Health Sudbury and Districts', 'Public health programs', 'Public Health Sudbury and Districts delivers provincially legislated public health programs across the Sudbury and Manitoulin districts, including immunization clinics, sexual health services, environmental health, family health, and health promotion. Its main office is at 1300 Paris Street in Sudbury, with district offices including Espanola and Mindemoya on Manitoulin Island.'],
            ['aph-public-health', 'Algoma Public Health', 'Public health programs', 'Algoma Public Health delivers provincially legislated public health services and community programs for the District of Algoma, including immunization clinics, communicable disease services, sexual health, environmental health inspections, and pregnancy and parenting supports. Its main office is at 294 Willow Avenue in Sault Ste. Marie, with offices in Blind River and Elliot Lake.'],
            ['talk4healing-helpline', 'Talk4Healing', 'Mental health and addictions crisis helpline', 'Talk4Healing is a culturally grounded, confidential helpline offering mental health and addictions support and crisis help for Indigenous women and their families, operated by Beendigen. It is available 24 hours a day, 7 days a week across Ontario by phone, text, and live chat at 1-855-554-4325, in 14 languages. It is a province wide service and is not tied to a single community.'],
            ['hope-for-wellness-line', 'Hope for Wellness Help Line', 'Mental health and crisis support helpline', 'The Hope for Wellness Help Line offers immediate mental health support and crisis intervention to Indigenous people across Canada, 24 hours a day, 7 days a week, by phone at 1-855-242-3310 and through online chat. Phone and chat are available in English and French, with Cree, Ojibway, and Inuktitut available by phone on request. It is a nationwide service and is not tied to a single community.'],
        ];

        $byServiceSlug = [];
        foreach ($this->curatedServiceRows() as $svc) {
            $byServiceSlug[(string) $svc['slug']] = $svc;
        }

        $rows = [];
        foreach ($defs as [$serviceSlug, $title, $heading, $text]) {
            $svc = $byServiceSlug[$serviceSlug] ?? null;
            if ($svc === null) {
                continue;
            }
            $rows[] = [
                'chunk_key' => self::CURATED_KEY_PREFIX . $serviceSlug,
                'source_url' => (string) $svc['source_url'],
                'title' => $title,
                'heading' => $heading,
                'text' => $text,
                'entity_type' => 'service',
                'entity_id' => $serviceSlug,
            ];
        }

        return $rows;
    }

    /**
     * Upsert the curated chunks by chunk_key (create or update all fields,
     * including the entity link). Idempotent and re-runnable.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function syncCuratedChunks(array $rows): string
    {
        $repo = $this->repos['doc_chunk'];
        $existing = [];
        foreach ($repo->findBy([]) as $chunk) {
            if ($chunk instanceof DocChunk) {
                $existing[$chunk->getChunkKey()] = $chunk;
            }
        }

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $key = (string) $row['chunk_key'];
            $current = $existing[$key] ?? null;
            if ($current instanceof DocChunk) {
                foreach ($row as $field => $value) {
                    $current->set($field, $value);
                }
                $repo->save($current);
                $updated++;
                continue;
            }
            $repo->save(DocChunk::make($row));
            $created++;
        }

        return sprintf('%d created, %d updated', $created, $updated);
    }
}
