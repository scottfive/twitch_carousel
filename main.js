/**
 * Project: Twitch Stream Carousel
 * main.js — vanilla JS to fetch streams from our PHP backend and render a looping, center-focused carousel.
 * Description: Simple front-end example of how you might implement a Twitch Stream Carousel.
 *
 * Just a quick implementation of a responsive carousel that grabs its card data from a backend.
 *
 * In this case, the backend delivers JSON data for twitch streams that match 3 filters:
 * 		category (game_id), stream title (keywords), and/or stream tags (tags).
 *
 * See `api/streams.php` included in this package for more details on the backend.
 *
 * Copyright (c) 2025 ScottFive
 * This source code is licensed under the MIT license found in the LICENSE file in the project root.
 *
 * @license MIT
 * @link https://github.com/scottfive/twitch-carousel
 * @author ScottFive
 * @since 2025-10-02
 */


const $track = document.getElementById('track');
const $loadingOverlay = document.getElementById('loadingOverlay');
const $prevBtn = document.getElementById('prevBtn');
const $nextBtn = document.getElementById('nextBtn');

const oPageConfig = window.__TWITCH_CAROUSEL_CONFIG || {};
const oConfig = normalizeConfig(oPageConfig);

let streamsData = [];
let currentIndex = 0; // index of the centered/highlighted card
let pointerActiveId = null;
let pointerStartX = 0;
let pointerStartScroll = 0;
let pointerIsDragging = false;
let pointerLastX = 0;
let pointerDidDrag = false;

function normalizeConfig(oRaw) {
	const params = new URLSearchParams(window.location.search);
	const normalized = {
		gameId: '',
		keywords: '',
		tags: ''
	};

	if (oRaw && typeof oRaw.gameId === 'string' && oRaw.gameId.trim()) {
		normalized.gameId = oRaw.gameId.trim();
	} else if (params.has('game_id')) {
		normalized.gameId = params.get('game_id').trim();
	}

	if (oRaw && typeof oRaw.keywords === 'string' && oRaw.keywords.trim()) {
		normalized.keywords = oRaw.keywords.trim();
	} else if (params.has('keywords')) {
		normalized.keywords = params.get('keywords').trim();
	}

	if (oRaw && typeof oRaw.tags === 'string' && oRaw.tags.trim()) {
		normalized.tags = oRaw.tags.trim();
	} else if (params.has('tags')) {
		normalized.tags = params.get('tags').trim();
	}

	return normalized;
}


/**
 * loadStreams
 *
 * Contact our custom backend API to gather stream data.
 */
async function loadStreams() {
	if (!$track) {
		return;
	}

	if (!oConfig.gameId) {
		renderStatusMessage('Please provide a game_id via the page query string.');
		return;
	}

	const params = new URLSearchParams();
	params.set('game_id', oConfig.gameId);
	if (oConfig.keywords) params.set('keywords', oConfig.keywords);
	if (oConfig.tags) params.set('tags', oConfig.tags);
	params.set('limit', 20);

	showLoading();
	try {
		const res = await fetch(`api/streams.php?${params.toString()}`, {			// If you move the backend portion, you must change this URL to match
			headers: { 'Accept': 'application/json' }
		});
		const json = await res.json();
		if (json.error) throw new Error(`${json.error} (${json.http_code || ''})`);
		streamsData = json.items || [];
		currentIndex = 0;
		if (streamsData.length === 0) {
			renderStatusMessage('No live streams matched your filters.');
			return;
		}
		renderCarousel(streamsData);
		focusIndex(currentIndex, false);
	} catch (err) {
		console.error(err);
		renderStatusMessage('Error loading streams. Check console for details.');
	} finally {
		hideLoading();
	}
}

function renderStatusMessage(message) {
	$track.innerHTML = '';
	const $message = document.createElement('div');
	$message.style.padding = '1rem';
	$message.style.fontSize = '.95rem';
	$message.textContent = message;
	$track.appendChild($message);
}


/**
 * renderCarousel
 *
 * The workhorse here. Builds the carousel from the twitch stream data we got from the backend.
 */
function renderCarousel(streams) {
	$track.innerHTML = '';
	streams.forEach((o, i) => {
		const $card = document.createElement('article');
		$card.className = 'card';
		$card.dataset.index = String(i);

		const loginSource = (o.user_login || o.user_name || '').trim();
		if (loginSource) {
			const normalizedLogin = o.user_login ? loginSource : loginSource.toLowerCase().replace(/\s+/g, '');
			$card.dataset.streamUrl = `https://www.twitch.tv/${encodeURIComponent(normalizedLogin)}`;
		}

		const $img = document.createElement('img');
		$img.className = 'thumb';
		$img.alt = `${o.user_name} thumbnail`;

		const { src, srcset } = buildThumbnailSources(o.thumbnail_url);
		$img.src = src;
		if (srcset) {
			$img.srcset = srcset;
			$img.sizes = '(min-width: 900px) 320px, (min-width: 720px) 280px, (min-width: 520px) 240px, 85vw';
		}
		$img.loading = 'lazy';
		$card.appendChild($img);

		const $meta = document.createElement('div');
		$meta.className = 'meta';

		const $name = document.createElement('div');
		$name.className = 'name';
		$name.textContent = o.user_name || o.user_login || 'Unknown';
		$meta.appendChild($name);

		const $title = document.createElement('div');
		$title.className = 'title';
		$title.textContent = o.title || '';
		$meta.appendChild($title);

		const $tagsWrap = document.createElement('div');
		$tagsWrap.className = 'tags';
		(o.tags || []).slice(0, 6).forEach(tag => {
			const $tag = document.createElement('span');
			$tag.className = 'tag';
			$tag.textContent = tag;
			$tagsWrap.appendChild($tag);
		});
		$meta.appendChild($tagsWrap);

		$card.appendChild($meta);
		$card.addEventListener('click', () => {
			if (pointerDidDrag) {
				pointerDidDrag = false;
				return;
			}
			currentIndex = Number($card.dataset.index);
			focusIndex(currentIndex, true);
			const streamUrl = $card.dataset.streamUrl;
			if (streamUrl) {
				const newWindow = window.open(streamUrl, '_blank', 'noopener');
				if (newWindow) newWindow.opener = null;
			}
		});

		$track.appendChild($card);
	});
}


/**
 * focusIndex
 *
 * Keep the selected index highlighted & (preferrably) centered by adjusting classes.
 */
function focusIndex(index, smooth = true) {
	const cards = Array.from($track.children);
	if (cards.length === 0) return;

	// Wrap index for infinite loop effect
	if(index<0){index=0;}
	if(index>=cards.length){index=cards.length-1;}
	currentIndex=index;

	// set classes to embiggen the two neighbors of the highlighted card
	cards.forEach((el, i) => {
		el.classList.remove('is-center', 'is-near');
		if (i === currentIndex) {
			el.classList.add('is-center');
		} else if (i === currentIndex-1 || i===currentIndex+1) {
			el.classList.add('is-near');
		}
	});

	// Center the current card using layout metrics (ignores transform scale)
	const $center = cards[currentIndex];
	const cardLeft = $center.offsetLeft; // not affected by CSS transforms
	const cardWidth = $center.offsetWidth; // layout width
	const trackWidth = $track.clientWidth;
	const targetLeft = Math.max(0, cardLeft - (trackWidth - cardWidth) / 2);
	const behavior = smooth ? 'smooth' : 'auto';
	if (typeof $track.scrollTo === 'function') {
		$track.scrollTo({ left: targetLeft, behavior });
	} else {
		$track.scrollLeft = targetLeft;
	}
}

// Simple scroll navigation
$prevBtn.addEventListener('click', () => {
	focusIndex(currentIndex - 1, true);
});
$nextBtn.addEventListener('click', () => {
	focusIndex(currentIndex + 1, true);
});

// Optional: arrow-key navigation
window.addEventListener('keydown', (e) => {
	if (e.key === 'ArrowLeft') focusIndex(currentIndex - 1, true);
	if (e.key === 'ArrowRight') focusIndex(currentIndex + 1, true);
});

// Re-center on resize so the focused card stays centered
window.addEventListener('resize', () => focusIndex(currentIndex, false));



/**
 * Loading Spinner
 */
function showLoading() {
	if ($loadingOverlay) {
		$loadingOverlay.classList.add('is-visible');
	}
	$track.setAttribute('aria-busy', 'true');
}

function hideLoading() {
	if ($loadingOverlay) {
		$loadingOverlay.classList.remove('is-visible');
	}
	$track.removeAttribute('aria-busy');
}



/**
 * THUMBNAIL SIZING
 *
 * Twitch serves thumbnails at various sizes. But, the URL we get back from the API has only a placeholder. So, we have
 * to replace that with the size we want.
 *
 * Since we want this to be responsive, and to look great on high resolution screens, we make multiple sizes available
 * to the browser, and let the browser decide what to load with a srcset.
 *
 * These are live streams, though, so we don't want stale images.
 * So, we also add in a "cache buster" so make sure the browser loads a fresh image.
 */
function buildThumbnailSources(template) {
	const cacheBust = Date.now();
	const base = typeof template === 'string' ? template : '';
	const hasPlaceholder = base.includes('{width}x{height}');
	const sizes = [
		{ width: 320, height: 180 },
		{ width: 480, height: 270 },
		{ width: 640, height: 360 },
		{ width: 800, height: 450 },
		{ width: 1280, height: 720 },
		{ width: 1920, height: 1080 }
	];
	const srcsetParts = [];
	let fallback = base;
	if (hasPlaceholder) {
		sizes.forEach(({ width, height }) => {
			const sizedUrl = base.replace('{width}x{height}', `${width}x${height}`);
			const withTs = `${sizedUrl}?t=${cacheBust}`;
			srcsetParts.push(`${withTs} ${width}w`);
			if (width === 640) {
				fallback = withTs;
			}
		});
		if (!fallback || fallback === base) {
			const sizedUrl = base.replace('{width}x{height}', '800x450');
			fallback = `${sizedUrl}?t=${cacheBust}`;
		}
	} else if (base) {
		fallback = `${base}?t=${cacheBust}`;
	}
	return {
		src: fallback,
		srcset: srcsetParts.join(', ')
	};
}



/**
 * mobile swipe motions
 *
 * In order for this to work reliably, we must turn off browser swipe handling for our carousel container. So, the CSS
 * for `#track` and `.card` must include `touch-action: none;` or this will be wonky at best.
 */
if ($track) {
	$track.addEventListener('pointerdown', handlePointerDown);
	$track.addEventListener('pointermove', handlePointerMove);
	$track.addEventListener('pointerup', handlePointerUp);
	$track.addEventListener('pointercancel', handlePointerUp);
}

function handlePointerDown(e) {
	if (e.pointerType === 'mouse' && e.button !== 0) {
		return;
	}
	pointerActiveId = e.pointerId;
	pointerStartX = e.clientX;
	pointerStartScroll = $track.scrollLeft;
	pointerIsDragging = false;
	pointerLastX = e.clientX;
	pointerDidDrag = false;
	if ($track.setPointerCapture) {
		$track.setPointerCapture(e.pointerId);
	}
}

function handlePointerMove(e) {
	if (pointerActiveId === null || e.pointerId !== pointerActiveId) {
		return;
	}
	pointerLastX = e.clientX;
	const delta = e.clientX - pointerStartX;
	if (!pointerIsDragging && Math.abs(delta) > 10) {
		pointerIsDragging = true;
	}
	if (pointerIsDragging) {
		e.preventDefault();
		pointerDidDrag = true;
		$track.scrollLeft = pointerStartScroll - delta;
	}
}

function handlePointerUp(e) {
	if (pointerActiveId === null || e.pointerId !== pointerActiveId) {
		return;
	}
	if ($track.releasePointerCapture) {
		try {
			$track.releasePointerCapture(e.pointerId);
		} catch (err) {
			// ignore release errors
			alert('release');
		}
	}
	const delta = pointerLastX - pointerStartX;
	const threshold = 45;
	if (pointerIsDragging) {
		if (delta <= -threshold) {
			focusIndex(currentIndex + 1, true);
		} else if (delta >= threshold) {
			focusIndex(currentIndex - 1, true);
		} else {
			focusIndex(currentIndex, true);
		}
	} else {
		focusIndex(currentIndex, true);
	}
	pointerActiveId = null;
	pointerIsDragging = false;
}



/**
 * Load the streams, LFG!
 */
loadStreams();
