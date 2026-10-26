# carve-php-media-embed

Media embed extension for `markup-carve/carve-php` 0.1.14 or later. It resolves provider directives through `dereuromark/media-embed`. See the [Carve documentation](https://markup-carve.github.io/carve/).

## Install

```sh
composer require markup-carve/carve-php-media-embed
```

## Usage

```php
$converter = new \MarkupCarve\Carve\CarveConverter();
$converter->addExtension(new \MarkupCarve\MediaEmbed\MediaEmbedExtension());
echo $converter->convert(":youtube[dQw4w9WgXcQ]");
```

## Documentation

- [Carve documentation](https://markup-carve.github.io/carve/)
- [Guide](docs/guide.md)
- [Documentation index](docs/README.md)
- [Security details](docs/guide.md#security)

## License

[MIT](LICENSE).
