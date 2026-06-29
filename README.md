# carve-php-media-embed

A [Carve](https://github.com/markup-carve/carve-php) extension that embeds media players
(YouTube, Vimeo, Spotify, and [~30 other providers](https://github.com/dereuromark/media-embed/blob/master/docs/supported.md))
using concise inline-extension syntax.

Powered by [dereuromark/media-embed](https://github.com/dereuromark/media-embed).

## Installation

```bash
composer require markup-carve/carve-php-media-embed
```

Requires PHP 8.2+ and `markup-carve/carve-php`.

## Quick Start

```php
$converter = new \MarkupCarve\Carve\CarveConverter();
$converter->addExtension(new \MarkupCarve\MediaEmbed\MediaEmbedExtension());

echo $converter->convert(':youtube[dQw4w9WgXcQ]');
```

## Syntax

Two syntax families are supported.

### Family A - Per-Provider

Use the provider's slug as the directive name and pass either a bare media ID or a full URL
of that same provider as the content.

```text
:youtube[dQw4w9WgXcQ]
:youtube[https://www.youtube.com/watch?v=dQw4w9WgXcQ]
:youtube[https://youtu.be/dQw4w9WgXcQ]
:vimeo[123456789]
:spotify[track/6rqhFgbbKwnb9MLmUQDhG6]
```

When the content is a bare ID it is resolved via `MediaEmbed::parseId()` using the directive
name as the host slug. When the content starts with `http://` or `https://` it is resolved
via `MediaEmbed::parseUrl()` and the detected provider slug must match the directive name -
a URL that resolves to a different provider is ignored and leaves the directive unhandled (no
embed is produced). Use `:media[URL]` if you want automatic provider detection without
enforcing a match.

Any of the [30+ supported provider slugs](https://github.com/dereuromark/media-embed/blob/master/docs/supported.md)
can be used as a directive name.

**Produced output (interactive HTML):**

```html
<iframe src="//www.youtube.com/embed/dQw4w9WgXcQ" ...></iframe>
```

### Family B - Catchall URL

Use the `:media` directive (configurable) and pass a full URL. The provider is detected
automatically via `MediaEmbed::parseUrl()`.

```text
:media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]
:media[https://vimeo.com/123456789]
:media[https://open.spotify.com/track/6rqhFgbbKwnb9MLmUQDhG6]
```

**Produced output (interactive HTML):**

```html
<iframe src="//www.youtube.com/embed/dQw4w9WgXcQ" ...></iframe>
```

> **Note:** `:video` is reserved for a future native local-video directive in Carve.
> This extension deliberately uses `:media` as the catchall name so the two will not conflict.

### Directive Attributes

A fixed allowlist of directive attributes is forwarded onto the produced iframe. Only these
named attributes are recognized; arbitrary author-supplied attribute names are intentionally
**not** forwarded.

| Attribute | Values | Description |
|---|---|---|
| `start` / `t` | non-negative integer (optional `s` suffix) | Playback start offset in seconds. Supported by YouTube and any other provider that declares timestamp support. Silently ignored for all other providers. |
| `title` | any non-empty string | Sets the iframe `title` attribute for accessibility (screen readers). |
| `loading` | `lazy` or `eager` | Sets the iframe `loading` attribute for browser-native lazy loading. Any other value is silently ignored. |
| `width` | positive integer | Per-embed iframe width in pixels. Overrides the global `width` config for this embed only. |
| `height` | positive integer | Per-embed iframe height in pixels. Overrides the global `height` config for this embed only. |
| `.className` | CSS class name | Carve shorthand for adding a CSS class (e.g. `{.responsive}`). Multiple classes can be added: `{.a .b}`. Forwarded as the iframe `class` attribute. |
| `class` | space-separated class names | Explicit CSS class attribute (e.g. `{class="a b"}`). Combined with any `{.class}` shorthand classes. |

Attributes compose freely:

```text
:youtube[dQw4w9WgXcQ]{start=90 title="My video" loading=lazy .responsive}
:youtube[dQw4w9WgXcQ]{width=800 height=450}
```

#### Start Offset

For YouTube (and any provider that declares timestamp support), add a `start` attribute
to begin the embed at a specific second. The optional trailing `s` suffix is stripped
automatically. The `t` attribute is accepted as an alias for `start`.

```text
:youtube[dQw4w9WgXcQ]{start=90}
:youtube[dQw4w9WgXcQ]{start=90s}
:youtube[dQw4w9WgXcQ]{t=90}
:media[https://www.youtube.com/watch?v=dQw4w9WgXcQ]{start=90}
```

All four produce an iframe src containing `start=90`.

When a URL already carries a timestamp (e.g. `?t=43s`) **and** the directive also has a
`start`/`t` attribute, the directive attribute wins.

Providers that do not declare timestamp support ignore the attribute silently; the iframe
still renders normally.

## Configuration

Pass config as the second constructor argument:

```php
$converter->addExtension(new \MarkupCarve\MediaEmbed\MediaEmbedExtension(null, [
    'catchall'  => 'media',          // directive name for URL-based catchall
    'width'     => 560,              // iframe width in pixels
    'height'    => 315,              // iframe height in pixels
    'providers' => ['youtube', 'vimeo'],  // whitelist; null = all providers
]));
```

| Key | Type | Default | Description |
|---|---|---|---|
| `catchall` | `string` | `'media'` | The directive name that accepts full URLs (`:media[URL]`). |
| `width` | `int` | provider default | Override the iframe `width` attribute. |
| `height` | `int` | provider default | Override the iframe `height` attribute. |
| `providers` | `array<string>` or `null` | `null` | Whitelist of allowed provider slugs. Applies to both per-provider and catchall directives. `null` means all providers are allowed. |

### Injecting a Preconfigured MediaEmbed Instance

Pass a `MediaEmbed` instance as the first argument to use a custom configuration - for
example, to register custom providers, attach a PSR-16 cache, or supply a custom HTTP client:

```php
use MediaEmbed\MediaEmbed;
use MediaEmbed\Provider\ProviderConfig;

$mediaEmbed = new MediaEmbed();
// $mediaEmbed->addProviderConfig(ProviderConfig::fromArray([...]));  // custom provider
// inject cache / HTTP client via MediaEmbed's own API

$converter->addExtension(new \MarkupCarve\MediaEmbed\MediaEmbedExtension($mediaEmbed));
```

Refer to [media-embed's documentation](https://github.com/dereuromark/media-embed) for the
full configuration API.

## Output and Degradation

The extension adapts its output to the active renderer and safe-mode settings.

| Context | Output |
|---|---|
| HTML renderer, interactive mode (default) | `<iframe ...>` |
| HTML renderer, static mode (`$converter->setRenderMode('static')`) | `<a href rel="noopener noreferrer">` |
| HTML renderer, safe mode with `RAW_HTML_STRIP` or `RAW_HTML_ESCAPE` | `<a href rel="noopener noreferrer">` |
| HTML renderer, safe mode with `RAW_HTML_ALLOW` | `<iframe ...>` |
| Markdown / PlainText renderer | `[name](embed-url)` |
| ANSI renderer | Carve default rendering (known limitation - no event hook) |

A plain `new CarveConverter()` has no restricting safe mode configured, so iframes render
out of the box. If you enable safe mode and still want iframes, explicitly allow raw HTML:

```php
use MarkupCarve\Carve\SafeMode;

$converter->setSafeMode(
    (new \MarkupCarve\Carve\SafeMode())->setRawHtmlMode(SafeMode::RAW_HTML_ALLOW)
);
```

## Security

- Provider URLs come from media-embed's trusted provider templates, not from raw author input.
- The author-supplied value is only a media ID or full URL, both validated by media-embed
  before any embed code is produced.
- Unknown provider slugs and unresolvable URLs silently produce no output (the directive is
  left unhandled).
- The `start`/`t` directive attributes are validated as non-negative integers before being
  appended as query parameters to the provider URL. Non-numeric values are silently ignored.
- Degraded links are sanitized with `htmlspecialchars` and carry `rel="noopener noreferrer"`.

## Supported Providers

See [media-embed's supported providers list](https://github.com/dereuromark/media-embed/blob/master/docs/supported.md)
for the full list of ~30 available slugs.

## License

MIT - see [LICENSE](LICENSE).
