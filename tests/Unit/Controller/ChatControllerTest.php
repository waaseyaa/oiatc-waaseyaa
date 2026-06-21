<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Analytics\ChatQueryLogInterface;
use App\Controller\ChatController;
use App\Support\ChatPromptBuilder;
use App\Support\Passage;
use App\Support\RateLimiterInterface;
use App\Support\RetrieverInterface;
use App\Support\TopicVocabulary;
use App\Tests\Doubles\CapturingChatQueryLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\MessageResponse;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\AI\Agent\Provider\StreamChunk;
use Waaseyaa\AI\Agent\Provider\StreamingProviderInterface;
use Waaseyaa\Foundation\Log\NullLogger;

final class ChatControllerTest extends TestCase
{
    #[Test]
    public function not_configured_streams_a_message_and_never_calls_the_model(): void
    {
        $provider = $this->provider();
        $response = $this->controller(retriever: $this->retriever([$this->passage()]), provider: $provider, configured: false)
            ->handle($this->request('How do I apply for housing?'));

        $out = $this->capture($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('not available', $out);
        self::assertSame(0, $provider->calls, 'No model call when unconfigured.');
    }

    #[Test]
    public function off_corpus_question_streams_the_refusal_without_calling_the_model(): void
    {
        $provider = $this->provider();
        $response = $this->controller(retriever: $this->retriever([]), provider: $provider, configured: true)
            ->handle($this->request('what is the weather tomorrow'));

        $out = $this->capture($response);
        self::assertStringContainsString(ChatPromptBuilder::NO_ANSWER, $out);
        self::assertSame(0, $provider->calls, 'No model call when retrieval is empty.');
    }

    #[Test]
    public function grounded_answer_streams_deltas_then_a_done_event_with_sources(): void
    {
        $provider = $this->provider(['The Housing ', 'Department handles applications.']);
        $response = $this->controller(retriever: $this->retriever([$this->passage()]), provider: $provider, configured: true)
            ->handle($this->request('How do I apply for housing?'));

        $out = $this->capture($response);
        self::assertSame(1, $provider->calls);
        self::assertStringContainsString('event: delta', $out);
        self::assertStringContainsString('The Housing ', $out);
        self::assertStringContainsString('Department handles applications.', $out);
        self::assertStringContainsString('event: done', $out);
        self::assertStringContainsString('/resources/sagamok', $out, 'done event carries the cited source.');
    }

    #[Test]
    public function model_em_and_en_dashes_are_stripped_before_streaming(): void
    {
        $provider = $this->provider(["Call Finance \u{2014} ext 225", " (hours 9\u{2013}5)."]);
        $response = $this->controller(retriever: $this->retriever([$this->passage()]), provider: $provider, configured: true)
            ->handle($this->request('who do I contact about per capita?'));

        $out = $this->capture($response);
        self::assertStringNotContainsString("\u{2014}", $out, 'No em dash may ship.');
        self::assertStringNotContainsString("\u{2013}", $out, 'No en dash may ship.');
        self::assertStringContainsString('Call Finance, ext 225', $out, 'Em dash collapsed to a comma.');
        self::assertStringContainsString('hours 9-5', $out, 'En dash became a hyphen.');
    }

    #[Test]
    public function massey_vantage_refusal_points_to_the_circle(): void
    {
        $request = Request::create('/api/chat', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.9'], json_encode(['question' => 'mental health help', 'community' => 'massey']));
        $response = $this->controller(retriever: $this->retriever([]), provider: $this->provider(), configured: true)->handle($request);

        $out = $this->capture($response);
        self::assertStringContainsString('https://rhtcircle.ca/land/massey-solar-project', $out, 'Massey refusal points to the Circle.');
        self::assertStringContainsString('limited', $out, 'Massey refusal names the thin corpus.');
    }

    #[Test]
    public function logs_an_answered_query_with_topic_and_sources(): void
    {
        $log = $this->queryLog();
        $response = $this->controller(retriever: $this->retriever([$this->passage()]), provider: $this->provider(['The Housing ', 'Department handles applications.']), configured: true, queryLog: $log)
            ->handle($this->request('How do I apply for housing?'));
        $this->capture($response);

        self::assertCount(1, $log->records);
        $rec = $log->records[0];
        self::assertSame('sagamok', $rec['community']);
        self::assertSame('How do I apply for housing?', $rec['question']);
        self::assertSame('answered', $rec['outcome']);
        self::assertSame('housing', $rec['topic']);
        self::assertSame(['/resources/sagamok'], $rec['sources']);
    }

    #[Test]
    public function logs_no_match_when_retrieval_is_empty(): void
    {
        $log = $this->queryLog();
        $response = $this->controller(retriever: $this->retriever([]), provider: $this->provider(), configured: true, queryLog: $log)
            ->handle($this->request('what is the weather tomorrow'));
        $this->capture($response);

        self::assertCount(1, $log->records);
        self::assertSame('no_match', $log->records[0]['outcome']);
        self::assertSame([], $log->records[0]['sources'], 'No sources on a no-match.');
    }

    #[Test]
    public function logs_refused_when_the_model_returns_the_refusal_text(): void
    {
        // Passages were retrieved, but the model replies with the exact refusal.
        $log = $this->queryLog();
        $response = $this->controller(retriever: $this->retriever([$this->passage()]), provider: $this->provider([ChatPromptBuilder::NO_ANSWER]), configured: true, queryLog: $log)
            ->handle($this->request('Do you sell concert tickets?'));
        $this->capture($response);

        self::assertCount(1, $log->records);
        self::assertSame('refused', $log->records[0]['outcome'], 'Grounded passages existed but the model could not answer.');
    }

    #[Test]
    public function web_research_attaches_the_search_tool_and_a_higher_token_ceiling(): void
    {
        // Web research on, with grounded passages present: the model is still
        // called, but now with the web_search tool available so it can supplement.
        $provider = $this->provider(['From OIATC: contact Housing.']);
        $response = $this->controller(retriever: $this->retriever([$this->passage()]), provider: $provider, configured: true, webResearch: true)
            ->handle($this->request('How do I apply for housing?'));
        $this->capture($response);

        self::assertSame(1, $provider->calls);
        self::assertNotNull($provider->lastRequest);
        $tools = $provider->lastRequest->tools;
        self::assertCount(1, $tools, 'The web_search tool is attached when web research is on.');
        self::assertSame('web_search', $tools[0]['name']);
        self::assertGreaterThan(700, $provider->lastRequest->maxTokens, 'Web research raises the token ceiling.');
    }

    #[Test]
    public function web_research_omits_the_tool_when_disabled(): void
    {
        $provider = $this->provider(['The Housing Department handles applications.']);
        $response = $this->controller(retriever: $this->retriever([$this->passage()]), provider: $provider, configured: true)
            ->handle($this->request('How do I apply for housing?'));
        $this->capture($response);

        self::assertNotNull($provider->lastRequest);
        self::assertSame([], $provider->lastRequest->tools, 'No tool is attached by default.');
    }

    #[Test]
    public function web_research_answers_an_on_topic_no_match_by_calling_the_model(): void
    {
        // No passages, but the question maps to an allowed topic (housing). With
        // web research on, the model is called so it can research, rather than the
        // deterministic refusal.
        $provider = $this->provider(['From the wider web: see ontario.ca.']);
        $response = $this->controller(retriever: $this->retriever([]), provider: $provider, configured: true, webResearch: true)
            ->handle($this->request('How do I apply for housing?'));
        $out = $this->capture($response);

        self::assertSame(1, $provider->calls, 'On-topic no-match calls the model when web research is on.');
        self::assertStringContainsString('From the wider web', $out);
        self::assertNotNull($provider->lastRequest);
        self::assertCount(1, $provider->lastRequest->tools, 'The web_search tool is available on the no-match path.');
    }

    #[Test]
    public function web_research_still_refuses_an_off_topic_no_match_without_calling_the_model(): void
    {
        // No passages and no allowed topic ("weather tomorrow" infers nothing):
        // the topic vocabulary stays the guardrail, so this still refuses and never
        // calls the model even with web research on.
        $provider = $this->provider();
        $response = $this->controller(retriever: $this->retriever([]), provider: $provider, configured: true, webResearch: true)
            ->handle($this->request('what is the weather tomorrow'));
        $out = $this->capture($response);

        self::assertSame(0, $provider->calls, 'Off-topic no-match never searches the web.');
        self::assertStringContainsString(ChatPromptBuilder::NO_ANSWER, $out);
    }

    #[Test]
    public function bad_request_without_a_question_is_rejected(): void
    {
        $response = $this->controller(retriever: $this->retriever([]), provider: $this->provider(), configured: true)
            ->handle($this->request(''));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_429_when_the_limiter_says_over_limit(): void
    {
        $controller = $this->controller(retriever: $this->retriever([]), provider: $this->provider(), configured: false, limiter: $this->limiter(12));

        $statuses = [];
        for ($i = 0; $i < 14; $i++) {
            $statuses[] = $controller->handle($this->request('hello'))->getStatusCode();
        }

        self::assertSame(200, $statuses[11], 'First 12 are allowed.');
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $statuses[12], '13th is throttled.');
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $statuses[13]);
    }

    // --- helpers -----------------------------------------------------------

    private function controller(
        RetrieverInterface $retriever,
        ProviderInterface $provider,
        bool $configured,
        ?RateLimiterInterface $limiter = null,
        ?ChatQueryLogInterface $queryLog = null,
        bool $webResearch = false,
    ): ChatController {
        return new ChatController(
            retriever: $retriever,
            prompts: new ChatPromptBuilder(),
            provider: $provider,
            limiter: $limiter ?? $this->limiter(PHP_INT_MAX),
            logger: new NullLogger(),
            queryLog: $queryLog ?? $this->queryLog(),
            topics: new TopicVocabulary(),
            configured: $configured,
            webResearch: $webResearch,
        );
    }

    private function queryLog(): CapturingChatQueryLog
    {
        return new CapturingChatQueryLog();
    }

    private function limiter(int $max): RateLimiterInterface
    {
        return new class ($max) implements RateLimiterInterface {
            private int $hits = 0;

            public function __construct(private int $max) {}

            public function retryAfter(string $key): ?int
            {
                return ++$this->hits > $this->max ? 30 : null;
            }
        };
    }

    private function request(string $question): Request
    {
        return Request::create('/api/chat', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.5'], json_encode(['question' => $question]));
    }

    private function passage(): Passage
    {
        return new Passage('/resources/sagamok', 'Sagamok member resources', 'Apply for housing', 'Contact the Housing Department.', 5.0);
    }

    /**
     * @param list<Passage> $passages
     */
    private function retriever(array $passages): RetrieverInterface
    {
        return new class ($passages) implements RetrieverInterface {
            /** @param list<Passage> $passages */
            public function __construct(private array $passages) {}

            public function retrieve(string $query, string $community, int $k = 6): array
            {
                return $this->passages;
            }
        };
    }

    /**
     * @param list<string> $deltas
     */
    private function provider(array $deltas = [])
    {
        return new class ($deltas) implements StreamingProviderInterface {
            public int $calls = 0;
            public ?MessageRequest $lastRequest = null;

            /** @param list<string> $deltas */
            public function __construct(private array $deltas) {}

            public function sendMessage(MessageRequest $request): MessageResponse
            {
                $this->calls++;
                $this->lastRequest = $request;

                return new MessageResponse([['type' => 'text', 'text' => implode('', $this->deltas)]], 'end_turn', ['input_tokens' => 10, 'output_tokens' => 5]);
            }

            public function streamMessage(MessageRequest $request, callable $onChunk): MessageResponse
            {
                $this->calls++;
                $this->lastRequest = $request;
                foreach ($this->deltas as $delta) {
                    $onChunk(new StreamChunk('text_delta', $delta));
                }

                return new MessageResponse([], 'end_turn', ['input_tokens' => 10, 'output_tokens' => 5]);
            }
        };
    }

    private function capture(Response $response): string
    {
        self::assertInstanceOf(StreamedResponse::class, $response);
        $buffer = '';
        ob_start(function (string $chunk) use (&$buffer): string {
            $buffer .= $chunk;

            return '';
        });
        $response->sendContent();
        if (ob_get_level() > 0) {
            @ob_end_flush();
        }

        return $buffer;
    }
}
