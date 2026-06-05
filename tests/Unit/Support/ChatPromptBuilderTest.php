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
    }

    #[Test]
    public function system_prompt_bans_em_and_en_dashes_and_self_disclaimers(): void
    {
        $system = new ChatPromptBuilder()->system();

        // The prompt must instruct the model to avoid both dash forms...
        self::assertStringContainsString('em dash', $system);
        self::assertStringContainsString('en dash', $system);
        // ...and must not itself contain either (the citation separator is a comma).
        self::assertStringNotContainsString("\u{2014}", $system, 'Prompt must not model an em dash.');
        self::assertStringNotContainsString("\u{2013}", $system, 'Prompt must not model an en dash.');
        // The model must not append its own disclaimer; the page shows one below.
        self::assertStringContainsString('disclaimer', $system);
    }

    #[Test]
    public function web_research_prompt_keeps_grounding_primary_and_separates_web_findings(): void
    {
        $system = new ChatPromptBuilder()->system('sagamok', webResearch: true);

        // OIATC passages stay primary and still cite.
        self::assertStringContainsString('Lead with', $system, 'OIATC passages remain primary.');
        self::assertStringContainsString('source:', $system, 'Must still instruct OIATC citation.');
        // Web findings are allowed but clearly separated and steered to trusted sources.
        self::assertStringContainsString('web_search', $system, 'Must name the web search tool.');
        self::assertStringContainsString('From the wider web:', $system, 'Web findings go in a separated section.');
        self::assertStringContainsString('government', $system, 'Must steer toward trusted sources.');
        // Safety rules survive unchanged.
        self::assertStringContainsString('Never invent', $system);
        self::assertStringContainsString('personal information', $system);
        self::assertStringContainsString('911', $system);
        self::assertStringContainsString(ChatPromptBuilder::NO_ANSWER, $system, 'Refusal text is still carried.');
        // The prompt itself must still model no dashes.
        self::assertStringNotContainsString("\u{2014}", $system);
        self::assertStringNotContainsString("\u{2013}", $system);
    }

    #[Test]
    public function web_research_prompt_is_off_by_default(): void
    {
        // The default (no flag) must remain the closed-corpus prompt.
        $default = new ChatPromptBuilder()->system('sagamok');

        self::assertStringContainsString('Answer ONLY from the passages', $default);
        self::assertStringNotContainsString('From the wider web:', $default);
        self::assertStringNotContainsString('web_search', $default);
    }

    #[Test]
    public function sanitize_dashes_strips_em_and_en_dashes_deterministically(): void
    {
        // Em dash with surrounding spaces collapses to a comma.
        self::assertSame('Finance, ext 225', ChatPromptBuilder::sanitizeDashes("Finance \u{2014} ext 225"));
        // Em dash without spaces still becomes a comma.
        self::assertSame('a, b', ChatPromptBuilder::sanitizeDashes("a\u{2014}b"));
        // En dash becomes a hyphen so ranges stay readable.
        self::assertSame('open 9-5', ChatPromptBuilder::sanitizeDashes("open 9\u{2013}5"));
        // Newlines are preserved (client-side markdown render is unaffected).
        self::assertSame("one\ntwo", ChatPromptBuilder::sanitizeDashes("one\ntwo"));
        // Neither dash character survives.
        $mixed = ChatPromptBuilder::sanitizeDashes("x\u{2014}y\u{2013}z");
        self::assertStringNotContainsString("\u{2014}", $mixed);
        self::assertStringNotContainsString("\u{2013}", $mixed);
    }

    #[Test]
    public function no_answer_is_vantage_specific(): void
    {
        $prompts = new ChatPromptBuilder();

        self::assertSame(ChatPromptBuilder::NO_ANSWER, $prompts->noAnswerFor('sagamok'));
        self::assertStringContainsString('sagamokanishnawbek.com', $prompts->noAnswerFor('sagamok'));

        $massey = $prompts->noAnswerFor('massey');
        self::assertStringContainsString('/explainers/massey-solar-project', $massey, 'Massey refusal points to the explainers.');
        self::assertStringContainsString('limited', $massey);

        // The Massey system prompt embeds the Massey refusal verbatim.
        self::assertStringContainsString($massey, $prompts->system('massey'));
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
