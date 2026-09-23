<?php

// ===== includes/ApiClient.php =====
class BrevoApiClient {
    private $api_key;
    private $base = 'https://api.brevo.com/v3';

    public function __construct($api_key) { $this->api_key = trim($api_key); }

    private function request($method, $path, $body = null, $query = []) {
        if (!$this->api_key) throw new Exception('Brevo API key is missing (Settings).');
        $url = $this->base . $path;
        if (!empty($query)) $url = add_query_arg($query, $url);
        $args = [
            'method'  => $method,
            'headers' => [
                'accept' => 'application/json',
                'api-key' => $this->api_key,
                'content-type' => 'application/json'
            ],
            'timeout' => 30,
        ];
        if ($body !== null) $args['body'] = wp_json_encode($body);
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) throw new Exception($res->get_error_message());
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if ($code >= 400) {
            $msg = $json['message'] ?? ('HTTP ' . $code);
            throw new Exception('Brevo API error: ' . $msg);
        }
        return $json;
    }

    // Lists
    public function get_lists($limit = 50, $offset = 0) {
        return $this->request('GET', '/contacts/lists', null, compact('limit','offset'));
    }

    public function update_status($campaignId, $status) {
        // npr. 'suspended', 'archive'...
        return $this->request('PUT', "/emailCampaigns/{$campaignId}/status", [
            'status' => $status,
        ]);
    }

    public function get_email_campaigns($limit = 50, $offset = 0, $status = null) {
        $q = array_filter(compact('limit','offset','status'));
        return $this->request('GET', '/emailCampaigns', null, $q);
    }

    public function create_email_campaign($data) {
        return $this->request('POST', '/emailCampaigns', $data);
    }

    public function update_email_campaign($id, $data) {
        return $this->request('PUT', "/emailCampaigns/{$id}", $data);
    }

    public function send_test_email($id, $emails) {
        return $this->request('POST', "/emailCampaigns/{$id}/sendTest", ['emailTo' => array_values($emails)]);
    }

    public function schedule_campaign($id, $iso_datetime) {
        return $this->request('PUT', "/emailCampaigns/{$id}/schedule", ['scheduledAt' => $iso_datetime]);
    }

    public function send_campaign_now($campaignId) {
        return $this->request('POST', "/emailCampaigns/{$campaignId}/sendNow", []); // telo prazno ili {}
    }

    public function create_or_update_contact($email, $listIds = []) {
        $body = ['email' => $email, 'updateEnabled' => true];
        if (!empty($listIds)) $body['listIds'] = array_values(array_map('intval', $listIds));
        return $this->request('POST', '/contacts', $body);
    }
    
    public function create_or_update_contact_new($email, array $listIds = [], array $attributes = []) {
        $email = trim($email);
        if (!$email) throw new \Exception('Missing email');
        // 1) Pokušaj kreiranje
        $payload = [
            'email'          => $email,
            'listIds'        => array_values(array_unique(array_map('intval', $listIds))),
            'updateEnabled'  => true, // dozvoli update ako već postoji
        ];
        if (!empty($attributes)) $payload['attributes'] = $attributes;

        $res = $this->request('POST', '/v3/contacts', $payload);
        // Ako već postoji, Brevo vrati 400 sa kodom “duplicate_parameter” – tada uradi update:
        if (isset($res['code']) && $res['code'] === 'duplicate_parameter') {
            $identifier = rawurlencode($email);
            $payload = [];
            if (!empty($attributes)) $payload['attributes'] = $attributes;
            if (!empty($listIds))    $payload['listIds']   = array_values(array_unique(array_map('intval',$listIds)));
            return $this->request('PUT', "/v3/contacts/{$identifier}", $payload);
        }
        return $res;
    }

    public function send_double_optin($email, array $attributes, array $includeListIds, int $templateId, string $redirectionUrl) {
        $payload = [
            'email'           => $email,
            'includeListIds'  => array_values(array_unique(array_map('intval', $includeListIds))),
            'templateId'      => $templateId,
            'redirectionUrl'  => $redirectionUrl ?: home_url('/'),
        ];
        if (!empty($attributes)) $payload['attributes'] = $attributes;
        return $this->request('POST', '/v3/contacts/doubleOptinConfirmation', $payload);
    }

    public function add_contacts_to_list($listId, $emails) {
        $emails = array_values(array_filter($emails));
        if (empty($emails)) return ['success' => []];
        return $this->request('POST', "/contacts/lists/{$listId}/contacts/add", [
            'emails' => $emails
        ]);
    }

    public function delete_email_campaign($id) {
        $id = intval($id);
        return $this->request('DELETE', "/emailCampaigns/{$id}");
    }

    public function get_email_campaign($id) {
        $id = intval($id);
        return $this->request('GET', "/emailCampaigns/{$id}");
    }

    public function get_campaign_report($campaignId) {
        // GET /v3/emailCampaigns/{campaignId}
        return $this->request('GET', "/emailCampaigns/{$campaignId}");
    }
    
    public function send_transactional_email(array $payload) {
        $url = 'https://api.brevo.com/v3/smtp/email';

        $args = [
            'headers' => [
                'api-key'      => $this->api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
            'method'  => 'POST',
        ];

        $res  = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            throw new \Exception($res->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body_raw = wp_remote_retrieve_body($res);
        $body = json_decode($body_raw ?: '{}', true);

        // Brevo vrati 201 (sent) ili 202 (scheduled).
        if (!in_array($code, [201, 202], true)) {
            $msg = is_array($body) && !empty($body['message']) ? $body['message'] : ('HTTP ' . $code);
            throw new \Exception('Brevo API error: ' . $msg);
        }

        return $body ?: ['ok' => true];
    }

}