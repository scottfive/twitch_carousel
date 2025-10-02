<?php
/**
 * Project: Twitch Stream Carousel
 * File: index.php
 * Description: Simple front-end example of how you might implement a Twitch Stream Carousel.
 *
 * Copyright (c) 2025 ScottFive
 * This source code is licensed under the MIT license found in the LICENSE file in the project root.
 *
 * @license MIT
 * @link https://github.com/scottfive/twitch-carousel
 * @author ScottFive
 * @since 2025-10-02
 */



	/**
	 * Make sure we have a config file
	 */
	if (is_file(__DIR__ . '/config.php')) {
		require_once __DIR__ . '/config.php';
	}


	/**
	 * Read any incoming Querystring fields
	 */
	function sanitize_color_value($value) {
		$value = trim((string)$value);
		if ($value === '') {
			return '';
		}

		if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
			return $value;
		}

		if (preg_match('/^(?:rgb|rgba|hsl|hsla)\([0-9%.,\s]+\)$/i', $value)) {
			return $value;
		}

		if (preg_match('/^[a-zA-Z]+$/', $value)) {
			return $value;
		}

		return '';
	}

	function read_color_param($key, $fallback) {
		if (isset($_GET[$key])) {
			$sanitized = sanitize_color_value($_GET[$key]);
			if ($sanitized !== '') {
				return $sanitized;
			}
		}

		return $fallback;
	}

	$defaultGameId = defined('TWITCH_CAROUSEL_DEFAULT_GAME_ID') ? trim((string)TWITCH_CAROUSEL_DEFAULT_GAME_ID) : '';
	$defaultKeywords = defined('TWITCH_CAROUSEL_DEFAULT_TITLE_KEYWORDS') ? trim((string)TWITCH_CAROUSEL_DEFAULT_TITLE_KEYWORDS) : '';
	$defaultTags = defined('TWITCH_CAROUSEL_DEFAULT_TAG_KEYWORDS') ? trim((string)TWITCH_CAROUSEL_DEFAULT_TAG_KEYWORDS) : '';

	$gameId = isset($_GET['game_id']) ? trim((string)$_GET['game_id']) : '';
	if ($gameId === '' && $defaultGameId !== '') {
		$gameId = $defaultGameId;
	}

	$keywords = isset($_GET['keywords']) ? trim((string)$_GET['keywords']) : '';
	if ($keywords === '' && $defaultKeywords !== '') {
		$keywords = $defaultKeywords;
	}

	$tags = isset($_GET['tags']) ? trim((string)$_GET['tags']) : '';
	if ($tags === '' && $defaultTags !== '') {
		$tags = $defaultTags;
	}



	/**
	 * Setup Color Defaults
	 */
	$colorDefaults = [
		'pageBg' => '#25252D',
		'pageText' => '#e6e6e6',
		'cardBg' => '#151924',
		'cardText' => '#cbd5e1',
		'tagBg' => '#22283a',
		'tagText' => '#c3d1ff'
	];

	$colorParams = [
		'pageBg' => 'background_color',
		'pageText' => 'text_color',
		'cardBg' => 'card_background_color',
		'cardText' => 'card_text_color',
		'tagBg' => 'tag_background_color',
		'tagText' => 'tag_text_color'
	];


	/**
	 * Merge color defaults with any incoming custom colors
	 */
	$colorConfig = [];
	foreach ($colorParams as $colorKey => $paramKey) {
		$colorConfig[$colorKey] = read_color_param($paramKey, $colorDefaults[$colorKey]);
	}

	$cssVarMap = [
		'pageBg' => '--page-bg',
		'pageText' => '--page-text',
		'cardBg' => '--card-bg',
		'cardText' => '--card-text',
		'tagBg' => '--tag-bg',
		'tagText' => '--tag-text'
	];

	$colorStyles = [];
	foreach ($cssVarMap as $colorKey => $cssVar) {
		$colorStyles[] = $cssVar . ': ' . $colorConfig[$colorKey] . ';';
	}

	$bodyStyle = trim(implode(' ', $colorStyles));


	/**
	 * Prep options for Javascript
	 */
	$oCarouselConfig = [
		'gameId' => $gameId,
		'keywords' => $keywords,
		'tags' => $tags,
		'colors' => $colorConfig
	];
?>

<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Twitch Streams Carousel</title>
	<link rel="preconnect" href="https://static-cdn.jtvnw.net">
	<style>
		:root {
			--card-w: 320px;
			--card-h: 180px;
			--gap: 1rem;
			--page-bg: #25252D;
			--page-text: #e6e6e6;
			--card-bg: #151924;
			--card-text: #cbd5e1;
			--tag-bg: #22283a;
			--tag-text: #c3d1ff;
		}
		* { box-sizing: border-box; }
		body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: var(--page-bg); color: var(--page-text); }
		.wrapper { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
		.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
		h1 { font-size: 1.25rem; font-weight: 600; margin: 0 0 1rem; }
		.carousel { position: relative; overflow: hidden; min-height: calc(var(--card-h) + 130px + 3rem); }
		.track { display: flex; align-items: center; gap: var(--gap); overflow-x: auto; overflow-y: hidden; padding: 1.5rem 3.5rem; position: relative; z-index:100; scroll-behavior: smooth; scrollbar-width: none; -ms-overflow-style: none; touch-action: none; }
		.track::-webkit-scrollbar { display: none; }
		.card { background: var(--card-bg); color: var(--card-text); border: 1px solid #222833; border-radius: .75rem; width: var(--card-w); height: calc(var(--card-h) + 130px); flex: 0 0 var(--card-w); box-shadow: 0 10px 20px rgba(0,0,0,.25); transform: scale(.85); opacity: .6; transition: transform .35s ease, opacity .35s ease, filter .35s ease; filter: blur(0.5px); will-change: transform; }
		.card { touch-action: none; }
		.card.is-near { transform: scale(.95); opacity: .9; filter: blur(0); }
		.card.is-center { transform: scale(1.15); opacity: 1; z-index: 3; filter: none; }
		.thumb { width: 100%; height: var(--card-h); display: block; border-top-left-radius: .75rem; border-top-right-radius: .75rem; object-fit: cover; background: #0a0c12; }
		.meta { padding: .75rem; color: var(--card-text); }
		.name { font-weight: 600; font-size: .95rem; margin-bottom: .25rem; }
		.title { font-size: .9rem; color: var(--card-text); line-height: 1.3; margin-bottom: .5rem; }
		.tags { display: flex; flex-wrap: wrap; gap: .25rem; }
		.tag { font-size: .75rem; background: var(--tag-bg); color: var(--tag-text); border: 1px solid #2f3850; padding: .2rem .45rem; border-radius: 999px; }
		.loading-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 400; opacity: 0; visibility: hidden; transition: opacity .2s ease; }
		.loading-overlay.is-visible { opacity: 1; visibility: visible; }
		.loading-overlay img { width: 64px; height: 64px; display: block; image-rendering: -webkit-optimize-contrast; }
		.loading-overlay svg	{width:75px; height:75px; animation-name: spin; animation-duration: 5000ms; animation-iteration-count: infinite; animation-timing-function: linear; }

		@keyframes spin {
			from {
				transform:rotate(0deg);
			}
			to {
				transform:rotate(360deg);
			}
		}
		.nav { display: flex; justify-content: space-between; position: absolute; inset: 0; align-items: center; pointer-events: none; z-index:1000; }
		.nav button { pointer-events: auto; background: rgba(15,17,21,.85); border: 1px solid #2a2f3a; color: #fff; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: grid; place-items: center; }
		.nav button:focus-visible { outline: 2px solid #7c5cff; }
		.nav .left { position: absolute; left: .5rem; }
		.nav .right { position: absolute; right: .5rem; }

		.nav button { pointer-events: auto; background: rgba(15,17,21,.85); border: 1px solid #2a2f3a; color: #fff; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: grid; place-items: center; }
		.nav button:focus-visible { outline: 2px solid #7c5cff; }
		.nav .left { position: absolute; left: .5rem; }
		.nav .right { position: absolute; right: .5rem; }
		@media (max-width: 900px) {
			:root {
				--card-w: 280px;
				--card-h: 158px;
			}
			.track { padding: 1.25rem 3rem; }
		}
		@media (max-width: 720px) {
			:root {
				--card-w: 240px;
				--card-h: 136px;
				--gap: .75rem;
			}
			.carousel { min-height: calc(var(--card-h) + 120px + 2.5rem); }
			.track { padding: 1.1rem 2.5rem; }
			.nav button { width: 36px; height: 36px; }
		}
		@media (max-width: 520px) {
			:root {
				--card-w: min(85vw, 220px);
				--card-h: calc(var(--card-w) * 9 / 16);
				--gap: .65rem;
			}
			.wrapper { padding: 0 .75rem; }
			.carousel { min-height: calc(var(--card-h) + 110px + 2rem); }
			.track { padding: .9rem 2rem; }
			.nav button { width: 32px; height: 32px; }
		}
	</style>
</head>
<body<?php echo $bodyStyle !== '' ? ' style="' . htmlspecialchars($bodyStyle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : ''; ?>>
	<div class="wrapper">
		<h1>Twitch Streams Carousel</h1>
		<div class="carousel">
			<div class="loading-overlay" id="loadingOverlay" role="status" aria-live="polite">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path fill="#9146ff" d="M272 112C272 85.5 293.5 64 320 64C346.5 64 368 85.5 368 112C368 138.5 346.5 160 320 160C293.5 160 272 138.5 272 112zM272 528C272 501.5 293.5 480 320 480C346.5 480 368 501.5 368 528C368 554.5 346.5 576 320 576C293.5 576 272 554.5 272 528zM112 272C138.5 272 160 293.5 160 320C160 346.5 138.5 368 112 368C85.5 368 64 346.5 64 320C64 293.5 85.5 272 112 272zM480 320C480 293.5 501.5 272 528 272C554.5 272 576 293.5 576 320C576 346.5 554.5 368 528 368C501.5 368 480 346.5 480 320zM139 433.1C157.8 414.3 188.1 414.3 206.9 433.1C225.7 451.9 225.7 482.2 206.9 501C188.1 519.8 157.8 519.8 139 501C120.2 482.2 120.2 451.9 139 433.1zM139 139C157.8 120.2 188.1 120.2 206.9 139C225.7 157.8 225.7 188.1 206.9 206.9C188.1 225.7 157.8 225.7 139 206.9C120.2 188.1 120.2 157.8 139 139zM501 433.1C519.8 451.9 519.8 482.2 501 501C482.2 519.8 451.9 519.8 433.1 501C414.3 482.2 414.3 451.9 433.1 433.1C451.9 414.3 482.2 414.3 501 433.1z"/></svg>
				<span class="sr-only">Loading streams...</span>
			</div>
			<div class="nav">
				<button id="prevBtn" class="left" aria-label="Previous">◀</button>
				<button id="nextBtn" class="right" aria-label="Next">▶</button>
			</div>
			<div class="track" id="track"></div>
		</div>
	</div>


<?php
	/**
	 * Wrap the options in a pretty package and put them where our main.js Javascript code can find them.
	 */
?>
	<script>
		window.__TWITCH_CAROUSEL_CONFIG = <?php echo json_encode($oCarouselConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
	</script>
	<script src="./main.js"></script>
</body>
</html>
