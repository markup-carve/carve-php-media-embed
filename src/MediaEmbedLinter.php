<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Lint\LintWarning;
use MarkupCarve\Carve\Lint\SourceOffsets;
use MarkupCarve\Carve\Node\Inline\InlineExtension;
use MarkupCarve\Carve\Node\Node;

/**
 * Reports media directives that render no embed. carve-php's `DocumentLinter` has no
 * extension hook, so callers merge these warnings with its result.
 */
class MediaEmbedLinter
{
    /**
     * @var string
     */
    public const RULE_UNRESOLVED = 'media-embed-unresolved';

    /**
     * @var string
     */
    public const RULE_PROVIDER_NOT_ALLOWED = 'media-embed-provider-not-allowed';

    protected MediaEmbedExtension $extension;

    /**
     * @param \MarkupCarve\MediaEmbed\MediaEmbedExtension|null $extension The configured extension the document renders with.
     */
    public function __construct(?MediaEmbedExtension $extension = null)
    {
        $this->extension = $extension ?? new MediaEmbedExtension();
    }

    /**
     * @param string $source
     *
     * @return list<\MarkupCarve\Carve\Lint\LintWarning>
     */
    public function lint(string $source): array
    {
        $converter = new CarveConverter();
        $converter->getParser()->enablePositionTracking();
        $document = $converter->parse($source);

        $warnings = [];
        $this->collect($document, SourceOffsets::map($source), strlen($source), $warnings);

        return $warnings;
    }

    /**
     * @param \MarkupCarve\Carve\Node\Node $node
     * @param array<int, int>|null $byteAt
     * @param int $length
     * @param list<\MarkupCarve\Carve\Lint\LintWarning> $warnings
     *
     * @return void
     */
    protected function collect(Node $node, ?array $byteAt, int $length, array &$warnings): void
    {
        if ($node instanceof InlineExtension) {
            $finding = $this->extension->diagnose($node);
            if ($finding !== null) {
                $warnings[] = $this->warning($node, $finding[0], $finding[1], $byteAt, $length);
            }
        }

        foreach ($node->getChildren() as $child) {
            $this->collect($child, $byteAt, $length, $warnings);
        }
    }

    /**
     * @param \MarkupCarve\Carve\Node\Inline\InlineExtension $node
     * @param string $rule
     * @param string $message
     * @param array<int, int>|null $byteAt
     * @param int $length
     *
     * @return \MarkupCarve\Carve\Lint\LintWarning
     */
    protected function warning(InlineExtension $node, string $rule, string $message, ?array $byteAt, int $length): LintWarning
    {
        $pos = $node->getPos();
        if ($pos === null) {
            return new LintWarning(1, 1, $rule, $message, 0, 0);
        }

        return new LintWarning(
            $pos->startLine,
            $pos->startColumn,
            $rule,
            $message,
            SourceOffsets::toByte($pos->startOffset, $byteAt, $length),
            SourceOffsets::toByte($pos->endOffset, $byteAt, $length),
        );
    }
}
