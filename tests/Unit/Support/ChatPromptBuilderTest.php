<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\ChatPromptBuilder;
use App\Support\Passage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChatPromptBuilderTest extends TestCase
{
    #[Test]
    public function system_prompt_states_the_grounding_and_safety_rules(): void
    {
        $system = new ChatPromptBuilder()->system();

        self::assertStringContainsString('ONLY', $system, 'Must restrict to supplied passages.');
        self::assertStringContainsString('source:', $system, 'Must instruct citation.');
        self::assertStringContainsString(ChatPromptBuilder::NO_ANSWER, $system, 'Must carry the exact refusal text.');
        self::assertStringContainsString('Never invent', $system);
        self::assertStringContainsString('personal information', $system);
        self::assertStringContainsString('911', $system);
        self::assertStringContainsString('not affiliated', $system);
    }

    #[Test]
    public function user_message_embeds_question_and_cited_passages(): void
    {
        $passages = [
            new Passage('/resources/sagamok', 'Sagamok member resources', 'Apply for housing', 'Contact the Housing Department.', 4.2),
            new Passage('/explainers/where-your-data-lives', 'Where does your community\'s data actually live?', 'Which laws apply', 'The CLOUD Act applies.', 2.1),
        ];

        $msg = new ChatPromptBuilder()->userMessage('How do I apply for housing?', $passages);

        self::assertStringContainsString('Question: How do I apply for housing?', $msg);
        self::assertStringContainsString('source_url: /resources/sagamok', $msg);
        self::assertStringContainsString('Apply for housing', $msg);
        self::assertStringContainsString('Contact the Housing Department.', $msg);
        self::assertStringContainsString('[Passage 2]', $msg, 'Passages are numbered for citation.');
    }

    #[Test]
    public function user_message_handles_no_passages(): void
    {
        $msg = new ChatPromptBuilder()->userMessage('anything', []);

        self::assertStringContainsString('(no passages found)', $msg);
    }
}
