<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Lint\LintWarning;
use MarkupCarve\Carve\Lint\SourceOffsets;
use MarkupCarve\Carve\Node\Block\Comment;
use MarkupCarve\Carve\Node\Inline\InlineExtension;
use MarkupCarve\Carve\Node\Node;

/**
 * Reports media directives that render no embed. carve-php's `DocumentLinter` has no
 * extension hook, so callers merge these warnings with its result. `carve-lint-*`
 * suppression comments apply here too, because `DocumentLinter` only filters its own.
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

        $byteAt = SourceOffsets::map($source);
        $warnings = [];
        $directives = [];
        $this->collect($document, $source, $byteAt, strlen($source), $warnings, $directives);

        return $this->applySuppressions($warnings, $directives);
    }

    /**
     * @param \MarkupCarve\Carve\Node\Node $node
     * @param string $source
     * @param array<int, int>|null $byteAt
     * @param int $length
     * @param list<\MarkupCarve\Carve\Lint\LintWarning> $warnings
     * @param list<array{0: int, 1: string, 2: array<string>}> $directives Line, action, rule ids.
     *
     * @return void
     */
    protected function collect(Node $node, string $source, ?array $byteAt, int $length, array &$warnings, array &$directives): void
    {
        if ($node instanceof InlineExtension) {
            $finding = $this->extension->diagnose($node);
            if ($finding !== null) {
                $warnings[] = $this->warning($node, $finding[0], $finding[1], $byteAt, $length);
            }
        }

        $pos = $node->getPos();
        // Only a `%% ` line or trailing comment carries a directive; `{% %}` and fenced bodies are inert.
        if (
            $node instanceof Comment && $node->getFenceLength() === null && $pos !== null
            && substr($source, SourceOffsets::toByte($pos->startOffset, $byteAt, $length), 3) === '%% '
            && preg_match('/^carve-lint-(disable-next-line|disable-line|disable|enable)(?:[ \t]+(.*))?$/', $node->getContent(), $match)
        ) {
            $directives[] = [$pos->startLine, $match[1], preg_split('/[ ,]+/', trim($match[2] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []];
        }

        foreach ($node->getChildren() as $child) {
            $this->collect($child, $source, $byteAt, $length, $warnings, $directives);
        }
    }

    /**
     * Drop warnings silenced by `carve-lint-*` comments, with carve-php's `DocumentLinter`
     * semantics: line and next-line scopes hold regardless of document scope, and for a rule
     * the last document-scope directive above the warning wins.
     *
     * @param list<\MarkupCarve\Carve\Lint\LintWarning> $warnings
     * @param list<array{0: int, 1: string, 2: array<string>}> $directives
     *
     * @return list<\MarkupCarve\Carve\Lint\LintWarning>
     */
    protected function applySuppressions(array $warnings, array $directives): array
    {
        if ($warnings === [] || $directives === []) {
            return $warnings;
        }

        usort($directives, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $lineRules = [];
        $scopes = [];
        $all = false;
        $scoped = [];
        foreach ($directives as [$line, $action, $rules]) {
            if ($action === 'disable-line' || $action === 'disable-next-line') {
                $target = $line + (int)($action === 'disable-next-line');
                foreach ($rules === [] ? ['*'] : $rules as $rule) {
                    $lineRules[$target][$rule] = true;
                }

                continue;
            }
            if ($rules === []) {
                $all = $action === 'disable';
                $scoped = [];
            } else {
                foreach ($rules as $rule) {
                    $scoped[$rule] = $action === 'disable';
                }
            }
            $scopes[$line] = [$all, $scoped];
        }

        return array_values(array_filter($warnings, static function (LintWarning $warning) use ($lineRules, $scopes): bool {
            if (isset($lineRules[$warning->line]['*']) || isset($lineRules[$warning->line][$warning->rule])) {
                return false;
            }
            $current = [false, []];
            foreach ($scopes as $line => $scope) {
                if ($line > $warning->line) {
                    break;
                }
                $current = $scope;
            }

            return !($current[1][$warning->rule] ?? $current[0]);
        }));
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
