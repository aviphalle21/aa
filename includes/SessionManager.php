<?php
// includes/SessionManager.php

class SessionManager {
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session cookie parameters
            $cookieParams = [
                'lifetime' => 0, // Until browser closes
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Only over HTTPS if available
                'httponly' => true, // JavaScript cannot access the cookie
                'samesite' => 'Strict' // Protect against cross-site request forgery
            ];
            session_set_cookie_params($cookieParams);
            
            session_start();
            
            // Send Security Headers
            self::sendSecurityHeaders();
        }
    }

    public static function sendSecurityHeaders() {
        if (!headers_sent()) {
            // Protect against Clickjacking
            header("X-Frame-Options: SAMEORIGIN");
            // Protect against MIME-type sniffing
            header("X-Content-Type-Options: nosniff");
            // Enable XSS protection in legacy browsers
            header("X-XSS-Protection: 1; mode=block");
            // Control referrer information
            header("Referrer-Policy: strict-origin-when-cross-origin");
            // Content Security Policy (Basic - allows inline styles/scripts used in this project but restricts external domains)
            // Adjust this if you use external CDNs like Google Fonts or Chart.js
            header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com https://fonts.gstatic.com https://ui-avatars.com; img-src 'self' data: https://ui-avatars.com https://api.qrserver.com;");
            
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                // HTTP Strict Transport Security
                header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
            }
        }
    }
}
?>
