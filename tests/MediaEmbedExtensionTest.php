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

}
