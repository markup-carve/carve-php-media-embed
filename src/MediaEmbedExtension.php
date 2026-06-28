<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed;

use Carve\CarveConverter;
use Carve\Event\RenderEvent;
use Carve\Extension\ExtensionInterface;
use Carve\Node\Inline\InlineExtension;
use Carve\Renderer\HtmlRenderer;
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
        if (!$renderer instanceof HtmlRenderer) {
            return;
        }

        $converter->on('render.inline_extension', function (RenderEvent $event): void {
            $node = $event->getNode();
            if (!$node instanceof InlineExtension) {
                return;
            }

            $media = $this->resolve($node->getExtensionType(), $event->getChildrenHtml());
            if ($media === null) {
                return;
            }

            $event->setHtml($media->getEmbedCode());
        });
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
