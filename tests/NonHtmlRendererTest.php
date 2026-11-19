<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\Carve;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

/**
 * 0.1 wrote an embed as a Markdown link on the Markdown and plain-text targets. carve-php 0.2 has no such writer
 * yet: they come back on the writer registry (markup-carve/carve-php#5263), so these tests skip until
 * `Carve::writers()` has the target.
 */
class NonHtmlRendererTest extends TestCase
{
    /**
     * @param string $target
     * @param string $source
     *
     * @return string
     */
    protected function write(string $target, string $source): string
    {
        if (!Carve::writers()->has($target)) {
            $this->markTestSkipped(sprintf('The %s writer is not available yet in carve-php 0.2 (markup-carve/carve-php#5263).', $target));
        }
        $extensions = ['extensions' => [new MediaEmbedExtension()]];

        return Carve::writeTo($target, Carve::parse($source, $extensions), $extensions);
    }

    /**
     * @return void
     */
    public function testMarkdownTargetEmitsLink(): void
    {
        $out = $this->write('markdown', ':youtube[dQw4w9WgXcQ]');

        $this->assertStringContainsString('dQw4w9WgXcQ', $out);
        $this->assertStringNotContainsString('<iframe', $out);
    }

    /**
     * @return void
     */
    public function testMarkdownLinkHasMarkdownSyntax(): void
    {
        $out = $this->write('markdown', ':youtube[dQw4w9WgXcQ]');

        // Must be a Markdown-style link [Name](<url>) with angle-bracket URL - not an HTML anchor.
        // getEmbedSrc() may return a protocol-relative URL (//host/...).
        $this->assertMatchesRegularExpression('/\[.+\]\(<(https?:)?\/\/.+>\)/', $out);
        $this->assertStringNotContainsString('<a ', $out);
    }

    /**
     * @return void
     */
    public function testMarkdownUnknownProviderIsNotClaimed(): void
    {
        $out = $this->write('markdown', ':spoiler[hidden text]');

        // Extension must not emit a link for non-media directives.
        $this->assertStringNotContainsString('](http', $out);
        $this->assertStringNotContainsString('](', $out);
        $this->assertStringContainsString('hidden text', $out);
    }

    /**
     * @return void
     */
    public function testPlainTextTargetEmitsLink(): void
    {
        $out = $this->write('text', ':youtube[dQw4w9WgXcQ]');

        $this->assertStringContainsString('dQw4w9WgXcQ', $out);
        $this->assertStringNotContainsString('<iframe', $out);
    }
}
