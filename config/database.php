<?php
require_once __DIR__ . '/cache.php';

define('SUPABASE_URL',              'https://ijrnahatonxpuzwjtykd.supabase.co');
define('SUPABASE_ANON_KEY',         'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imlqcm5haGF0b254cHV6d2p0eWtkIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTU2NzMwMDYsImV4cCI6MjA3MTI0OTAwNn0.cTqgwDjRywsc-Gq8_bolSGT-rzQRr4GONrs6W8VXc8E');
define('SUPABASE_SERVICE_ROLE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imlqcm5haGF0b254cHV6d2p0eWtkIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1NTY3MzAwNiwiZXhwIjoyMDcxMjQ5MDA2fQ.Il9Ydbdt_phqJyN09FDg9Dqvb_vZOtLEAi7EIz80B3Y');

class SupabaseDB {
    private string $rest;
    private string $key;
    private bool $lastOk = true;

    public function __construct(bool $useServiceRole = true) {
        $this->rest = SUPABASE_URL . '/rest/v1';
        $this->key  = $useServiceRole ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
    }

    public function lastRequestOk(): bool {
        return $this->lastOk;
    }

    public function select(string $table, array $params = [], bool $withCount = false): array {
        $url = $this->rest . '/' . rawurlencode($table);
        $qs  = $this->buildQS($params);
        if ($qs) $url .= '?' . $qs;
        $extra = $withCount ? ['Prefer: count=exact'] : [];
        [$code, $headers, $body] = $this->curl('GET', $url, null, $extra);
        if ($code === 0 || $code >= 400) {
            $this->lastOk = false;
            error_log("Supabase select error {$code} [{$table}]: {$body}");
            return $withCount ? ['data' => [], 'count' => 0] : [];
        }
        $this->lastOk = true;
        $rows = json_decode($body, true) ?? [];
        if ($withCount) {
            preg_match('/content-range:\s*\S+\/(\d+|\*)/i', $headers, $m);
            $count = isset($m[1]) && is_numeric($m[1]) ? (int)$m[1] : count($rows);
            return ['data' => $rows, 'count' => $count];
        }
        return $rows;
    }

    public function update(string $table, array $data, array $filters): bool {
        $url = $this->rest . '/' . rawurlencode($table) . '?' . $this->buildQS($filters);
        [$code] = $this->curl('PATCH', $url, $data, ['Prefer: return=minimal']);
        return $code >= 200 && $code < 300;
    }

    public function insert(string $table, array $data): ?array {
        [$code, , $body] = $this->curl('POST', $this->rest . '/' . rawurlencode($table), $data, ['Prefer: return=representation']);
        if ($code >= 400) return null;
        $rows = json_decode($body, true);
        return is_array($rows) ? ($rows[0] ?? null) : null;
    }

    public function rpc(string $fn, array $params = []): mixed {
        [, , $body] = $this->curl('POST', $this->rest . '/rpc/' . $fn, $params);
        return json_decode($body, true);
    }

    public function rpcWithStatus(string $fn, array $params = []): array {
        [$code, , $body] = $this->curl('POST', $this->rest . '/rpc/' . $fn, $params);
        return ['code' => $code, 'data' => json_decode($body, true)];
    }

    /**
     * Execute multiple SELECT queries in parallel via curl_multi.
     * @param array $queries  Each item: ['table'=>string, 'params'=>array, 'withCount'=>bool]
     * @return array          Results in the same order as $queries.
     *                        withCount=true  → ['data'=>[], 'count'=>N]
     *                        withCount=false → []  (row array)
     */
    public function selectParallel(array $queries): array {
        if (empty($queries)) return [];

        $mh      = curl_multi_init();
        $handles = [];

        foreach ($queries as $i => $q) {
            $url = $this->rest . '/' . rawurlencode($q['table']);
            $qs  = $this->buildQS($q['params'] ?? []);
            if ($qs) $url .= '?' . $qs;

            $withCount    = $q['withCount'] ?? false;
            $extraHeaders = $withCount ? ['Prefer: count=exact'] : [];

            $ch = $this->buildHandle('GET', $url, null, $extraHeaders);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = ['ch' => $ch, 'withCount' => $withCount, 'table' => $q['table']];
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh);
        } while ($running > 0);

        $results = [];
        foreach ($handles as $i => $h) {
            $raw     = curl_multi_getcontent($h['ch']);
            $hdrSize = curl_getinfo($h['ch'], CURLINFO_HEADER_SIZE);
            $code    = curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
            $headers = substr($raw ?? '', 0, $hdrSize);
            $body    = substr($raw ?? '', $hdrSize);

            curl_multi_remove_handle($mh, $h['ch']);

            if ($code >= 400 || $raw === false) {
                error_log("Supabase parallel error {$code} [{$h['table']}]: " . substr($body, 0, 200));
                $results[$i] = $h['withCount'] ? ['data' => [], 'count' => 0] : [];
            } else {
                $rows = json_decode($body, true) ?? [];
                if ($h['withCount']) {
                    preg_match('/content-range:\s*\S+\/(\d+|\*)/i', $headers, $m);
                    $count       = isset($m[1]) && is_numeric($m[1]) ? (int)$m[1] : count($rows);
                    $results[$i] = ['data' => $rows, 'count' => $count];
                } else {
                    $results[$i] = $rows;
                }
            }
        }

        curl_multi_close($mh);
        ksort($results);
        return $results;
    }

    private function buildQS(array $params): string {
        $parts = [];
        foreach ($params as $key => $value) {
            foreach ((array)$value as $v) {
                $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$v);
            }
        }
        return implode('&', $parts);
    }

    private function buildHandle(string $method, string $url, ?array $body, array $extraHeaders = []): \CurlHandle {
        $headers = [
            'apikey: '               . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            ...$extraHeaders,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        return $ch;
    }

    public function delete(string $table, array $filters): bool {
        $url = $this->rest . '/' . rawurlencode($table) . '?' . $this->buildQS($filters);
        [$code] = $this->curl('DELETE', $url, null, ['Prefer: return=minimal']);
        return $code >= 200 && $code < 300;
    }

    public function deleteAuthUser(string $userId): bool {
        $url = SUPABASE_URL . '/auth/v1/admin/users/' . rawurlencode($userId);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'apikey: '               . SUPABASE_SERVICE_ROLE_KEY,
                'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code < 200 || $code >= 300) {
            error_log("Supabase auth delete error {$code} [{$userId}]: {$body}");
            return false;
        }
        return true;
    }

    public function uploadFile(string $bucket, string $path, string $content, string $mimeType): ?string {
        $url = SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'apikey: '               . $this->key,
                'Authorization: Bearer ' . $this->key,
                'Content-Type: '         . $mimeType,
                'x-upsert: true',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code < 200 || $code >= 300) {
            error_log("Supabase storage upload error {$code} [{$bucket}/{$path}]: {$body}");
            return null;
        }
        return SUPABASE_URL . '/storage/v1/object/public/' . $bucket . '/' . $path;
    }

    private function curl(string $method, string $url, ?array $body, array $extraHeaders = []): array {
        $ch      = $this->buildHandle($method, $url, $body, $extraHeaders);
        $raw     = curl_exec($ch);
        $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $err = curl_error($ch);
            error_log("cURL error ({$method} {$url}): {$err}");
            return [0, '', ''];
        }
        return [$code, substr($raw, 0, $hdrSize), substr($raw, $hdrSize)];
    }
}

function getDB(): SupabaseDB {
    static $instance = null;
    $instance ??= new SupabaseDB(true);
    return $instance;
}
