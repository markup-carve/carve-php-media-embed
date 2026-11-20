<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\Carve;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

/**
 * The 0.2 Markdown and plain exporters use the extension's core fallback text.
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

        return Carve::writeTo($target, Carve::parse($source, $extensions));
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
    public function testMarkdownTargetUsesExtensionLinkFallback(): void
    {
        $out = $this->write('markdown', ':youtube[dQw4w9WgXcQ]');

        $this->assertSame("[//www.youtube.com/embed/dQw4w9WgXcQ?wmode=transparent](//www.youtube.com/embed/dQw4w9WgXcQ?wmode=transparent)\n", $out);
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
