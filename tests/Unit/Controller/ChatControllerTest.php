<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ChatController;
use App\Support\ChatPromptBuilder;
use App\Support\Passage;
use App\Support\RateLimiterInterface;
use App\Support\RetrieverInterface;
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
    ): ChatController {
        return new ChatController(
            retriever: $retriever,
            prompts: new ChatPromptBuilder(),
            provider: $provider,
            limiter: $limiter ?? $this->limiter(PHP_INT_MAX),
            logger: new NullLogger(),
            configured: $configured,
        );
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

            public function retrieve(string $query, int $k = 6): array
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

            /** @param list<string> $deltas */
            public function __construct(private array $deltas) {}

            public function sendMessage(MessageRequest $request): MessageResponse
            {
                $this->calls++;

                return new MessageResponse([['type' => 'text', 'text' => implode('', $this->deltas)]], 'end_turn', ['input_tokens' => 10, 'output_tokens' => 5]);
            }

            public function streamMessage(MessageRequest $request, callable $onChunk): MessageResponse
            {
                $this->calls++;
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
