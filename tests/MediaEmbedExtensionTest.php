<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use Carve\CarveConverter;
use Carve\SafeMode;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

class MediaEmbedExtensionTest extends TestCase {

    protected function convert(string $input, array $config = []): string {
        $converter = new CarveConverter();
        $converter->addExtension(new MediaEmbedExtension(null, $config));

        return $converter->convert($input);
    }

    public function testYoutubeIdRendersIframe(): void {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('youtube', $html);
    }

    public function testCatchallUrlRendersIframe(): void {
        $html = $this->convert(':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('dQw4w9WgXcQ', $html);
    }

    public function testCatchallVimeoUrlRendersIframe(): void {
        $html = $this->convert(':media[https://vimeo.com/123456789]');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('123456789', $html);
    }

    public function testUnknownDirectiveIsNotClaimed(): void {
        // 'spoiler' is not a media provider; extension must not emit an iframe,
        // leaving the node for Carve's default render / other extensions.
        $html = $this->convert(':spoiler[hidden text]');
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('hidden text', $html);
    }

    public function testUnknownProviderSlugIsNotClaimed(): void {
        $html = $this->convert(':notaprovider[whatever]');
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function testProviderWhitelistBlocksOthers(): void {
        $html = $this->convert(':vimeo[123456789]', ['providers' => ['youtube']]);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function testProviderWhitelistAllowsListed(): void {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', ['providers' => ['youtube']]);
        $this->assertStringContainsString('<iframe', $html);
    }

    public function testWhitelistAlsoGatesCatchall(): void {
        $html = $this->convert(
            ':media[https://vimeo.com/123456789]',
            ['providers' => ['youtube']],
        );
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function testWidthHeightConfigAppliedToIframe(): void {
        $html = $this->convert(':youtube[dQw4w9WgXcQ]', ['width' => 800, 'height' => 450]);
        $this->assertStringContainsString('800', $html);
        $this->assertStringContainsString('450', $html);
    }

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

    public function testSafeModeAllowStillEmitsIframe(): void {
        $converter = new CarveConverter();
        $converter->setSafeMode((new SafeMode())->setRawHtmlMode(SafeMode::RAW_HTML_ALLOW));
        $converter->addExtension(new MediaEmbedExtension());
        $html = $converter->convert(':youtube[dQw4w9WgXcQ]');

        $this->assertStringContainsString('<iframe', $html);
    }

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

}
