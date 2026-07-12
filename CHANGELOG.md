# Changelog

## [Unreleased]

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
