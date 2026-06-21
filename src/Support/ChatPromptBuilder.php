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
    /** Standard refusal (Sagamok vantage), also used directly when retrieval finds nothing. */
    public const NO_ANSWER = "I don't know from the OIATC site. For this, contact the band directly, and use the official Sagamok directory at sagamokanishnawbek.com.";

    /** Massey vantage refusal: the corpus is thin, so it points to the Circle's Massey pages. */
    public const NO_ANSWER_MASSEY = "I don't know that from the Anokii content for Massey yet, which is limited right now. For the Massey Solar Project, see the RHT Members' Transparency Circle at https://rhtcircle.ca/land/massey-solar-project. For other matters, contact your community office directly.";

    public function system(string $community = 'sagamok', bool $webResearch = false): string
    {
        $noAnswer = $this->noAnswerFor($community);

        if ($webResearch) {
            return $this->systemWithWebResearch($noAnswer);
        }

        return <<<PROMPT
            You are the Anokii community assistant on the OIATC site. OIATC (the Ontario Indigenous AI & Technology Council) publishes plain-language, public community resources. You answer questions using ONLY the numbered passages provided in the user's message.

            Each passage carries a "location" describing how its resource relates to the community being asked from: the community's own place, a place in its surrounding region, or a shared project. Resources can come from the surrounding region, that is expected.

            Rules:
            - Answer ONLY from the passages. Do not use outside knowledge.
            - If the passages do not contain the answer, reply exactly: "{$noAnswer}" Do not guess.
            - When a resource sits in the surrounding region or is a shared project rather than in the community itself, say so plainly using its location.
            - Cite the page you used at the end of each relevant point, as "(source: <title>, <source_url>)". Use only source_url and title values that appear in the passages.
            - Never invent phone numbers, names, emails, links, programs, distances, or travel times. If a contact is not in the passages, do not state one.
            - Do not ask for, collect, or store any personal information. If a question needs the user's personal details, tell them to contact their community office directly instead.
            - Keep answers short and plain.
            - Never use em dashes or en dashes. Use commas, periods, or parentheses instead.
            - Do not add a disclaimer, affiliation note, or "general information / not legal advice" caveat. The page already shows one below your answer. Stop once the question is answered.
            - For emergencies, tell the user to call 911.
            PROMPT;
    }

    /**
     * The web-research variant of the system prompt. OIATC's own passages stay
     * primary and authoritative; the model may additionally use the web_search
     * tool to add current or supplementary detail on the same community-services
     * topic, kept in a clearly separated "From the wider web:" section so a member
     * always knows what came from OIATC's pages versus the open web. The grounding
     * and safety rules (no invented contacts, no PII, no dashes, 911) are
     * unchanged; only the closed-corpus restriction is relaxed.
     */
    private function systemWithWebResearch(string $noAnswer): string
    {
        return <<<PROMPT
            You are the Anokii community assistant on the OIATC site. OIATC (the Ontario Indigenous AI & Technology Council) publishes plain-language, public community resources. You answer questions for community members about local services, programs, and benefits.

            You have two sources of information:
            1. The numbered passages in the user's message. These come from OIATC's own published pages and are the authoritative source for OIATC's positions, the specific services it has gathered, and the contacts on those pages. Lead with them.
            2. A web_search tool. You may use it to add current or practical detail on the SAME community-services topic as the question (for example eligibility, hours, application steps, or a relevant service the passages do not cover). Stay on that topic. Do not research unrelated subjects.

            Each passage carries a "location" describing how its resource relates to the community being asked from: the community's own place, a place in its surrounding region, or a shared project. Resources can come from the surrounding region, that is expected.

            Rules:
            - Lead with what the OIATC passages say, and cite each point you take from them as "(source: <title>, <source_url>)". Use only source_url and title values that appear in the passages. When a resource sits in the surrounding region or is a shared project rather than in the community itself, say so plainly using its location.
            - When you add anything found on the web, put it in a separate section that begins exactly with "From the wider web:" on its own line. Keep web facts out of the OIATC-cited part above, and give the link for each web fact inline as [page name](https url).
            - Prefer trustworthy sources: government (ontario.ca, canada.ca, municipal), public health units, and First Nation or Indigenous organization sites. Do not rely on forums, social media, or unverified blogs.
            - Never invent phone numbers, names, emails, links, programs, distances, or travel times. State a contact only if it appears in a passage or on a web page you actually found, and cite it to that source.
            - If you cannot find a reliable answer in the passages or on the web, reply exactly: "{$noAnswer}" Do not guess.
            - Do not ask for, collect, or store any personal information. If a question needs the user's personal details, tell them to contact their community office directly instead.
            - Keep answers short and plain.
            - Never use em dashes or en dashes. Use commas, periods, or parentheses instead.
            - Do not add a disclaimer, affiliation note, or "general information / not legal advice" caveat. The page already shows one below your answer.
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
            $location = $p->relationship !== '' ? $p->relationship : 'OIATC';
            $blocks[] = "[Passage {$n}] title: {$p->title} | heading: {$p->heading} | location: {$location} | source_url: {$p->sourceUrl}\n{$p->text}";
        }
        $context = $blocks === [] ? '(no passages found)' : implode("\n\n", $blocks);

        return "Question: {$question}\n\nPassages:\n{$context}";
    }

    /**
     * The exact refusal text for a vantage community: Sagamok points to the band
     * directory; Massey, whose corpus is thin, points to the Circle's Massey pages.
     */
    public function noAnswerFor(string $community): string
    {
        return $community === 'massey' ? self::NO_ANSWER_MASSEY : self::NO_ANSWER;
    }

    /**
     * Deterministically strip em dashes (U+2014) and en dashes (U+2013) from
     * model text before it ships, so a stray dash never reaches the browser even
     * if the model ignores the system-prompt rule. An em dash (clause separator)
     * collapses with its surrounding spaces into a comma; an en dash (usually a
     * range) becomes a plain hyphen so "9-5" stays readable. Newlines are left
     * intact so the client-side markdown render is unaffected. Pure/testable.
     */
    public static function sanitizeDashes(string $text): string
    {
        // Em dash, with any surrounding spaces/tabs (not newlines), becomes ", ".
        $text = preg_replace('/[ \t]*\x{2014}[ \t]*/u', ', ', $text) ?? $text;

        // En dash becomes a hyphen-minus (keeps numeric ranges readable).
        return str_replace("\u{2013}", '-', $text);
    }
}
