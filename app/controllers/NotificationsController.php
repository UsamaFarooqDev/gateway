<?php
require_once 'app/models/AlertsModel.php';

class NotificationsController {
    private string $campaignsFile;
    private string $integrationsFile;

    public function __construct(private SupabaseDB $db) {
        $this->campaignsFile    = __DIR__ . '/../../config/push_campaigns.json';
        $this->integrationsFile = __DIR__ . '/../../config/integrations.json';
    }

    public function index(): void {
        // JSON: alert counts for the notification bell dropdown
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_alerts') {
            header('Content-Type: application/json');
            echo json_encode((new AlertsModel($this->db))->getAllAlerts());
            exit;
        }

        // JSON: search users for specific targeting
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search_users') {
            header('Content-Type: application/json');
            $this->handleSearchUsers();
            return;
        }

        // JSON: send push campaign
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_push') {
            $this->handleSendPush();
            return;
        }

        $tab  = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'push');
        $tabs = ['push' => 'Push Campaigns', 'sms' => 'SMS & Email Broadcast', 'alerts' => 'System Alerts', 'scheduled' => 'Scheduled Announcements'];
        if (!isset($tabs[$tab])) $tab = 'push';

        $alerts    = null;
        $campaigns = [];

        if ($tab === 'alerts') {
            $alerts = (new AlertsModel($this->db))->getAllAlerts();
        }
        if ($tab === 'push') {
            $campaigns = $this->loadCampaigns();
        }

        $currentPage = 'notifications';
        $pageTitle   = 'Notifications & Alerts';
        $pageCrumbs  = ['Notifications & Alerts'];

        require_once 'includes/header.php';
        require_once 'app/views/notifications/index.php';
        require_once 'includes/footer.php';
    }

    private function getFcmAccessToken(string $saJson): ?string {
        $sa = json_decode($saJson, true);
        if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) return null;
        $now    = time();
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claims = rtrim(strtr(base64_encode(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ])), '+/', '-_'), '=');
        $sig = '';
        if (!openssl_sign("{$header}.{$claims}", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) return null;
        $sig = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        $ch  = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => "{$header}.{$claims}.{$sig}",
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res = json_decode(curl_exec($ch), true);
        return $res['access_token'] ?? null;
    }

    private function handleSearchUsers(): void {
        $q    = trim($_GET['q'] ?? '');
        $type = ($_GET['type'] ?? 'driver') === 'passenger' ? 'passenger' : 'driver';

        if (strlen($q) < 2) { echo json_encode([]); exit; }

        // Strip PostgREST-special characters to prevent filter injection
        $qSafe = str_replace(['(', ')', ',', '.', '*'], '', $q);

        if ($type === 'driver') {
            $rows = $this->db->select('drivers', [
                'select'     => 'id,full_name,email,phone,fcm_token',
                'or'         => "(full_name.ilike.*{$qSafe}*,email.ilike.*{$qSafe}*,phone.ilike.*{$qSafe}*)",
                'deleted_at' => 'is.null',
                'limit'      => 8,
            ]);
            $results = array_map(fn($r) => [
                'id'        => $r['id'],
                'name'      => $r['full_name'] ?? '',
                'email'     => $r['email'] ?? '',
                'phone'     => $r['phone'] ?? '',
                'has_token' => !empty($r['fcm_token']),
            ], $rows);
        } else {
            $rows = $this->db->select('passengers', [
                'select'     => 'id,name,email,phone,fcm_token',
                'or'         => "(name.ilike.*{$qSafe}*,email.ilike.*{$qSafe}*,phone.ilike.*{$qSafe}*)",
                'deleted_at' => 'is.null',
                'limit'      => 8,
            ]);
            $results = array_map(fn($r) => [
                'id'        => $r['id'],
                'name'      => $r['name'] ?? '',
                'email'     => $r['email'] ?? '',
                'phone'     => $r['phone'] ?? '',
                'has_token' => !empty($r['fcm_token']),
            ], $rows);
        }

        echo json_encode($results);
        exit;
    }

    private function handleSendPush(): void {
        header('Content-Type: application/json');

        $title    = trim($_POST['title']    ?? '');
        $message  = trim($_POST['message']  ?? '');
        $audience = $_POST['audience'] ?? 'all';
        $targetId = trim($_POST['target_id']   ?? '');
        $isSpecific = in_array($audience, ['specific_driver', 'specific_passenger'], true);

        if (empty($title) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Title and message are required.']);
            exit;
        }

        if ($isSpecific && empty($targetId)) {
            echo json_encode(['success' => false, 'message' => 'Please select a specific user.']);
            exit;
        }

        $campaign = [
            'id'       => uniqid('push_', true),
            'title'    => $title,
            'message'  => $message,
            'audience' => $audience,
            'sent_at'  => date('c'),
            'fcm_sent' => false,
        ];

        // For specific targets: always resolve name + FCM token (regardless of server key)
        $fcmToken = '';
        if ($isSpecific) {
            $table   = ($audience === 'specific_driver') ? 'drivers' : 'passengers';
            $nameCol = ($audience === 'specific_driver') ? 'full_name' : 'name';
            $userRow = $this->db->select($table, [
                'select' => "id,{$nameCol},fcm_token",
                'id'     => "eq.{$targetId}",
                'limit'  => 1,
            ]);
            $fcmToken = $userRow[0]['fcm_token'] ?? '';
            $campaign['target_id']   = $targetId;
            $campaign['target_name'] = $userRow[0][$nameCol] ?? '';
        }

        // Attempt FCM V1 delivery if service account is configured
        $saJson    = '';
        $projectId = '';
        if (file_exists($this->integrationsFile)) {
            $int       = json_decode(file_get_contents($this->integrationsFile), true);
            $saJson    = $int['firebase']['service_account_json'] ?? '';
            $projectId = $int['firebase']['project_id'] ?? '';
        }

        if (!empty($saJson) && !empty($projectId)) {
            $bearerToken = $this->getFcmAccessToken($saJson);
            if ($bearerToken) {
                // Platform config ensures reliable delivery on both iOS (APNS) and Android
                $platformCfg = [
                    'apns'    => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['sound' => 'default', 'badge' => 1]],
                    ],
                    'android' => ['priority' => 'high'],
                ];

                if ($isSpecific && !empty($fcmToken)) {
                    $fcmMsg = array_merge([
                        'token'        => $fcmToken,
                        'notification' => ['title' => $title, 'body' => $message],
                        'data'         => ['click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
                    ], $platformCfg);
                    $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$bearerToken}", 'Content-Type: application/json'],
                        CURLOPT_POSTFIELDS     => json_encode(['message' => $fcmMsg]),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 10,
                    ]);
                    $fcmRes   = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $decoded  = json_decode($fcmRes, true);
                    $campaign['fcm_sent'] = ($httpCode === 200 && empty($decoded['error']));
                    if (!$campaign['fcm_sent']) {
                        $campaign['fcm_error'] = $decoded['error']['message'] ?? "HTTP {$httpCode}";
                    }
                } else {
                    // Token-based group send — FCM topics require app-side subscribeToTopic() which is not guaranteed
                    $tokens = $this->fetchGroupTokens($audience);
                    if (!empty($tokens)) {
                        $result = $this->sendFcmBatch($tokens, $bearerToken, $projectId, $title, $message, $platformCfg);
                        $campaign['fcm_sent']       = $result['sent'] > 0;
                        $campaign['fcm_sent_count'] = $result['sent'];
                        if ($result['failed'] > 0) {
                            $campaign['fcm_error'] = "{$result['failed']} token(s) failed to deliver.";
                        }
                    } else {
                        $campaign['fcm_sent']  = false;
                        $campaign['fcm_error'] = 'No registered devices found for this audience.';
                    }
                }
            } else {
                $campaign['fcm_error'] = 'Failed to obtain FCM access token — check service account JSON in Integrations.';
            }
        }

        // Persist campaign
        $campaigns = $this->loadCampaigns();
        array_unshift($campaigns, $campaign);
        if (count($campaigns) > 100) $campaigns = array_slice($campaigns, 0, 100);
        file_put_contents($this->campaignsFile, json_encode($campaigns, JSON_PRETTY_PRINT));

        if ($isSpecific && empty($fcmToken)) {
            $msg = 'Campaign saved. This user has no FCM token registered — notification not delivered.';
        } elseif ($campaign['fcm_sent']) {
            $count = $campaign['fcm_sent_count'] ?? null;
            $msg   = $count !== null
                ? "Notification sent to {$count} device(s) via Firebase FCM."
                : 'Notification sent via Firebase FCM.';
        } else {
            $err = $campaign['fcm_error'] ?? '';
            $msg = empty($saJson)
                ? 'Campaign saved. Add Firebase service account JSON in Integrations to enable FCM delivery.'
                : 'Campaign saved. FCM delivery failed' . ($err ? ': ' . $err : '.') . '';
        }

        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    }

    private function fetchGroupTokens(string $audience): array {
        $base = ['select' => 'fcm_token', 'fcm_token' => 'not.is.null', 'deleted_at' => 'is.null'];

        $rows = match($audience) {
            'active_drivers'  => $this->db->select('drivers',    $base + ['status' => 'eq.approved']),
            'pending_drivers' => $this->db->select('drivers',    $base + ['status' => 'eq.pending']),
            'all_drivers'     => $this->db->select('drivers',    $base),
            'all_passengers'  => $this->db->select('passengers', $base),
            default           => array_merge(
                $this->db->select('drivers',    $base),
                $this->db->select('passengers', $base)
            ),
        };

        return array_values(array_unique(array_filter(array_column($rows, 'fcm_token'))));
    }

    private function sendFcmBatch(
        array $tokens,
        string $bearerToken,
        string $projectId,
        string $title,
        string $message,
        array $platformCfg
    ): array {
        $url     = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $headers = ["Authorization: Bearer {$bearerToken}", 'Content-Type: application/json'];
        $sent    = 0;
        $failed  = 0;

        foreach (array_chunk($tokens, 50) as $chunk) {
            $mh      = curl_multi_init();
            $handles = [];

            foreach ($chunk as $token) {
                $body = json_encode(['message' => array_merge([
                    'token'        => $token,
                    'notification' => ['title' => $title, 'body' => $message],
                    'data'         => ['click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
                ], $platformCfg)]);

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => $body,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                if ($running) curl_multi_select($mh);
            } while ($running > 0);

            foreach ($handles as $ch) {
                curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 ? $sent++ : $failed++;
                curl_multi_remove_handle($mh, $ch);
            }

            curl_multi_close($mh);
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private function loadCampaigns(): array {
        if (!file_exists($this->campaignsFile)) return [];
        return json_decode(file_get_contents($this->campaignsFile), true) ?? [];
    }
}
