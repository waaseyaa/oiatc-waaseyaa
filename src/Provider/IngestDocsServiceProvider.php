<?php

declare(strict_types=1);

namespace App\Provider;

use App\Command\IngestDocsCommand;
use App\Support\DocChunker;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Registers `app:ingest-docs`. Dependencies are resolved lazily inside the
 * handler so registering the command (which happens for every CLI invocation)
 * stays cheap and never touches storage or Twig until the command actually runs.
 */
final class IngestDocsServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    /**
     * @return iterable<CommandDefinition>
     */
    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'app:ingest-docs',
            description: 'Extract and upsert doc_chunk passages from published pages (RAG content source).',
            options: [
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Preview extracted chunks without writing.',
                ),
                new OptionDefinition(
                    name: 'prune',
                    mode: OptionMode::Negatable,
                    description: 'Delete stored chunks no longer present (use --no-prune to keep).',
                    default: true,
                ),
            ],
            handler: function (CliIO $io): int {
                $entityTypeManager = $this->resolve(EntityTypeManager::class);
                $twig = SsrServiceProvider::createTwigEnvironment($this->projectRoot, $this->config);

                $command = new IngestDocsCommand(
                    chunks: $entityTypeManager->getRepository('doc_chunk'),
                    twig: $twig,
                    chunker: new DocChunker(),
                    news: $entityTypeManager->getRepository('news_post'),
                );

                return $command->run($io);
            },
        );
    }
}
