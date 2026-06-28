<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use Carve\CarveConverter;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use PHPUnit\Framework\TestCase;

class NonHtmlRendererTest extends TestCase {

	/**
	 * @return void
	 */
	public function testMarkdownTargetEmitsLink(): void {
		$converter = CarveConverter::markdown();
		$converter->addExtension(new MediaEmbedExtension());
		$out = $converter->convert(':youtube[dQw4w9WgXcQ]');

		$this->assertStringContainsString('dQw4w9WgXcQ', $out);
		$this->assertStringNotContainsString('<iframe', $out);
	}

	/**
	 * @return void
	 */
	public function testMarkdownLinkHasMarkdownSyntax(): void {
		$converter = CarveConverter::markdown();
		$converter->addExtension(new MediaEmbedExtension());
		$out = $converter->convert(':youtube[dQw4w9WgXcQ]');

		// Must be a Markdown-style link [Name](url) - not an HTML anchor.
		// getEmbedSrc() may return a protocol-relative URL (//host/...).
		$this->assertMatchesRegularExpression('/\[.+\]\((https?:)?\/\/.+\)/', $out);
		$this->assertStringNotContainsString('<a ', $out);
	}

	/**
	 * @return void
	 */
	public function testMarkdownUnknownProviderIsNotClaimed(): void {
		$converter = CarveConverter::markdown();
		$converter->addExtension(new MediaEmbedExtension());
		$out = $converter->convert(':spoiler[hidden text]');

		// Extension must not emit a link for non-media directives.
		$this->assertStringNotContainsString('](http', $out);
		$this->assertStringContainsString('hidden text', $out);
	}

	/**
	 * @return void
	 */
	public function testPlainTextTargetEmitsLink(): void {
		$converter = CarveConverter::plainText();
		$converter->addExtension(new MediaEmbedExtension());
		$out = $converter->convert(':youtube[dQw4w9WgXcQ]');

		$this->assertStringContainsString('dQw4w9WgXcQ', $out);
		$this->assertStringNotContainsString('<iframe', $out);
	}

}
