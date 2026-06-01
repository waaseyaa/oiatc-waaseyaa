<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builds the grounded, cited system prompt and the user message for the chat
 * endpoint. Pure and deterministic so the prompt contract can be tested.
 *
 * The model is told to answer ONLY from the supplied passages, cite the page it
 * used, refuse when the passages don't cover the question, and never invent
 * contacts or links or collect personal information.
 */
final class ChatPromptBuilder
{
    /** Standard refusal, also used directly when retrieval finds nothing. */
    public const NO_ANSWER = "I don't know from the OIATC site. For this, contact the band directly, and use the official Sagamok directory at sagamokanishnawbek.com.";

    public function system(): string
    {
        return <<<PROMPT
            You are the OIATC site assistant. OIATC (the Ontario Indigenous AI & Technology Council) publishes plain-language community resources. You answer questions using ONLY the numbered passages provided in the user's message.

            Rules:
            - Answer ONLY from the passages. Do not use outside knowledge.
            - If the passages do not contain the answer, reply exactly: "{$this->noAnswer()}" Do not guess.
            - Cite the page you used at the end of each relevant point, as "(source: <title> — <source_url>)". Use only source_url and title values that appear in the passages.
            - Never invent phone numbers, names, emails, links, or programs. If a contact is not in the passages, do not state one.
            - Do not ask for, collect, or store any personal information. If a question needs the user's personal details, tell them to contact the band directly instead.
            - Keep answers short and plain. This is general information from public pages, not legal, medical, or financial advice, and not affiliated with or endorsed by Sagamok Chief and Council.
            - For emergencies, tell the user to call 911.
            PROMPT;
    }

    /**
     * @param list<Passage> $passages
     */
    public function userMessage(string $question, array $passages): string
    {
        $blocks = [];
        foreach ($passages as $i => $p) {
            $n = $i + 1;
            $blocks[] = "[Passage {$n}] title: {$p->title} | heading: {$p->heading} | source_url: {$p->sourceUrl}\n{$p->text}";
        }
        $context = $blocks === [] ? '(no passages found)' : implode("\n\n", $blocks);

        return "Question: {$question}\n\nPassages:\n{$context}";
    }

    private function noAnswer(): string
    {
        return self::NO_ANSWER;
    }
}
