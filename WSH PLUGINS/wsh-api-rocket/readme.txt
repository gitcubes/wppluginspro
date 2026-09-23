=== WSH API Rocket ===
Contributors: wsh
Tags: api, rest, mobile, app, wordpress, headless
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, mobile-friendly REST endpoints for WordPress content (menu, categories, posts, search) + configurable Home endpoint. Includes API keys, rate limiting, and proxy-safe IP detection.

== Description ==

WSH API Rocket provides a lightweight content API for mobile apps:

FREE:
- Home endpoint (simple builder): Header manual posts + Main category latest + Latest + More news
- Menu endpoint with normalization (detects category/post targets)
- Categories, posts list, single post, search
- API key protection (recommended) or public mode
- Rate limiting per API key + IP (429 + headers)
- Proxy-safe client IP detection (Cloudflare/X-Forwarded-For)
- Home caching with invalidation

PRO (locked in FREE):
- Advanced Home Builder (drag&drop, tags, CPT sections, most popular)
- Push notifications (Firebase/FCM) + admin panel + logs
- App settings endpoint (remote config)
- WooCommerce endpoints (products, orders, webhooks)

== Installation ==

1. Upload plugin folder to /wp-content/plugins/ OR install via WordPress admin.
2. Activate "WSH API Rocket".
3. Go to WP Admin → WSH API Rocket → API tab:
   - Add at least one API key (recommended)
   - Set Access mode to "Require API key"
   - Configure Rate Limit and Proxy settings (if behind Cloudflare)
4. Go to WP Admin → WSH API Rocket → Home tab:
   - Configure sections and click "Preview JSON"
5. Test endpoints using Postman or curl.

== Configuration ==

=== API Keys ===
Send header:
X-WSH-API-KEY: <your-key>

=== Rate limiting ===
Headers returned:
X-RateLimit-Limit
X-RateLimit-Remaining
X-RateLimit-Reset
Retry-After (on 429)

== REST API Endpoints ==

Base namespace: /wp-json/wsh/v1

GET /home
- Returns home sections JSON (cached)

GET /menu?location=primary OR /menu?menu_id=123
- Returns menu tree with "target" mapping (category/post/external)

GET /categories?per_page=100

GET /posts?page=1&per_page=20&category_id=3&tag=politika&search=abc

GET /posts/{id}

GET /search?q=abc&page=1&per_page=20

== FAQ ==

= Should I enable public mode? =
Not recommended for production mobile apps. Use API keys + rate limiting.

= I am behind Cloudflare, rate limiting groups all users =
Enable "Trust proxy headers" and set mode to "Cloudflare".

== Changelog ==

= 1.0.0 =
- Initial release: home builder + content endpoints + api keys + rate limit + proxy IP + caching

== Upgrade Notice ==

= 1.0.0 =
Initial release.
