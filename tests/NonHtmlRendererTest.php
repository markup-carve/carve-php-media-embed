<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\Carve;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

/**
 * The 0.2 exporters use target-specific output when registered, and otherwise use the core fallback.
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
    public function testMarkdownTargetUsesProviderTitle(): void
    {
        $out = $this->write('markdown', ':youtube[dQw4w9WgXcQ]');

        $this->assertSame("[YouTube](//www.youtube.com/embed/dQw4w9WgXcQ?wmode=transparent)\n", $out);
    }

    public function testMarkdownUsesResolvedProviderForCatchallAndProviderDirectives(): void
    {
        $source = ':vimeo[76979871] :media[https://vimeo.com/76979871]';

        $this->assertSame(
            "[Vimeo](//player.vimeo.com/video/76979871?wmode=transparent) [Vimeo](//player.vimeo.com/video/76979871?wmode=transparent)\n",
            $this->write('markdown', $source),
        );
    }

    public function testUnregisteredExportKeepsCoreFallback(): void
    {
        $extensions = ['extensions' => [new MediaEmbedExtension()]];
        $tree = Carve::parse(':youtube[dQw4w9WgXcQ]', $extensions);

        $this->assertSame(
            "[//www.youtube.com/embed/dQw4w9WgXcQ?wmode=transparent](//www.youtube.com/embed/dQw4w9WgXcQ?wmode=transparent)\n",
            Carve::writeTo('markdown', $tree),
        );
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
        $out = $this->write('plain', ':youtube[dQw4w9WgXcQ]');

        $this->assertStringContainsString('dQw4w9WgXcQ', $out);
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertSame("//www.youtube.com/embed/dQw4w9WgXcQ?wmode=transparent\n", $out);
    }
}
