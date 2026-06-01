<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\DocChunker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocChunkerTest extends TestCase
{
    private const SAMPLE = <<<'HTML'
        <!doctype html><html lang="en"><head><title>Test page · OIATC</title></head>
        <body>
          <header class="top"><a href="/">OIATC chrome nav</a></header>
          <main id="main-content">
            <p class="eyebrow">OIATC · Explainer</p>
            <p class="lede">An intro paragraph that sits before the first heading and should become the intro chunk.</p>
            <h2>First section</h2>
            <p>Content under the first section, explaining the first idea in enough words to clear the minimum.</p>
            <script>var leak = "this script text must never appear in a chunk";</script>
            <h2>Second section</h2>
            <ul><li>A list item under the second section with sufficient length to be retained.</li></ul>
          </main>
          <footer class="site-foot">Footer chrome that must not be ingested</footer>
        </body></html>
        HTML;

    private function chunker(): DocChunker
    {
        return new DocChunker();
    }

    #[Test]
    public function it_extracts_title_and_sections_by_heading(): void
    {
        $chunks = $this->chunker()->chunkHtml(self::SAMPLE, '/test');

        self::assertNotSame([], $chunks);
        foreach ($chunks as $c) {
            self::assertSame('/test', $c->sourceUrl);
            self::assertSame('Test page', $c->title, 'Title is the <title> with the OIATC suffix stripped.');
        }

        $headings = array_map(static fn($c) => $c->heading, $chunks);
        self::assertContains('', $headings, 'Intro text before the first heading becomes an empty-heading chunk.');
        self::assertContains('First section', $headings);
        self::assertContains('Second section', $headings);
    }

    #[Test]
    public function it_excludes_chrome_and_script_text(): void
    {
        $allText = implode("\n", array_map(static fn($c) => $c->text, $this->chunker()->chunkHtml(self::SAMPLE, '/test')));

        self::assertStringNotContainsString('chrome nav', $allText, 'Header (outside <main>) must be excluded.');
        self::assertStringNotContainsString('Footer chrome', $allText, 'Footer (outside <main>) must be excluded.');
        self::assertStringNotContainsString('leak', $allText, '<script> text must be excluded.');
        self::assertStringContainsString('first idea', $allText);
    }

    #[Test]
    public function chunk_keys_are_stable_and_deterministic(): void
    {
        $first = array_map(static fn($c) => $c->chunkKey, $this->chunker()->chunkHtml(self::SAMPLE, '/test'));
        $second = array_map(static fn($c) => $c->chunkKey, $this->chunker()->chunkHtml(self::SAMPLE, '/test'));

        self::assertSame($first, $second, 'Same input yields identical keys (idempotency depends on this).');
        self::assertSame(count($first), count(array_unique($first)), 'Keys are unique within a page.');
        self::assertContains('/test#first-section-0', $first, 'Keys are derived from url + heading slug + part index.');
    }

    #[Test]
    public function it_splits_oversized_sections_on_word_boundaries(): void
    {
        $long = str_repeat('alpha beta gamma delta ', 200); // ~4600 chars
        $html = '<html><head><title>Long</title></head><body><main><h2>Big</h2><p>' . $long . '</p></main></body></html>';

        $chunks = $this->chunker()->chunkHtml($html, '/long');

        self::assertGreaterThan(1, count($chunks), 'A >MAX_CHARS section splits into multiple parts.');
        foreach ($chunks as $c) {
            self::assertLessThanOrEqual(DocChunker::MAX_CHARS, mb_strlen($c->text));
            self::assertStringNotContainsString('alph beta', $c->text, 'Splits fall on word boundaries, never mid-word.');
        }
        $keys = array_map(static fn($c) => $c->chunkKey, $chunks);
        self::assertSame('/long#big-0', $keys[0]);
        self::assertSame('/long#big-1', $keys[1]);
    }

    #[Test]
    public function chunk_text_keys_and_splits_plain_text(): void
    {
        $chunks = $this->chunker()->chunkText('A short published news body about the Massey Solar Project.', '/news/example', 'Example post', 'Example post');

        self::assertCount(1, $chunks);
        self::assertSame('/news/example', $chunks[0]->sourceUrl);
        self::assertSame('Example post', $chunks[0]->heading);
        self::assertSame('/news/example#example-post-0', $chunks[0]->chunkKey);
    }

    #[Test]
    public function it_drops_fragments_below_the_minimum_length(): void
    {
        $html = '<html><head><title>Tiny</title></head><body><main><h2>Hi</h2><p>ok</p></main></body></html>';

        self::assertSame([], $this->chunker()->chunkHtml($html, '/tiny'), 'Sub-MIN_CHARS fragments are dropped.');
    }
}
