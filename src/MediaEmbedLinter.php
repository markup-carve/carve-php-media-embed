<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed;

use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Diagnostics\Diagnostic;
use MarkupCarve\Carve\Node;

/**
 * Reports media directives that render no embed. `Carve::lint()` has no extension hook, so callers merge
 * these diagnostics with its result. `carve-lint-*` control comments (C2-B-215, C2-B-216) apply here too,
 * because `Carve::lint()` only filters its own findings.
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
     * Diagnostics on channel `lint` with severity `warning`; the range is in UTF-8 bytes (C2-A-173).
     *
     * @param string $source
     *
     * @return list<\MarkupCarve\Carve\Diagnostics\Diagnostic>
     */
    public function lint(string $source): array
    {
        $document = Carve::parse($source);

        $warnings = [];
        $directives = [];
        $this->collect($document, '', $source, $warnings, $directives);

        return $this->applySuppressions($warnings, $directives, $source);
    }

    /**
     * The 1-based line a byte offset of the source falls on.
     *
     * @param string $source
     * @param int $offset
     *
     * @return int
     */
    public static function lineOf(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, max(0, min($offset, strlen($source)))) + 1;
    }

    /**
     * @param \MarkupCarve\Carve\Node $node
     * @param string $path RFC 6901 pointer of the node.
     * @param string $source
     * @param list<\MarkupCarve\Carve\Diagnostics\Diagnostic> $warnings
     * @param list<array{0: int, 1: string, 2: array<string>}> $directives Line, action, rule ids.
     *
     * @return void
     */
    protected function collect(Node $node, string $path, string $source, array &$warnings, array &$directives): void
    {
        if ($node->type === 'inline_extension') {
            $finding = $this->extension->diagnose($node);
            if ($finding !== null) {
                $warnings[] = new Diagnostic($finding[0], 'warning', 'lint', $finding[1], $path, $node->start, $node->end);
            }
        }

        // Only a `%% ` line or trailing comment carries a directive; braced and block comments are inert.
        if ($node->type === 'comment' && ($node->f['form'] ?? null) === 'line' && $node->start >= 0) {
            $marker = strpos($source, '%%', $node->start);
            if (
                $marker !== false && ($source[$marker + 2] ?? '') === ' '
                && preg_match('/^carve-lint-(disable-next-line|disable-line|disable|enable)(?:[ \t]+(.*))?$/', (string)($node->f['value'] ?? ''), $match)
            ) {
                $directives[] = [self::lineOf($source, $node->start), $match[1], preg_split('/[ ,]+/', trim($match[2] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []];
            }
        }

        foreach ($node->children ?? [] as $i => $child) {
            $this->collect($child, $path . '/children/' . $i, $source, $warnings, $directives);
        }
    }

    /**
     * Drop warnings silenced by `carve-lint-*` comments, with `Carve::lint()`
     * semantics: line and next-line scopes hold regardless of document scope, and for a rule
     * the last document-scope directive above the warning wins.
     *
     * @param list<\MarkupCarve\Carve\Diagnostics\Diagnostic> $warnings
     * @param list<array{0: int, 1: string, 2: array<string>}> $directives
     * @param string $source
     *
     * @return list<\MarkupCarve\Carve\Diagnostics\Diagnostic>
     */
    protected function applySuppressions(array $warnings, array $directives, string $source): array
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

        $scopeLines = array_keys($scopes);

        return array_values(array_filter($warnings, static function (Diagnostic $warning) use ($lineRules, $scopes, $scopeLines, $source): bool {
            $line = self::lineOf($source, $warning->startOffset);
            if (isset($lineRules[$line]['*']) || isset($lineRules[$line][$warning->code])) {
                return false;
            }
            // Last scope line at or above the warning, by binary search.
            $low = 0;
            $high = count($scopeLines) - 1;
            $current = [false, []];
            while ($low <= $high) {
                $mid = intdiv($low + $high, 2);
                if ($scopeLines[$mid] <= $line) {
                    $current = $scopes[$scopeLines[$mid]];
                    $low = $mid + 1;
                } else {
                    $high = $mid - 1;
                }
            }

            return !($current[1][$warning->code] ?? $current[0]);
        }));
    }
}
