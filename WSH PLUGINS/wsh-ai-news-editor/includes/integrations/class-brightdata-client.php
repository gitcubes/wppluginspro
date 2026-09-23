<?php
if (! defined('ABSPATH')) {
    exit;
}

class WSH_AINE_BrightData_Client
{

    const API_BASE = 'https://api.brightdata.com/datasets/v3/';

    protected static $last_error = '';

    public static function is_configured(array $options = array()): bool
    {
        $options = ! empty($options) ? $options : get_option('wsh_aine_settings', array());

        return ! empty($options['brightdata_api_token']) && ! empty($options['brightdata_instagram_profile_dataset_id']);
    }

    public static function get_last_error(): string
    {
        return (string) self::$last_error;
    }

    protected static function set_error(string $message): void
    {
        self::$last_error = $message;
    }

    public static function trigger_dataset(array $rows, string $dataset_id, array $options = array()): array
    {
        $options = ! empty($options) ? $options : get_option('wsh_aine_settings', array());
        $token   = isset($options['brightdata_api_token']) ? trim((string) $options['brightdata_api_token']) : '';

        if ('' === $token || '' === $dataset_id) {
            self::set_error(__('Bright Data API token or dataset ID is missing.', 'wsh-ai-news-editor'));
            return array();
        }

        $url = self::API_BASE . 'trigger?dataset_id=' . rawurlencode($dataset_id) . '&include_errors=true';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(array_values($rows)),
            )
        );

        if (is_wp_error($response)) {
            self::set_error($response->get_error_message());
            return array();
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            self::set_error(sprintf('Bright Data trigger failed (%d): %s', $code, $body));
            return array();
        }

        if (! is_array($data) || empty($data['snapshot_id'])) {
            self::set_error(__('Bright Data did not return a snapshot ID.', 'wsh-ai-news-editor'));
            return array();
        }

        return $data;
    }

    public static function fetch_snapshot_items(string $snapshot_id, array $options = array(), int $max_attempts = 3, int $sleep_seconds = 2): array
    {
        $options = ! empty($options) ? $options : get_option('wsh_aine_settings', array());
        $token   = isset($options['brightdata_api_token']) ? trim((string) $options['brightdata_api_token']) : '';

        if ('' === $token || '' === $snapshot_id) {
            self::set_error(__('Bright Data token or snapshot ID is missing.', 'wsh-ai-news-editor'));
            return array();
        }

        $url = self::API_BASE . 'snapshot/' . rawurlencode($snapshot_id);

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 30,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ),
                )
            );

            if (is_wp_error($response)) {
                self::set_error($response->get_error_message());
                return array();
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code >= 200 && $code < 300 && is_array($data)) {
                if (isset($data[0]) || empty($data['status'])) {
                    return $data;
                }

                if (isset($data['status']) && in_array($data['status'], array('running', 'pending'), true)) {
                    if ($attempt < $max_attempts) {
                        sleep($sleep_seconds);
                        continue;
                    }
                }
            }

            self::set_error(sprintf('Bright Data snapshot fetch failed (%d): %s', $code, $body));
            return array();
        }

        self::set_error(__('Bright Data snapshot is still processing. Try refreshing again.', 'wsh-ai-news-editor'));
        return array();
    }

    public static function wait_until_ready(string $snapshot_id, array $options = array(), int $max_attempts = 8, int $sleep_seconds = 2): bool
    {
        $options = ! empty($options) ? $options : get_option('wsh_aine_settings', array());
        $token   = isset($options['brightdata_api_token']) ? trim((string) $options['brightdata_api_token']) : '';

        if ('' === $token || '' === $snapshot_id) {
            self::set_error(__('Bright Data token or snapshot ID is missing.', 'wsh-ai-news-editor'));
            return false;
        }

        $url = self::API_BASE . 'progress/' . rawurlencode($snapshot_id);

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 30,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ),
                )
            );

            if (is_wp_error($response)) {
                self::set_error($response->get_error_message());
                return false;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code >= 200 && $code < 300 && is_array($data)) {
                $status = isset($data['status']) ? (string) $data['status'] : '';

                if ('ready' === $status) {
                    return true;
                }

                if ('failed' === $status) {
                    self::set_error(__('Bright Data snapshot failed.', 'wsh-ai-news-editor'));
                    return false;
                }
            }

            sleep($sleep_seconds);
        }

        self::set_error(__('Bright Data snapshot is still not ready.', 'wsh-ai-news-editor'));
        return false;
    }
}
