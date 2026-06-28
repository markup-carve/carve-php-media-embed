<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed;

use Carve\CarveConverter;
use Carve\Event\RenderEvent;
use Carve\Extension\ExtensionInterface;
use Carve\Node\ContentNodeInterface;
use Carve\Node\Inline\InlineExtension;
use Carve\Node\Node;
use Carve\Renderer\HtmlRenderer;
use Carve\SafeMode;
use MediaEmbed\MediaEmbed;
use MediaEmbed\Object\MediaObject;

class MediaEmbedExtension implements ExtensionInterface
{
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
        ];
    }

    /**
     * @param \Carve\CarveConverter $converter
     *
     * @return void
     */
    public function register(CarveConverter $converter): void
    {
        $renderer = $converter->getRenderer();

        if ($renderer instanceof HtmlRenderer) {
            $converter->on('render.inline_extension', function (RenderEvent $event) use ($renderer): void {
                $media = $this->resolveEvent($event);
                if ($media === null) {
                    return;
                }

                if ($this->mustDegrade($renderer)) {
                    $event->setHtml($this->linkFallback($media));

                    return;
                }

                $event->setHtml($media->getEmbedCode());
            });

            return;
        }

        // Non-HTML renderers (Markdown, PlainText, Carve) expose on() via
        // EventDispatcherTrait; ANSI renderer may not — check before wiring.
        if (method_exists($renderer, 'on')) {
            $renderer->on('render.inline_extension', function (RenderEvent $event): void {
                $media = $this->resolveEvent($event);
                if ($media === null) {
                    return;
                }

                // Markdown-style link is readable across all plain/markdown targets.
                $event->setHtml('[' . $media->name() . '](<' . $media->getEmbedSrc() . '>)');
            });
        }
    }

    /**
     * @param \Carve\Renderer\HtmlRenderer $renderer
     *
     * @return bool
     */
    protected function mustDegrade(HtmlRenderer $renderer): bool
    {
        if ($renderer->isStaticMode()) {
            return true;
        }

        $safeMode = $renderer->getSafeMode();
        if ($safeMode === null) {
            return false;
        }

        return in_array(
            $safeMode->getRawHtmlMode(),
            [SafeMode::RAW_HTML_STRIP, SafeMode::RAW_HTML_ESCAPE],
            true,
        );
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

        return $this->applyDimensions($this->mediaEmbed->parseId($content, $type), $width, $height);
    }

    /**
     * @param \Carve\Event\RenderEvent $event
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    private function resolveEvent(RenderEvent $event): ?MediaObject
    {
        $node = $event->getNode();
        if (!$node instanceof InlineExtension) {
            return null;
        }

        $attrs = $node->getAttributes();
        $width = $this->parsePositiveInt($attrs['width'] ?? null);
        $height = $this->parsePositiveInt($attrs['height'] ?? null);

        $content = $this->extractChildText($node);
        $media = $this->resolve($node->getExtensionType(), $content, $width, $height);
        $media = $this->applyStartOffset($media, $attrs);

        return $this->applyIframeAttributes($media, $node);
    }

    /**
     * Recursively collect plain text from a node's children.
     *
     * Used as a fallback when the renderer does not provide a childrenRenderer
     * on the RenderEvent (i.e. non-HTML renderers).
     *
     * @param \Carve\Node\Node $node
     *
     * @return string
     */
    private function extractChildText(Node $node): string
    {
        $text = '';
        foreach ($node->getChildren() as $child) {
            if ($child instanceof ContentNodeInterface) {
                $text .= $child->getContent();
            } else {
                $text .= $this->extractChildText($child);
            }
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
            $media->setWidth($w);
        }
        if ($h !== null) {
            $media->setHeight($h);
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
     * @param \Carve\Node\Inline\InlineExtension $node
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    private function applyIframeAttributes(?MediaObject $media, InlineExtension $node): ?MediaObject
    {
        if ($media === null) {
            return null;
        }

        $attrs = $node->getAttributes();

        // title - accessibility label for the iframe
        $title = $attrs['title'] ?? null;
        if ($title !== null && $title !== '') {
            $media->setAttribute('title', $title);
        }

        // loading - lazy/eager only; any other value is silently ignored
        $loading = $attrs['loading'] ?? null;
        if ($loading === 'lazy' || $loading === 'eager') {
            $media->setAttribute('loading', $loading);
        }

        // CSS classes - combines {.class} shorthand and explicit class="..." attribute
        $classes = $node->getClassList();
        if ($classes !== []) {
            $media->setAttribute('class', implode(' ', $classes));
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
     * @param array<string, string> $nodeAttributes
     *
     * @return \MediaEmbed\Object\MediaObject|null
     */
    private function applyStartOffset(?MediaObject $media, array $nodeAttributes): ?MediaObject
    {
        if ($media === null) {
            return null;
        }

        // Prefer 'start'; fall back to 't' (YouTube-style alias).
        $raw = $nodeAttributes['start'] ?? $nodeAttributes['t'] ?? null;
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

        $media->setParam($stub['timestamp-param'] ?? 'start', $seconds);

        return $media;
    }
}
