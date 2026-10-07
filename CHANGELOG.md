# Changelog

## [Unreleased]

### Changed

- Require carve-php `^0.1.11`. Class attributes now reach the iframe merged: a
  directive written `{.a class="b c"}` emits `class="a b c"`, where the previous
  pin dropped the `.a` shorthand
- A renderer that declares the HTML target without extending `HtmlRenderer` gets
  an iframe rather than the plain-target link fallback, and safe mode and static
  mode are read through the renderer capability interfaces
- The class value is read from the merged `class` attribute rather than assembled
  from the class list, which may hold an explicit multi-name value unsplit

## [0.1.3] - 2026-08-18

### Security

- Require carve-php `^0.1.5`, which probes **every** candidate in a list-valued
  URL attribute instead of trusting the value's leading scheme.
  `srcset="safe.png 1x, javascript:alert(1) 2x"` passed the probe on its second
  entry. Upgrade if you render untrusted Carve or import untrusted HTML.

## [0.1.2] - 2026-08-10

### Changed

- Require carve-php `^0.1.4`, the current security and parser/writer
  convergence release
- Correct the Composer branch alias for the repository's `main` branch and
  pre-1.0 release line
- Move CI to the current checkout action runtime

## [0.1.1] - 2026-07-12

### Fixed

- Demo scripts fataled against the tagged release: they still imported the
  pre-rename `Carve\CarveConverter` class; now `MarkupCarve`, and the
  wildcard carve-php constraint is `^0.1.1`
- README uses a consistent 30+ provider count

## [0.1.0] - 2026-07-02

### Added
- Carve media-embed extension integrating dereuromark/media-embed with markup-carve/carve-php.
- Per-provider shorthand directive `:youtube[ID]` for direct provider lookup by slug.
- Catchall `:media[URL]` directive for URL-based provider detection across 30+ platforms.
- Directive attribute `start`/`t` for timestamp/start-offset support on providers that declare it.
- Directive attributes `title`, `loading`, `width`, `height`, `class` forwarded to the iframe.
- Graceful link degradation in safe-mode (strip/escape raw HTML) and static-mode renderers.
- Non-HTML renderer support (Markdown, PlainText, Carve) via a readable link fallback.
- Provider allowlist via `providers` config key to restrict which platforms are accepted.
