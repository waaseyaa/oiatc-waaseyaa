<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\GraphRetriever;
use App\Support\Passage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;

final class GraphRetrieverTest extends TestCase
{
    #[Test]
    public function sagamok_primary_care_question_surfaces_the_on_reserve_clinic_first_then_the_region(): void
    {
        $top = $this->retriever()->retrieve('I need to see a doctor for primary care', 'sagamok', 6);

        self::assertNotSame([], $top);
        self::assertSame('/anokii/sagamok', $top[0]->sourceUrl);
        self::assertSame('Primary care', $top[0]->heading);
        self::assertSame('Sagamok', $top[0]->relationship, 'On-reserve primary care leads.');

        // A regional Indigenous health body is reachable behind the on-reserve one.
        $labels = array_map(static fn(Passage $p): string => $p->relationship, $top);
        self::assertContains('Serpent River First Nation (region)', $labels);
    }

    #[Test]
    public function sagamok_mental_health_question_reaches_regional_and_province_wide_options_on_reserve_first(): void
    {
        $top = $this->retriever()->retrieve('mental health and addictions support', 'sagamok', 6);

        self::assertNotSame([], $top);
        // On-reserve mental health option leads.
        self::assertSame('/anokii/sagamok', $top[0]->sourceUrl);
        self::assertSame('Mental health and addictions', $top[0]->heading);
        self::assertSame('Sagamok', $top[0]->relationship);

        $labels = array_map(static fn(Passage $p): string => $p->relationship, $top);
        // A regional Indigenous health body, then a province-wide helpline labelled
        // by its own name (never pinned to a town, never implied to be OIATC).
        self::assertContains('Serpent River First Nation (region)', $labels);
        self::assertContains('Talk4Healing helpline', $labels);
        self::assertNotContains('OIATC', $labels, 'A province-wide service must not be labelled OIATC.');

        // Everything stays grounded and citable.
        foreach ($top as $passage) {
            self::assertNotSame('', $passage->sourceUrl);
        }
    }

    #[Test]
    public function a_province_wide_helpline_is_reachable_from_a_vantage_with_no_own_service(): void
    {
        // Massey has no mental health service of its own; it still reaches the
        // region and the province-wide helpline.
        $top = $this->retriever()->retrieve('mental health and addictions support', 'massey', 6);

        self::assertNotSame([], $top);
        $labels = array_map(static fn(Passage $p): string => $p->relationship, $top);
        self::assertContains('Talk4Healing helpline', $labels);
        // The nearest region option (Sagamok) outranks the farther Serpent River one.
        self::assertStringContainsString('region', $top[0]->relationship);
        self::assertSame('/anokii/sagamok', $top[0]->sourceUrl);
    }

    #[Test]
    public function a_shared_project_is_reachable_from_a_related_community(): void
    {
        $top = $this->retriever()->retrieve('solar energy battery project', 'sagamok', 3);

        self::assertNotSame([], $top);
        self::assertSame('https://rhtcircle.ca/land/massey-solar-project', $top[0]->sourceUrl);
        self::assertStringContainsString('shared project', $top[0]->relationship);
    }

    #[Test]
    public function general_oiatc_content_stays_answerable_from_any_vantage(): void
    {
        $top = $this->retriever()->retrieve('robinson huron treaty annuity', 'sagamok', 3);

        self::assertNotSame([], $top);
        self::assertSame('https://rhtcircle.ca/treaty-wide/the-treaty', $top[0]->sourceUrl);
        self::assertSame('OIATC', $top[0]->relationship);
        // The dedicated explainer is genuinely the most relevant; a related entity
        // that only mentions the treaty in passing (the solar project) must not be
        // dropped-in-its-place nor cited over it.
        $urls = array_map(static fn(Passage $p): string => $p->sourceUrl, $top);
        self::assertNotContains('https://rhtcircle.ca/land/massey-solar-project', $urls);
    }

    #[Test]
    public function an_income_support_question_cites_only_the_on_topic_services_not_broader_pages(): void
    {
        // The treaty (general) page clears the keyword gate on incidental overlap
        // ("apply", "Ontario Works", "income support"), and the solar project is a
        // related shared project; both are off topic. With a confident
        // income-support match (the District Services Boards), neither may be
        // grounded on or cited.
        $top = $this->retriever()->retrieve('How do I apply for Ontario Works income support?', 'sagamok', 6);

        self::assertNotSame([], $top);

        $urls = array_map(static fn(Passage $p): string => $p->sourceUrl, $top);
        sort($urls);
        self::assertSame(
            ['https://www.adsab.on.ca/en/about-us/', 'https://www.msdsb.net/'],
            array_values(array_unique($urls)),
            'only the on-topic District Services Boards are cited',
        );
        self::assertNotContains('https://rhtcircle.ca/land/massey-solar-project', $urls, 'off-topic shared project must not be cited');
        self::assertNotContains('https://rhtcircle.ca/treaty-wide/the-treaty', $urls, 'off-topic treaty page must not be cited');

        // Cross-community reach is intact: both region DSBs are present and labelled.
        $labels = array_map(static fn(Passage $p): string => $p->relationship, $top);
        self::assertContains('Espanola (region)', $labels);
        self::assertContains('Elliot Lake (region, about 45 minutes by road)', $labels);
    }

    #[Test]
    public function off_corpus_question_returns_nothing(): void
    {
        self::assertSame([], $this->retriever()->retrieve('xylophone zebra quasar', 'sagamok'));
    }

    #[Test]
    public function a_clear_single_topic_question_cites_only_on_topic_sources(): void
    {
        // Housing is a strong own-community match, so the weakly-overlapping
        // general pages (and the off-topic health/regional services) must be
        // dropped, not padded in and cited.
        $top = $this->retriever()->retrieve('How do I apply for housing?', 'sagamok', 6);

        self::assertNotSame([], $top);
        self::assertSame('Apply for housing', $top[0]->heading);
        self::assertSame('/anokii/sagamok', $top[0]->sourceUrl);

        $urls = array_map(static fn(Passage $p): string => $p->sourceUrl, $top);
        self::assertNotContains('/explainers/where-your-data-lives', $urls, 'data-sovereignty page must not be cited');
        self::assertNotContains('https://rhtcircle.ca/treaty-wide/the-treaty', $urls, 'RHT page must not be cited');
        self::assertSame(['/anokii/sagamok'], array_values(array_unique($urls)), 'only the on-topic Sagamok source remains');
    }

    #[Test]
    public function it_returns_nothing_when_the_graph_is_not_seeded(): void
    {
        $retriever = new GraphRetriever(DBALDatabase::createSqlite(':memory:'));

        self::assertSame([], $retriever->retrieve('mental health', 'sagamok'));
    }

    private function retriever(): GraphRetriever
    {
        $db = DBALDatabase::createSqlite(':memory:');
        foreach (['place', 'community', 'service', 'project'] as $t) {
            $db->query("CREATE TABLE {$t} (name TEXT, _data TEXT)");
        }
        $db->query('CREATE TABLE doc_chunk (title TEXT, _data TEXT)');

        $this->place($db, 'Sagamok', ['slug' => 'sagamok', 'lat' => '46.1575', 'lng' => '-82.1102', 'travel_note' => '']);
        $this->place($db, 'Massey', ['slug' => 'massey', 'lat' => '46.2126', 'lng' => '-82.0776', 'travel_note' => '']);
        $this->place($db, 'Serpent River First Nation', ['slug' => 'serpent-river', 'lat' => '46.2021', 'lng' => '-82.4681', 'travel_note' => '']);
        $this->place($db, 'Elliot Lake', ['slug' => 'elliot-lake', 'lat' => '46.3833', 'lng' => '-82.6500', 'travel_note' => 'about 45 minutes by road']);
        $this->place($db, 'Sault Ste. Marie', ['slug' => 'sault-ste-marie', 'lat' => '46.5168', 'lng' => '-84.3333', 'travel_note' => '']);
        $this->place($db, 'Espanola', ['slug' => 'espanola', 'lat' => '46.2584', 'lng' => '-81.7665', 'travel_note' => '']);

        $this->row($db, 'community', 'Sagamok', ['slug' => 'sagamok', 'located_at' => 'sagamok', 'region' => json_encode(['massey', 'serpent-river', 'elliot-lake', 'espanola', 'sault-ste-marie'])]);
        $this->row($db, 'community', 'Massey', ['slug' => 'massey', 'located_at' => 'massey', 'region' => json_encode(['sagamok', 'serpent-river', 'elliot-lake', 'espanola', 'sault-ste-marie'])]);

        // Sagamok's own services: primary care and mental health both point at the
        // Sagamok page, plus housing.
        $this->row($db, 'service', 'Community Wellness Department', ['slug' => 'sagamok-health', 'located_at' => 'sagamok', 'has_topic' => 'primary-health']);
        $this->row($db, 'service', 'Community Wellness (mental health and addictions)', ['slug' => 'sagamok-mental-health', 'located_at' => 'sagamok', 'has_topic' => 'mental-health-addictions']);
        $this->row($db, 'service', 'Housing Department', ['slug' => 'sagamok-housing', 'located_at' => 'sagamok', 'has_topic' => 'housing']);
        // Regional Indigenous health body in the catchment.
        $this->row($db, 'service', "N'Mninoeyaa Aboriginal Health Access Centre", ['slug' => 'nmninoeyaa', 'located_at' => 'serpent-river', 'has_topic' => 'primary-health']);
        $this->row($db, 'service', 'Maamwesying Mental Wellness and Addictions', ['slug' => 'maamwesying-mental-wellness', 'located_at' => 'serpent-river', 'has_topic' => 'mental-health-addictions']);
        // Province-wide helpline: empty located_at.
        $this->row($db, 'service', 'Talk4Healing helpline', ['slug' => 'talk4healing-helpline', 'located_at' => '', 'has_topic' => 'mental-health-addictions']);
        // Income-support region services (District Services Boards).
        $this->row($db, 'service', 'Manitoulin-Sudbury DSB Ontario Works', ['slug' => 'msdsb-ow', 'located_at' => 'espanola', 'has_topic' => 'income-support']);
        $this->row($db, 'service', 'Algoma DSAB Ontario Works', ['slug' => 'adsab-ow', 'located_at' => 'elliot-lake', 'has_topic' => 'income-support']);

        $this->row($db, 'project', 'Massey Solar Project', ['slug' => 'massey-solar', 'located_at' => 'massey', 'has_topic' => 'energy-solar', 'relates_to' => json_encode(['sagamok', 'massey'])]);

        $this->chunk($db, 'Primary care', 'The Community Wellness Department provides primary care with a nurse and access to a doctor and clinic services.', '/anokii/sagamok', 'service', 'sagamok-health');
        $this->chunk($db, 'Mental health and addictions', 'The Community Wellness Department offers mental health and addictions counselling and crisis support.', '/anokii/sagamok', 'service', 'sagamok-mental-health');
        $this->chunk($db, 'Apply for housing', 'The Housing Department handles housing rentals, rent-to-own, and self-help loans. To apply for housing, contact the Housing Department.', '/anokii/sagamok', 'service', 'sagamok-housing');
        $this->chunk($db, 'Primary health care clinic', "N'Mninoeyaa provides primary health care with nurse practitioners and physicians for North Shore First Nations.", 'https://maamwesying.ca/nmninoeyaa-aboriginal-health-access-centre', 'service', 'nmninoeyaa');
        $this->chunk($db, 'Mental wellness and addictions', 'Maamwesying delivers mental wellness and addictions counselling and crisis support across member communities.', 'https://maamwesying.ca/about-us/', 'service', 'maamwesying-mental-wellness');
        $this->chunk($db, 'Mental health and addictions crisis helpline', 'Talk4Healing is a confidential helpline offering mental health and addictions support and crisis help, available across Ontario by phone, text, and chat.', 'https://www.talk4healing.com/', 'service', 'talk4healing-helpline');
        // Mentions the treaty only in passing (one overlapping term), so a treaty
        // query matches it weakly; the dedicated RHT explainer must win and this
        // incidental mention must be gated out, not cited over it.
        $this->chunk($db, 'The project itself', 'The Massey Solar Project is a solar energy and battery storage project near treaty lands.', 'https://rhtcircle.ca/land/massey-solar-project', 'project', 'massey-solar');
        // Two general OIATC pages that weakly overlap the housing query (they
        // contain "apply" but not "housing"); the relevance gate must drop them.
        // The treaty page also mentions Ontario Works terms in passing here, so it
        // clears the keyword gate for an Ontario Works question; being a general
        // page (no topic), the topic gate must drop it from that answer while it
        // still wins a genuine treaty question.
        $this->chunk($db, 'The annuity', 'The Robinson Huron Treaty annuity case concerns the treaty. Members can apply for the Ontario Works distribution and income support.', 'https://rhtcircle.ca/treaty-wide/the-treaty', '', '');
        $this->chunk($db, 'Where your data lives', 'Where your community data actually lives. You can apply data-sovereignty principles.', '/explainers/where-your-data-lives', '', '');
        // Income-support region services (District Services Boards) that answer an
        // Ontario Works question on topic.
        $this->chunk($db, 'Ontario Works', 'The Manitoulin-Sudbury District Services Board delivers Ontario Works income support. You can apply for Ontario Works income support.', 'https://www.msdsb.net/', 'service', 'msdsb-ow');
        $this->chunk($db, 'Ontario Works', 'The Algoma District Services Administration Board delivers Ontario Works income support. Apply for Ontario Works income support.', 'https://www.adsab.on.ca/en/about-us/', 'service', 'adsab-ow');

        return new GraphRetriever($db);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function place(DatabaseInterface $db, string $name, array $data): void
    {
        $this->row($db, 'place', $name, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function row(DatabaseInterface $db, string $table, string $name, array $data): void
    {
        $db->query("INSERT INTO {$table} (name, _data) VALUES (?, ?)", [$name, json_encode($data, JSON_THROW_ON_ERROR)]);
    }

    private function chunk(DatabaseInterface $db, string $heading, string $text, string $sourceUrl, string $entityType, string $entityId): void
    {
        $data = json_encode([
            'source_url' => $sourceUrl,
            'heading' => $heading,
            'text' => $text,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], JSON_THROW_ON_ERROR);

        $db->query('INSERT INTO doc_chunk (title, _data) VALUES (?, ?)', ['Anokii content', $data]);
    }
}
