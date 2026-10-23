<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Lint\LintWarning;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use MarkupCarve\MediaEmbed\MediaEmbedLinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaEmbedLinterTest extends TestCase
{
    /**
     * @param string $source
     * @param array<string, mixed> $config
     *
     * @return array<string>
     */
    protected function rules(string $source, array $config = []): array
    {
        $linter = new MediaEmbedLinter(new MediaEmbedExtension(null, $config));

        return array_map(static fn (LintWarning $w): string => $w->rule, $linter->lint($source));
    }

    /**
     * @return array<string, array{0: string, 1: array<string>, 2?: array<string, mixed>}>
     */
    public static function cases(): array
    {
        return [
            'provider id embeds' => [':youtube[dQw4w9WgXcQ]', []],
            'catchall URL embeds' => [':media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]', []],
            'catchall URL no provider knows' => [':media[https://example.com/clip]', [MediaEmbedLinter::RULE_UNRESOLVED]],
            'provider URL of another provider' => [':vimeo[https://www.youtube.com/watch?v=dQw4w9WgXcQ]', [MediaEmbedLinter::RULE_UNRESOLVED]],
            'provider given an unknown URL' => [':youtube[https://example.com/clip]', [MediaEmbedLinter::RULE_UNRESOLVED]],
            'whitespace-only content' => [':youtube[ ]', [MediaEmbedLinter::RULE_UNRESOLVED]],
            'directive of another extension is not ours' => [':kbd[Ctrl]', []],
            'provider outside the allowlist' => [':vimeo[76979871]', [MediaEmbedLinter::RULE_PROVIDER_NOT_ALLOWED], ['providers' => ['youtube']]],
            'catchall resolving outside the allowlist' => [
                ':media[https://vimeo.com/76979871]',
                [MediaEmbedLinter::RULE_PROVIDER_NOT_ALLOWED],
                ['providers' => ['youtube']],
            ],
            'renamed catchall' => [
                ':embed[https://example.com/clip] :media[https://example.com/clip]',
                [MediaEmbedLinter::RULE_UNRESOLVED],
                ['catchall' => 'embed'],
            ],
            'nested in a list' => ["- item :media[https://example.com/clip]\n", [MediaEmbedLinter::RULE_UNRESOLVED]],
        ];
    }

    /**
     * @param string $source
     * @param array<string> $expected
     * @param array<string, mixed> $config
     *
     * @return void
     */
    #[DataProvider('cases')]
    public function testRules(string $source, array $expected, array $config = []): void
    {
        $this->assertSame($expected, $this->rules($source, $config));
    }

    /**
     * Positions follow carve-php's lint units: codepoint columns, byte offsets.
     *
     * @return void
     */
    public function testPositionUsesLineColumnAndByteOffsets(): void
    {
        $source = "Intro\n\nä :media[https://example.com/clip]\n";
        $warnings = (new MediaEmbedLinter())->lint($source);

        $this->assertCount(1, $warnings);
        $this->assertSame(3, $warnings[0]->line);
        $this->assertSame(3, $warnings[0]->column);
        $this->assertSame(':media[https://example.com/clip]', substr($source, $warnings[0]->start, $warnings[0]->end - $warnings[0]->start));
        $this->assertStringContainsString('https://example.com/clip', $warnings[0]->message);
    }

    /**
     * No warning means the renderer emits an iframe, and a warning means it does not.
     *
     * @return void
     */
    public function testAgreesWithTheRenderer(): void
    {
        $converter = new CarveConverter();
        $converter->addExtension(new MediaEmbedExtension());

        foreach ([':youtube[dQw4w9WgXcQ]', ':media[https://example.com/clip]', ':vimeo[https://youtu.be/dQw4w9WgXcQ]'] as $source) {
            $embeds = str_contains($converter->convert($source), '<iframe');
            $this->assertSame($embeds, (new MediaEmbedLinter())->lint($source) === [], $source);
        }
    }
}
