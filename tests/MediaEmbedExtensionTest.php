<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use Carve\CarveConverter;
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

}
