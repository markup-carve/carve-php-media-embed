<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed;

use MarkupCarve\Carve\Attrs;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Node;
use MediaEmbed\MediaEmbed;
use MediaEmbed\Object\MediaObject;

/**
 * Claims `:<provider>[...]` and the catchall `:media[...]` inline extensions (C2-X-006) and renders them as embeds.
 * Pass an instance in the `extensions` option of `Carve::render()` (and of `parse`, `format`, `write`).
 */
class MediaEmbedExtension implements ExtensionProvider
{
    /**
     * Qualified-name prefix of the extension nodes this package creates (C2-X-001).
     *
     * @var string
     */
    public const NAME_PREFIX = 'media-embed.';

    /**
     * @var string
     */
    public const VERSION = '1';

    /**
     * Profiles for untrusted content: an embed degrades to a plain link there, as 0.1 safe mode did.
     *
     * @var array<string>
     */
    public const LINK_ONLY_PROFILES = ['comment', 'minimal'];

    protected MediaEmbed $mediaEmbed;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param \MediaEmbed\MediaEmbed|null $mediaEmbed
     * @param array<string, mixed> $config
     */
    public function __construct(?MediaEmbed $mediaEmbed = null, array $config = [])
    {
        $this->mediaEmbed = $mediaEmbed ?? new MediaEmbed();
        $this->config = $config + [
            'catchall' => 'media',
            'providers' => null,
            'linkOnly' => false,
        ];
    }

    /**
     * One definition per directive name: the catchall and every provider slug MediaEmbed knows.
     *
     * @return array<\MarkupCarve\Carve\Extension\ExtensionDefinition>
     */
    public function definitions(): array
    {
        $names = array_unique(array_merge([(string)$this->config['catchall']], array_map('strval', array_keys($this->mediaEmbed->getHosts()))));
        $definitions = [];
        foreach ($names as $name) {
            $definitions[] = new ExtensionDefinition(
                self::NAME_PREFIX . $name,
                self::VERSION,
                [],
                matcher: ['form' => 'inline', 'name' => $name],
                transform: fn (Claim $claim): ?Node => $this->claim($claim),
                renderer: fn (Node $node, RenderHost $host): string => $this->renderEmbed($node, $host),
                buildTimeRenderer: fn (Node $node, RenderHost $host): string => $this->renderLink($node),
            );
        }

        return $definitions;
    }

    /**
     * Turn a resolvable directive into an extension node; an unresolvable one stays the core node (the transform returns null),
     * which the engine renders as its unclaimed-extension span.
     *
     * @param \MarkupCarve\Carve\Extension\Claim $claim
     *
     * @return \MarkupCarve\Carve\Node|null
     */
    protected function claim(Claim $claim): ?Node
    {
        $content = $this->extractChildText($claim->node);
        if ($this->resolveNode($claim->name, $content, self::attrs($claim->node)) === null) {
            return null;
        }

        $node = new Node('inline_extension', []);
        $node->f = [
            'name' => self::NAME_PREFIX . $claim->name,
            'version' => self::VERSION,
            'props' => ['type' => $claim->name, 'content' => $content],
            'fallback' => [$claim->copyNode()],
        ];

        return $node;
    }

    /**
     * @param \MarkupCarve\Carve\Node $node
     * @param \MarkupCarve\Carve\Extension\RenderHost $host
     *
     * @return string
     */
    protected function renderEmbed(Node $node, RenderHost $host): string
    {
        if ($this->config['linkOnly'] || in_array($host->profile, self::LINK_ONLY_PROFILES, true)) {
            return $this->renderLink($node);
        }
        $media = $this->mediaFor($node);
        if ($media === null) {
            return '';
        }

        return $this->stampSource($media->getEmbedCode(), $node);
    }

    /**
     * The link an embed degrades to: in static mode (C2-X-052), a link-only profile, or with `linkOnly`.
     *
     * @param \MarkupCarve\Carve\Node $node
     *
     * @return string
     */
    protected function renderLink(Node $node): string
    {
        $media = $this->mediaFor($node);
        if ($media === null) {
            return '';
        }

        return $this->stampSource($this->linkFallback($media), $node);
    }

    /**
     * @param \MarkupCarve\Carve\Node $node
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    protected function mediaFor(Node $node): ?MediaObject
    {
        $props = is_array($node->f['props'] ?? null) ? $node->f['props'] : [];

        return $this->resolveNode((string)($props['type'] ?? ''), (string)($props['content'] ?? ''), self::attrs($node));
    }

    /**
     * @param \MediaEmbed\Object\MediaObject $media
     *
     * @return string
     */
    protected function linkFallback(MediaObject $media): string
    {
        $href = htmlspecialchars($media->getEmbedSrc(), ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($media->name(), ENT_QUOTES, 'UTF-8');

        return '<a href="' . $href . '" rel="noopener noreferrer">' . $text . '</a>';
    }

    /**
     * @param string $type
     * @param string $content
     * @param int|null $width Per-directive width override (takes precedence over config).
     * @param int|null $height Per-directive height override (takes precedence over config).
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    protected function resolve(string $type, string $content, ?int $width = null, ?int $height = null): ?MediaObject
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        $providers = $this->config['providers'];

        if ($type === $this->config['catchall']) {
            $media = $this->mediaEmbed->parseUrl($content);
            // Whitelist also gates the catchall: reject a resolved provider not in the list.
            if ($media !== null && is_array($providers) && !in_array($media->slug(), $providers, true)) {
                return null;
            }

            return $this->applyDimensions($media, $width, $height);
        }

        if (is_array($providers) && !in_array($type, $providers, true)) {
            return null;
        }

        // If content is a full URL, resolve via parseUrl() and enforce that the
        // detected provider matches the directive name (e.g. :youtube[vimeo URL] -> no embed).
        if (preg_match('/^https?:\/\//i', $content)) {
            $media = $this->mediaEmbed->parseUrl($content);
            if ($media === null || $media->slug() !== $type) {
                return null;
            }

            return $this->applyDimensions($media, $width, $height);
        }

        return $this->applyDimensions($this->mediaEmbed->parseId($content, $type), $width, $height);
    }

    /**
     * Whether a directive of this name is one this extension renders: the catchall
     * or a provider slug the MediaEmbed instance knows.
     *
     * @param string $type
     *
     * @return bool
     */
    public function handles(string $type): bool
    {
        return $type === $this->config['catchall'] || isset($this->mediaEmbed->getHosts()[$type]);
    }

    /**
     * Why a handled directive renders no embed, as `[rule, message]`, or null when it
     * embeds (or is not a directive this extension handles).
     *
     * @param \MarkupCarve\Carve\Node $node A core `inline_extension` node.
     *
     * @return array{0: string, 1: string}|null
     */
    public function diagnose(Node $node): ?array
    {
        if ($node->type !== 'inline_extension') {
            return null;
        }
        $type = (string)($node->f['name'] ?? '');
        if (!$this->handles($type)) {
            return null;
        }

        $content = trim($this->extractChildText($node));
        if ($content === '') {
            return [MediaEmbedLinter::RULE_UNRESOLVED, sprintf('":%s[]" names no media, so nothing is embedded.', $type)];
        }
        if ($this->resolve($type, $content) !== null) {
            return null;
        }

        $providers = $this->config['providers'];
        $isUrl = preg_match('/^https?:\/\//i', $content) === 1;
        $media = $isUrl ? $this->mediaEmbed->parseUrl($content) : null;

        if ($type === $this->config['catchall']) {
            if ($media === null) {
                return [MediaEmbedLinter::RULE_UNRESOLVED, sprintf('No provider recognizes "%s", so nothing is embedded.', $content)];
            }

            return [MediaEmbedLinter::RULE_PROVIDER_NOT_ALLOWED, sprintf('"%s" resolves to provider "%s", which the providers list does not allow.', $content, $media->slug())];
        }

        if (is_array($providers) && !in_array($type, $providers, true)) {
            return [MediaEmbedLinter::RULE_PROVIDER_NOT_ALLOWED, sprintf('Provider "%s" is not in the providers list, so nothing is embedded.', $type)];
        }
        if ($isUrl && $media !== null) {
            return [MediaEmbedLinter::RULE_UNRESOLVED, sprintf('"%s" is a %s URL, not a %s one, so nothing is embedded.', $content, $media->slug(), $type)];
        }

        return [MediaEmbedLinter::RULE_UNRESOLVED, sprintf('"%s" is not a %s URL or id, so nothing is embedded.', $content, $type)];
    }

    /**
     * @param string $type
     * @param string $content
     * @param \MarkupCarve\Carve\Attrs|null $attrs
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    private function resolveNode(string $type, string $content, ?Attrs $attrs): ?MediaObject
    {
        $width = $this->parsePositiveInt($attrs?->get('width'));
        $height = $this->parsePositiveInt($attrs?->get('height'));

        $media = $this->resolve($type, $content, $width, $height);
        $media = $this->applyStartOffset($media, $attrs);

        return $this->applyIframeAttributes($media, $attrs);
    }

    /**
     * @param \MarkupCarve\Carve\Node $node
     *
     * @return \MarkupCarve\Carve\Attrs|null
     */
    private static function attrs(Node $node): ?Attrs
    {
        $attrs = $node->f['attrs'] ?? null;

        return $attrs instanceof Attrs ? $attrs : null;
    }

    /**
     * Stamp the rendered embed with its Carve source (data-carve-source) so a
     * WYSIWYG round-trip can rebuild the directive: the rendered iframe URL
     * cannot be reversed reliably.
     *
     * The attribute block is written back in canonical order rather than the
     * authored spelling, which does not survive to the renderer: `{.a class="b"}`
     * and `{class="a b"}` both arrive merged. So the stamp re-renders to the same
     * embed, it is not a byte copy of what the author typed.
     *
     * @param string $html Rendered embed markup (iframe or link fallback).
     * @param \MarkupCarve\Carve\Node $node The extension node.
     *
     * @return string
     */
    private function stampSource(string $html, Node $node): string
    {
        $props = is_array($node->f['props'] ?? null) ? $node->f['props'] : [];
        $source = ':' . ($props['type'] ?? '') . '[' . ($props['content'] ?? '') . ']'
            . $this->attributeBlock(self::attrs($node));
        $stamp = 'data-carve-source="' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8') . '"';

        return preg_replace_callback(
            '/<(iframe|a|span|div)\b/i',
            fn (array $m): string => '<' . $m[1] . ' ' . $stamp,
            $html,
            1,
        ) ?? $html;
    }

    /**
     * Spell the node's merged attributes as a Carve attribute block, or an empty
     * string when it carries none.
     *
     * Order is `#id`, then one `.class` per class in list order, then the
     * remaining key-value attributes in the order the parser merged them.
     *
     * @param \MarkupCarve\Carve\Attrs|null $attrs
     *
     * @return string
     */
    private function attributeBlock(?Attrs $attrs): string
    {
        if ($attrs === null) {
            return '';
        }
        $parts = [];

        if (($attrs->id ?? '') !== '') {
            $parts[] = '#' . $attrs->id;
        }

        foreach ($attrs->classes as $class) {
            $parts[] = '.' . $class;
        }

        foreach ($attrs->pairs as [$name, $value]) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            $parts[] = $name . '="' . $escaped . '"';
        }

        return $parts === [] ? '' : '{' . implode(' ', $parts) . '}';
    }

    /**
     * Recursively collect the plain text of a node's children.
     *
     * @param \MarkupCarve\Carve\Node $node
     *
     * @return string
     */
    private function extractChildText(Node $node): string
    {
        $text = '';
        foreach ($node->children ?? [] as $child) {
            // Smart punctuation keeps its source spelling: an id or URL means the typed bytes.
            $value = $child->type === 'smart_punctuation' ? ($child->f['source'] ?? null) : ($child->f['value'] ?? null);
            $text .= is_string($value) ? $value : $this->extractChildText($child);
        }

        return $text;
    }

    /**
     * Apply width/height to the media object. Per-directive values take precedence over config.
     *
     * @param \MediaEmbed\Object\MediaObject|null $media
     * @param int|null $width Per-directive override; falls back to config when null.
     * @param int|null $height Per-directive override; falls back to config when null.
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    private function applyDimensions(?MediaObject $media, ?int $width = null, ?int $height = null): ?MediaObject
    {
        if ($media === null) {
            return null;
        }

        $w = $width ?? (isset($this->config['width']) ? (int)$this->config['width'] : null);
        $h = $height ?? (isset($this->config['height']) ? (int)$this->config['height'] : null);

        if ($w !== null) {
            $media = $media->withWidth($w);
        }
        if ($h !== null) {
            $media = $media->withHeight($h);
        }

        return $media;
    }

    /**
     * Apply allowlisted iframe attributes from the directive node: title, loading, CSS classes.
     *
     * Only a fixed set of known-safe attribute names is forwarded; arbitrary author attributes
     * are intentionally NOT passed through.
     *
     * @param \MediaEmbed\Object\MediaObject|null $media
     * @param \MarkupCarve\Carve\Attrs|null $attrs
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    private function applyIframeAttributes(?MediaObject $media, ?Attrs $attrs): ?MediaObject
    {
        if ($media === null || $attrs === null) {
            return $media;
        }

        // title - accessibility label for the iframe
        $title = $attrs->get('title');
        if ($title !== null && $title !== '') {
            $media = $media->withAttribute('title', $title);
        }

        // loading - lazy/eager only; any other value is silently ignored
        $loading = $attrs->get('loading');
        if ($loading === 'lazy' || $loading === 'eager') {
            $media = $media->withAttribute('loading', $loading);
        }

        // CSS classes: 0.2 merges the `.class` shorthand and `class="..."` into one list (C2-A-035).
        $class = $attrs->classes === [] ? null : implode(' ', $attrs->classes);
        if ($class !== null) {
            $media = $media->withAttribute('class', $class);
        }

        return $media;
    }

    /**
     * Parse a string as a positive integer; returns null for empty, non-numeric, or non-positive input.
     *
     * @param string|null $value
     *
     * @return int|null
     */
    private function parsePositiveInt(?string $value): ?int
    {
        if ($value === null || $value === '' || !ctype_digit($value)) {
            return null;
        }
        $int = (int)$value;

        return $int > 0 ? $int : null;
    }

    /**
     * Apply a start-offset to the media object if the node carries a `start` or `t` attribute
     * and the resolved provider supports timestamps.
     *
     * Accepts an optional trailing 's' suffix (e.g. '90s' → 90). Invalid or non-numeric values
     * are silently ignored. Providers that do not declare `supports-timestamp` are also skipped.
     *
     * When both a URL-embedded timestamp and a `start`/`t` attribute are present, the attribute
     * wins because it is applied after the MediaObject is constructed.
     *
     * @param \MediaEmbed\Object\MediaObject|null $media
     * @param \MarkupCarve\Carve\Attrs|null $attrs
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    private function applyStartOffset(?MediaObject $media, ?Attrs $attrs): ?MediaObject
    {
        if ($media === null || $attrs === null) {
            return $media;
        }

        // Prefer 'start'; fall back to 't' (YouTube-style alias).
        $raw = $attrs->get('start') ?? $attrs->get('t');
        if ($raw === null) {
            return $media;
        }

        // Strip optional trailing 's' (e.g. '90s' → '90').
        $raw = rtrim((string)$raw, 's');

        // Must be a non-negative integer string; anything else is silently ignored.
        if ($raw === '' || !ctype_digit($raw)) {
            return $media;
        }

        $seconds = (int)$raw;

        // Only apply when the resolved provider declares timestamp support.
        $stub = $this->mediaEmbed->getHosts()[$media->slug()] ?? null;
        if ($stub === null || empty($stub['supports-timestamp'])) {
            return $media;
        }

        $media = $media->withParam($stub['timestamp-param'] ?? 'start', $seconds);

        return $media;
    }
}
