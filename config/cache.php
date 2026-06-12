<?php

/**
 * Simple file-based cache with TTL.
 * Stores entries as JSON in the system temp directory.
 * Cache files are shared across all requests (not per-session).
 */
class Cache {
    private static string $dir = '';

    private static function dir(): string {
        if (!self::$dir) {
            self::$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pc_cache' . DIRECTORY_SEPARATOR;
            if (!is_dir(self::$dir)) @mkdir(self::$dir, 0755, true);
        }
        return self::$dir;
    }

    public static function get(string $key): mixed {
        $file = self::dir() . md5($key) . '.json';
        if (!is_file($file)) return null;
        $raw = @file_get_contents($file);
        if (!$raw) return null;
        $data = json_decode($raw, true);
        if (!$data || ($data['exp'] ?? 0) < time()) {
            @unlink($file);
            return null;
        }
        return $data['val'];
    }

    public static function set(string $key, mixed $value, int $ttl = 30): void {
        @file_put_contents(
            self::dir() . md5($key) . '.json',
            json_encode(['exp' => time() + $ttl, 'val' => $value], JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    public static function forget(string $key): void {
        @unlink(self::dir() . md5($key) . '.json');
    }
}
