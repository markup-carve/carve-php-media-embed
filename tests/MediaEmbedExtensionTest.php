<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use Carve\CarveConverter;
use Carve\SafeMode;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

class MediaEmbedExtensionTest extends TestCase {

	/**
	 * @param string $input
	 * @param array<string, mixed> $config
	 * @return string
	 */
	protected function convert(string $input, array $config = []): string {
		$converter = new CarveConverter();
		$converter->addExtension(new MediaEmbedExtension(null, $config));

		return $converter->convert($input);
	}

	/**
	 * @return void
	 */
	public function testYoutubeIdRendersIframe(): void {
		$html = $this->convert(':youtube[dQw4w9WgXcQ]');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('dQw4w9WgXcQ', $html);
		$this->assertStringContainsString('youtube', $html);
	}

	/**
	 * @return void
	 */
	public function testCatchallUrlRendersIframe(): void {
		$html = $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('dQw4w9WgXcQ', $html);
	}

	/**
	 * @return void
	 */
	public function testCatchallUrlWithTimestampRetainsTimestamp(): void {
		// Regression: HTML-escaped & in getChildrenHtml() broke params after first & (e.g. &t=43s became &amp;t=43s).
		$html = $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=43s]');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('dQw4w9WgXcQ', $html);
		$this->assertStringContainsString('start=43', $html);
	}

	/**
	 * @return void
	 */
	public function testCatchallVimeoUrlRendersIframe(): void {
		$html = $this->convert(':media[https://vimeo.com/123456789]');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('123456789', $html);
	}

	/**
	 * @return void
	 */
	public function testUnknownDirectiveIsNotClaimed(): void {
		// 'spoiler' is not a media provider; extension must not emit an iframe,
		// leaving the node for Carve's default render / other extensions.
		$html = $this->convert(':spoiler[hidden text]');
		$this->assertStringNotContainsString('<iframe', $html);
		$this->assertStringContainsString('hidden text', $html);
	}

	/**
	 * @return void
	 */
	public function testUnknownProviderSlugIsNotClaimed(): void {
		$html = $this->convert(':notaprovider[whatever]');
		$this->assertStringNotContainsString('<iframe', $html);
	}

	/**
	 * @return void
	 */
	public function testProviderWhitelistBlocksOthers(): void {
		$html = $this->convert(':vimeo[123456789]', ['providers' => ['youtube']]);
		$this->assertStringNotContainsString('<iframe', $html);
	}

	/**
	 * @return void
	 */
	public function testProviderWhitelistAllowsListed(): void {
		$html = $this->convert(':youtube[dQw4w9WgXcQ]', ['providers' => ['youtube']]);
		$this->assertStringContainsString('<iframe', $html);
	}

	/**
	 * @return void
	 */
	public function testWhitelistAlsoGatesCatchall(): void {
		$html = $this->convert(
			':media[https://vimeo.com/123456789]',
			['providers' => ['youtube']],
		);
		$this->assertStringNotContainsString('<iframe', $html);
	}

	/**
	 * @return void
	 */
	public function testWidthHeightConfigAppliedToIframe(): void {
		$html = $this->convert(':youtube[dQw4w9WgXcQ]', ['width' => 800, 'height' => 450]);
		$this->assertStringContainsString('800', $html);
		$this->assertStringContainsString('450', $html);
	}

	/**
	 * @return void
	 */
	public function testSafeModeStripEmitsLinkNotIframe(): void {
		$converter = new CarveConverter();
		$converter->setSafeMode((new SafeMode())->setRawHtmlMode(SafeMode::RAW_HTML_STRIP));
		$converter->addExtension(new MediaEmbedExtension());
		$html = $converter->convert(':youtube[dQw4w9WgXcQ]');

		$this->assertStringNotContainsString('<iframe', $html);
		$this->assertStringContainsString('<a ', $html);
		$this->assertStringContainsString('dQw4w9WgXcQ', $html);
		$this->assertStringContainsString('rel="noopener noreferrer"', $html);
	}

	/**
	 * @return void
	 */
	public function testSafeModeEscapeEmitsLinkNotIframe(): void {
		$converter = new CarveConverter();
		$converter->setSafeMode((new SafeMode())->setRawHtmlMode(SafeMode::RAW_HTML_ESCAPE));
		$converter->addExtension(new MediaEmbedExtension());
		$html = $converter->convert(':youtube[dQw4w9WgXcQ]');

		$this->assertStringNotContainsString('<iframe', $html);
		$this->assertStringContainsString('<a ', $html);
		$this->assertStringContainsString('dQw4w9WgXcQ', $html);
		$this->assertStringContainsString('rel="noopener noreferrer"', $html);
	}

	/**
	 * @return void
	 */
	public function testSafeModeAllowStillEmitsIframe(): void {
		$converter = new CarveConverter();
		$converter->setSafeMode((new SafeMode())->setRawHtmlMode(SafeMode::RAW_HTML_ALLOW));
		$converter->addExtension(new MediaEmbedExtension());
		$html = $converter->convert(':youtube[dQw4w9WgXcQ]');

		$this->assertStringContainsString('<iframe', $html);
	}

	/**
	 * @return void
	 */
	public function testStaticModeEmitsLinkNotIframe(): void {
		$converter = new CarveConverter();
		$converter->setRenderMode('static');
		$converter->addExtension(new MediaEmbedExtension());
		$html = $converter->convert(':youtube[dQw4w9WgXcQ]');

		$this->assertStringNotContainsString('<iframe', $html);
		$this->assertStringContainsString('<a ', $html);
		$this->assertStringContainsString('dQw4w9WgXcQ', $html);
		$this->assertStringContainsString('rel="noopener noreferrer"', $html);
	}

	/**
	 * @return void
	 */
	public function testStartAttributeAppendsStartParam(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{start=90}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('start=90', $html);
	}

	/**
	 * @return void
	 */
	public function testTAttributeIsAliasForStart(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{t=90}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('start=90', $html);
	}

	/**
	 * @return void
	 */
	public function testStartAttributeWithTrailingSIsStripped(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{start=90s}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('start=90', $html);
		$this->assertStringNotContainsString('start=90s', $html);
	}

	/**
	 * @return void
	 */
	public function testInvalidStartAttributeIsIgnored(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{start=abc}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringNotContainsString('start=abc', $html);
		$this->assertStringNotContainsString('start=', $html);
	}

	/**
	 * @return void
	 */
	public function testStartAttributeIgnoredForProviderWithoutTimestampSupport(): void {
		// Vimeo does not declare supports-timestamp; start param must not appear.
		$html = $this->convert(':vimeo[123456789]{start=90}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringNotContainsString('start=90', $html);
	}

	/**
	 * @return void
	 */
	public function testStartAttributeOnCatchallAppendsStartParam(): void {
		$html = $this->convert(':media[https://www.youtube.com/watch?v=aqz-KE-bpKQ]{start=90}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('start=90', $html);
	}

	/**
	 * @return void
	 */
	public function testStartAttributeWinsOverUrlTimestamp(): void {
		// URL carries t=43s; directive attribute {start=90} should take precedence.
		$html = $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=43s]{start=90}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('start=90', $html);
		$this->assertStringNotContainsString('start=43', $html);
	}

	/**
	 * @return void
	 */
	public function testTitleAttributeAppliedToIframe(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{title="Intro video"}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('title="Intro video"', $html);
	}

	/**
	 * @return void
	 */
	public function testLoadingLazyAppliedToIframe(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{loading=lazy}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('loading="lazy"', $html);
	}

	/**
	 * @return void
	 */
	public function testLoadingInvalidValueIgnored(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{loading=bogus}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringNotContainsString('loading=', $html);
	}

	/**
	 * @return void
	 */
	public function testPerDirectiveWidthHeightOverridesDimensions(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{width=800 height=450}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('800', $html);
		$this->assertStringContainsString('450', $html);
	}

	/**
	 * @return void
	 */
	public function testPerDirectiveWidthOverridesGlobalConfig(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{width=800}', ['width' => 200]);
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('800', $html);
		$this->assertStringNotContainsString('200', $html);
	}

	/**
	 * @return void
	 */
	public function testCarveClassShorthandAppliedToIframe(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{.responsive}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('responsive', $html);
	}

	/**
	 * @return void
	 */
	public function testExplicitClassAttributeAppliedToIframe(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{class="a b"}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('class=', $html);
		$this->assertStringContainsString('a', $html);
		$this->assertStringContainsString('b', $html);
	}

	/**
	 * @return void
	 */
	public function testCompositionOfAllAttributes(): void {
		$html = $this->convert(':youtube[aqz-KE-bpKQ]{start=90 title="x" loading=lazy}');
		$this->assertStringContainsString('<iframe', $html);
		$this->assertStringContainsString('start=90', $html);
		$this->assertStringContainsString('title="x"', $html);
		$this->assertStringContainsString('loading="lazy"', $html);
	}

}
