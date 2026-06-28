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

class MediaEmbedExtension implements ExtensionInterface {

    protected MediaEmbed $mediaEmbed;

    /**
     * @var array{catchall: string, providers: array<string>|null, width?: int, height?: int, ...}
     */
    protected array $config;

    /**
     * @param \MediaEmbed\MediaEmbed|null $mediaEmbed
     * @param array{catchall?: string, providers?: array<string>|null, width?: int, height?: int, ...} $config
     */
    public function __construct(?MediaEmbed $mediaEmbed = null, array $config = []) {
        $this->mediaEmbed = $mediaEmbed ?? new MediaEmbed();
        $this->config = $config + [
            'catchall' => 'media',
            'providers' => null,
        ];
    }

    public function register(CarveConverter $converter): void {
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
                $event->setHtml('[' . $media->name() . '](' . $media->getEmbedSrc() . ')');
            });
        }
    }

    protected function mustDegrade(HtmlRenderer $renderer): bool {
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

    protected function linkFallback(MediaObject $media): string {
        $href = htmlspecialchars($media->getEmbedSrc(), ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($media->name(), ENT_QUOTES, 'UTF-8');

        return '<a href="' . $href . '" rel="noopener noreferrer">' . $text . '</a>';
    }

    protected function resolve(string $type, string $content): ?MediaObject {
        $content = trim(strip_tags($content));
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

            return $this->applyDimensions($media);
        }

        if (is_array($providers) && !in_array($type, $providers, true)) {
            return null;
        }

        return $this->applyDimensions($this->mediaEmbed->parseId($content, $type));
    }

    /**
     * Resolve a MediaObject from a render event, handling both HTML and
     * non-HTML renderers. HtmlRenderer sets a childrenRenderer on the event
     * so getChildrenHtml() returns the rendered text; non-HTML renderers
     * (MarkdownRenderer, PlainTextRenderer, CarveRenderer) do not, so we fall
     * back to walking the node's children and collecting raw text content.
     */
    private function resolveEvent(RenderEvent $event): ?MediaObject {
        $node = $event->getNode();
        if (!$node instanceof InlineExtension) {
            return null;
        }

        $content = $event->getChildrenHtml();
        if ($content === '') {
            $content = $this->extractChildText($node);
        }

        return $this->resolve($node->getExtensionType(), $content);
    }

    /**
     * Recursively collect plain text from a node's children.
     *
     * Used as a fallback when the renderer does not provide a childrenRenderer
     * on the RenderEvent (i.e. non-HTML renderers).
     */
    private function extractChildText(Node $node): string {
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

    // MediaEmbed does not expose width/height through call config; apply via setWidth/setHeight.
    private function applyDimensions(?MediaObject $media): ?MediaObject {
        if ($media === null) {
            return null;
        }
        if (isset($this->config['width'])) {
            $media->setWidth((int)$this->config['width']);
        }
        if (isset($this->config['height'])) {
            $media->setHeight((int)$this->config['height']);
        }

        return $media;
    }

}
