<?php

namespace SEOPressPro\Services\Table;

defined( 'ABSPATH' ) || exit;

use SEOPressPro\Models\Table\TableStructure;
use SEOPressPro\Models\Table\TableColumn;
use SEOPressPro\Models\Table\Table;

class TableList {

	public function getTableSignificantKeywords() {
		$tableStructureImportantKeywords = new TableStructure(
			array(
				new TableColumn(
					'id',
					array(
						'type'       => 'bigint(20)',
						'primaryKey' => true,
					)
				),
				new TableColumn(
					'post_id',
					array(
						'type'    => 'bigint(20)',
						'index'   => true,
						'default' => 0,
					)
				),
				new TableColumn(
					'word',
					array(
						'type'    => 'varchar(100)',
						'index'   => true,
						'default' => '',
					)
				),
				new TableColumn(
					'count',
					array(
						'type'    => 'int(11)',
						'default' => 0,
					)
				),
				new TableColumn(
					'tf',
					array(
						'type'    => 'float',
						'default' => 0.0,
					)
				),
			)
		);

		return new Table( 'seopress_significant_keywords', $tableStructureImportantKeywords, 1 );
	}

	public function getTableSEOIssues() {
		$tableStructure = new TableStructure(
			array(
				new TableColumn(
					'id',
					array(
						'type'       => 'bigint(20)',
						'primaryKey' => true,
					)
				),
				new TableColumn(
					'post_id',
					array(
						'type'    => 'bigint(20)',
						'index'   => true,
						'default' => 0,
					)
				),
				new TableColumn(
					'issue_name',
					array(
						'type'    => 'longtext',
						'default' => '',
					)
				),
				new TableColumn(
					'issue_desc',
					array(
						'type'    => 'longtext',
						'default' => '',
					)
				),
				new TableColumn(
					'issue_type',
					array(
						'type'    => 'text',
						'default' => '',
					)
				),
				new TableColumn(
					'issue_priority',
					array(
						'type'    => 'text',
						'default' => '',
					)
				),
				new TableColumn(
					'issue_ignore',
					array(
						'type'    => 'boolean',
						'default' => 0,
					)
				),
			)
		);

		return new Table( 'seopress_seo_issues', $tableStructure, 1 );
	}

	public function getTables() {
		return array(
			'seopress_significant_keywords' => $this->getTableSignificantKeywords(),
			'seopress_seo_issues'           => $this->getTableSEOIssues(),
		);
	}
}
