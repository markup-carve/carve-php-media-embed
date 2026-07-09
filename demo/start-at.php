<?php

declare(strict_types=1);

/**
 * Demo: starting a video at a later timestamp.
 *
 * Run from the package root:
 *   php -S 127.0.0.1:8910 -t demo
 * then open http://127.0.0.1:8910/start-at.php
 *
 * Start-time support comes from MediaEmbed's URL parsing: a YouTube `t=<seconds>`
 * (or `t=<seconds>s`) query param is mapped to the embed's `start=` param. Use the
 * catchall `:media[URL]` directive with a timestamped URL. Plain seconds only -
 * composite forms like `1m30s` are not parsed.
 */

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\MediaEmbed\MediaEmbedExtension;

require dirname(__DIR__) . '/vendor/autoload.php';

function carve(string $source): string {
	$converter = new CarveConverter();
	$converter->addExtension(new MediaEmbedExtension());
	$html = $converter->convert($source);

	// Demo-only: upgrade protocol-relative provider URLs so embeds load over http.
	return str_replace(['src="//', 'href="//'], ['src="https://', 'href="https://'], $html);
}

$videoId = 'aqz-KE-bpKQ';

/**
 * @var array<array{title: string, note: string, source: string}> $examples
 */
$examples = [
	[
		'title' => 'From the start (0:00)',
		'note' => 'No timestamp - plays from the beginning.',
		'source' => ":media[https://www.youtube.com/watch?v={$videoId}]",
	],
	[
		'title' => 'Start at 0:30 (t=30s)',
		'note' => 'Half a minute in. Maps to the embed param start=30.',
		'source' => ":media[https://www.youtube.com/watch?v={$videoId}&t=30s]",
	],
	[
		'title' => 'Start at 1:30 (t=90s)',
		'note' => 'A minute and a half in. start=90.',
		'source' => ":media[https://www.youtube.com/watch?v={$videoId}&t=90s]",
	],
	[
		'title' => 'Start at 2:30 (t=150s)',
		'note' => 'Two and a half minutes in. start=150.',
		'source' => ":media[https://www.youtube.com/watch?v={$videoId}&t=150s]",
	],
	[
		'title' => 'Short youtu.be link works too',
		'note' => 'youtu.be/ID?t=150 - same result, start=150.',
		'source' => ":media[https://youtu.be/{$videoId}?t=150]",
	],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>carve-php-media-embed - start at timestamp</title>
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
		header { padding: 40px 24px 24px; max-width: 980px; margin: 0 auto; }
		h1 { margin: 0 0 6px; font-size: 26px; }
		header p { margin: 4px 0; color: var(--muted); }
		header code, header a { color: var(--accent); }
		.callout {
			max-width: 980px;
			margin: 0 auto 8px;
			padding: 0 24px;
			color: var(--muted);
			font-size: 14px;
		}
		main {
			max-width: 980px;
			margin: 0 auto;
			padding: 16px 24px 64px;
			display: grid;
			gap: 22px;
		}
		.card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
		.card h2 { margin: 0; padding: 16px 20px 6px; font-size: 17px; }
		.card .note { padding: 0 20px 12px; color: var(--muted); font-size: 14px; }
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
		.render { padding: 16px 20px 20px; border-top: 1px dashed var(--border); }
		.render iframe { max-width: 100%; border: 0; border-radius: 8px; }
	</style>
</head>
<body>
	<header>
		<h1>Start a video at a later point</h1>
		<p>Use the catchall <code>:media[URL]</code> with a YouTube <code>t=&lt;seconds&gt;</code> timestamp.</p>
		<p><a href="/">&larr; back to the main demo</a></p>
	</header>
	<div class="callout">
		Note: start-time is honored for YouTube (mapped to the embed <code>start=</code> param). Use plain seconds
		(<code>t=90s</code> or <code>t=90</code>); composite forms like <code>1m30s</code> are not parsed. Other
		providers ignore the timestamp in this version.
	</div>
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
</body>
</html>
