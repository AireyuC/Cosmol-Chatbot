<?php

namespace App\Core;

class RateLimiter {
    private static $maxRequests = 30;
    private static $windowSeconds = 60;

    public static function check(): void {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $safeIp = preg_replace('/[^a-zA-Z0-9\._\-]/', '_', $ip);
        $file = sys_get_temp_dir() . "/rl_{$safeIp}.json";

        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
        $now = time();

        if (!$data || ($now - $data['window_start']) >= self::$windowSeconds) {
            $data = ['window_start' => $now, 'count' => 0];
        }

        $data['count']++;

        if ($data['count'] > self::$maxRequests) {
            Logger::info('IP Bloqueada por exceso de peticiones', [
                'ip'    => $ip,
                'limit' => self::$maxRequests,
            ]);

            http_response_code(429);
            header('Retry-After: ' . self::$windowSeconds);
            echo json_encode([
                'success' => false,
                'message' => 'Demasiadas peticiones. Intenta en 60 segundos.',
                'data' => null
            ]);
            exit;
        }

        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
