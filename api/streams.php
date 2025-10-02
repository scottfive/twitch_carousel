<?php
/**
 * streams.php
 *
 * Lightweight backend endpoint that queries Twitch Helix Get Streams, then filters by:
 * - game_id (required)
 * - keywords in the stream title (comma-separated, case-insensitive)
 * - tag names (comma-separated, case-insensitive)
 *
 * Caching: If Redis is configured, results are cached in Redis and refreshed no more than once every 5 minutes.
 *
 * Returns a JSON list with the fields you requested:
 * - streamer name (user_name)
 * - stream title (title)
 * - stream thumbnail (thumbnail_url template from Twitch)
 * - stream tag list (tags)
 *
 * Example:
 * /api/streams.php?game_id=494131&keywords=nightmares,hablamos&tags=español&limit=24
 */

require_once __DIR__ . '/../config.php';


/**
 * Just a quick stub for logging errors and things.
 */
function write_syslog( string $message ){
	// only if logs are explicitly enabled
	if( LOG_ENABLED!=='true'){return;}

	$http_host = '**NO HOST**';
	if( !empty( $_SERVER['HTTP_HOST'] ) ){ $http_host = $_SERVER['HTTP_HOST']; }
	$ua = '**NO UA**';
	if( !empty( $_SERVER['HTTP_USER_AGENT'] ) ){ $ua = $_SERVER['HTTP_USER_AGENT']; }
	openlog('twitch_carousel('.$http_host.')', LOG_NDELAY|LOG_PID, LOG_USER);

	syslog( LOG_NOTICE, "{$_SERVER['REMOTE_ADDR']} - $message - ({$ua})" );
}



if (ENABLE_CORS_ALL_ORIGINS) {
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type');
}

header('Content-Type: application/json; charset=utf-8');



// --- Helpers ---------------------------------------------------------------

function is_redis_enabled(){
	return( defined('REDIS_ENABLED') && REDIS_ENABLED==='true' );
}


/**
 * Perform a simple HTTP GET with cURL.
 * @return array{code:int,body:string|false,error:string}
 */
function httpGet(string $url, array $headers = []): array {
	$oCurl = curl_init();
	curl_setopt_array($oCurl, [
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_TIMEOUT => 15,
	]);
	$responseBody = curl_exec($oCurl);
	$httpCode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
	$error = curl_error($oCurl);
	curl_close($oCurl);
	return [
		'code' => $httpCode,
		'body' => $responseBody,
		'error' => $error,
	];
}

/**
 * Build a Twitch Helix URL with query params.
 */
function buildUrl(string $base, array $params): string {
	$query = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
	return $base . (strpos($base, '?') === false ? '?' : '&') . $query;
}

/**
 * Parse a comma or space-delimited list into an array of lowercased terms.
 */
function parseTerms(?string $raw): array {
	if (!$raw) return [];
	$normalized = str_replace(["\n", "\t", ';'], ',', $raw);
	$parts = preg_split('/[\s,]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
	return array_values(array_unique(array_map('mb_strtolower', $parts)));
}

/**
 * Case-insensitive check if any term exists in a haystack string.
 */
function anyTermInString(array $terms, string $haystack): bool {
	$needle = mb_strtolower($haystack);
	foreach ($terms as $term) {
		if ($term !== '' && mb_stripos($needle, $term) !== false) return true;
	}
	return empty($terms); // if no terms, treat as pass-through
}

/**
 * Case-insensitive intersection between desired terms and an array of tag names.
 */
function anyTermInTags(array $terms, array $tagList): bool {
	if (empty($terms)) return true; // pass-through if no tag filter
	$lowerTags = array_map('mb_strtolower', $tagList);
	foreach ($terms as $term) {
		if (in_array($term, $lowerTags, true)) return true;
	}
	return false;
}

/**
 * Get a Redis connection using pecl/redis extension.
 * Returns Redis instance or null if connection/auth fails.
 */
function getRedis(): ?Redis {
	if( !is_redis_enabled() ){ return false;}

	try {
		$oRedis = new Redis();
		$oRedis->connect(REDIS_HOST, REDIS_PORT, 1.5);
		if (REDIS_PASSWORD !== '') {
			$oRedis->auth(REDIS_PASSWORD);
		}
		if (REDIS_DB !== null) {
			$oRedis->select((int)REDIS_DB);
		}
		return $oRedis;
	} catch (Throwable $e) {
		write_syslog('redis error: '.$e->getMessage());

		return null; // proceed without cache
	}
}

/**
 * Build a stable cache key for a given query.
 */
function buildCacheKey(string $gameId, array $keywords, array $tags, int $limit): string {
	$payload = json_encode([
		'g' => $gameId,
		'kw' => array_values($keywords),
		'tags' => array_values($tags),
		'limit' => $limit,
	]);
	$hash = substr(sha1($payload), 0, 20);
	return REDIS_PREFIX . 'streams:' . $hash;
}



// --- Inputs ---------------------------------------------------------------

$gameId = isset($_GET['game_id']) ? trim($_GET['game_id']) : '';
$keywords = parseTerms($_GET['keywords'] ?? '');
$tagTerms = parseTerms($_GET['tags'] ?? '');
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
$first = min($limit, TWITCH_DEFAULT_FIRST); // per-page size for Twitch

if ($gameId === '') {
	http_response_code(400);
	echo json_encode(['error' => 'Missing required parameter: game_id']);
	exit;
}



// --- Redis cache look-up --------------------------------------------------

$oRedis = getRedis();
$cacheKey = buildCacheKey($gameId, $keywords, $tagTerms, $limit);
if ($oRedis instanceof Redis) {
	$cached = $oRedis->get($cacheKey);
	if (is_string($cached) && $cached !== '') {
		write_syslog('served from redis');
		echo $cached; // Serve cached JSON directly
		exit;
	}
}
write_syslog('redis not found, building from scratch');



// --- Query Twitch ---------------------------------------------------------

$baseUrl = 'https://api.twitch.tv/helix/streams';
$params = [
	'game_id' => $gameId,		// required so we don't try to query all 100k+ live streams lol
	'first' => $first,			// cursor position for paged results
];

$headers = [
	'Authorization: Bearer ' . TWITCH_APP_ACCESS_TOKEN,
	'Client-Id: ' . TWITCH_CLIENT_ID,
];

$collected = [];	// array of "collected" streams that match the filters
$cursor = null;		// twitch cursor to advance through pages of streams returned


// loop through the pages of streams until we run out of pages, or reach our limit
while (count($collected) < $limit) {
	$url = buildUrl($baseUrl, array_merge($params, $cursor ? ['after' => $cursor] : []));
	$result = httpGet($url, $headers);
	if ($result['code'] !== 200 || !$result['body']) {
		$response = json_encode([
			'error' => 'Twitch API error',
			'http_code' => $result['code'],
			'detail' => $result['error'] ?: $result['body'],
		]);
		echo $response;
		// Don't cache errors
		exit;
	}
	$oPayload = json_decode($result['body'], true);
	if (!isset($oPayload['data'])) break;

	foreach ($oPayload['data'] as $oStream) {
		// Guard: some responses may use different casing; normalize expected fields.
		$streamTitle = (string)($oStream['title'] ?? '');
		$streamUserName = (string)($oStream['user_name'] ?? $oStream['user_login'] ?? '');
		$streamTags = (array)($oStream['tags'] ?? []); // Twitch returns array of tag names in many locales

		// Apply filters
		$matchesKeywords = anyTermInString($keywords, $streamTitle);
		$matchesTags = anyTermInTags($tagTerms, $streamTags);

		if ($matchesKeywords && $matchesTags) {
			$thumbnailTemplate = (string)($oStream['thumbnail_url'] ?? '');

			// use the user_login as a key so we can weed out duplicate entries returned from twitch
			if( !array_key_exists($oStream['user_login'], $collected ) ){
				$collected[$oStream['user_login']] = [
					'user_name' => $streamUserName,
					'user_login' => (string)($oStream['user_login'] ?? ''),
					'title' => $streamTitle,
					'thumbnail_url' => $thumbnailTemplate, // e.g. ...-{width}x{height}.jpg
					'tags' => $streamTags,
				];
			}
			if (count($collected) >= $limit) break 2;
		}
	}

	$cursor = $oPayload['pagination']['cursor'] ?? null;
	if (!$cursor) break; // no more pages
}


// package up the list in JSON
$response = json_encode([
	'count' => count($collected),
	'items' => array_values($collected),		// redo the indeces so they're numerical for the client
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo $response;



// --- Cache store (TTL = 5 minutes) ---------------------------------------
if ($oRedis instanceof Redis) {
	try {
		$oRedis->setEx($cacheKey, CACHE_TTL_SECONDS, $response);
	} catch (Throwable $e) {
		// ignore cache store errors
		write_syslog('redis error' . $e.message);
	}

}
