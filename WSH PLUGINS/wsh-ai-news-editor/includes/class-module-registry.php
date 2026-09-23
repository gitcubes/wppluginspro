<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Module_Registry {

	public static function get_modules() : array {
		$modules = array(
			array(
				'slug'          => 'wsh-ai-news-editor-local-media',
				'menu_title'    => __( 'Local Media', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Local Media', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Local_Media_Page', 'render' ),
				'required_plan' => 'free',
				'group'         => 'sources',
				'description'   => __( 'RSS and local media sources workflow.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-google-news',
				'menu_title'    => __( 'Google News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Google News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Google_News_Page', 'render' ),
				'required_plan' => 'free',
				'group'         => 'sources',
				'description'   => __( 'Google News aggregation workflow.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-youtube-news',
				'menu_title'    => __( 'YouTube News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'YouTube News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_YouTube_News_Page', 'render' ),
				'required_plan' => 'free',
				'group'         => 'sources',
				'description'   => __( 'YouTube videos and comments into drafts.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-twitter-news',
				'menu_title'    => __( 'Twitter / X News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Twitter / X News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Twitter_News_Page', 'render' ),
				'required_plan' => 'pro',
				'group'         => 'sources',
				'description'   => __( 'Tweets, accounts, and topic tracking.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-grok-news',
				'menu_title'    => __( 'Grok News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Grok News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Grok_News_Page', 'render' ),
				'required_plan' => 'pro',
				'group'         => 'research',
				'description'   => __( 'X research and angle generation.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-perplexity-news',
				'menu_title'    => __( 'Perplexity News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Perplexity News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Perplexity_News_Page', 'render' ),
				'required_plan' => 'pro',
				'group'         => 'research',
				'description'   => __( 'Web research and source-backed summaries.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-sports-news',
				'menu_title'    => __( 'Sports News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Sports News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Sports_News_Page', 'render' ),
				'required_plan' => 'pro',
				'group'         => 'verticals',
				'description'   => __( 'Sports-specific sourcing and workflows.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-social-network-news',
				'menu_title'    => __( 'Social Network News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Social Network News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Social_Network_News_Page', 'render' ),
				'required_plan' => 'pro',
				'group'         => 'sources',
				'description'   => __( 'Paste a social network post URL and generate an AI news article.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-insta-news',
				'menu_title'    => __( 'Insta News', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'Insta News', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_Insta_News_Page', 'render' ),
				'required_plan' => 'pro',
				'group'         => 'sources',
				'description'   => __( 'Instagram account tracking and post-to-draft workflow.', 'wsh-ai-news-editor' ),
			),
			array(
				'slug'          => 'wsh-ai-news-editor-ai-editor',
				'menu_title'    => __( 'AI Editor', 'wsh-ai-news-editor' ),
				'page_title'    => __( 'AI Editor', 'wsh-ai-news-editor' ),
				'callback'      => array( 'WSH_AINE_AI_Editor_Page', 'render' ),
				'required_plan' => 'free',
				'group'         => 'editor',
				'description'   => __( 'Compose and regenerate AI drafts.', 'wsh-ai-news-editor' ),
			),
		);

		return apply_filters( 'wsh_aine_module_registry', $modules );
	}

	public static function get_module( string $slug ) : ?array {
		foreach ( self::get_modules() as $module ) {
			if ( isset( $module['slug'] ) && $slug === $module['slug'] ) {
				return $module;
			}
		}

		return null;
	}
}
