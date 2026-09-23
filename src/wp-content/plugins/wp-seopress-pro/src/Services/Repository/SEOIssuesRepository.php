<?php

namespace SEOPressPro\Services\Repository;

defined( 'ABSPATH' ) || exit;

use SEOPress\Models\AbstractRepository;

class SEOIssuesRepository extends AbstractRepository {


	public function __construct() {
		$this->table = seopress_pro_get_service( 'TableList' )->getTableSEOIssues();
	}

	protected function getAuthorizedInsertValues(): array {
		return array(
			'post_id',
			'issue_name',
			'issue_desc',
			'issue_type',
			'issue_priority',
			'issue_ignore',
		);
	}

	protected function getAuthorizedUpdateValues(): array {
		return array(
			'issue_name',
			'issue_desc',
			'issue_type',
			'issue_priority',
			'issue_ignore',
		);
	}

	public function issueAlreadyExistForPostId( $postId, $issue_name ) {
		global $wpdb;

		$postId     = absint( $postId );
		$issue_name = sanitize_text_field( $issue_name );

		$tableName = esc_sql( $this->getTableName() );

		$sql = $wpdb->prepare( "SELECT id FROM {$tableName} WHERE post_id = %d AND issue_name = %s", $postId, $issue_name );

		$result = $wpdb->get_results( $sql );

		return ! empty( $result );
	}

	/**
	 * @param array $issue
	 */
	public function insertSEOIssue( $issue ) {

		global $wpdb;
		$sql  = $this->getInsertInstruction( $issue );
		$sql .= $this->getInsertValuesInstruction( $issue );

		try {
			return $wpdb->query( $sql );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public function updateSEOIssue( $postId, $issue ) {
		global $wpdb;

		$postId     = absint( $postId );
		$issue_name = sanitize_text_field( $issue['issue_name'] );

		$sql  = $this->getUpdateInstruction( $issue );
		$sql .= $this->getUpdateValues( $issue );
		$sql .= $wpdb->prepare( ' WHERE post_id = %d AND issue_name = %s', $postId, $issue_name );

		try {
			return $wpdb->query( $sql );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public function deleteSEOIssue( $postId, $issue_type ) {
		global $wpdb;

		$postId     = absint( $postId );
		$issue_type = sanitize_text_field( $issue_type );

		$tableName = esc_sql( $this->getTableName() );

		try {
			return $wpdb->delete(
				$tableName,
				array(
					'post_id'    => $postId,
					'issue_type' => $issue_type,
				),
				array(
					'%d',
					'%s',
				)
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public function getSEOIssue( $postId, $columns = array( '*' ) ) {

		global $wpdb;
		$strColumns = implode( ', ', $columns );
		$sql        = $wpdb->prepare(
			"SELECT *
             FROM {$this->getTableName()}
             WHERE post_id = %d AND issue_type = %s
             LIMIT 100",
			$postId,
			$strColumns
		);

		$result = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $result ) ) {
			return null;
		}

		return array_map( 'maybe_unserialize', $result );
	}
}
