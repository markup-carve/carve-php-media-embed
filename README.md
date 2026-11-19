# carve-php-media-embed

Media embed extension for `markup-carve/carve-php` 0.2 (the `next2` branch; unreleased). It resolves provider directives through `dereuromark/media-embed`. See the [Carve documentation](https://markup-carve.github.io/carve/).

## Install

```sh
composer require markup-carve/carve-php-media-embed
```

## Usage

```php
use MarkupCarve\Carve\Carve;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;

echo Carve::render(':youtube[dQw4w9WgXcQ]', ['extensions' => [new MediaEmbedExtension()]]);
```

## Documentation

- [Carve documentation](https://markup-carve.github.io/carve/)
- [Guide](docs/guide.md)
- [Documentation index](docs/README.md)
- [Security details](docs/guide.md#security)

## License

[MIT](LICENSE).
