<?php

declare(strict_types=1);

/**
 * Demo page for carve-php-media-embed.
 *
 * Run from the package root:
 *   php -S 127.0.0.1:8910 -t demo
 * then open http://127.0.0.1:8910/
 */

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Render a Carve source string with the media-embed extension enabled.
 */
function carve(string $source): string {
	$converter = new CarveConverter();
	$converter->addExtension(new MediaEmbedExtension());
	$html = $converter->convert($source);

	// Demo-only: upgrade protocol-relative provider URLs (//host) to https so the
	// embeds load when this page is served over plain http. Not part of the library.
	return str_replace(['src="//', 'href="//'], ['src="https://', 'href="https://'], $html);
}

/**
 * One showcase entry: a title, the Carve source, and its rendered output.
 *
 * @var array<array{title: string, note: string, source: string}> $examples
 */
$examples = [
	[
		'title' => 'YouTube - per-provider directive',
		'note' => ':youtube[ID] resolves via MediaEmbed::parseId().',
		'source' => ':youtube[aqz-KE-bpKQ]',
	],
	[
		'title' => 'Vimeo - per-provider directive',
		'note' => ':vimeo[ID] resolves via MediaEmbed::parseId().',
		'source' => ':vimeo[76979871]',
	],
	[
		'title' => 'Catchall - paste any supported URL',
		'note' => ':media[URL] resolves via MediaEmbed::parseUrl() across 30+ providers.',
		'source' => ':media[https://www.youtube.com/watch?v=aqz-KE-bpKQ]',
	],
	[
		'title' => 'Catchall with query params (the &amp; fix)',
		'note' => 'Params after the first & survive on the HTML path - note the timestamp.',
		'source' => ':media[https://www.youtube.com/watch?v=aqz-KE-bpKQ&t=43s]',
	],
	[
		'title' => 'Vimeo via catchall URL',
		'note' => 'Same directive, different provider, auto-detected from the URL.',
		'source' => ':media[https://vimeo.com/76979871]',
	],
	[
		'title' => 'Dailymotion via catchall URL',
		'note' => 'Video provider auto-matched.',
		'source' => ':media[https://www.dailymotion.com/video/x7tgad0]',
	],
	[
		'title' => 'Spotify - audio provider',
		'note' => 'MediaEmbed covers audio too; this is why the catchall is :media, not :video.',
		'source' => ':media[https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC]',
	],
	[
		'title' => 'SoundCloud - audio provider',
		'note' => 'Another audio embed through the same directive.',
		'source' => ':media[https://soundcloud.com/forss/flickermood]',
	],
	[
		'title' => 'Inline text + embed',
		'note' => 'A directive inside a paragraph renders inline with surrounding prose.',
		'source' => 'Check this out: :youtube[aqz-KE-bpKQ] - pretty neat.',
	],
	[
		'title' => 'Unknown directive is left untouched',
		'note' => 'Non-provider directives are not claimed, so other extensions/defaults handle them.',
		'source' => ':spoiler[hidden text stays as-is]',
	],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>carve-php-media-embed - demo</title>
	<style>
		:root {
			--bg: #0f1115;
			--card: #181b22;
			--border: #272b35;
			--text: #e6e8ec;
			--muted: #9aa3b2;
			--accent: #6ea8fe;
			--code: #11141a;
		}
		* { box-sizing: border-box; }
		body {
			margin: 0;
			font: 16px/1.55 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
			background: var(--bg);
			color: var(--text);
		}
		header {
			padding: 40px 24px 24px;
			max-width: 980px;
			margin: 0 auto;
		}
		h1 { margin: 0 0 6px; font-size: 28px; }
		header p { margin: 4px 0; color: var(--muted); }
		header code { color: var(--accent); }
		main {
			max-width: 980px;
			margin: 0 auto;
			padding: 0 24px 64px;
			display: grid;
			gap: 22px;
		}
		.card {
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: 12px;
			overflow: hidden;
		}
		.card h2 {
			margin: 0;
			padding: 16px 20px 6px;
			font-size: 17px;
		}
		.card .note {
			padding: 0 20px 12px;
			color: var(--muted);
			font-size: 14px;
		}
		.card pre {
			margin: 0 20px 16px;
			padding: 12px 14px;
			background: var(--code);
			border: 1px solid var(--border);
			border-radius: 8px;
			color: #c9d3e3;
			font: 13px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace;
			overflow-x: auto;
		}
		.render {
			padding: 16px 20px 20px;
			border-top: 1px dashed var(--border);
		}
		.render iframe { max-width: 100%; border: 0; border-radius: 8px; }
		.render p { margin: 0; }
		footer {
			max-width: 980px;
			margin: 0 auto;
			padding: 24px;
			color: var(--muted);
			font-size: 14px;
			border-top: 1px solid var(--border);
		}
		footer a { color: var(--accent); }
	</style>
</head>
<body>
	<header>
		<h1>carve-php-media-embed</h1>
		<p>Opt-in <strong>Carve</strong> extension - embed 30+ media providers via <code>dereuromark/media-embed</code>.</p>
		<p>Each card shows the Carve source and its rendered output.</p>
	</header>
	<main>
		<?php foreach ($examples as $example) { ?>
			<section class="card">
				<h2><?= $example['title'] ?></h2>
				<div class="note"><?= $example['note'] ?></div>
				<pre><?= htmlspecialchars($example['source'], ENT_QUOTES, 'UTF-8') ?></pre>
				<div class="render"><?= carve($example['source']) ?></div>
			</section>
		<?php } ?>
	</main>
	<footer>
		Powered by <a href="https://github.com/markup-carve/carve-php">carve-php</a>
		&amp; <a href="https://github.com/dereuromark/media-embed">media-embed</a>.
	</footer>
</body>
</html>
