<?php
// Charger le fichier .env
if (file_exists(".env")) {
    $env = parse_ini_file(".env");
    define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
    define('DB_USER', $env['DB_USER'] ?? 'root');
    define('DB_PASS', $env['DB_PASS'] ?? '');
    define('DB_NAME', $env['DB_NAME'] ?? 'si_stock');
} else {
    // Fallback si .env absent
    define('DB_HOST', 'localhost');
    define('DB_USER', 'uriel');
    define('DB_PASS', 'r1ph12l5r32l');
    define('DB_NAME', 'si_stock');
}

// Clé secrète pour CSRF tokens
define('CSRF_TOKEN_NAME', '_csrf_token');
?>
