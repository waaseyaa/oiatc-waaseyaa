<?php

declare(strict_types=1);

namespace App\Provider;

use App\Command\IngestDocsCommand;
use App\Command\SeedGraphCommand;
use App\Support\DocChunker;
use Waaseyaa\CLI\Command\HandlerCommand;
use Waaseyaa\CLI\Command\HandlerOption;
use Waaseyaa\CLI\Command\HandlerOptionMode;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\Capability\ProvidesConsoleCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Registers `app:ingest-docs`. Dependencies are resolved lazily inside the
 * handler so registering the command (which happens for every CLI invocation)
 * stays cheap and never touches storage or Twig until the command actually runs.
 */
final class IngestDocsServiceProvider extends ServiceProvider implements ProvidesConsoleCommandsInterface
{
    public function register(): void {}

    /**
     * @return iterable<HandlerCommand>
     */
    public function consoleCommands(): iterable
    {
        yield new HandlerCommand(
            name: 'app:ingest-docs',
            description: 'Extract and upsert doc_chunk passages from published pages (RAG content source).',
            options: [
                new HandlerOption(
                    name: 'dry-run',
                    mode: HandlerOptionMode::None,
                    description: 'Preview extracted chunks without writing.',
                ),
                new HandlerOption(
                    name: 'prune',
                    mode: HandlerOptionMode::Negatable,
                    description: 'Delete stored chunks no longer present (use --no-prune to keep).',
                    default: true,
                ),
            ],
            handler: function (SymfonyCommandIO $io): int {
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

        yield new HandlerCommand(
            name: 'app:seed-graph',
            description: 'Seed the Anokii relational graph (communities, places, services, the shared project) and backfill chunk links.',
            options: [
                new HandlerOption(
                    name: 'dry-run',
                    mode: HandlerOptionMode::None,
                    description: 'Report what would be seeded without writing.',
                ),
            ],
            handler: function (SymfonyCommandIO $io): int {
                $entityTypeManager = $this->resolve(EntityTypeManager::class);
                $types = ['topic', 'place', 'community', 'organization', 'service', 'project', 'doc_chunk'];
                $repos = [];
                foreach ($types as $type) {
                    $repos[$type] = $entityTypeManager->getRepository($type);
                }

                return new SeedGraphCommand($repos)->run($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:news-og-manifest',
            description: 'Print published news posts as JSON (slug, title, meta description) for the OG card generator.',
            options: [],
            handler: function (SymfonyCommandIO $io): int {
                $entityTypeManager = $this->resolve(EntityTypeManager::class);
                $controller = new \App\Controller\NewsController($entityTypeManager->getRepository('news_post'));
                $io->writeln(json_encode($controller->publishedList(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

                return 0;
            },
        );
    }
}
