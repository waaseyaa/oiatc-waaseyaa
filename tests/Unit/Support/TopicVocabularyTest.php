<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\TopicVocabulary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TopicVocabularyTest extends TestCase
{
    #[Test]
    public function the_expanded_canonical_topics_are_present_and_health_wellness_is_retired(): void
    {
        $slugs = array_keys(new TopicVocabulary()->all());

        foreach (['primary-health', 'mental-health-addictions', 'food-security', 'transportation', 'legal-aid', 'child-and-family', 'income-support', 'employment-training', 'education-youth', 'housing'] as $expected) {
            self::assertContains($expected, $slugs, "Topic {$expected} must exist.");
        }

        self::assertNotContains('health-wellness', $slugs, 'health-wellness is retired in favour of primary-health and mental-health-addictions.');
    }

    /**
     * The retriever's topic gate ranks on these inferences, so they must be
     * stable. In particular primary-health and mental-health-addictions must not
     * collide on the obvious queries.
     */
    #[Test]
    #[DataProvider('inferenceCases')]
    public function infer_routes_a_question_to_the_expected_topic(string $query, string $expected): void
    {
        self::assertSame($expected, new TopicVocabulary()->infer($query));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inferenceCases(): iterable
    {
        yield 'mental health' => ['where can I get mental health and addictions support', 'mental-health-addictions'];
        yield 'crisis line' => ['I need a crisis line right now', 'mental-health-addictions'];
        yield 'primary care' => ['I need to see a doctor for primary care', 'primary-health'];
        yield 'clinic' => ['is there a health clinic nearby', 'primary-health'];
        yield 'food bank' => ['where is the nearest food bank', 'food-security'];
        yield 'ontario works' => ['how do I apply for ontario works', 'income-support'];
        yield 'legal aid' => ['I need legal aid and a lawyer', 'legal-aid'];
        yield 'child care' => ['is there a daycare or child care subsidy', 'child-and-family'];
        yield 'transportation' => ['how do I get a ride to my appointment by bus', 'transportation'];
        yield 'housing' => ['how do I apply for housing', 'housing'];
        yield 'jobs' => ['I am looking for a job and training', 'employment-training'];
    }
}
