<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Diagnostics\Diagnostic;
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

        return array_map(static fn (Diagnostic $w): string => $w->code, $linter->lint($source));
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
     * Suppression comments as specified for authoring lint: the warning's line decides.
     *
     * @return array<string, array{0: string, 1: array<int>}>
     */
    public static function suppressionCases(): array
    {
        $bad = ':media[https://example.com/clip]';

        return [
            'next-line with the rule id' => ["%% carve-lint-disable-next-line media-embed-unresolved\n$bad\n\n$bad\n", [4]],
            'next-line without ids silences every rule' => ["%% carve-lint-disable-next-line\n$bad\n", []],
            'next-line naming another rule' => ["%% carve-lint-disable-next-line media-embed-provider-not-allowed\n$bad\n", [2]],
            'comma and space separated ids' => ["%% carve-lint-disable-next-line foo,media-embed-unresolved bar\n$bad\n", []],
            'next-line reaches into a list item' => ["%% carve-lint-disable-next-line\n- item $bad\n", []],
            'trailing comment disables its own line' => ["$bad %% carve-lint-disable-line media-embed-unresolved\n\n$bad\n", [3]],
            'document scope until enable' => [
                "%% carve-lint-disable media-embed-unresolved\n\n$bad\n\n%% carve-lint-enable media-embed-unresolved\n\n$bad\n",
                [7],
            ],
            'enable without ids ends a disable without ids' => ["%% carve-lint-disable\n\n$bad\n\n%% carve-lint-enable\n\n$bad\n", [7]],
            'later enable keeps a next-line suppression' => [
                "%% carve-lint-disable\n%% carve-lint-disable-next-line\n$bad %% carve-lint-enable\n",
                [],
            ],
            'directive in code is inert' => ["```\n%% carve-lint-disable\n```\n\n$bad\n", [5]],
            'fenced comment body is inert' => ["%%%\ncarve-lint-disable\n%%%\n\n$bad\n", [5]],
            'braced comment is inert' => ["{% carve-lint-disable %}\n\n$bad\n", [3]],
            'no space after the opener is inert' => ["%%carve-lint-disable\n\n$bad\n", [3]],
        ];
    }

    /**
     * @param string $source
     * @param array<int> $lines
     *
     * @return void
     */
    #[DataProvider('suppressionCases')]
    public function testSuppressionComments(string $source, array $lines): void
    {
        $warnings = (new MediaEmbedLinter())->lint($source);

        // A 0.2 diagnostic carries a byte range, not a line (C2-A-173), so the line is derived from it.
        $this->assertSame($lines, array_map(static fn (Diagnostic $w): int => MediaEmbedLinter::lineOf($source, $w->startOffset), $warnings));
    }

    /**
     * A 0.2 diagnostic is on channel `lint` with a UTF-8 byte range and an RFC 6901 path (C2-A-173); the 0.1 line
     * and codepoint column fields are gone, so the line is derived and the column is not asserted.
     *
     * @return void
     */
    public function testPositionUsesByteOffsetsAndPath(): void
    {
        $source = "Intro\n\nä :media[https://example.com/clip]\n";
        $warnings = (new MediaEmbedLinter())->lint($source);

        $this->assertCount(1, $warnings);
        $this->assertSame(3, MediaEmbedLinter::lineOf($source, $warnings[0]->startOffset));
        $this->assertSame('lint', $warnings[0]->channel);
        $this->assertSame('warning', $warnings[0]->severity);
        $this->assertSame('/children/1/children/1', $warnings[0]->path);
        $this->assertSame(':media[https://example.com/clip]', substr($source, $warnings[0]->startOffset, $warnings[0]->endOffset - $warnings[0]->startOffset));
        $this->assertStringContainsString('https://example.com/clip', $warnings[0]->message);
    }

    /**
     * No warning means the renderer emits an iframe, and a warning means it does not.
     *
     * @return void
     */
    public function testAgreesWithTheRenderer(): void
    {
        foreach ([':youtube[dQw4w9WgXcQ]', ':media[https://example.com/clip]', ':vimeo[https://youtu.be/dQw4w9WgXcQ]'] as $source) {
            $embeds = str_contains(Carve::render($source, ['extensions' => [new MediaEmbedExtension()]]), '<iframe');
            $this->assertSame($embeds, (new MediaEmbedLinter())->lint($source) === [], $source);
        }
    }
}
