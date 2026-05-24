<?php
// Error Settings
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Paths
define('BASE_PATH', __DIR__);
define('APK_PATH', BASE_PATH . '/apk/file/');
define('LOGO_PATH', BASE_PATH . '/apk/logo/');
define('SCREENSHOTS_PATH', BASE_PATH . '/apk/screenshots/');
define('INFO_FILE', BASE_PATH . '/apk/apk_info.json');
define('EXP_FILE', BASE_PATH . '/apk/apk_exp.json');
define('DEVELOPER_FILE', BASE_PATH . '/developer.json');

// Create directories if not exist
$dirs = [APK_PATH, LOGO_PATH, SCREENSHOTS_PATH];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialize JSON files if not exist
$jsonFiles = [
    INFO_FILE => ['apps' => [], 'metadata' => ['version' => '1.0', 'last_updated' => date('Y-m-d H:i:s'), 'total_apps' => 0]],
    EXP_FILE => ['apps' => [], 'metadata' => ['version' => '1.0', 'last_updated' => date('Y-m-d H:i:s')]],
    DEVELOPER_FILE => ['developers' => []]
];

foreach ($jsonFiles as $file => $defaultData) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// Security functions
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function readJSON($file) {
    if (!file_exists($file)) return null;
    $data = file_get_contents($file);
    return json_decode($data, true);
}

function writeJSON($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
?>
