=== WSH Views Counter ===
Contributors: wsh-plugins
Tags: post views, views counter, analytics, pageviews, popular posts, hit counter, traffic stats
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

WSH Views Counter is a lightweight, fast, and modern WordPress views counter plugin.  
It tracks post, page, product, and custom post type views with per-day statistics stored in an optimized custom database table. A powerful reporting dashboard lets you analyze traffic by post, author, date, device type, or logged-in status.

Designed for high-traffic websites — news portals, magazines, blogs, and WooCommerce stores — the plugin is built with performance in mind: no slow queries, no heavy scripts, no bloat.

== Features ==

### 📊 Free Features
* Track views for **any post type** (post, page, product, CPTs)
* Daily view tracking stored in a custom table
* Total views saved as post meta
* Device analytics (mobile / desktop)
* Logged-in user tracking
* Admin dashboard reports:
  - Views by Post  
  - Views by Author  
  - Views by Date  
* Filter by time range (1, 7, 15, 30, 90, 365 days)
* Filter by post type
* Search and sortable columns
* Manual editing of view records
* Shortcode to display view count anywhere
* Optional view count display on the Post Edit screen
* Exclude admin visits
* Exclude specific IP addresses
* Adjustable view count interval (30m, 1h, 3h, 6h, 12h, 1d)
* Optimized SQL queries for large databases
* One-click import from “Post Views Counter”
* One-click "Delete All Data" reset

### 🔒 PRO Version (Coming Soon)
The upcoming PRO version will include:
* Interactive charts (daily / monthly analytics)
* Geo-location statistics
* Advanced AJAX tables with live filtering
* CSV / Excel export
* REST API endpoints
* Gutenberg blocks for front-end display
* Popular posts widget with design presets
* Background processing for large datasets
* Priority support & updates

A non-intrusive upgrade notice will appear once PRO is released.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wsh-views-counter/`, or install via Plugins → Add New.
2. Activate the plugin through the **Plugins** menu.
3. Navigate to **Views Counter → Reports** to view analytics.
4. Visit **Views Counter → Settings** to configure:
   * Post types to track
   * Excluded IP addresses
   * Exclude admin views
   * View interval settings

== Frequently Asked Questions ==

= Does this plugin slow down my website? =
No. All tracking is optimized and stored in a dedicated table with indexed fields.

= Does it track views for custom post types? =
Yes — any public post type can be enabled in Settings.

= Can I import data from Post Views Counter? =
Yes — a built-in one-click importer is included.

= Do I need the PRO version? =
No. The free version is fully functional.  
PRO adds advanced analytics and tools.

= Can I reset all data? =
Yes — there is a one-click “Delete All Data” tool in Settings.

= Does it use cookies? =
No. Tracking uses `localStorage`, not cookies.

== Screenshots ==

1. Reports overview screen  
2. Settings panel – general options  
3. Settings panel – post type tracking  
4. Posts list column with view counts

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First stable release of WSH Views Counter. Includes full tracking system, reporting tools, importer, and settings panel.
