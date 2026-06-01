<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\GraphRetriever;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;

final class GraphRetrieverTest extends TestCase
{
    #[Test]
    public function sagamok_vantage_answers_a_health_question_from_its_own_service(): void
    {
        $top = $this->retriever()->retrieve('mental health support', 'sagamok', 3);

        self::assertNotSame([], $top);
        self::assertSame('/anokii/sagamok', $top[0]->sourceUrl);
        self::assertSame('Health and wellness', $top[0]->heading);
        self::assertSame('Sagamok', $top[0]->relationship, 'Own-community resource is labelled with the community.');
    }

    #[Test]
    public function massey_vantage_reaches_into_the_region_for_a_sagamok_service(): void
    {
        // Massey has no health service of its own; Sagamok is in Massey's region.
        $top = $this->retriever()->retrieve('mental health support', 'massey', 3);

        self::assertNotSame([], $top);
        self::assertSame('/anokii/sagamok', $top[0]->sourceUrl);
        self::assertStringContainsString('region', $top[0]->relationship, 'Cross-community resource is labelled as region.');
    }

    #[Test]
    public function a_shared_project_is_reachable_from_a_related_community(): void
    {
        $top = $this->retriever()->retrieve('solar energy battery project', 'sagamok', 3);

        self::assertNotSame([], $top);
        self::assertSame('/explainers/massey-solar-project', $top[0]->sourceUrl);
        self::assertStringContainsString('shared project', $top[0]->relationship);
    }

    #[Test]
    public function general_oiatc_content_stays_answerable_from_any_vantage(): void
    {
        $top = $this->retriever()->retrieve('robinson huron treaty annuity', 'sagamok', 3);

        self::assertNotSame([], $top);
        self::assertSame('/explainers/robinson-huron-treaty', $top[0]->sourceUrl);
        self::assertSame('OIATC', $top[0]->relationship);
    }

    #[Test]
    public function off_corpus_question_returns_nothing(): void
    {
        self::assertSame([], $this->retriever()->retrieve('xylophone zebra quasar', 'sagamok'));
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
        $this->place($db, 'Elliot Lake', ['slug' => 'elliot-lake', 'lat' => '46.3833', 'lng' => '-82.6500', 'travel_note' => 'about 45 minutes by road']);

        $this->row($db, 'community', 'Sagamok', ['slug' => 'sagamok', 'located_at' => 'sagamok', 'region' => json_encode(['massey', 'elliot-lake'])]);
        $this->row($db, 'community', 'Massey', ['slug' => 'massey', 'located_at' => 'massey', 'region' => json_encode(['sagamok', 'elliot-lake'])]);

        $this->row($db, 'service', 'Community Wellness Department', ['slug' => 'sagamok-health', 'located_at' => 'sagamok', 'has_topic' => 'health-wellness']);
        $this->row($db, 'project', 'Massey Solar Project', ['slug' => 'massey-solar', 'located_at' => 'massey', 'has_topic' => 'energy-solar', 'relates_to' => json_encode(['sagamok', 'massey'])]);

        $this->chunk($db, 'Health and wellness', 'The Community Wellness Department offers mental health and addictions support and medical transportation.', '/anokii/sagamok', 'service', 'sagamok-health');
        $this->chunk($db, 'The project itself', 'The Massey Solar Project is a solar energy and battery storage project.', '/explainers/massey-solar-project', 'project', 'massey-solar');
        $this->chunk($db, 'The annuity', 'The Robinson Huron Treaty annuity case concerns the treaty.', '/explainers/robinson-huron-treaty', '', '');

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
