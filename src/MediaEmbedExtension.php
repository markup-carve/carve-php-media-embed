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
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param \MediaEmbed\MediaEmbed|null $mediaEmbed
     * @param array<string, mixed> $config
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

        if ($type === $this->config['catchall']) {
            return $this->mediaEmbed->parseUrl($content);
        }

        return $this->mediaEmbed->parseId($content, $type);
    }

}
