<?php
if (!defined('SARASWATI_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'Library');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
?>
