<?php

declare(strict_types=1);

namespace App\Controller;

use App\Provider\AiServiceProvider;
use App\Support\ChatPromptBuilder;
use App\Support\Passage;
use App\Support\RateLimiterInterface;
use App\Support\RetrieverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\AI\Agent\Provider\StreamChunk;
use Waaseyaa\AI\Agent\Provider\StreamingProviderInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;

/**
 * POST /api/chat — grounded, cited Q&A over the doc_chunk knowledge base.
 *
 * Path B (retrieve then prompt): keyword-retrieve the top passages, hand them to
 * Claude with a grounded/cited system prompt, and stream the answer back as SSE.
 * Off-corpus questions and the not-configured case short-circuit to a
 * deterministic message and never call the model. Rate-limited per client.
 */
final class ChatController
{
    private const TOP_K = 6;
    private const MAX_QUESTION_CHARS = 500;
    private const MAX_TOKENS = 700;
    public function __construct(
        private readonly RetrieverInterface $retriever,
        private readonly ChatPromptBuilder $prompts,
        private readonly ProviderInterface $provider,
        private readonly RateLimiterInterface $limiter,
        private readonly LoggerInterface $logger,
        private readonly bool $configured,
    ) {}

    public function handle(Request $request): Response
    {
        $retryAfter = $this->limiter->retryAfter($this->clientKey($request));
        if ($retryAfter !== null) {
            return new JsonResponse(
                ['error' => 'Too many requests. Please wait a moment.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfter],
            );
        }

        $question = $this->readQuestion($request);
        if ($question === null) {
            return new JsonResponse(['error' => 'Provide a non-empty "question" (max 500 characters).'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->configured) {
            return $this->streamMessageText('The assistant is not available right now. Please use the page contacts, or the Sagamok directory at sagamokanishnawbek.com.', []);
        }

        $passages = $this->retriever->retrieve($question, self::TOP_K);
        if ($passages === []) {
            // Off-corpus: deterministic refusal, no model call.
            return $this->streamMessageText(ChatPromptBuilder::NO_ANSWER, []);
        }

        return $this->streamAnswer($question, $passages);
    }

    /**
     * @param list<Passage> $passages
     */
    private function streamAnswer(string $question, array $passages): StreamedResponse
    {
        $messageRequest = new MessageRequest(
            messages: [['role' => 'user', 'content' => $this->prompts->userMessage($question, $passages)]],
            system: $this->prompts->system(),
            maxTokens: self::MAX_TOKENS,
        );
        $sources = $this->sources($passages);
        $provider = $this->provider;
        $prompts = $this->prompts;
        $logger = $this->logger;

        return $this->sse(static function () use ($provider, $messageRequest, $sources, $prompts, $logger): void {
            try {
                if ($provider instanceof StreamingProviderInterface) {
                    $response = $provider->streamMessage($messageRequest, static function (StreamChunk $chunk): void {
                        if ($chunk->type === 'text_delta' && $chunk->text !== '') {
                            self::emit('delta', ['text' => $chunk->text]);
                        }
                    });
                } else {
                    // Provider can't stream: send once and emit the whole answer.
                    $response = $provider->sendMessage($messageRequest);
                    self::emit('delta', ['text' => $response->getText()]);
                }
                self::emit('done', ['sources' => $sources]);
                // Token-spend record (ai-observability cost traces require an
                // active AgentExecutor trace, which Path B doesn't use; logging
                // the counts is the honest minimal record — see upstream #013).
                $usage = $response->usage + ['input_tokens' => 0, 'output_tokens' => 0];
                $logger->info('chat.llm.completed', [
                    'model' => AiServiceProvider::MODEL,
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'stop_reason' => $response->stopReason,
                    'sources' => count($sources),
                ]);
            } catch (\Throwable $e) {
                $logger->error('chat.llm.failed', ['error' => $e->getMessage()]);
                self::emit('delta', ['text' => $prompts::NO_ANSWER]);
                self::emit('done', ['sources' => []]);
            }
        });
    }

    /**
     * Stream a fixed message as a single delta then done (used for refusals and
     * the not-configured case, so the widget handles every reply uniformly).
     *
     * @param list<array{title: string, source_url: string}> $sources
     */
    private function streamMessageText(string $message, array $sources): StreamedResponse
    {
        return $this->sse(static function () use ($message, $sources): void {
            self::emit('delta', ['text' => $message]);
            self::emit('done', ['sources' => $sources]);
        });
    }

    private function sse(callable $body): StreamedResponse
    {
        return new StreamedResponse(static function () use ($body): void {
            $body();
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function emit(string $event, array $data): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    /**
     * @param list<Passage> $passages
     *
     * @return list<array{title: string, source_url: string}>
     */
    private function sources(array $passages): array
    {
        $seen = [];
        $out = [];
        foreach ($passages as $p) {
            if (isset($seen[$p->sourceUrl])) {
                continue;
            }
            $seen[$p->sourceUrl] = true;
            $out[] = ['title' => $p->title, 'source_url' => $p->sourceUrl];
        }

        return $out;
    }

    private function readQuestion(Request $request): ?string
    {
        $payload = json_decode((string) $request->getContent(), true);
        $question = is_array($payload) ? ($payload['question'] ?? null) : null;
        if (!is_string($question)) {
            return null;
        }
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            return null;
        }

        return $question;
    }

    private function clientKey(Request $request): string
    {
        // Behind Cloudflare + cloudflared, the real client is in CF-Connecting-IP;
        // fall back to the first X-Forwarded-For hop, then the socket address.
        $cf = $request->headers->get('CF-Connecting-IP');
        if (is_string($cf) && $cf !== '') {
            return $cf;
        }
        $xff = $request->headers->get('X-Forwarded-For');
        if (is_string($xff) && $xff !== '') {
            return trim(explode(',', $xff)[0]);
        }

        return (string) $request->getClientIp();
    }
}
