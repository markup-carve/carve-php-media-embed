<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\Carve;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

class MediaEmbedExtensionTest extends TestCase
{
    /**
     * @param string $input
     * @param array<string, mixed> $config
     * @param array<string, mixed> $options Carve render options.
     *
     * @return string
     */
    protected function convert(string $input, array $config = [], array $options = []): string
    {
        return Carve::render($input, ['extensions' => [new MediaEmbedExtension(null, $config)]] + $options);
    }

    /**
     * The iframe's src, which is where a start offset either lands or does not.
     * The whole HTML is the wrong subject: data-carve-source records what the
     * author wrote, ignored attributes included.
     *
     * @param string $html
     *
     * @return string
     */
    protected function src(string $html): string
    {
        if (preg_match('/ src="([^"]*)"/', $html, $matches) !== 1) {
            $this->fail('no iframe src in ' . $html);
        }

        return $matches[1];
    }

    /**
     * @return void
     */
    public function testYoutubeIdRendersIframe(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('youtube', $html);
    }

    /**
     * The rendered embed carries the Carve source so a WYSIWYG round-trip can
     * rebuild the directive (see the data-carve-source convention).
     *
     * @return void
     */
    public function testEmbedIsStampedWithCarveSource(): void
    {
        $this->assertStringContainsString('data-carve-source=":youtube[dQw4w9WgXcQ]"', $this->convert(':youtube[dQw4w9WgXcQ]'));
        $this->assertStringContainsString('data-carve-source=":media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]"', $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]'));
    }

    /**
     * The attribute block reaches the stamp: without it a round-trip restoring
     * from data-carve-source silently drops the id, the classes, the dimensions
     * and the start offset.
     *
     * @return void
     */
    public function testStampKeepsTheDirectiveAttributes(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]{#vid .frame .two title="Talk" start="90"}');
        $this->assertStringContainsString(
            'data-carve-source=":youtube[dQw4w9WgXcQ]{#vid .frame .two title=&quot;Talk&quot; start=&quot;90&quot;}"',
            $html,
        );
        $this->assertStringContainsString(
            'data-carve-source=":youtube[dQw4w9WgXcQ]{title=&quot;a \\&quot;quoted\\&quot; talk&quot;}"',
            $this->convert(':youtube[dQw4w9WgXcQ]{title="a \\"quoted\\" talk"}'),
        );
    }

    /**
     * The point of the stamp: rendering it again produces the same embed. This
     * is the property the attribute block exists for, so it is asserted rather
     * than described.
     *
     * @return void
     */
    public function testStampedSourceRendersTheSameEmbed(): void
    {
        $sources = [
            ':youtube[dQw4w9WgXcQ]',
            ':youtube[dQw4w9WgXcQ]{#vid .frame .two title="Talk" start="90" width="640" height="360" loading="eager"}',
            ':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]{.x}',
        ];

        foreach ($sources as $source) {
            $html = $this->convert($source);
            if (preg_match('/data-carve-source="([^"]*)"/', $html, $matches) !== 1) {
                $this->fail('no stamp in ' . $html);
            }
            $stamped = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');

            $strip = fn (string $out): string => (string)preg_replace('/ data-carve-source="[^"]*"/', '', $out);
            $this->assertSame($strip($html), $strip($this->convert($stamped)), $source);
        }
    }

    /**
     * One qualified inline extension per directive name: the catchall and every provider slug (C2-X-001, C2-X-006).
     *
     * @return void
     */
    public function testDefinitionsCoverCatchallAndProviders(): void
    {
        $definitions = (new MediaEmbedExtension(null, ['catchall' => 'embed']))->definitions();
        $byName = [];
        foreach ($definitions as $definition) {
            $byName[$definition->name] = $definition;
        }

        $this->assertArrayHasKey('media-embed.embed', $byName);
        $this->assertArrayHasKey('media-embed.youtube', $byName);
        $this->assertArrayNotHasKey('media-embed.media', $byName);
        $this->assertSame(['form' => 'inline', 'name' => 'youtube'], $byName['media-embed.youtube']->matcher);
        $this->assertSame(MediaEmbedExtension::VERSION, $byName['media-embed.youtube']->version);
        $this->assertNotNull($byName['media-embed.youtube']->buildTimeRenderer);
    }

    /**
     * @return void
     */
    public function testCatchallUrlRendersIframe(): void
    {
        $html = $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
    }

    /**
     * @return void
     */
    public function testCatchallUrlWithTimestampRetainsTimestamp(): void
    {
        // Regression: HTML-escaped & in getChildrenHtml() broke params after first & (e.g. &t=43s became &amp;t=43s).
        $html = $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=43s]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('start=43', $this->src($html));
    }

    /**
     * @return void
     */
    public function testCatchallVimeoUrlRendersIframe(): void
    {
        $html = $this->convert(':media[https://vimeo.com/123456789]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('123456789', $html);
    }

    /**
     * @return void
     */
    public function testUnknownDirectiveIsNotClaimed(): void
    {
        // 'spoiler' is not a media provider; extension must not emit an iframe,
        // leaving the node for Carve's default render / other extensions.
        $html = $this->convert(':spoiler[hidden text]');
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('hidden text', $html);
    }

    /**
     * @return void
     */
    public function testUnknownProviderSlugIsNotClaimed(): void
    {
        $html = $this->convert(':notaprovider[whatever]');
        $this->assertStringNotContainsString('<iframe', $html);
    }

    /**
     * @return void
     */
    public function testProviderWhitelistBlocksOthers(): void
    {
        $html = $this->convert(':vimeo[123456789]', ['providers' => ['youtube']]);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    /**
     * @return void
     */
    public function testProviderWhitelistAllowsListed(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', ['providers' => ['youtube']]);
        $this->assertStringContainsString('<iframe', $html);
    }

    /**
     * @return void
     */
    public function testWhitelistAlsoGatesCatchall(): void
    {
        $html = $this->convert(
            ':media[https://vimeo.com/123456789]',
            ['providers' => ['youtube']],
        );
        $this->assertStringNotContainsString('<iframe', $html);
    }

    /**
     * @return void
     */
    public function testWidthHeightConfigAppliedToIframe(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', ['width' => 800, 'height' => 450]);
        $this->assertStringContainsString('800', $html);
        $this->assertStringContainsString('450', $html);
    }

    /**
     * 0.1 safe mode (raw HTML strip) is the `comment` profile in 0.2 (carve-php migration guide: `setSafeMode` becomes `profile`; C2-X-034: the renderer
     * applies the profile), and an embed degrades to a link there.
     *
     * @return void
     */
    public function testCommentProfileEmitsLinkNotIframe(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', [], ['profile' => 'comment']);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    /**
     * 0.1 safe mode (raw HTML escape) maps to the `minimal` profile the same way (C2-H-163, C2-X-034).
     *
     * @return void
     */
    public function testMinimalProfileEmitsLinkNotIframe(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', [], ['profile' => 'minimal']);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    /**
     * The `linkOnly` setting degrades every embed whatever the profile, for a host that keeps `full`.
     *
     * @return void
     */
    public function testLinkOnlyConfigEmitsLinkNotIframe(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', ['linkOnly' => true]);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    /**
     * 0.1 safe mode allowing raw HTML corresponds to the trusted `full` and `article` profiles (C2-H-163).
     *
     * @return void
     */
    public function testTrustedProfilesStillEmitIframe(): void
    {
        $this->assertStringContainsString('<iframe', $this->convert(':youtube[dQw4w9WgXcQ]', [], ['profile' => 'full']));
        $this->assertStringContainsString('<iframe', $this->convert(':youtube[dQw4w9WgXcQ]', [], ['profile' => 'article']));
    }

    /**
     * Static mode selects the build-time renderer, which writes the link (C2-X-052).
     *
     * @return void
     */
    public function testStaticModeEmitsLinkNotIframe(): void
    {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', [], ['mode' => 'static']);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    /**
     * The extension node writes back as the directive it was claimed from (C2-X-024), so `format` keeps it.
     *
     * @return void
     */
    public function testFormatKeepsTheDirective(): void
    {
        $extensions = ['extensions' => [new MediaEmbedExtension()]];
        $this->assertSame(":youtube[dQw4w9WgXcQ]\n", Carve::format(':youtube[dQw4w9WgXcQ]', $extensions));
        $this->assertSame(":media[https://example.com/clip]\n", Carve::format(':media[https://example.com/clip]', $extensions));
    }

    /**
     * Quote characters in a directive body reach the provider as typed, not as smart punctuation.
     *
     * @return void
     */
    public function testBodyQuotesAreNotSmartened(): void
    {
        $this->assertStringContainsString('embed/a&quot;b', $this->convert(':youtube[a"b]'));
    }

    /**
     * @return void
     */
    public function testStartAttributeAppendsStartParam(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{start=90}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('start=90', $this->src($html));
    }

    /**
     * @return void
     */
    public function testTAttributeIsAliasForStart(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{t=90}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('start=90', $this->src($html));
    }

    /**
     * @return void
     */
    public function testStartAttributeWithTrailingSIsStripped(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{start=90s}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('start=90', $this->src($html));
        $this->assertStringNotContainsString('start=90s', $this->src($html));
    }

    /**
     * @return void
     */
    public function testInvalidStartAttributeIsIgnored(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{start=abc}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringNotContainsString('start=', $this->src($html));
    }

    /**
     * @return void
     */
    public function testStartAttributeIgnoredForProviderWithoutTimestampSupport(): void
    {
        // Vimeo does not declare supports-timestamp; start param must not appear.
        $html = $this->convert(':vimeo[123456789]{start=90}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringNotContainsString('start=90', $this->src($html));
    }

    /**
     * @return void
     */
    public function testStartAttributeOnCatchallAppendsStartParam(): void
    {
        $html = $this->convert(':media[https://www.youtube.com/watch?v=aqz-KE-bpKQ]{start=90}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('start=90', $this->src($html));
    }

    /**
     * @return void
     */
    public function testStartAttributeWinsOverUrlTimestamp(): void
    {
        // URL carries t=43s; directive attribute {start=90} should take precedence.
        $html = $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=43s]{start=90}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('start=90', $this->src($html));
        $this->assertStringNotContainsString('start=43', $this->src($html));
    }

    /**
     * @return void
     */
    public function testTitleAttributeAppliedToIframe(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{title="Intro video"}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('title="Intro video"', $html);
    }

    /**
     * @return void
     */
    public function testLoadingAppliedToIframe(): void
    {
        // media-embed defaults to loading="lazy"; use eager to prove the attribute overrides it.
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{loading=eager}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }

    /**
     * @return void
     */
    public function testLoadingInvalidValueIgnored(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{loading=bogus}');
        $this->assertStringContainsString('<iframe', $html);
        // The invalid value must not be applied; media-embed's default loading stays untouched.
        $this->assertStringNotContainsString('loading="bogus"', $html);
    }

    /**
     * @return void
     */
    public function testPerDirectiveWidthHeightOverridesDimensions(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{width=800 height=450}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('800', $html);
        $this->assertStringContainsString('450', $html);
    }

    /**
     * @return void
     */
    public function testPerDirectiveWidthOverridesGlobalConfig(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{width=800}', ['width' => 200]);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('800', $html);
        $this->assertStringNotContainsString('200', $html);
    }

    /**
     * @return void
     */
    public function testCarveClassShorthandAppliedToIframe(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{.responsive}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('responsive', $html);
    }

    /**
     * @return void
     */
    public function testExplicitClassAttributeAppliedToIframe(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{class="a b"}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('class=', $html);
        $this->assertStringContainsString('a', $html);
        $this->assertStringContainsString('b', $html);
    }

    /**
     * @return void
     */
    public function testCompositionOfAllAttributes(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]{start=90 title="x" loading=lazy}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('start=90', $this->src($html));
        $this->assertStringContainsString('title="x"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    // --- Per-provider URL content ---

    /**
     * Bare ID path still works (regression guard).
     *
     * @return void
     */
    public function testPerProviderBareIdRendersCleanEmbed(): void
    {
        $html = $this->convert(':youtube[aqz-KE-bpKQ]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('embed/aqz-KE-bpKQ', $html);
        $this->assertStringNotContainsString('embed/https', $html);
    }

    /**
     * Full YouTube watch URL inside :youtube[] resolves to the same clean embed.
     *
     * @return void
     */
    public function testPerProviderFullUrlRendersCleanEmbed(): void
    {
        $html = $this->convert(':youtube[https://www.youtube.com/watch?v=aqz-KE-bpKQ]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('embed/aqz-KE-bpKQ', $html);
        // The broken form must never appear.
        $this->assertStringNotContainsString('embed/https', $html);
    }

    /**
     * Short youtu.be URL inside :youtube[] resolves to the same clean embed.
     *
     * @return void
     */
    public function testPerProviderShortUrlRendersCleanEmbed(): void
    {
        $html = $this->convert(':youtube[https://youtu.be/aqz-KE-bpKQ]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('embed/aqz-KE-bpKQ', $html);
        $this->assertStringNotContainsString('embed/https', $html);
    }

    /**
     * A Vimeo URL inside :youtube[] must produce NO iframe (provider mismatch).
     *
     * @return void
     */
    public function testPerProviderUrlMismatchProducesNoIframe(): void
    {
        $html = $this->convert(':youtube[https://vimeo.com/76979871]');
        $this->assertStringNotContainsString('<iframe', $html);
    }

    /**
     * Whitelist is still respected when content is a URL.
     *
     * @return void
     */
    public function testPerProviderUrlRespectsWhitelist(): void
    {
        // youtube not in whitelist -> no iframe even for a valid YouTube URL.
        $html = $this->convert(
            ':youtube[https://www.youtube.com/watch?v=aqz-KE-bpKQ]',
            ['providers' => ['vimeo']],
        );
        $this->assertStringNotContainsString('<iframe', $html);
    }

    /**
     * Directive attributes still compose when content is a full URL.
     *
     * @return void
     */
    public function testPerProviderUrlWithTitleAttribute(): void
    {
        $html = $this->convert(':youtube[https://www.youtube.com/watch?v=aqz-KE-bpKQ]{title="x"}');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('embed/aqz-KE-bpKQ', $html);
        $this->assertStringContainsString('title="x"', $html);
    }
}
