<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\RenderTarget;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

/**
 * Pins the carve-php surfaces this extension sits on, so a parent release that
 * moves one of them fails here rather than silently changing the embed.
 */
class CarvePhpContractTest extends TestCase
{
    /**
     * @param string $input
     *
     * @return string
     */
    protected function html(string $input): string
    {
        $converter = new CarveConverter();
        $converter->addExtension(new MediaEmbedExtension());

        return $converter->convert($input);
    }

    /**
     * carve-php 0.1.11 merges default classes in one pass and preserves authored
     * duplicates, so `{.a class="a b"}` yields `a a b`. 0.1.9 collapsed it to `a b`.
     * The extension forwards the engine's list verbatim rather than deduplicating.
     *
     * @return void
     */
    public function testClassListIsForwardedVerbatimIncludingAuthoredDuplicates(): void
    {
        $this->assertStringContainsString('class="a a b"', $this->html(':youtube[dQw4w9WgXcQ]{.a class="a b"}'));
        $this->assertStringContainsString('class="a b"', $this->html(':youtube[dQw4w9WgXcQ]{.a .b}'));
        // 0.1.9 dropped the shorthand when an explicit class attribute was present.
        $this->assertStringContainsString('class="a b c"', $this->html(':youtube[dQw4w9WgXcQ]{.a class="b c"}'));
        // An explicit multi-name class value reaches the iframe whole, not as one token.
        $this->assertStringContainsString('class="b c"', $this->html(':youtube[dQw4w9WgXcQ]{class="b c"}'));
    }

    /**
     * A renderer that declares the HTML target without extending HtmlRenderer is
     * supported from carve-php 0.1.11 on, and must still get an iframe.
     *
     * @return void
     */
    public function testCustomHtmlTargetRendererStillGetsAnIframe(): void
    {
        $renderer = new class extends MarkdownRenderer {
            /**
             * @return string
             */
            public function getRenderTarget(): string
            {
                return RenderTarget::HTML;
            }
        };
        $converter = new CarveConverter(renderer: $renderer);
        $converter->addExtension(new MediaEmbedExtension());
        $out = $converter->convert(':youtube[dQw4w9WgXcQ]');

        $this->assertStringContainsString('<iframe', $out);
        $this->assertStringNotContainsString('](<', $out);
    }

    /**
     * The ANSI and Carve renderers dispatch no render events, so the directive is
     * left to the engine. Registering the extension must not fatal on them.
     *
     * @return void
     */
    public function testEventlessRenderersAreLeftAlone(): void
    {
        $ansi = CarveConverter::ansi();
        $ansi->addExtension(new MediaEmbedExtension());
        $this->assertStringNotContainsString('<iframe', $ansi->convert(':youtube[dQw4w9WgXcQ]'));

        $carve = CarveConverter::carve();
        $carve->addExtension(new MediaEmbedExtension());
        // The Carve target round-trips the directive verbatim.
        $this->assertSame(':youtube[dQw4w9WgXcQ]', trim($carve->convert(':youtube[dQw4w9WgXcQ]')));
    }

    /**
     * carve-php 0.1.11 adds the `destination-denied` render-loss code. The extension
     * writes its embed markup directly, so it must neither suppress a denied
     * destination elsewhere in the document nor add a row of its own.
     *
     * @return void
     */
    public function testDestinationDeniedLossRowSurvivesAlongsideAnEmbed(): void
    {
        $converter = new CarveConverter();
        $converter->addExtension(new MediaEmbedExtension());
        $report = $converter->convertWithReport(':youtube[dQw4w9WgXcQ] [x](javascript:alert(1))')->toArray();

        $codes = array_column($report['losses'], 'code');
        $this->assertSame(['destination-denied'], $codes);
        $this->assertSame('inline', $report['losses'][0]['nodeType']);
        $this->assertNotSame('', $report['losses'][0]['message']);
    }

    /**
     * The degraded link fallback escapes both href and text; nothing from the
     * provider reaches the document unescaped.
     *
     * @return void
     */
    public function testLinkFallbackEscapesHrefAndText(): void
    {
        $converter = new CarveConverter();
        $converter->setSafeMode((new SafeMode())->setRawHtmlMode(SafeMode::RAW_HTML_STRIP));
        $converter->addExtension(new MediaEmbedExtension());
        $out = $converter->convert(':youtube[a"b]');

        $this->assertStringContainsString('<a ', $out);
        $this->assertStringNotContainsString('href="//www.youtube.com/embed/a"b', $out);
        $this->assertStringContainsString('&quot;', $out);
    }

    /**
     * A non-positive width or height is ignored, leaving the provider default.
     *
     * @return void
     */
    public function testNonPositiveDimensionsAreIgnored(): void
    {
        $this->assertStringContainsString('width="480"', $this->html(':youtube[dQw4w9WgXcQ]{width="0"}'));
        $this->assertStringContainsString('height="295"', $this->html(':youtube[dQw4w9WgXcQ]{height="0"}'));
        $this->assertStringContainsString('width="480"', $this->html(':youtube[dQw4w9WgXcQ]{width="-5"}'));
    }

    /**
     * An empty or whitespace-only directive body resolves to nothing, and the
     * engine renders its own unclaimed-extension span.
     *
     * @return void
     */
    public function testEmptyDirectiveBodyIsNotClaimed(): void
    {
        foreach ([':youtube[]', ':youtube[   ]', ':media[]'] as $input) {
            $out = $this->html($input);
            $this->assertStringNotContainsString('<iframe', $out, $input);
            $this->assertStringContainsString('<span class="ext-', $out, $input);
        }
    }
}
