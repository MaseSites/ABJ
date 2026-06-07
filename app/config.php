<?php
// Basis-Konfiguration
define('BASE_PATH', dirname(dirname(__FILE__)));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public_html');
define('DATA_PATH', BASE_PATH . '/data');

// Database
define('DB_PATH', DATA_PATH . '/app.db');

// Sicherheit
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$invalid'); // Wird in DB gelesen

// Umgebung
define('IS_DEV', true);
define('BASE_URL', 'http://localhost:3000');

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Error Handling
if (IS_DEV) {
    ini_set('display_errors', 1);
    ini_set('error_reporting', E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Helpers
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function render($template, $data = []) {
    extract($data);
    if (!isset($settings)) {
        $settingModel = new Setting();
        $settings = $settingModel->all();
    }
    $file = APP_PATH . '/views/' . $template . '.php';
    if (!file_exists($file)) {
        die("Template nicht gefunden: $template");
    }
    include $file;
}

function view($template, $data = []) {
    ob_start();
    render($template, $data);
    return ob_get_clean();
}
?>
