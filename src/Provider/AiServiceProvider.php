<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\AI\Agent\Provider\AnthropicProvider;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Wires the chat model for the RAG endpoint.
 *
 * The framework's MessagingServiceProvider binds ProviderInterface to
 * NullLlmProvider by default; this rebinds it to a real AnthropicProvider
 * (current Claude Sonnet) only when ANTHROPIC_API_KEY is set in the server
 * environment. When the key is absent (local dev, or prod before Russell sets
 * it), the binding is left untouched and ChatController returns a clean
 * "not configured" message instead of failing.
 *
 * The key is read server-side via getenv() and passed only to the provider's
 * constructor, which uses it solely as an outbound `x-api-key` header. It is
 * never bound into any response, template, or route — it cannot reach the page.
 */
final class AiServiceProvider extends ServiceProvider
{
    public const MODEL = 'claude-sonnet-4-6';

    public function register(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY') ?: '';
        if ($apiKey === '') {
            return;
        }

        $this->singleton(
            ProviderInterface::class,
            fn(): ProviderInterface => new AnthropicProvider($apiKey, self::MODEL),
        );
    }
}
