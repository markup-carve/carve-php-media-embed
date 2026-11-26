# carve-php-media-embed

A [Carve](https://github.com/markup-carve/carve-php) extension that embeds media players
(YouTube, Vimeo, Spotify, and [30+ other providers](https://github.com/dereuromark/media-embed/blob/master/docs/supported.md))
using concise inline-extension syntax.

Powered by [dereuromark/media-embed](https://github.com/dereuromark/media-embed).

## Installation

```bash
composer require markup-carve/carve-php-media-embed
```

Requires PHP 8.2+ and `markup-carve/carve-php` 0.2. The 0.2 engine is not released yet: this
branch pins a carve-php `main` commit through a Composer VCS repository, so add the same
repository to your project:

```json
"repositories": [{"type": "vcs", "url": "https://github.com/markup-carve/carve-php"}]
```

## Quick Start

```php
use MarkupCarve\Carve\Carve;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;

echo Carve::render(':youtube[dQw4w9WgXcQ]', ['extensions' => [new MediaEmbedExtension()]]);
```

`MediaEmbedExtension` is an `ExtensionProvider` (provisional in Carve 0.2). Pass the same
instance in the `extensions` option of every call that reads the document (`Carve::parse()`,
`Carve::render()`, `Carve::format()`, `Carve::write()`), so the directives are claimed the
same way everywhere. It registers one inline extension per directive name, named
`media-embed.<name>` (version `1`): the catchall and every provider slug MediaEmbed knows.
A directive that resolves to no embed is not claimed and renders as the engine's
`<span class="ext-NAME">`.

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
$extension = new \MarkupCarve\MediaEmbed\MediaEmbedExtension(null, [
    'catchall'  => 'media',          // directive name for URL-based catchall
    'width'     => 560,              // iframe width in pixels
    'height'    => 315,              // iframe height in pixels
    'providers' => ['youtube', 'vimeo'],  // whitelist; null = all providers
    'linkOnly'  => false,            // true = always write a link, never an iframe
]);
```

| Key | Type | Default | Description |
|---|---|---|---|
| `catchall` | `string` | `'media'` | The directive name that accepts full URLs (`:media[URL]`). |
| `width` | `int` | provider default | Override the iframe `width` attribute. |
| `height` | `int` | provider default | Override the iframe `height` attribute. |
| `providers` | `array<string>` or `null` | `null` | Whitelist of allowed provider slugs. Applies to both per-provider and catchall directives. `null` means all providers are allowed. |
| `linkOnly` | `bool` | `false` | Write every embed as a plain link, whatever the profile. For a host that renders untrusted text with the `full` or `article` profile. |

### Injecting a Preconfigured MediaEmbed Instance

Pass a `MediaEmbed` instance as the first argument to use a custom configuration - for
example, to register custom providers, attach a PSR-16 cache, or supply a custom HTTP client:

```php
use MediaEmbed\MediaEmbed;
use MediaEmbed\Provider\ProviderConfig;

$mediaEmbed = new MediaEmbed();
// $mediaEmbed->addProviderConfig(ProviderConfig::fromArray([...]));  // custom provider
// inject cache / HTTP client via MediaEmbed's own API

$extension = new \MarkupCarve\MediaEmbed\MediaEmbedExtension($mediaEmbed);
```

Refer to [media-embed's documentation](https://github.com/dereuromark/media-embed) for the
full configuration API.

## Output and Degradation

The extension adapts its output to the render options.

| Context | Output |
|---|---|
| `Carve::render()`, profile `full` (default) or `article`, interactive mode | `<iframe ...>` |
| `mode: static` (the build-time renderer) | `<a href rel="noopener noreferrer">` |
| profile `comment` or `minimal` (untrusted content) | `<a href rel="noopener noreferrer">` |
| config `linkOnly: true` | `<a href rel="noopener noreferrer">` |
| Markdown writer | `[provider name](embed-url)` |
| Plain-text and ANSI writers | Embed URL from the core fallback |

Pass the extension in the `extensions` option to both `Carve::parse()` and
`Carve::writeTo()` so the Markdown writer can use the provider name. Without
the extension at write time, it writes the core fallback link with the URL as
its label.

Carve 0.1 chose between iframe and link by the safe mode. In 0.2 safe mode is the profile
plus the URL policy, and the `rawHtml` option governs author-written raw HTML only: the
iframe is written by this trusted extension renderer, not by the author. So render untrusted
documents with the `comment` or `minimal` profile, or set `linkOnly`, to get links.

```php
$html = Carve::render($source, [
    'extensions' => [new MediaEmbedExtension()],
    'profile' => 'comment',
]);
```

## Linting

A directive that resolves to no embed renders no player, so a typo in a URL goes
unnoticed. `MediaEmbedLinter` reports those directives. `Carve::lint()` has no
extension hook, so merge the two results:

```php
use MarkupCarve\Carve\Carve;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;
use MarkupCarve\MediaEmbed\MediaEmbedLinter;

$extension = new MediaEmbedExtension(null, ['providers' => ['youtube', 'vimeo']]);

$diagnostics = array_merge(
    Carve::lint($source),
    (new MediaEmbedLinter($extension))->lint($source),
);
```

Pass the same configured extension you render with, so the linter applies the same
catchall name, provider allowlist and `MediaEmbed` instance. Findings are
`MarkupCarve\Carve\Diagnostics\Diagnostic` objects like those of `Carve::lint()`:
channel `lint`, severity `warning`, the rule in `code`, a UTF-8 byte range
(`startOffset`, `endOffset`) and a tree `path`. `MediaEmbedLinter::lineOf($source, $offset)`
gives the line of an offset.

| Rule | Reported when |
|---|---|
| `media-embed-unresolved` | The directive is the catchall or a known provider, but no provider recognizes the URL, the URL belongs to a different provider, or the content is empty. |
| `media-embed-provider-not-allowed` | The provider is known but missing from the `providers` allowlist. |

Directives of other extensions (`:kbd[...]` and similar) are not reported.

`Carve::lint()` drops its own findings under `carve-lint-*` suppression comments,
but not the warnings you merge in afterwards. `MediaEmbedLinter` reads the same
comments, so one comment silences both:

```text
%% carve-lint-disable-next-line media-embed-unresolved
:media[https://example.com/private-clip]

:media[https://example.com/clip] %% carve-lint-disable-line

%% carve-lint-disable media-embed-provider-not-allowed
```

`disable-line` covers its own line, `disable-next-line` the line after it, and
`disable` runs until a matching `carve-lint-enable`. Without rule ids a comment
covers every rule; separate several ids with commas or spaces.

## Security

- Provider URLs come from media-embed's trusted provider templates, not from raw author input.
- The author-supplied value is only a media ID or full URL, both validated by media-embed
  before any embed code is produced.
- Unknown provider slugs and unresolvable URLs produce no embed (the directive is left
  unclaimed and renders as the engine's `<span class="ext-NAME">`). `MediaEmbedLinter` reports them; see [Linting](#linting).
- The `start`/`t` directive attributes are validated as non-negative integers before being
  appended as query parameters to the provider URL. Non-numeric values are silently ignored.
- Degraded links are sanitized with `htmlspecialchars` and carry `rel="noopener noreferrer"`.

## Supported Providers

See [media-embed's supported providers list](https://github.com/dereuromark/media-embed/blob/master/docs/supported.md)
for the full list of 30+ available slugs.
