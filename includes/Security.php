<?php
// includes/Security.php

class Security {
    
    // CSRF Token Generation
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // CSRF Token Validation
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }

    // Input Sanitization
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map(['Security', 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    // Device/Browser Parsing (Simple Helper)
    public static function parseUserAgent($userAgent) {
        $browser = 'Unknown Browser';
        $os = 'Unknown OS';
        $device = 'Desktop';

        // OS
        if (preg_match('/windows nt 10/i', $userAgent)) $os = 'Windows 10/11';
        elseif (preg_match('/windows nt 6.3/i', $userAgent)) $os = 'Windows 8.1';
        elseif (preg_match('/windows nt 6.2/i', $userAgent)) $os = 'Windows 8';
        elseif (preg_match('/windows nt 6.1/i', $userAgent)) $os = 'Windows 7';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'Mac OS';
        elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';
        elseif (preg_match('/android/i', $userAgent)) $os = 'Android';
        elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) $os = 'iOS';

        // Browser
        if (preg_match('/edge/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/msie|trident/i', $userAgent)) $browser = 'Internet Explorer';

        // Device
        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $userAgent)) {
            $device = 'Mobile';
        }

        return ['browser' => $browser, 'os' => $os, 'device' => $device];
    }
}
?>
