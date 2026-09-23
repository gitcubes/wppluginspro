<?php
if ( ! defined('ABSPATH') ) exit;

class WSH_WCBPM_Preset_Manager {

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'wsh_wcbpm_presets';
	}

	protected static function is_pro_active() : bool {
		return class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active();
	}

	public static function list_presets( int $limit = 200 ) : array {
		if ( ! self::is_pro_active() ) return array();

		global $wpdb;
		$table = self::table_name();
		$limit = max(1, min(500, $limit));

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, description, created_at, updated_at, created_by
				 FROM {$table}
				 ORDER BY updated_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public static function get_preset( int $id ) : ?array {
		if ( ! self::is_pro_active() ) return null;

		global $wpdb;
		$table = self::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, description, payload, created_at, updated_at, created_by
				 FROM {$table}
				 WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) return null;

		$payload = json_decode( (string)($row['payload'] ?? ''), true );
		if ( ! is_array($payload) ) $payload = array();

		$row['payload'] = $payload;
		return $row;
	}

	public static function save_preset( string $name, string $description, array $payload, int $id = 0 ) : array {
		if ( ! self::is_pro_active() ) {
			return array('success'=>false,'message'=>__('PRO feature. Activate your license.', 'wsh-wcbpm'));
		}

		$name = trim($name);
		if ( $name === '' ) {
			return array('success'=>false,'message'=>__('Preset name is required.', 'wsh-wcbpm'));
		}

		global $wpdb;
		$table = self::table_name();

		$now = current_time('mysql');
		$user_id = get_current_user_id();

		$data = array(
			'name'        => $name,
			'description' => $description,
			'payload'     => wp_json_encode($payload),
			'updated_at'  => $now,
		);

		$formats = array('%s','%s','%s','%s');

		if ( $id > 0 ) {
			$ok = $wpdb->update($table, $data, array('id'=>$id), $formats, array('%d'));
			if ( false === $ok ) {
				return array('success'=>false,'message'=>__('Failed to update preset.', 'wsh-wcbpm'));
			}
			return array('success'=>true,'message'=>__('Preset updated.', 'wsh-wcbpm'), 'id'=>$id);
		}

		$data['created_at'] = $now;
		$data['created_by'] = (int) $user_id;
		$formats[] = '%s';
		$formats[] = '%d';

		$ok = $wpdb->insert($table, $data, $formats);
		if ( ! $ok ) {
			return array('success'=>false,'message'=>__('Failed to save preset.', 'wsh-wcbpm'));
		}

		return array('success'=>true,'message'=>__('Preset saved.', 'wsh-wcbpm'), 'id'=>(int)$wpdb->insert_id);
	}

	public static function delete_preset( int $id ) : array {
		if ( ! self::is_pro_active() ) {
			return array('success'=>false,'message'=>__('PRO feature. Activate your license.', 'wsh-wcbpm'));
		}

		if ( $id <= 0 ) {
			return array('success'=>false,'message'=>__('Invalid preset.', 'wsh-wcbpm'));
		}

		global $wpdb;
		$table = self::table_name();

		$deleted = $wpdb->delete($table, array('id'=>$id), array('%d'));
		if ( false === $deleted ) {
			return array('success'=>false,'message'=>__('Delete failed.', 'wsh-wcbpm'));
		}

		return array('success'=>true,'message'=>__('Preset deleted.', 'wsh-wcbpm'));
	}
}
