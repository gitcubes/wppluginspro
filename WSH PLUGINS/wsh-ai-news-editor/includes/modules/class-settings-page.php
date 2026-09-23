<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Settings_Page {

	public static function init() : void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() : void {
		register_setting(
			'wsh_aine_settings_group',
			'wsh_aine_settings',
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	public static function sanitize_settings( array $input ) : array {
		$output = array();

		$output['country']             = isset( $input['country'] ) ? sanitize_text_field( $input['country'] ) : 'RS';
		$output['language']            = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : 'sr';
		$output['default_post_status'] = isset( $input['default_post_status'] ) ? sanitize_text_field( $input['default_post_status'] ) : 'draft';
		$output['default_author']      = isset( $input['default_author'] ) ? absint( $input['default_author'] ) : get_current_user_id();

		$output['openai_api_key']     = isset( $input['openai_api_key'] ) ? sanitize_text_field( $input['openai_api_key'] ) : '';
		$output['openai_model']       = isset( $input['openai_model'] ) ? sanitize_text_field( $input['openai_model'] ) : 'gpt-4.1-mini';
		$output['openai_temperature'] = isset( $input['openai_temperature'] ) ? sanitize_text_field( $input['openai_temperature'] ) : '0.7';

		$output['local_sources']  = self::sanitize_source_rows( $input, 'local_sources', 'url' );
		$output['google_sources'] = self::sanitize_source_rows( $input, 'google_sources', 'url' );

		$output['youtube_api_key']      = isset( $input['youtube_api_key'] ) ? sanitize_text_field( $input['youtube_api_key'] ) : '';
		$output['youtube_region']       = isset( $input['youtube_region'] ) ? sanitize_text_field( $input['youtube_region'] ) : 'RS';
		$output['youtube_max_videos']   = isset( $input['youtube_max_videos'] ) ? max( 1, min( 25, absint( $input['youtube_max_videos'] ) ) ) : 10;
		$output['youtube_max_comments'] = isset( $input['youtube_max_comments'] ) ? max( 0, min( 20, absint( $input['youtube_max_comments'] ) ) ) : 5;

		$output['youtube_categories'] = array();
		$categories_present = ! empty( $input['youtube_categories_present'] );

		if ( $categories_present && isset( $input['youtube_categories'] ) && is_array( $input['youtube_categories'] ) ) {
			$allowed_categories = array_map( 'strval', array_keys( self::get_youtube_categories() ) );
			$selected           = array_map( 'strval', $input['youtube_categories'] );

			foreach ( $selected as $cat_id ) {
				if ( in_array( $cat_id, $allowed_categories, true ) ) {
					$output['youtube_categories'][] = $cat_id;
				}
			}

			$output['youtube_categories'] = array_values( array_unique( $output['youtube_categories'] ) );
		}

		$output['twitter_bearer_token']     = isset( $input['twitter_bearer_token'] ) ? strval( $input['twitter_bearer_token'] ) : '';
		$output['twitter_region']           = isset( $input['twitter_region'] ) ? sanitize_text_field( $input['twitter_region'] ) : 'RS';
		$output['twitter_language']         = isset( $input['twitter_language'] ) ? sanitize_text_field( $input['twitter_language'] ) : 'sr';
		$output['twitter_max_results']      = isset( $input['twitter_max_results'] ) ? max( 1, min( 25, absint( $input['twitter_max_results'] ) ) ) : 10;
		$output['twitter_exclude_replies']  = ! empty( $input['twitter_exclude_replies'] ) ? 1 : 0;
		$output['twitter_exclude_retweets'] = ! empty( $input['twitter_exclude_retweets'] ) ? 1 : 0;

		$output['twitter_accounts'] = self::sanitize_source_rows( $input, 'twitter_accounts', 'twitter_account' );
		$output['twitter_topics']   = self::sanitize_source_rows( $input, 'twitter_topics', 'twitter_topic' );

		$output['grok_api_key'] = isset( $input['grok_api_key'] )
			? trim( wp_unslash( (string) $input['grok_api_key'] ) )
			: '';

		$output['grok_model'] = isset( $input['grok_model'] )
			? sanitize_text_field( $input['grok_model'] )
			: 'grok-4.20-beta-latest-non-reasoning';

		$output['grok_use_x_search']   = ! empty( $input['grok_use_x_search'] ) ? 1 : 0;
		$output['grok_use_web_search'] = ! empty( $input['grok_use_web_search'] ) ? 1 : 0;
		$output['grok_max_items']      = isset( $input['grok_max_items'] ) ? max( 1, min( 20, absint( $input['grok_max_items'] ) ) ) : 10;

		$output['perplexity_api_key'] = isset( $input['perplexity_api_key'] )
			? trim( wp_unslash( (string) $input['perplexity_api_key'] ) )
			: '';

		$output['perplexity_model'] = isset( $input['perplexity_model'] )
			? sanitize_text_field( $input['perplexity_model'] )
			: 'sonar';

		$output['perplexity_max_items'] = isset( $input['perplexity_max_items'] )
			? max( 1, min( 20, absint( $input['perplexity_max_items'] ) ) )
			: 10;

		$output['perplexity_search_context_size'] = isset( $input['perplexity_search_context_size'] )
			? sanitize_text_field( $input['perplexity_search_context_size'] )
			: 'medium';

		$output['api_sports_api_key'] = isset( $input['api_sports_api_key'] )
			? trim( wp_unslash( (string) $input['api_sports_api_key'] ) )
			: '';

		$output['sports_enabled'] = array();
		if ( isset( $input['sports_enabled'] ) && is_array( $input['sports_enabled'] ) ) {
			$allowed_sports = array( 'football', 'basketball', 'tennis' );
			foreach ( array_map( 'sanitize_key', $input['sports_enabled'] ) as $sport_key ) {
				if ( in_array( $sport_key, $allowed_sports, true ) ) {
					$output['sports_enabled'][] = $sport_key;
				}
			}
			$output['sports_enabled'] = array_values( array_unique( $output['sports_enabled'] ) );
		}

		$output['brightdata_api_token']                = isset( $input['brightdata_api_token'] ) ? trim( wp_unslash( (string) $input['brightdata_api_token'] ) ) : '';
		//$output['brightdata_instagram_dataset_id']     = isset( $input['brightdata_instagram_dataset_id'] ) ? sanitize_text_field( $input['brightdata_instagram_dataset_id'] ) : '';
		//$output['brightdata_instagram_post_dataset_id']= isset( $input['brightdata_instagram_post_dataset_id'] ) ? sanitize_text_field( $input['brightdata_instagram_post_dataset_id'] ) : '';

		$output['brightdata_instagram_profile_dataset_id']  = isset( $input['brightdata_instagram_profile_dataset_id'] ) ? sanitize_text_field( $input['brightdata_instagram_profile_dataset_id'] ) : '';
		$output['brightdata_instagram_post_dataset_id']     = isset( $input['brightdata_instagram_post_dataset_id'] ) ? sanitize_text_field( $input['brightdata_instagram_post_dataset_id'] ) : '';
		$output['brightdata_instagram_comments_dataset_id'] = isset( $input['brightdata_instagram_comments_dataset_id'] ) ? sanitize_text_field( $input['brightdata_instagram_comments_dataset_id'] ) : '';

		$output['brightdata_tiktok_profile_dataset_id']  = isset( $input['brightdata_tiktok_profile_dataset_id'] ) ? sanitize_text_field( $input['brightdata_tiktok_profile_dataset_id'] ) : '';
		$output['brightdata_tiktok_post_dataset_id']     = isset( $input['brightdata_tiktok_post_dataset_id'] ) ? sanitize_text_field( $input['brightdata_tiktok_post_dataset_id'] ) : '';
		$output['brightdata_tiktok_comments_dataset_id'] = isset( $input['brightdata_tiktok_comments_dataset_id'] ) ? sanitize_text_field( $input['brightdata_tiktok_comments_dataset_id'] ) : '';

		$output['brightdata_youtube_profile_dataset_id']  = isset( $input['brightdata_youtube_profile_dataset_id'] ) ? sanitize_text_field( $input['brightdata_youtube_profile_dataset_id'] ) : '';
		$output['brightdata_youtube_post_dataset_id']     = isset( $input['brightdata_youtube_post_dataset_id'] ) ? sanitize_text_field( $input['brightdata_youtube_post_dataset_id'] ) : '';
		$output['brightdata_youtube_comments_dataset_id'] = isset( $input['brightdata_youtube_comments_dataset_id'] ) ? sanitize_text_field( $input['brightdata_youtube_comments_dataset_id'] ) : '';

		$output['brightdata_linkedin_profile_dataset_id']  = isset( $input['brightdata_linkedin_profile_dataset_id'] ) ? sanitize_text_field( $input['brightdata_linkedin_profile_dataset_id'] ) : '';
		$output['brightdata_linkedin_post_dataset_id']     = isset( $input['brightdata_linkedin_post_dataset_id'] ) ? sanitize_text_field( $input['brightdata_linkedin_post_dataset_id'] ) : '';
		$output['brightdata_linkedin_comments_dataset_id'] = isset( $input['brightdata_linkedin_comments_dataset_id'] ) ? sanitize_text_field( $input['brightdata_linkedin_comments_dataset_id'] ) : '';

		$output['brightdata_twitter_profile_dataset_id']  = isset( $input['brightdata_twitter_profile_dataset_id'] ) ? sanitize_text_field( $input['brightdata_twitter_profile_dataset_id'] ) : '';
		$output['brightdata_twitter_post_dataset_id']     = isset( $input['brightdata_twitter_post_dataset_id'] ) ? sanitize_text_field( $input['brightdata_twitter_post_dataset_id'] ) : '';
		$output['brightdata_twitter_comments_dataset_id'] = isset( $input['brightdata_twitter_comments_dataset_id'] ) ? sanitize_text_field( $input['brightdata_twitter_comments_dataset_id'] ) : '';

		$output['instagram_max_posts']        = isset( $input['instagram_max_posts'] ) ? max( 1, min( 20, absint( $input['instagram_max_posts'] ) ) ) : 6;
		$output['instagram_include_comments'] = ! empty( $input['instagram_include_comments'] ) ? 1 : 0;
		$output['instagram_include_reels']    = ! empty( $input['instagram_include_reels'] ) ? 1 : 0;
		$output['instagram_accounts']         = self::sanitize_source_rows( $input, 'instagram_accounts', 'instagram_account' );


		//Images
		$output['image_services_enabled'] = array();
		if ( isset( $input['image_services_enabled'] ) && is_array( $input['image_services_enabled'] ) ) {
			$allowed_image_services = array( 'pexels', 'pixabay', 'unsplash', 'ai_generated', 'reuters');

			foreach ( array_map( 'sanitize_key', $input['image_services_enabled'] ) as $service_key ) {
				if ( in_array( $service_key, $allowed_image_services, true ) ) {
					$output['image_services_enabled'][] = $service_key;
				}
			}

			$output['image_services_enabled'] = array_values( array_unique( $output['image_services_enabled'] ) );
		}

		$output['pexels_api_key'] = isset( $input['pexels_api_key'] )
			? trim( wp_unslash( (string) $input['pexels_api_key'] ) )
			: '';

		$output['pixabay_api_key'] = isset( $input['pixabay_api_key'] )
			? trim( wp_unslash( (string) $input['pixabay_api_key'] ) )
			: '';

		$output['unsplash_access_key'] = isset( $input['unsplash_access_key'] )
			? trim( wp_unslash( (string) $input['unsplash_access_key'] ) )
			: '';

		$output['reuters_client_id'] = isset( $input['reuters_client_id'] )
			? trim( wp_unslash( (string) $input['reuters_client_id'] ) )
			: '';

		$output['reuters_secret_id'] = isset( $input['reuters_secret_id'] )
			? trim( wp_unslash( (string) $input['reuters_secret_id'] ) )
			: '';

		$output['reuters_audience'] = isset( $input['reuters_audience'] )
			? trim( wp_unslash( (string) $input['reuters_audience'] ) )
			: '';

		return $output;
	}

	protected static function sanitize_source_rows( array $input, string $key, string $mode = 'url' ) : array {
		if ( empty( $input[ $key ] ) || ! is_array( $input[ $key ] ) ) {
			return array();
		}

		$clean = array();

		foreach ( $input[ $key ] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$url  = isset( $row['url'] ) ? (string) $row['url'] : '';

			if ( 'twitter_account' === $mode || 'instagram_account' === $mode ) {
				$url = sanitize_text_field( ltrim( trim( $url ), '@' ) );
			} elseif ( 'twitter_topic' === $mode ) {
				$url = sanitize_text_field( trim( $url ) );
			} else {
				$url = esc_url_raw( trim( $url ) );
			}

			if ( '' === $name && '' === $url ) {
				continue;
			}

			$clean[] = array(
				'name'   => $name,
				'url'    => $url,
				'active' => ! empty( $row['active'] ) ? 1 : 0,
			);
		}

		$clean = WSH_AINE_Access::enforce_source_limit( $clean, $key );

		return array_values( $clean );
	}

	protected static function get_source_limit_notice( string $source_key ) : string {
		$limit = WSH_AINE_Access::get_source_limit( $source_key );
		$count = WSH_AINE_Usage::get_source_count( $source_key );

		if ( $limit <= 0 ) {
			return '';
		}

			return sprintf(
			/* translators: 1: current count, 2: source limit, 3: plan label */
			__( 'Configured: %1$d / %2$d sources on the %3$s plan.', 'wsh-ai-news-editor' ),
			$count,
			$limit,
			WSH_AINE_Access::get_plan_label()
		);
	}

	public static function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		$options = get_option( 'wsh_aine_settings', array() );
		$authors = get_users(
			array(
				'who' => 'authors',
			)
		);

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs = array(
			'general'    => array( 'label' => __( 'General', 'wsh-ai-news-editor' ), 'plan' => 'free' ),
			'openai'     => array( 'label' => __( 'OpenAI', 'wsh-ai-news-editor' ), 'plan' => 'free' ),
			'youtube'    => array( 'label' => __( 'YouTube', 'wsh-ai-news-editor' ), 'plan' => 'free' ),
			'twitter'    => array( 'label' => __( 'Twitter / X', 'wsh-ai-news-editor' ), 'plan' => 'pro' ),
			'brightdata'  => array( 'label' => __( 'Brightdata', 'wsh-ai-news-editor' ), 'plan' => 'pro' ),
			'instagram'  => array( 'label' => __( 'Instagram', 'wsh-ai-news-editor' ), 'plan' => 'pro' ),
			'grok'       => array( 'label' => __( 'Grok / xAI', 'wsh-ai-news-editor' ), 'plan' => 'pro' ),
			'perplexity' => array( 'label' => __( 'Perplexity', 'wsh-ai-news-editor' ), 'plan' => 'pro' ),
			'sports'     => array( 'label' => __( 'Sports', 'wsh-ai-news-editor' ), 'plan' => 'pro' ),
			'local'      => array( 'label' => __( 'Local RSS', 'wsh-ai-news-editor' ), 'plan' => 'free' ),
			'google'     => array( 'label' => __( 'Google News', 'wsh-ai-news-editor' ), 'plan' => 'free' ),
			'images' => array( 'label' => __( 'Images', 'wsh-ai-news-editor' ), 'plan' => 'free' ), //Images
			
		);

		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap wsh-aine-settings-page">
			<h1><?php esc_html_e( 'WSH AI News Editor — Settings', 'wsh-ai-news-editor' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Podesi izvore, AI provajdere i osnovna pravila objave na jednom mestu.', 'wsh-ai-news-editor' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'wsh_aine_settings_group' ); ?>

				<div class="wsh-aine-settings-layout">
					<aside class="wsh-aine-settings-sidebar">
						<div class="wsh-aine-settings-summary">
							<div class="wsh-aine-overline"><?php esc_html_e( 'Current plan', 'wsh-ai-news-editor' ); ?></div>
							<div class="wsh-aine-settings-summary__plan">
								<span class="wsh-aine-plan-badge wsh-aine-plan-badge--<?php echo esc_attr( WSH_AINE_Access::get_plan() ); ?>"><?php echo esc_html( WSH_AINE_Access::get_plan_label() ); ?></span>
								<span><?php echo esc_html( WSH_AINE_Usage::get_ai_total_today() ); ?> / <?php echo esc_html( WSH_AINE_Access::get_limit( 'daily_ai_generations' ) ); ?> <?php esc_html_e( 'AI used today', 'wsh-ai-news-editor' ); ?></span>
							</div>
							<a class="button button-secondary" href="<?php echo esc_url( WSH_AINE_Access::get_upgrade_url() ); ?>"><?php echo esc_html( WSH_AINE_Access::get_upgrade_button_label() ); ?></a>
						</div>

						<div class="wsh-aine-settings-nav" data-wsh-settings-tabs>
							<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
								<button
									type="button"
									class="wsh-aine-settings-tab <?php echo $tab_key === $active_tab ? 'is-active' : ''; ?>"
									data-tab-target="<?php echo esc_attr( $tab_key ); ?>"
								>
									<span><?php echo esc_html( $tab_data['label'] ); ?></span>
									<?php if ( ! empty( $tab_data['plan'] ) && 'free' !== $tab_data['plan'] ) : ?>
										<span class="wsh-aine-inline-plan wsh-aine-inline-plan--<?php echo esc_attr( $tab_data['plan'] ); ?>"><?php echo esc_html( strtoupper( $tab_data['plan'] ) ); ?></span>
									<?php endif; ?>
								</button>
							<?php endforeach; ?>
						</div>
					</aside>

					<div class="wsh-aine-settings-content">
						<div class="wsh-aine-settings-panel <?php echo 'general' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="general">
							<?php self::render_general_section( $options, $authors ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'openai' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="openai">
							<?php self::render_openai_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'youtube' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="youtube">
							<?php self::render_youtube_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'twitter' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="twitter">
							<?php self::render_twitter_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'grok' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="grok">
							<?php self::render_grok_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'brightdata' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="brightdata">
							<?php self::render_brightdata_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'perplexity' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="perplexity">
							<?php self::render_perplexity_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'instagram' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="instagram">
							<?php self::render_instagram_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'sports' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="sports">
							<?php self::render_sports_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'local' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="local">
							<?php self::render_local_sources_section( $options ); ?>
						</div>

						<div class="wsh-aine-settings-panel <?php echo 'google' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="google">
							<?php self::render_google_sources_section( $options ); ?>
						</div>

						<!-- Images -->
						<div class="wsh-aine-settings-panel <?php echo 'images' === $active_tab ? 'is-active' : ''; ?>" data-tab-panel="images">
							<?php self::render_images_section( $options ); ?>
						</div>
					</div>
				</div>

				<div class="wsh-aine-settings-actions">
					<?php submit_button( __( 'Save Settings', 'wsh-ai-news-editor' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	protected static function render_general_section( array $options, array $authors ) : void {
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'General Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Country', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[country]" value="<?php echo esc_attr( $options['country'] ?? 'RS' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Example: RS, US, DE', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Language', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[language]" value="<?php echo esc_attr( $options['language'] ?? 'sr' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Example: sr, en, de', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Post Status', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<select name="wsh_aine_settings[default_post_status]">
							<option value="draft" <?php selected( $options['default_post_status'] ?? 'draft', 'draft' ); ?>><?php esc_html_e( 'Draft', 'wsh-ai-news-editor' ); ?></option>
							<option value="pending" <?php selected( $options['default_post_status'] ?? 'draft', 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'wsh-ai-news-editor' ); ?></option>
							<option value="publish" <?php selected( $options['default_post_status'] ?? 'draft', 'publish' ); ?>><?php esc_html_e( 'Publish', 'wsh-ai-news-editor' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Author', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<select name="wsh_aine_settings[default_author]">
							<option value=""><?php esc_html_e( 'Logged In User', 'wsh-ai-news-editor' ); ?></option>
							<?php foreach ( $authors as $author ) : ?>
								<option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( (int) ( $options['default_author'] ?? get_current_user_id() ), (int) $author->ID ); ?>>
									<?php echo esc_html( $author->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	protected static function render_openai_section( array $options ) : void {
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'OpenAI Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Key', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="password" name="wsh_aine_settings[openai_api_key]" value="<?php echo esc_attr( $options['openai_api_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Model', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="text" name="wsh_aine_settings[openai_model]" value="<?php echo esc_attr( $options['openai_model'] ?? 'gpt-4.1-mini' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Temperature', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="text" name="wsh_aine_settings[openai_temperature]" value="<?php echo esc_attr( $options['openai_temperature'] ?? '0.7' ); ?>" class="small-text" /></td>
				</tr>
			</table>
		</div>
		<?php
	}

	protected static function render_youtube_section( array $options ) : void {
		$yt_categories = self::get_youtube_categories();
		$selected_categories = isset( $options['youtube_categories'] ) && is_array( $options['youtube_categories'] )
			? array_map( 'strval', $options['youtube_categories'] )
			: array( '25', '17', '24', '28' );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'YouTube Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'YouTube API Key', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="password" name="wsh_aine_settings[youtube_api_key]" value="<?php echo esc_attr( $options['youtube_api_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Region', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[youtube_region]" value="<?php echo esc_attr( $options['youtube_region'] ?? 'RS' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Example: RS, US, DE', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Videos', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="number" min="1" max="25" name="wsh_aine_settings[youtube_max_videos]" value="<?php echo esc_attr( $options['youtube_max_videos'] ?? 10 ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Comments per Video', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="number" min="0" max="20" name="wsh_aine_settings[youtube_max_comments]" value="<?php echo esc_attr( $options['youtube_max_comments'] ?? 5 ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'YouTube Categories', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="hidden" name="wsh_aine_settings[youtube_categories_present]" value="1" />
						<div class="wsh-aine-category-box">
							<?php foreach ( $yt_categories as $cat_id => $cat_label ) : ?>
								<label class="wsh-aine-category-item">
									<input type="checkbox" name="wsh_aine_settings[youtube_categories][]" value="<?php echo esc_attr( $cat_id ); ?>" <?php checked( in_array( (string) $cat_id, $selected_categories, true ) ); ?> />
									<span><?php echo esc_html( $cat_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Select which YouTube content categories should be shown in the YouTube News module.', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	protected static function render_twitter_section( array $options ) : void {
		self::render_plan_notice( 'pro', __( 'Twitter / X workflows are part of the Pro plan. You can prepare settings now and unlock the module from the License page.', 'wsh-ai-news-editor' ) );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'Twitter / X Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Bearer Token', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="password" name="wsh_aine_settings[twitter_bearer_token]" value="<?php echo esc_attr( $options['twitter_bearer_token'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Region', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="text" name="wsh_aine_settings[twitter_region]" value="<?php echo esc_attr( $options['twitter_region'] ?? 'RS' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Language', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="text" name="wsh_aine_settings[twitter_language]" value="<?php echo esc_attr( $options['twitter_language'] ?? 'sr' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Tweets', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="number" min="1" max="25" name="wsh_aine_settings[twitter_max_results]" value="<?php echo esc_attr( $options['twitter_max_results'] ?? 10 ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Filters', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" name="wsh_aine_settings[twitter_exclude_replies]" value="1" <?php checked( ! empty( $options['twitter_exclude_replies'] ) ); ?> />
							<?php esc_html_e( 'Exclude replies', 'wsh-ai-news-editor' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" name="wsh_aine_settings[twitter_exclude_retweets]" value="1" <?php checked( ! empty( $options['twitter_exclude_retweets'] ) ); ?> />
							<?php esc_html_e( 'Exclude retweets', 'wsh-ai-news-editor' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<?php
		self::render_repeater_section(
			__( 'Tracked Accounts', 'wsh-ai-news-editor' ),
			__( 'Dodaj X naloge koje želiš da pratiš. Username se čuva bez @.', 'wsh-ai-news-editor' ),
			'twitter_accounts',
			'wsh_aine_settings[twitter_accounts]',
			$options['twitter_accounts'] ?? array(),
			__( 'Add Account', 'wsh-ai-news-editor' ),
			__( 'Name', 'wsh-ai-news-editor' ),
			__( 'Username', 'wsh-ai-news-editor' ),
			'username'
		);

		self::render_repeater_section(
			__( 'Tracked Topics', 'wsh-ai-news-editor' ),
			__( 'Dodaj pretrage ili teme koje želiš da koristiš za X pretragu.', 'wsh-ai-news-editor' ),
			'twitter_topics',
			'wsh_aine_settings[twitter_topics]',
			$options['twitter_topics'] ?? array(),
			__( 'Add Topic', 'wsh-ai-news-editor' ),
			__( 'Name', 'wsh-ai-news-editor' ),
			__( 'Query', 'wsh-ai-news-editor' ),
			'query'
		);
	}

	protected static function render_grok_section( array $options ) : void {
		self::render_plan_notice( 'pro', __( 'Grok research is positioned as a Pro feature. Keep the configuration ready, then activate your license to unlock the module.', 'wsh-ai-news-editor' ) );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'Grok / xAI Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Grok API Key', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="password" name="wsh_aine_settings[grok_api_key]" value="<?php echo esc_attr( $options['grok_api_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Grok Model', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[grok_model]" value="<?php echo esc_attr( $options['grok_model'] ?? 'grok-4.20-beta-latest-non-reasoning' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Example: grok-4.20-beta-latest-non-reasoning', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Search Tools', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" name="wsh_aine_settings[grok_use_x_search]" value="1" <?php checked( ! empty( $options['grok_use_x_search'] ) ); ?> />
							<?php esc_html_e( 'Enable X Search', 'wsh-ai-news-editor' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" name="wsh_aine_settings[grok_use_web_search]" value="1" <?php checked( ! empty( $options['grok_use_web_search'] ) ); ?> />
							<?php esc_html_e( 'Enable Web Search', 'wsh-ai-news-editor' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Items', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="number" min="1" max="20" name="wsh_aine_settings[grok_max_items]" value="<?php echo esc_attr( $options['grok_max_items'] ?? 10 ); ?>" class="small-text" /></td>
				</tr>
			</table>
		</div>
		<?php
	}

	protected static function render_perplexity_section( array $options ) : void {
		self::render_plan_notice( 'pro', __( 'Perplexity web research is available on Pro. This section stays visible so you can pre-configure it before activation.', 'wsh-ai-news-editor' ) );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'Perplexity Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Perplexity API Key', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="password" name="wsh_aine_settings[perplexity_api_key]" value="<?php echo esc_attr( $options['perplexity_api_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Model', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[perplexity_model]" value="<?php echo esc_attr( $options['perplexity_model'] ?? 'sonar' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Example: sonar', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Items', 'wsh-ai-news-editor' ); ?></th>
					<td><input type="number" min="1" max="20" name="wsh_aine_settings[perplexity_max_items]" value="<?php echo esc_attr( $options['perplexity_max_items'] ?? 10 ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Search Context Size', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<select name="wsh_aine_settings[perplexity_search_context_size]">
							<option value="low" <?php selected( $options['perplexity_search_context_size'] ?? 'medium', 'low' ); ?>>low</option>
							<option value="medium" <?php selected( $options['perplexity_search_context_size'] ?? 'medium', 'medium' ); ?>>medium</option>
							<option value="high" <?php selected( $options['perplexity_search_context_size'] ?? 'medium', 'high' ); ?>>high</option>
						</select>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	protected static function render_instagram_section( array $options ) : void {
		self::render_plan_notice( 'pro', __( 'Insta News is part of the Pro plan. Configure Instagram Data and tracked Instagram accounts here.', 'wsh-ai-news-editor' ) );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'Instagram Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Bright Data API Token', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" disabled name="wsh_aine_settings[brightdata_api_token]" value="<?php echo esc_attr( $options['brightdata_api_token'] ?? '' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( '*Set in Brightdata panel below.', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Instagram Dataset ID', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" disabled name="wsh_aine_settings[brightdata_instagram_profile_dataset_id]" value="<?php echo esc_attr( $options['brightdata_instagram_profile_dataset_id'] ?? '' ); ?>" class="regular-text code" />
						<p class="description"><?php esc_html_e( 'Dataset used to fetch account posts - *Set in Brightdata panel below.', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Instagram Post Dataset ID', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" disabled name="wsh_aine_settings[brightdata_instagram_post_dataset_id]" value="<?php echo esc_attr( $options['brightdata_instagram_post_dataset_id'] ?? '' ); ?>" class="regular-text code" />
						<p class="description"><?php esc_html_e( 'Dataset used to fetch single post data - *Set in Brightdata panel below.', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Posts Per Account', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="number" min="1" max="20" name="wsh_aine_settings[instagram_max_posts]" value="<?php echo esc_attr( $options['instagram_max_posts'] ?? 6 ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" name="wsh_aine_settings[instagram_include_comments]" value="1" <?php checked( ! empty( $options['instagram_include_comments'] ) ); ?> />
							<?php esc_html_e( 'Include comments when available', 'wsh-ai-news-editor' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" name="wsh_aine_settings[instagram_include_reels]" value="1" <?php checked( ! empty( $options['instagram_include_reels'] ) ); ?> />
							<?php esc_html_e( 'Include reels', 'wsh-ai-news-editor' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<?php
		self::render_repeater_section(
			__( 'Tracked Instagram Accounts', 'wsh-ai-news-editor' ),
			__( 'Dodaj Instagram naloge koje želiš da pratiš. Username se čuva bez @.', 'wsh-ai-news-editor' ),
			'instagram_accounts',
			'wsh_aine_settings[instagram_accounts]',
			$options['instagram_accounts'] ?? array(),
			__( 'Add Instagram Account', 'wsh-ai-news-editor' ),
			__( 'Name', 'wsh-ai-news-editor' ),
			__( 'Instagram Username', 'wsh-ai-news-editor' ),
			'username'
		);
	}

	protected static function render_brightdata_section( array $options ) : void {
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'Bright Data API', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Token', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" name="wsh_aine_settings[brightdata_api_token]" value="<?php echo esc_attr( $options['brightdata_api_token'] ?? '' ); ?>" class="regular-text" />
					</td>
				</tr>
			</table>
		</div>
		<?php

		self::render_brightdata_platform_fields( 'instagram', 'Instagram', $options );
		self::render_brightdata_platform_fields( 'tiktok', 'TikTok', $options );
		self::render_brightdata_platform_fields( 'youtube', 'YouTube', $options );
		self::render_brightdata_platform_fields( 'linkedin', 'LinkedIn', $options );
		self::render_brightdata_platform_fields( 'twitter', 'Twitter / X', $options );
	}

	protected static function render_brightdata_platform_fields( string $platform, string $label, array $options ) : void {
		$profile_key  = 'brightdata_' . $platform . '_profile_dataset_id';
		$post_key     = 'brightdata_' . $platform . '_post_dataset_id';
		$comments_key = 'brightdata_' . $platform . '_comments_dataset_id';
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php echo esc_html( $label ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Profile Dataset ID', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[<?php echo esc_attr( $profile_key ); ?>]" value="<?php echo esc_attr( $options[ $profile_key ] ?? '' ); ?>" class="regular-text code" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post Dataset ID', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[<?php echo esc_attr( $post_key ); ?>]" value="<?php echo esc_attr( $options[ $post_key ] ?? '' ); ?>" class="regular-text code" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Comments Dataset ID', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[<?php echo esc_attr( $comments_key ); ?>]" value="<?php echo esc_attr( $options[ $comments_key ] ?? '' ); ?>" class="regular-text code" />
						<p class="description">
							<?php esc_html_e( 'Optional for platforms where comments dataset is not available yet.', 'wsh-ai-news-editor' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	protected static function render_sports_section( array $options ) : void {
		self::render_plan_notice( 'pro', __( 'Sports News is reserved for the PRO plan. Configure it in advance or upgrade when you are ready to unlock the module.', 'wsh-ai-news-editor' ) );
		$enabled_sports = isset( $options['sports_enabled'] ) && is_array( $options['sports_enabled'] ) ? $options['sports_enabled'] : array( 'football', 'basketball' );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'API-SPORTS Settings', 'wsh-ai-news-editor' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'API-SPORTS API Key', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" name="wsh_aine_settings[api_sports_api_key]" value="<?php echo esc_attr( $options['api_sports_api_key'] ?? '' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Used for the Sports News module.', 'wsh-ai-news-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled Sports', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" name="wsh_aine_settings[sports_enabled][]" value="football" <?php checked( in_array( 'football', $enabled_sports, true ) ); ?> />
							<?php esc_html_e( 'Football', 'wsh-ai-news-editor' ); ?>
						</label>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" name="wsh_aine_settings[sports_enabled][]" value="basketball" <?php checked( in_array( 'basketball', $enabled_sports, true ) ); ?> />
							<?php esc_html_e( 'Basketball', 'wsh-ai-news-editor' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" name="wsh_aine_settings[sports_enabled][]" value="tennis" <?php checked( in_array( 'tennis', $enabled_sports, true ) ); ?> />
							<?php esc_html_e( 'Tennis (UI placeholder for phase 2)', 'wsh-ai-news-editor' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	protected static function render_local_sources_section( array $options ) : void {
		self::render_repeater_section(
			__( 'Local Media Sources', 'wsh-ai-news-editor' ),
			__( 'Dodaj lokalne RSS feed izvore bez ručnog JSON unosa.', 'wsh-ai-news-editor' ),
			'local_sources',
			'wsh_aine_settings[local_sources]',
			$options['local_sources'] ?? array(),
			__( 'Add RSS Source', 'wsh-ai-news-editor' ),
			__( 'Name', 'wsh-ai-news-editor' ),
			__( 'Feed URL', 'wsh-ai-news-editor' ),
			'url'
		);
	}

	protected static function render_google_sources_section( array $options ) : void {
		self::render_repeater_section(
			__( 'Google News Sources', 'wsh-ai-news-editor' ),
			__( 'Dodaj unapred pripremljene Google News RSS izvore ili kategorije.', 'wsh-ai-news-editor' ),
			'google_sources',
			'wsh_aine_settings[google_sources]',
			$options['google_sources'] ?? array(),
			__( 'Add Google Source', 'wsh-ai-news-editor' ),
			__( 'Name', 'wsh-ai-news-editor' ),
			__( 'Google News RSS URL', 'wsh-ai-news-editor' ),
			'url'
		);
	}


	protected static function render_plan_notice( string $required_plan, string $message ) : void {
		if ( WSH_AINE_Access::can_access_plan( $required_plan ) ) {
			return;
		}
		?>
		<div class="wsh-aine-upsell-box">
			<div>
				<div class="wsh-aine-upsell-box__title">
					<span class="wsh-aine-plan-badge wsh-aine-plan-badge--<?php echo esc_attr( $required_plan ); ?>"><?php echo esc_html( WSH_AINE_Access::get_plan_label( $required_plan ) ); ?></span>
					<span><?php esc_html_e( 'Premium feature', 'wsh-ai-news-editor' ); ?></span>
				</div>
				<p><?php echo esc_html( $message ); ?></p>
			</div>
			<a class="button button-secondary" href="<?php echo esc_url( WSH_AINE_Access::get_upgrade_url() ); ?>"><?php echo esc_html( WSH_AINE_Access::get_upgrade_button_label( $required_plan ) ); ?></a>
		</div>
		<?php
	}

	protected static function render_repeater_section( string $title, string $description, string $source_key, string $field_name, array $rows, string $button_text, string $name_label, string $value_label, string $value_type = 'url' ) : void {
		$rows = is_array( $rows ) && ! empty( $rows ) ? array_values( $rows ) : array(
			array(
				'name'   => '',
				'url'    => '',
				'active' => 1,
			),
		);
		$limit_notice = self::get_source_limit_notice( $source_key );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p class="description"><?php echo esc_html( $description ); ?></p>
			<?php if ( '' !== $limit_notice ) : ?>
				<p class="description" style="margin-top:8px;font-weight:600;"><?php echo esc_html( $limit_notice ); ?></p>
			<?php endif; ?>

			<div class="wsh-aine-repeater" data-repeater data-field-name="<?php echo esc_attr( $field_name ); ?>" data-value-type="<?php echo esc_attr( $value_type ); ?>">
				<?php if ( WSH_AINE_Access::get_source_limit( $source_key ) <= count( $rows ) ) : ?>
				<div class="wsh-aine-inline-upgrade">
					<span><?php esc_html_e( 'You are at your current source limit.', 'wsh-ai-news-editor' ); ?></span>
					<a href="<?php echo esc_url( WSH_AINE_Access::get_upgrade_url() ); ?>"><?php echo esc_html( WSH_AINE_Access::get_upgrade_button_label() ); ?></a>
				</div>
			<?php endif; ?>
			<div class="wsh-aine-repeater-rows" data-repeater-rows>
					<?php foreach ( $rows as $index => $row ) : ?>
						<?php self::render_repeater_row( $field_name, (int) $index, $row, $name_label, $value_label, $value_type ); ?>
					<?php endforeach; ?>
				</div>

				<script type="text/template" class="wsh-aine-repeater-template">
					<?php self::render_repeater_row( $field_name, '__index__', array( 'name' => '', 'url' => '', 'active' => 1 ), $name_label, $value_label, $value_type ); ?>
				</script>

				<p><button type="button" class="button button-secondary" data-repeater-add><?php echo esc_html( $button_text ); ?></button></p>
			</div>
		</div>
		<?php
	}

	protected static function render_repeater_row( string $field_name, $index, array $row, string $name_label, string $value_label, string $value_type ) : void {
		$name   = isset( $row['name'] ) ? (string) $row['name'] : '';
		$url    = isset( $row['url'] ) ? (string) $row['url'] : '';
		$active = ! empty( $row['active'] );
		$placeholder = 'url' === $value_type ? 'https://example.com/feed' : ( 'username' === $value_type ? 'DjokerNole' : '"Novak Djokovic" lang:sr' );
		?>
		<div class="wsh-aine-repeater-row" data-repeater-row>
			<div class="wsh-aine-repeater-grid">
				<div>
					<label><?php echo esc_html( $name_label ); ?></label>
					<input type="text" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" class="regular-text" />
				</div>
				<div>
					<label><?php echo esc_html( $value_label ); ?></label>
					<input type="text" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" class="regular-text code" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				</div>
				<div class="wsh-aine-repeater-toggle">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $index ); ?>][active]" value="1" <?php checked( $active ); ?> />
						<?php esc_html_e( 'Active', 'wsh-ai-news-editor' ); ?>
					</label>
				</div>
				<div class="wsh-aine-repeater-actions">
					<button type="button" class="button-link-delete" data-repeater-remove><?php esc_html_e( 'Remove', 'wsh-ai-news-editor' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	protected static function get_youtube_categories() : array {
		return array(
			'1'  => 'Film & Animation',
			'2'  => 'Autos & Vehicles',
			'10' => 'Music',
			'15' => 'Pets & Animals',
			'17' => 'Sports',
			'19' => 'Travel & Events',
			'20' => 'Gaming',
			'22' => 'People & Blogs',
			'23' => 'Comedy',
			'24' => 'Entertainment',
			'25' => 'News & Politics',
			'26' => 'Howto & Style',
			'27' => 'Education',
			'28' => 'Science & Technology',
		);
	}

	protected static function render_images_section( array $options ) : void {
		$enabled = isset( $options['image_services_enabled'] ) && is_array( $options['image_services_enabled'] )
			? $options['image_services_enabled']
			: array( 'pexels', 'pixabay', 'unsplash', 'ai_generated', 'reuters' );
		?>
		<div class="wsh-aine-settings-card">
			<h2><?php esc_html_e( 'Image Services Settings', 'wsh-ai-news-editor' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure image providers used inside AI Editor when searching for a new featured image.', 'wsh-ai-news-editor' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled Image Services', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wsh_aine_settings[image_services_enabled][]" value="pexels" <?php checked( in_array( 'pexels', $enabled, true ) ); ?> />
							<?php esc_html_e( 'Pexels', 'wsh-ai-news-editor' ); ?>
						</label>
						<br>

						<label>
							<input type="checkbox" name="wsh_aine_settings[image_services_enabled][]" value="pixabay" <?php checked( in_array( 'pixabay', $enabled, true ) ); ?> />
							<?php esc_html_e( 'Pixabay', 'wsh-ai-news-editor' ); ?>
						</label>
						<br>

						<label>
							<input type="checkbox" name="wsh_aine_settings[image_services_enabled][]" value="unsplash" <?php checked( in_array( 'unsplash', $enabled, true ) ); ?> />
							<?php esc_html_e( 'Unsplash', 'wsh-ai-news-editor' ); ?>
						</label>

						<br>
						<label>
							<input type="checkbox" name="wsh_aine_settings[image_services_enabled][]" value="ai_generated" <?php checked( in_array( 'ai_generated', $enabled, true ) ); ?> />
							<?php esc_html_e( 'AI Generated', 'wsh-ai-news-editor' ); ?>
						</label>

						<br>
						<label>
							<input type="checkbox" name="wsh_aine_settings[image_services_enabled][]" value="reuters" <?php checked( in_array( 'reuters', $enabled, true ) ); ?> />
							<?php esc_html_e( 'Reuters', 'wsh-ai-news-editor' ); ?>
						</label>

						<p class="description">
							<?php esc_html_e( 'Only selected services will appear in the AI Editor image search popup.', 'wsh-ai-news-editor' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Pexels API Key', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" name="wsh_aine_settings[pexels_api_key]" value="<?php echo esc_attr( $options['pexels_api_key'] ?? '' ); ?>" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Used for searching high-quality free images from Pexels.', 'wsh-ai-news-editor' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Pixabay API Key', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" name="wsh_aine_settings[pixabay_api_key]" value="<?php echo esc_attr( $options['pixabay_api_key'] ?? '' ); ?>" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Used for searching free images from Pixabay.', 'wsh-ai-news-editor' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Unsplash Access Key', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" name="wsh_aine_settings[unsplash_access_key]" value="<?php echo esc_attr( $options['unsplash_access_key'] ?? '' ); ?>" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Used for searching premium-style photos from Unsplash. Make sure you follow Unsplash API guidelines.', 'wsh-ai-news-editor' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Reuters Client ID', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" name="wsh_aine_settings[reuters_client_id]" value="<?php echo esc_attr( $options['reuters_client_id'] ?? '' ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Reuters Secret ID', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="password" name="wsh_aine_settings[reuters_secret_id]" value="<?php echo esc_attr( $options['reuters_secret_id'] ?? '' ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Reuters Audience', 'wsh-ai-news-editor' ); ?></th>
					<td>
						<input type="text" name="wsh_aine_settings[reuters_audience]" value="<?php echo esc_attr( $options['reuters_audience'] ?? '' ); ?>" class="regular-text" />
					</td>
				</tr>
				
			</table>
		</div>
		<?php
	}
}
