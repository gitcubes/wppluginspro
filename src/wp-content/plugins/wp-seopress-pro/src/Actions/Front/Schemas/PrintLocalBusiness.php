<?php

namespace SEOPressPro\Actions\Front\Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEOPress\Core\Hooks\ExecuteHooksFrontend;
use SEOPress\Helpers\RichSnippetType;

class PrintLocalBusiness implements ExecuteHooksFrontend {
	public function hooks() {
		add_action( 'wp_head', array( $this, 'render' ), 2 );
	}

	public function render() {
		$data     = seopress_pro_get_service( 'OptionPro' )->getLocalBusinessOpeningHours();
		$fallback = true;
		if ( isset( $data[0] ) && isset( $data[0]['am'], $data[0]['pm'] ) ) {
			$fallback = false;
		}

		if ( apply_filters( 'seopress_fallback_local_business_schema_renderer', $fallback ) ) {
			return;
		}

		$render = false;
		$page   = seopress_pro_get_service( 'OptionPro' )->getLocalBusinessPage();
		if ( ! empty( $page ) && ( is_single( $page ) || is_page( $page ) ) ) {
			$render = true;
		} elseif ( empty( $page ) && ( is_home() || is_front_page() ) ) {
			$render = true;
		}

		if ( ! $render ) {
			return;
		}

		$toggle = seopress_get_service( 'ToggleOption' )->getToggleLocalBusiness();
		if ( '1' !== $toggle ) {
			return;
		}

		if ( 'localbusiness' === get_post_meta( get_the_ID(), '_seopress_pro_rich_snippets_type', true ) ) {
			return;
		}

		$jsons = seopress_get_service( 'JsonSchemaGenerator' )->getJsonsEncoded(
			array(
				'local-business',
			),
			array( 'type' => RichSnippetType::OPTION_LOCAL_BUSINESS )
		);

		$jsons = seopress_get_service( 'TagsToString' )->replaceDataToString( $jsons );

		?><script type="application/ld+json"><?php echo apply_filters( 'seopress_schemas_local_business_html', $jsons[0] ); ?></script>
		<?php
	}
}
