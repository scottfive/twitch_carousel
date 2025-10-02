<?php
/**
 * Twitch API + Redis config.
 * NOTE: Never commit real secrets. Consider environment variables in production.
 */

// Your Twitch app Client ID
const TWITCH_CLIENT_ID = 'YOUR_CLIENT_ID_HERE';

// Your Twitch app Client Secret (only needed if you implement token generation, which we don't have yet)
const TWITCH_CLIENT_SECRET = 'YOUR_CLIENT_SECRET_HERE';

// App Access Token (you’ll add it)
const TWITCH_APP_ACCESS_TOKEN = 'YOUR_APP_ACCESS_TOKEN_HERE';

// Optional: default page size when querying Twitch (max 100)
const TWITCH_DEFAULT_FIRST = 50;

// Optional: allow CORS for local development; tune this for production
const ENABLE_CORS_ALL_ORIGINS = true;

// Redis cache — if enabled, will cache results to redis for 5 mins. Otherwise, refreshes on every page access (which may rate limit your twitch app.)
const REDIS_ENABLED = 'false';		// ('true'|'false')
const REDIS_HOST = '127.0.0.1';
const REDIS_PORT = 6379;
const REDIS_PASSWORD = 'YOUR_REDIS_PASSWORD_HERE'; // '' if none
const REDIS_DB = 0; // change if desired
const REDIS_PREFIX = 'twitch_carousel:'; // namespace for keys

// Cache policy: do not refresh more than once every 5 minutes
const CACHE_TTL_SECONDS = 300;

// Optional defaults for the public carousel when query params are missing
const TWITCH_CAROUSEL_DEFAULT_GAME_ID = '1469308723';		// Software and Game Development
const TWITCH_CAROUSEL_DEFAULT_TITLE_KEYWORDS = '';
const TWITCH_CAROUSEL_DEFAULT_TAG_KEYWORDS = '';



/**
 * Logging control
 * If you want (limited) logging to syslog. Mostly for debugging redis connection/usage.
 *
 * Can get chatty in your log files.
 */
const LOG_ENABLED = 'false';		// (true|false)
