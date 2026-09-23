<?php
/**
 * Plugin Name: WSH AI News Editor
 * Plugin URI: https://websolutions.online/
 * Description: AI newsroom assistant for WordPress news sites.
 * Version: 1.0.0
 * Author: WSH
 * Author URI: https://websolutions.online/
 * Text Domain: wsh-ai-news-editor
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSH_AINE_VERSION', '1.0.0' );
define( 'WSH_AINE_FILE', __FILE__ );
define( 'WSH_AINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSH_AINE_URL', plugin_dir_url( __FILE__ ) );
define( 'WSH_AINE_BASENAME', plugin_basename( __FILE__ ) );
define( 'WSH_AINE_PLUGIN_SLUG', 'wsh-ai-news-editor' );

if ( ! defined( 'WSH_LICENSE_API_BASE' ) ) {
	define( 'WSH_LICENSE_API_BASE', 'https://wppluginspro.io/wp-json/wsh-license/v1' );
}

if ( ! defined( 'WSH_AINE_LICENSE_API_SECRET' ) ) {
	define( 'WSH_AINE_LICENSE_API_SECRET', 'wsh-ai-news-editor' );
}

require_once WSH_AINE_PATH . 'includes/helpers.php';
require_once WSH_AINE_PATH . 'includes/class-install.php';
require_once WSH_AINE_PATH . 'includes/class-plugin.php';
require_once WSH_AINE_PATH . 'includes/class-admin.php';
require_once WSH_AINE_PATH . 'includes/class-usage.php';
require_once WSH_AINE_PATH . 'includes/class-admin-notices.php';
require_once WSH_AINE_PATH . 'includes/class-access.php';
require_once WSH_AINE_PATH . 'includes/class-module-registry.php';
require_once WSH_AINE_PATH . 'includes/class-menu.php';
require_once WSH_AINE_PATH . 'includes/class-ai-drafts.php';
require_once WSH_AINE_PATH . 'includes/class-posts.php';
require_once WSH_AINE_PATH . 'includes/class-media.php';
require_once WSH_AINE_PATH . 'includes/class-comments.php';
require_once WSH_AINE_PATH . 'includes/class-ai-post-metabox.php';


require_once WSH_AINE_PATH . 'includes/license/class-license.php';
require_once WSH_AINE_PATH . 'includes/license/class-license-page.php';

require_once WSH_AINE_PATH . 'includes/shortcodes/class-standings-shortcode.php';
require_once WSH_AINE_PATH . 'includes/shortcodes/class-round-fixtures-shortcode.php';
require_once WSH_AINE_PATH . 'includes/shortcodes/class-live-score-shortcode.php';

require_once WSH_AINE_PATH . 'includes/integrations/class-youtube-fetcher.php';

require_once WSH_AINE_PATH . 'includes/modules/class-settings-page.php';
require_once WSH_AINE_PATH . 'includes/modules/class-local-media-page.php';
require_once WSH_AINE_PATH . 'includes/modules/class-google-news-page.php';
require_once WSH_AINE_PATH . 'includes/modules/class-ai-editor-page.php';
require_once WSH_AINE_PATH . 'includes/modules/class-youtube-news-page.php';
require_once WSH_AINE_PATH . 'includes/modules/class-twitter-news-page.php';

require_once WSH_AINE_PATH . 'includes/integrations/class-google-news-url-resolver.php';
require_once WSH_AINE_PATH . 'includes/integrations/class-remote-image-finder.php';
require_once WSH_AINE_PATH . 'includes/integrations/class-rss-fetcher.php';
require_once WSH_AINE_PATH . 'includes/integrations/class-ai-payload.php';
require_once WSH_AINE_PATH . 'includes/integrations/class-ai-provider-openai.php';
require_once WSH_AINE_PATH . 'includes/integrations/class-twitter-fetcher.php';

require_once WSH_AINE_PATH . 'includes/integrations/class-grok-fetcher.php';
require_once WSH_AINE_PATH . 'includes/modules/class-grok-news-page.php';

require_once WSH_AINE_PATH . 'includes/integrations/class-perplexity-fetcher.php';
require_once WSH_AINE_PATH . 'includes/modules/class-perplexity-news-page.php';

require_once WSH_AINE_PATH . 'includes/integrations/class-api-sports-fetcher.php';
require_once WSH_AINE_PATH . 'includes/modules/class-sports-news-page.php';

require_once WSH_AINE_PATH . 'includes/integrations/class-brightdata-client.php';
require_once WSH_AINE_PATH . 'includes/integrations/class-instagram-fetcher.php';
require_once WSH_AINE_PATH . 'includes/modules/class-insta-news-page.php';

require_once WSH_AINE_PATH . 'includes/helpers/class-social-url-helper.php';
require_once WSH_AINE_PATH . 'includes/integrations/class-social-post-fetcher.php';
require_once WSH_AINE_PATH . 'includes/modules/class-social-network-news-page.php';

register_activation_hook( __FILE__, array( 'WSH_AINE_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WSH_AINE_Install', 'deactivate' ) );

function wsh_aine_boot() {
	return WSH_AINE_Plugin::instance();
}

function wsh_aine_enqueue_twitter_widgets() {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();

	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}

	$content = (string) $post->post_content;

	if ( false === strpos( $content, 'twitter-tweet' ) ) {
		return;
	}

	wp_enqueue_script(
		'wsh-aine-twitter-widgets',
		'https://platform.twitter.com/widgets.js',
		array(),
		null,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'wsh_aine_enqueue_twitter_widgets' );

wsh_aine_boot();
