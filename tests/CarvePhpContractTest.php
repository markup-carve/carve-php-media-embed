<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\Carve;
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
     * @param array<string, mixed> $options
     *
     * @return string
     */
    protected function html(string $input, array $options = []): string
    {
        return Carve::render($input, ['extensions' => [new MediaEmbedExtension()]] + $options);
    }

    /**
     * Classes add up and authored duplicates stay (C2-A-035), so `{.a class="a b"}` yields `a a b`.
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
     * 0.1 let a custom renderer declare the HTML target; in 0.2 the target is the `html` writer of the registry
     * (migration guide: `RenderTargetInterface` becomes `Output\OutputWriter`), and it must still get an iframe.
     *
     * @return void
     */
    public function testHtmlWriterOfTheRegistryGetsAnIframe(): void
    {
        $options = ['extensions' => [new MediaEmbedExtension()]];
        $out = Carve::writeTo('html', Carve::parse(':youtube[dQw4w9WgXcQ]', $options), $options);

        $this->assertStringContainsString('<iframe', $out);
        $this->assertStringNotContainsString('](<', $out);
    }

    /**
     * The ANSI target leaves the directive to the engine's fallback export.
     *
     * @return void
     */
    public function testAnsiWriterIsLeftAlone(): void
    {
        $options = ['extensions' => [new MediaEmbedExtension()]];
        $this->assertStringNotContainsString('<iframe', Carve::writeTo('ansi', Carve::parse(':youtube[dQw4w9WgXcQ]', $options)));
    }

    /**
     * The Carve target round-trips the directive verbatim: 0.1 `CarveConverter::carve()` is `Carve::format()` in
     * 0.2, and the extension node writes back from its source record (C2-X-024).
     *
     * @return void
     */
    public function testCarveTargetRoundTripsTheDirective(): void
    {
        $this->assertSame(':youtube[dQw4w9WgXcQ]', trim(Carve::format(':youtube[dQw4w9WgXcQ]', ['extensions' => [new MediaEmbedExtension()]])));
    }

    /**
     * The extension writes its embed markup directly, so it must neither suppress a denied destination elsewhere
     * in the document nor add an entry of its own. 0.1 reported the loss with a node type; a 0.2 render-loss
     * diagnostic carries a tree path instead (C2-A-173).
     *
     * @return void
     */
    public function testDestinationDeniedLossRowSurvivesAlongsideAnEmbed(): void
    {
        $result = Carve::renderWithDiagnostics(':youtube[dQw4w9WgXcQ] [x](javascript:alert(1))', ['extensions' => [new MediaEmbedExtension()]]);

        $this->assertSame(['destination-denied'], array_map(static fn ($d): string => $d->code, $result->diagnostics));
        $this->assertSame('render-loss', $result->diagnostics[0]->channel);
        $this->assertSame('/children/0/children/2', $result->diagnostics[0]->path);
        $this->assertNotSame('', $result->diagnostics[0]->message);
    }

    /**
     * The degraded link fallback escapes both href and text; nothing from the
     * provider reaches the document unescaped. 0.1 safe mode is the `comment` profile in 0.2 (C2-X-034).
     *
     * @return void
     */
    public function testLinkFallbackEscapesHrefAndText(): void
    {
        $out = $this->html(':youtube[a"b]', ['profile' => 'comment']);

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
