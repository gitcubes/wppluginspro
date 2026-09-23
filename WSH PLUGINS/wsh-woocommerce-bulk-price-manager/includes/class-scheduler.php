<?php
if ( ! defined('ABSPATH') ) exit;

class WSH_WCBPM_Scheduler {

	const JOB_OPTION_PREFIX = 'wsh_wcbpm_sched_job_';

	public static function init() {
		// AJAX schedule
		add_action('wp_ajax_wsh_wcbpm_schedule', array(__CLASS__, 'ajax_schedule'));

		// Jobs UI (list / details / cancel / run now)
		add_action('wp_ajax_wsh_wcbpm_jobs_list', array(__CLASS__, 'ajax_jobs_list'));
		add_action('wp_ajax_wsh_wcbpm_job_details', array(__CLASS__, 'ajax_job_details'));
		add_action('wp_ajax_wsh_wcbpm_job_cancel', array(__CLASS__, 'ajax_job_cancel'));
		add_action('wp_ajax_wsh_wcbpm_job_run_now', array(__CLASS__, 'ajax_job_run_now'));
        add_action('wp_ajax_wsh_wcbpm_job_delete', array(__CLASS__, 'ajax_job_delete'));

		// Runner hook (Action Scheduler or WP-Cron will call this)
		add_action('wsh_wcbpm_run_scheduled', array(__CLASS__, 'run_scheduled_job'), 10, 1);
	}

	protected static function is_pro_active(): bool {
		return class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active();
	}

	protected static function require_caps_or_die() {
		if ( ! current_user_can('manage_woocommerce') ) {
			wp_send_json_error(array('message' => __("You don't have permission.", 'wsh-wcbpm')), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		if ( ! self::is_pro_active() ) {
			wp_send_json_error(array('message' => __('This is a PRO feature. Activate your license.', 'wsh-wcbpm')), 200);
		}

		if ( ! function_exists('wc_get_product') ) {
			wp_send_json_error(array('message' => __('WooCommerce is not active.', 'wsh-wcbpm')), 200);
		}
	}

	/* =========================
	 * AJAX: Create schedule
	 * ========================= */
	public static function ajax_schedule() {
        self::require_caps_or_die();

        $filters = (isset($_POST['filters']) && is_array($_POST['filters'])) ? (array) $_POST['filters'] : array();
        $update  = (isset($_POST['update'])  && is_array($_POST['update']))  ? (array) $_POST['update']  : array();

        $mode         = isset($_POST['mode']) ? sanitize_text_field(wp_unslash((string) $_POST['mode'])) : 'now'; // now|later
        $run_at_local = isset($_POST['run_at']) ? sanitize_text_field(wp_unslash((string) $_POST['run_at'])) : ''; // "YYYY-MM-DDTHH:MM"
        $batch_size   = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 200;
        $batch_size   = max(50, min(2000, $batch_size));

        // normalize mode
        if (!in_array($mode, array('now','later'), true)) {
            $mode = 'now';
        }

        // Determine run timestamp
        $ts = time();
        if ($mode === 'later') {
            if ($run_at_local === '') {
                wp_send_json_error(array('message' => __('Please select date and time.', 'wsh-wcbpm')), 200);
            }

            try {
                $tz = wp_timezone();
                $dt = new DateTime($run_at_local, $tz);
                $ts = $dt->getTimestamp();
            } catch (Exception $e) {
                wp_send_json_error(array('message' => __('Invalid date/time format.', 'wsh-wcbpm')), 200);
            }

            if ($ts < time() + 60) {
                wp_send_json_error(array('message' => __('Scheduled time must be in the future (at least 1 minute).', 'wsh-wcbpm')), 200);
            }
        } else {
            // allow "now" to be immediate, but avoid edge-case with ActionScheduler requiring future
            $ts = max(time() + 1, (int) $ts);
        }

        // Optional: frontend can send target_count from dry-run
        $target_count = isset($_POST['target_count']) ? (int) $_POST['target_count'] : 0;
        if ($target_count < 0) $target_count = 0;

        // If not provided, calculate targets on server for correctness + to avoid "0 targets" jobs
        if ($target_count === 0 && class_exists('WSH_WCBPM_Filter_Engine')) {
            $targets = WSH_WCBPM_Filter_Engine::find_targets($filters);

            $count = 0;
            if (is_array($targets)) {
                $count += !empty($targets['products']) ? count((array)$targets['products']) : 0;
                $count += !empty($targets['variations']) ? count((array)$targets['variations']) : 0;
            }
            $target_count = (int) $count;
        }

        if ($target_count <= 0) {
            wp_send_json_error(array(
                'message' => __('No products match the selected filters. Please change filters and try again.', 'wsh-wcbpm')
            ), 200);
        }

        // Build summaries (for UX)
        $summary_filters = self::build_filters_summary($filters);
        $summary_update  = self::build_update_summary($filters, $update);

        $title = sprintf(
            /* translators: 1: update summary, 2: target count */
            __('Bulk price update: %1$s (%2$d)', 'wsh-wcbpm'),
            $summary_update,
            (int) $target_count
        );

        /**
         * ✅ DUPLICATE PREVENTION (server-side idempotency)
         * If user double-clicks or browser retries request, do NOT create a second job.
         */
        $fingerprint = md5(wp_json_encode(array(
            'user'    => get_current_user_id(),
            'run_at'  => (int) $ts,
            'batch'   => (int) $batch_size,
            'filters' => $filters,
            'update'  => $update,
        )));
        $lock_key = 'wsh_wcbpm_sched_lock_' . $fingerprint;

        // hard lock for 10s
        if (get_transient($lock_key)) {
            // Try to find an existing scheduled job with same fingerprint and return it
            $existing = self::find_job_by_fingerprint($fingerprint);
            if ($existing && !empty($existing['job_id'])) {
                wp_send_json_success(array(
                    'job_id'         => (string) $existing['job_id'],
                    'run_at'         => (int) ($existing['run_at'] ?? $ts),
                    'title'          => (string) ($existing['title'] ?? $title),
                    'summary_filters'=> (string) ($existing['summary_filters'] ?? $summary_filters),
                    'summary_update' => (string) ($existing['summary_update'] ?? $summary_update),
                    'message'        => __('Job already scheduled (duplicate prevented).', 'wsh-wcbpm'),
                ));
            }

            wp_send_json_error(array(
                'message' => __('This job was already scheduled (duplicate prevented).', 'wsh-wcbpm')
            ), 200);
        }
        set_transient($lock_key, 1, 10);

        // Create job_id
        $job_id = wp_generate_password(18, false, false) . '_' . time();
        $key    = self::JOB_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $job_id);

        $payload = array(
            'job_id'          => $job_id,
            'fingerprint'     => $fingerprint,

            'title'           => $title,
            'summary_filters' => $summary_filters,
            'summary_update'  => $summary_update,
            'target_count'    => (int) $target_count,

            'created_at'      => time(),
            'created_by'      => get_current_user_id(),
            'run_at'          => (int) $ts,

            'status'          => 'scheduled', // scheduled|running|done|failed|canceled
            'filters'         => $filters,
            'update'          => $update,
            'batch_size'      => (int) $batch_size,

            'runner'          => array(), // filled by schedule_runner
            'last_error'      => '',
            'result'          => array(),
        );

        // ✅ Schedule first; only persist if scheduling succeeded
        $scheduled = self::schedule_runner((int)$ts, (string)$job_id, $payload);

        if (!$scheduled) {
            delete_transient($lock_key);
            wp_send_json_error(array('message' => __('Unable to schedule the job. Please try again.', 'wsh-wcbpm')), 200);
        }

        update_option($key, $payload, false);

        $msg = ($mode === 'now')
            ? __('Job scheduled to run now.', 'wsh-wcbpm')
            : sprintf(__('Job scheduled for %s.', 'wsh-wcbpm'), wp_date('Y-m-d H:i', (int)$ts));

        wp_send_json_success(array(
            'job_id'          => $job_id,
            'run_at'          => (int) $ts,
            'title'           => $title,
            'summary_filters' => $summary_filters,
            'summary_update'  => $summary_update,
            'target_count'    => (int) $target_count,
            'message'         => $msg,
        ));
    }

    protected static function find_job_by_fingerprint(string $fingerprint) {
        global $wpdb;

        // Search options that look like our job options
        $like = self::JOB_OPTION_PREFIX . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                WHERE option_name LIKE %s
                ORDER BY option_id DESC
                LIMIT 50",
                $like
            ),
            ARRAY_A
        );

        if (!$rows) return null;

        foreach ($rows as $r) {
            $val = maybe_unserialize($r['option_value']);
            if (!is_array($val)) continue;

            if (!empty($val['fingerprint']) && (string)$val['fingerprint'] === $fingerprint) {
                // only return if still scheduled/running
                $st = isset($val['status']) ? (string)$val['status'] : '';
                if (in_array($st, array('scheduled','running'), true)) {
                    return $val;
                }
            }
        }

        return null;
    }


	/**
	 * Prefer Action Scheduler (WooCommerce). Fallback WP-Cron.
	 * Writes runner metadata into $payload.
	 */
	protected static function schedule_runner(int $timestamp, string $job_id, array &$payload): bool {
		// Prefer Action Scheduler (WooCommerce)
		if (function_exists('as_schedule_single_action')) {
			$action_id = as_schedule_single_action(
				$timestamp,
				'wsh_wcbpm_run_scheduled',
				array('job_id' => $job_id),
				'wsh-wcbpm'
			);

			$payload['runner'] = array(
				'type'      => 'action_scheduler',
				'action_id' => is_numeric($action_id) ? (int)$action_id : 0,
			);

			return true;
		}

		// Fallback: WP-Cron
		$already = wp_next_scheduled('wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
		if ( ! $already ) {
			$ok = wp_schedule_single_event($timestamp, 'wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
			$payload['runner'] = array(
				'type'      => 'wp_cron',
				'timestamp' => (int)$timestamp,
			);
			return (bool)$ok;
		}

		$payload['runner'] = array(
			'type'      => 'wp_cron',
			'timestamp' => (int)$already,
		);
		return true;
	}

	/* =========================
	 * AJAX: Jobs list
	 * ========================= */
	public static function ajax_jobs_list() {
		self::require_caps_or_die();

		global $wpdb;

		$like = $wpdb->esc_like(self::JOB_OPTION_PREFIX) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 200",
				$like
			),
			ARRAY_A
		);

		$jobs = array();

		foreach ($rows as $r) {
			$data = maybe_unserialize($r['option_value']);
			if (!is_array($data) || empty($data['job_id'])) continue;

			$jobs[] = array(
				'job_id'          => (string)($data['job_id'] ?? ''),
				'title'           => (string)($data['title'] ?? ''),
				'run_at'          => (int)($data['run_at'] ?? 0),
				'status'          => (string)($data['status'] ?? 'scheduled'),
				'created_at'      => (int)($data['created_at'] ?? 0),
				'created_by'      => (int)($data['created_by'] ?? 0),
				'target_count'    => (int)($data['target_count'] ?? 0),
				'summary_filters' => (string)($data['summary_filters'] ?? ''),
				'summary_update'  => (string)($data['summary_update'] ?? ''),
				'last_error'      => (string)($data['last_error'] ?? ''),
			);
		}

		wp_send_json_success(array('jobs' => $jobs));
	}

	/* =========================
	 * AJAX: Job details
	 * ========================= */
	public static function ajax_job_details() {
		self::require_caps_or_die();

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash((string)$_POST['job_id'])) : '';
		if ($job_id === '') {
			wp_send_json_error(array('message' => __('Missing job_id.', 'wsh-wcbpm')), 200);
		}

		$key = self::JOB_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $job_id);
		$job = get_option($key);

		if (!is_array($job)) {
			wp_send_json_error(array('message' => __('Job not found.', 'wsh-wcbpm')), 200);
		}

		// Keep details useful but not huge: store only summary result if present
		$result = is_array($job['result'] ?? null) ? (array)$job['result'] : array();
		$result_small = array();
		if (!empty($result)) {
			$result_small = array(
				'success' => !empty($result['success']),
				'message' => isset($result['message']) ? (string)$result['message'] : '',
				'summary' => isset($result['summary']) && is_array($result['summary']) ? $result['summary'] : array(),
			);
		}

		wp_send_json_success(array(
			'job' => array(
				'job_id'          => (string)($job['job_id'] ?? ''),
				'title'           => (string)($job['title'] ?? ''),
				'status'          => (string)($job['status'] ?? ''),
				'run_at'          => (int)($job['run_at'] ?? 0),
				'created_at'      => (int)($job['created_at'] ?? 0),
				'created_by'      => (int)($job['created_by'] ?? 0),
				'target_count'    => (int)($job['target_count'] ?? 0),
				'summary_filters' => (string)($job['summary_filters'] ?? ''),
				'summary_update'  => (string)($job['summary_update'] ?? ''),
				'batch_size'      => (int)($job['batch_size'] ?? 0),
				'runner'          => isset($job['runner']) && is_array($job['runner']) ? $job['runner'] : array(),
				'last_error'      => (string)($job['last_error'] ?? ''),
				'filters'         => isset($job['filters']) && is_array($job['filters']) ? $job['filters'] : array(),
				'update'          => isset($job['update']) && is_array($job['update']) ? $job['update'] : array(),
				'result'          => $result_small,
			)
		));
	}

	/* =========================
	 * AJAX: Cancel job
	 * ========================= */
	public static function ajax_job_cancel() {
		self::require_caps_or_die();

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash((string)$_POST['job_id'])) : '';
		if ($job_id === '') {
			wp_send_json_error(array('message' => __('Missing job_id.', 'wsh-wcbpm')), 200);
		}

		$key = self::JOB_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $job_id);
		$job = get_option($key);
		if (!is_array($job)) {
			wp_send_json_error(array('message' => __('Job not found.', 'wsh-wcbpm')), 200);
		}

		$status = (string)($job['status'] ?? '');
		if (in_array($status, array('running','done'), true)) {
			wp_send_json_error(array('message' => __('This job cannot be canceled (already running or finished).', 'wsh-wcbpm')), 200);
		}

		// Try unschedule via Action Scheduler first
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions('wsh_wcbpm_run_scheduled', array('job_id' => $job_id), 'wsh-wcbpm');
		} else {
			$ts = wp_next_scheduled('wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
			if ($ts) {
				wp_unschedule_event($ts, 'wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
			}
		}

		$job['status'] = 'canceled';
		update_option($key, $job, false);

		wp_send_json_success(array('message' => __('Job canceled.', 'wsh-wcbpm')));
	}

	/* =========================
	 * AJAX: Run job now
	 * (reschedules it for immediate execution)
	 * ========================= */
	public static function ajax_job_run_now() {
		self::require_caps_or_die();

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash((string)$_POST['job_id'])) : '';
		if ($job_id === '') {
			wp_send_json_error(array('message' => __('Missing job_id.', 'wsh-wcbpm')), 200);
		}

		$key = self::JOB_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $job_id);
		$job = get_option($key);
		if (!is_array($job)) {
			wp_send_json_error(array('message' => __('Job not found.', 'wsh-wcbpm')), 200);
		}

		$status = (string)($job['status'] ?? '');
		if (in_array($status, array('running','done'), true)) {
			wp_send_json_error(array('message' => __('This job is already running or finished.', 'wsh-wcbpm')), 200);
		}
		if ($status === 'canceled') {
			wp_send_json_error(array('message' => __('Canceled jobs cannot be run. Please schedule a new job.', 'wsh-wcbpm')), 200);
		}

		// Cancel old schedule first
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions('wsh_wcbpm_run_scheduled', array('job_id' => $job_id), 'wsh-wcbpm');
		} else {
			$ts_old = wp_next_scheduled('wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
			if ($ts_old) {
				wp_unschedule_event($ts_old, 'wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
			}
		}

		$job['run_at']     = time() + 5; // tiny delay
		$job['status']     = 'scheduled';
		$job['last_error'] = '';
		$job['runner']     = array();

		$ok = self::schedule_runner((int)$job['run_at'], $job_id, $job);

		update_option($key, $job, false);

		if (!$ok) {
			wp_send_json_error(array('message' => __('Unable to run job now. Please try again.', 'wsh-wcbpm')), 200);
		}

		wp_send_json_success(array(
			'message' => __('Job queued to run now.', 'wsh-wcbpm'),
			'run_at'  => (int)$job['run_at'],
		));
	}

	/* =========================
	 * Runner: execute scheduled job
	 * ========================= */
	public static function run_scheduled_job($args) {
		$job_id = '';
		if (is_string($args)) {
            $job_id = (string) $args;
        } elseif (is_array($args) && isset($args['job_id'])) {
            $job_id = (string) $args['job_id'];
        }

		$job_id = trim($job_id);
        if ($job_id === '') return;

		$key = self::JOB_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $job_id);
		$job = get_option($key);

		if (!is_array($job) || empty($job['filters']) || empty($job['update'])) {
			return;
		}

		// Avoid double-run
		$status = (string)($job['status'] ?? '');
		if (in_array($status, array('running','done','canceled'), true)) {
			return;
		}

		if (!class_exists('WSH_WCBPM_Dry_Run')) {
			$job['status'] = 'failed';
			$job['last_error'] = 'Dry run module is not loaded.';
			update_option($key, $job, false);
			return;
		}

		$job['status']     = 'running';
		$job['last_error'] = '';
		update_option($key, $job, false);

		try {
			// Current implementation: direct apply (later we can switch to batch/performance job)
			$result = WSH_WCBPM_Dry_Run::apply((array)$job['filters'], (array)$job['update']);

			$job['result'] = is_array($result) ? $result : array();
			$job['status'] = (!empty($result['success'])) ? 'done' : 'failed';

			if (empty($result['success'])) {
				$job['last_error'] = isset($result['message']) ? (string)$result['message'] : 'Apply failed.';
			}
		} catch (Exception $e) {
			$job['status'] = 'failed';
			$job['last_error'] = $e->getMessage();
		}

		update_option($key, $job, false);

		// Optional later: auto-clean jobs older than X days
	}

	/* =========================
	 * Human summaries (job clarity)
	 * ========================= */
	protected static function build_filters_summary(array $filters): string {
		$parts = array();

		$cats = isset($filters['categories']) ? (array)$filters['categories'] : array();
		$tags = isset($filters['tags']) ? (array)$filters['tags'] : array();

		if (!empty($cats)) $parts[] = 'cats:' . count($cats);
		if (!empty($tags)) $parts[] = 'tags:' . count($tags);

		$tax = isset($filters['attribute_tax']) ? (string)$filters['attribute_tax'] : '';

		// PRO: attribute_terms (multi)
		$terms = isset($filters['attribute_terms']) ? (array)$filters['attribute_terms'] : array();

		// Legacy FREE: attribute_term (single)
		$term_legacy = isset($filters['attribute_term']) ? (string)$filters['attribute_term'] : '';

		if ($tax) {
			if (!empty($terms)) {
				$show = array_slice($terms, 0, 6);
				$parts[] = $tax . '=' . implode(',', array_map('strval', $show)) . (count($terms) > 6 ? '…' : '');
			} elseif ($term_legacy !== '') {
				$parts[] = $tax . '=' . $term_legacy;
			}
		}

		$logic = isset($filters['logic']) ? strtolower((string)$filters['logic']) : '';
		$logic = ($logic === 'or') ? 'OR' : 'AND';
		$parts[] = 'logic:' . $logic;

		$inc = !empty($filters['include_variable_children']) ? 'vars:yes' : 'vars:no';
		$parts[] = $inc;

		return implode(' | ', $parts);
	}

	protected static function build_update_summary(array $filters, array $update): string {
		$scope  = isset($filters['price_scope']) ? (string)$filters['price_scope'] : 'both';
		$method = isset($update['method']) ? (string)$update['method'] : 'percent';
		$action = isset($update['action']) ? (string)$update['action'] : 'decrease';
		$val    = isset($update['value']) ? trim((string)$update['value']) : '';
		$round  = isset($update['rounding']) ? (string)$update['rounding'] : 'none';

		$op = ($action === 'increase') ? '+' : '-';
		$unit = ($method === 'percent') ? '%' : '';

		if ($val === '') $val = '0';

		return sprintf('%s%s%s scope:%s round:%s', $op, $val, $unit, $scope, $round);
	}

    public static function ajax_job_delete() {
        self::require_caps_or_die();

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash((string)$_POST['job_id'])) : '';
        if ($job_id === '') {
            wp_send_json_error(array('message' => __('Missing job_id.', 'wsh-wcbpm')), 200);
        }

        $key = self::JOB_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $job_id);
        $job = get_option($key);

        if (!is_array($job)) {
            wp_send_json_error(array('message' => __('Job not found.', 'wsh-wcbpm')), 200);
        }

        // 🔥 Unschedule runner FIRST
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(
                'wsh_wcbpm_run_scheduled',
                array('job_id' => $job_id),
                'wsh-wcbpm'
            );
        } else {
            $ts = wp_next_scheduled('wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
            if ($ts) {
                wp_unschedule_event($ts, 'wsh_wcbpm_run_scheduled', array('job_id' => $job_id));
            }
        }

        // 🔥 HARD DELETE
        delete_option($key);

        wp_send_json_success(array(
            'message' => __('Job permanently deleted.', 'wsh-wcbpm')
        ));
    }
}
