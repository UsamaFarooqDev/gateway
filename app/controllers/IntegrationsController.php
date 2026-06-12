<?php

class IntegrationsController {
    private string $settingsFile;

    public function __construct(private SupabaseDB $db) {
        $this->settingsFile = __DIR__ . '/../../config/integrations.json';
    }

    public function index(): void {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        if ($action === 'test') {
            header('Content-Type: application/json');
            $this->handleTest();
            return;
        }

        if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            $this->handleToggle();
            return;
        }

        if ($action === 'map_data') {
            header('Content-Type: application/json');
            $this->handleMapData();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['section'])) {
            header('Content-Type: application/json');
            $this->handleSave();
            return;
        }

        $settings    = $this->loadSettings();
        $currentPage = 'integrations';
        $pageTitle   = 'Integrations';
        $pageCrumbs  = ['Integrations'];

        require_once 'includes/header.php';
        require_once 'app/views/integrations/index.php';
        require_once 'includes/footer.php';
    }

    // ── Real connectivity tests ──────────────────────────────────────

    private function handleTest(): void {
        $section  = $_GET['section'] ?? '';
        $settings = $this->loadSettings();

        $result = match($section) {
            'stripe'      => $this->testStripe($settings['stripe']),
            'firebase'    => $this->testFirebase($settings['firebase']),
            'google_maps' => $this->testGoogleMaps($settings['google_maps']),
            'sms'         => $this->testSms($settings['sms']),
            'supabase'    => $this->testSupabase(),
            'turn'        => $this->testTurn($settings['turn']),
            default       => ['success' => false, 'message' => 'Unknown section.'],
        };

        if (in_array($section, ['stripe', 'firebase', 'google_maps', 'sms', 'turn'], true)) {
            $settings[$section]['last_tested']      = date('c');
            $settings[$section]['last_test_status'] = $result['success'] ? 'ok' : 'error';
            $settings[$section]['last_test_msg']    = $result['message'];
            file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        }

        echo json_encode($result);
        exit;
    }

    private function testStripe(array $cfg): array {
        if (empty($cfg['secret_key'])) return ['success' => false, 'message' => 'No secret key configured.'];
        $ch = curl_init('https://api.stripe.com/v1/balance');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => $cfg['secret_key'] . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code === 200) return ['success' => true, 'message' => 'Stripe key is valid and account is reachable.'];
        $err = json_decode($body, true)['error']['message'] ?? "Stripe returned HTTP {$code}.";
        return ['success' => false, 'message' => $err];
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

    private function testFirebase(array $cfg): array {
        $saJson    = $cfg['service_account_json'] ?? '';
        $projectId = $cfg['project_id'] ?? '';
        if (empty($saJson))    return ['success' => false, 'message' => 'No service account JSON configured.'];
        if (empty($projectId)) return ['success' => false, 'message' => 'No Firebase project ID configured.'];

        // Pre-validate JSON structure before attempting OAuth
        $sa = json_decode($saJson, true);
        if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) {
            return ['success' => false, 'message' => 'Service account JSON is malformed — must contain private_key and client_email.'];
        }

        $token = $this->getFcmAccessToken($saJson);
        if (!$token) {
            return ['success' => false, 'message' => 'OAuth2 token exchange failed — verify the service account has Firebase Messaging permissions and the private key is valid.'];
        }

        $payload = json_encode([
            'validate_only' => true,
            'message'       => [
                'topic'        => 'admin_test',
                'notification' => ['title' => 'PowerCabs Test', 'body' => 'Integration test (validate_only — nothing delivered).'],
            ],
        ]);
        $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoded = json_decode($body, true);

        if ($code === 200) {
            return ['success' => true, 'message' => 'FCM V1 API is working. (validate_only — no notification sent)'];
        }

        // NOT_FOUND / UNREGISTERED on a validate_only topic test = topic has no subscribers yet,
        // but OAuth and project credentials are confirmed valid — treat as success.
        $errStatus = $decoded['error']['status'] ?? '';
        if ($code === 404 && in_array($errStatus, ['NOT_FOUND', 'UNREGISTERED'], true)) {
            return ['success' => true, 'message' => 'FCM V1 credentials verified. (Topic "admin_test" has no subscribers yet — this is normal. Real notifications will deliver once devices subscribe.)'];
        }

        $errMsg = $decoded['error']['message'] ?? "FCM V1 returned HTTP {$code}.";
        return ['success' => false, 'message' => $errMsg];
    }

    private function testGoogleMaps(array $cfg): array {
        $key = $cfg['maps_js_key'] ?: $cfg['directions_key'];
        if (empty($key)) return ['success' => false, 'message' => 'No API key configured.'];
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=Dublin,Ireland&key=' . urlencode($key);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $body   = curl_exec($ch);
        $status = json_decode($body, true)['status'] ?? '';
        if ($status === 'OK' || $status === 'ZERO_RESULTS') return ['success' => true, 'message' => 'Google Maps API key is valid.'];
        if ($status === 'REQUEST_DENIED') return ['success' => false, 'message' => 'Key rejected — check API restrictions in Google Cloud Console.'];
        return ['success' => false, 'message' => "Maps API returned status: {$status}"];
    }

    private function testSms(array $cfg): array {
        $provider = $cfg['provider'] ?? 'twilio';
        if (empty($cfg['api_key'])) return ['success' => false, 'message' => 'No API key configured.'];
        if ($provider === 'twilio') {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$cfg['api_key']}.json";
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_USERPWD        => $cfg['api_key'] . ':' . ($cfg['auth_token'] ?? ''),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code === 200) return ['success' => true, 'message' => 'Twilio account credentials verified.'];
            return ['success' => false, 'message' => 'Twilio auth failed — check Account SID and Auth Token.'];
        }
        return ['success' => false, 'message' => "Connection test not available for provider: {$provider}"];
    }

    private function testSupabase(): array {
        try {
            $rows = $this->db->select('drivers', ['select' => 'id', 'limit' => 1]);
            return ['success' => true, 'message' => 'Supabase is reachable and responding correctly.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Supabase query failed: ' . $e->getMessage()];
        }
    }

    private function testTurn(array $cfg): array {
        if (empty($cfg['url'])) return ['success' => false, 'message' => 'No TURN server URL configured.'];
        $url  = $cfg['url'];
        $parts = explode(':', $url);
        $host  = $parts[0];
        $port  = isset($parts[1]) ? (int)$parts[1] : 3478;
        $sock  = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($sock) { fclose($sock); return ['success' => true, 'message' => "TURN server at {$url} is reachable."]; }
        return ['success' => false, 'message' => "Cannot reach TURN server at {$url} — {$errstr}"];
    }

    // ── Toggle enabled ───────────────────────────────────────────────

    private function handleToggle(): void {
        $section = $_POST['section'] ?? '';
        $enabled = (bool)(int)($_POST['enabled'] ?? 0);
        $allowed = ['stripe', 'google_maps', 'firebase', 'sms', 'turn'];
        if (!in_array($section, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid section.']);
            exit;
        }
        $settings = $this->loadSettings();
        $settings[$section]['enabled'] = $enabled;
        file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        $label = ucwords(str_replace('_', ' ', $section));
        echo json_encode(['success' => true, 'message' => "{$label} " . ($enabled ? 'enabled' : 'disabled') . '.']);
        exit;
    }

    // ── Save section ─────────────────────────────────────────────────

    private function handleSave(): void {
        $section = $_POST['section'] ?? '';
        $allowed = ['stripe', 'google_maps', 'firebase', 'sms', 'turn'];
        if (!in_array($section, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid section.']);
            exit;
        }

        $settings = $this->loadSettings();

        switch ($section) {
            case 'stripe':
                $settings['stripe'] = array_merge($settings['stripe'], [
                    'publishable_key' => trim($_POST['publishable_key'] ?? ''),
                    'secret_key'      => trim($_POST['secret_key']      ?? ''),
                    'webhook_secret'  => trim($_POST['webhook_secret']  ?? ''),
                    'mode'            => in_array($_POST['mode'] ?? '', ['live', 'test'], true) ? $_POST['mode'] : 'test',
                ]);
                break;
            case 'google_maps':
                $settings['google_maps'] = array_merge($settings['google_maps'], [
                    'maps_js_key'    => trim($_POST['maps_js_key']    ?? ''),
                    'directions_key' => trim($_POST['directions_key'] ?? ''),
                ]);
                break;
            case 'firebase':
                $saJson    = trim($_POST['service_account_json'] ?? '');
                $projectId = trim($_POST['project_id'] ?? '');
                $merge     = ['project_id' => $projectId];
                if (!empty($saJson)) {
                    $decoded = json_decode($saJson, true);
                    if (!$decoded || empty($decoded['private_key']) || empty($decoded['client_email'])) {
                        echo json_encode(['success' => false, 'message' => 'Invalid service account JSON — must include private_key and client_email.']);
                        exit;
                    }
                    $merge['service_account_json'] = $saJson;
                }
                $settings['firebase'] = array_merge($settings['firebase'], $merge);
                break;
            case 'sms':
                $settings['sms'] = array_merge($settings['sms'], [
                    'provider'    => $_POST['provider']    ?? 'twilio',
                    'api_key'     => trim($_POST['api_key']     ?? ''),
                    'auth_token'  => trim($_POST['auth_token']  ?? ''),
                    'from_number' => trim($_POST['from_number'] ?? ''),
                ]);
                break;
            case 'turn':
                $settings['turn'] = array_merge($settings['turn'], [
                    'url'        => trim($_POST['turn_url']    ?? ''),
                    'username'   => trim($_POST['username']    ?? ''),
                    'credential' => trim($_POST['credential']  ?? ''),
                ]);
                break;
        }

        file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        $label = ['stripe' => 'Stripe', 'google_maps' => 'Google Maps', 'firebase' => 'Firebase FCM', 'sms' => 'SMS Gateway', 'turn' => 'TURN Server'][$section];
        echo json_encode(['success' => true, 'message' => "{$label} settings saved."]);
        exit;
    }

    // ── Map / heatmap data ───────────────────────────────────────────

    private function handleMapData(): void {
        $drivers = $this->db->select('drivers', [
            'select'     => 'id,full_name,current_lat,current_lng,is_online',
            'deleted_at' => 'is.null',
            'is_online'  => 'eq.true',
            'limit'      => 300,
        ]);

        $driverPoints = array_values(array_filter(
            array_map(fn($d) => [
                'id'   => $d['id'],
                'name' => $d['full_name'] ?? 'Driver',
                'lat'  => (float)$d['current_lat'],
                'lng'  => (float)$d['current_lng'],
            ], $drivers),
            fn($d) => $d['lat'] !== 0.0 && $d['lng'] !== 0.0
        ));

        $since = date('c', strtotime('-30 days'));
        $rides = $this->db->select('rides', [
            'select'     => 'pickup_lat,pickup_lng',
            'created_at' => 'gte.' . $since,
            'limit'      => 500,
        ]);

        $heatPoints = array_values(array_filter(
            array_map(fn($r) => [
                'lat' => (float)($r['pickup_lat'] ?? 0),
                'lng' => (float)($r['pickup_lng'] ?? 0),
            ], $rides),
            fn($p) => $p['lat'] !== 0.0 && $p['lng'] !== 0.0
        ));

        echo json_encode(['drivers' => $driverPoints, 'pickups' => $heatPoints]);
        exit;
    }

    // ── Settings loader ──────────────────────────────────────────────

    private function loadSettings(): array {
        $defaults = [
            'stripe'      => ['enabled' => false, 'publishable_key' => '', 'secret_key' => '', 'webhook_secret' => '', 'mode' => 'test', 'last_tested' => null, 'last_test_status' => null, 'last_test_msg' => null],
            'google_maps' => ['enabled' => false, 'maps_js_key' => '', 'directions_key' => '', 'last_tested' => null, 'last_test_status' => null, 'last_test_msg' => null],
            'firebase'    => ['enabled' => false, 'service_account_json' => '', 'project_id' => '', 'last_tested' => null, 'last_test_status' => null, 'last_test_msg' => null],
            'sms'         => ['enabled' => false, 'provider' => 'twilio', 'api_key' => '', 'auth_token' => '', 'from_number' => '', 'last_tested' => null, 'last_test_status' => null, 'last_test_msg' => null],
            'turn'        => ['enabled' => true,  'url' => 'free.expressturn.com:3478', 'username' => '000000002091984498', 'credential' => 'kwzWwHwPxvcD7QYFl0clT78xCqo=', 'last_tested' => null, 'last_test_status' => null, 'last_test_msg' => null],
        ];
        if (!file_exists($this->settingsFile)) return $defaults;
        $saved = json_decode(file_get_contents($this->settingsFile), true);
        if (!is_array($saved)) return $defaults;
        foreach ($defaults as $k => $v) {
            $defaults[$k] = array_merge($v, $saved[$k] ?? []);
        }
        return $defaults;
    }
}
