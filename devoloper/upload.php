<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Only POST method allowed'], 405);
}

// Check file
if (!isset($_FILES['apk_file'])) {
    jsonResponse(['error' => 'No APK file uploaded'], 400);
}

$file = $_FILES['apk_file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Upload failed with error code: ' . $file['error']], 400);
}

// Check extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'apk') {
    jsonResponse(['error' => 'Only .apk files are allowed'], 400);
}

// Check size (100MB max)
$maxSize = 100 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    jsonResponse(['error' => 'File too large. Max 100MB allowed'], 400);
}

// Generate unique filename
$newFilename = 'app_' . time() . '_' . bin2hex(random_bytes(4)) . '.apk';
$destination = APK_PATH . $newFilename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonResponse(['error' => 'Failed to save file'], 500);
}

// Extract APK info using ZipArchive
$apkInfo = extractAPKInfo($destination);

// Extract icon
$logoFilename = extractAPKIcon($destination, $apkInfo['package_name']);

$result = [
    'success' => true,
    'message' => 'APK uploaded successfully',
    'data' => [
        'filename' => $newFilename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'size_formatted' => formatSize($file['size']),
        'apk_info' => $apkInfo,
        'logo' => $logoFilename,
        'upload_time' => date('Y-m-d H:i:s')
    ]
];

jsonResponse($result);

// ============== FUNCTIONS ==============

function extractAPKInfo($apkPath) {
    $info = [
        'package_name' => '',
        'version_name' => 'Unknown',
        'version_code' => '0',
        'app_name' => '',
        'min_sdk' => '',
        'target_sdk' => '',
        'permissions' => [],
        'file_size' => filesize($apkPath),
        'md5' => md5_file($apkPath),
        'sha1' => sha1_file($apkPath),
        'sha256' => hash_file('sha256', $apkPath)
    ];
    
    $zip = new ZipArchive();
    if ($zip->open($apkPath) === TRUE) {
        // Get AndroidManifest.xml
        $manifest = $zip->getFromName('AndroidManifest.xml');
        
        if ($manifest) {
            // Extract Package Name (simple regex)
            if (preg_match('/package="([^"]+)"/', $manifest, $m)) {
                $info['package_name'] = $m[1];
            }
            
            // Extract Version
            if (preg_match('/versionName="([^"]+)"/', $manifest, $m)) {
                $info['version_name'] = $m[1];
            }
            
            if (preg_match('/versionCode="([^"]+)"/', $manifest, $m)) {
                $info['version_code'] = $m[1];
            }
            
            // Extract permissions
            preg_match_all('/uses-permission.*?android:name="([^"]+)"/', $manifest, $matches);
            if (!empty($matches[1])) {
                $info['permissions'] = $matches[1];
            }
        }
        
        // Try to get app name from resources.arsc
        $resources = $zip->getFromName('resources.arsc');
        if ($resources && preg_match('/app_name/i', $resources)) {
            // Simple extraction - might not always work
            if (preg_match('/[\x00-\x1f]*([A-Za-z][A-Za-z ]+)[\x00-\x1f]*/', $resources, $m)) {
                $info['app_name'] = trim($m[1]);
            }
        }
        
        $zip->close();
    }
    
    // If package name empty, generate from filename
    if (empty($info['package_name'])) {
        $info['package_name'] = 'com.unknown.app' . time();
    }
    
    // If app name empty, use filename
    if (empty($info['app_name'])) {
        $info['app_name'] = pathinfo($apkPath, PATHINFO_FILENAME);
    }
    
    return $info;
}

function extractAPKIcon($apkPath, $packageName) {
    $zip = new ZipArchive();
    if ($zip->open($apkPath) !== TRUE) {
        return '';
    }
    
    // Search for icon files
    $iconPaths = [
        'res/mipmap-xxxhdpi-v4/ic_launcher.png',
        'res/mipmap-xxhdpi-v4/ic_launcher.png',
        'res/mipmap-xhdpi-v4/ic_launcher.png',
        'res/mipmap-hdpi-v4/ic_launcher.png',
        'res/mipmap-mdpi-v4/ic_launcher.png',
        'res/drawable-xxxhdpi/ic_launcher.png',
        'res/drawable/ic_launcher.png',
        'icon.png',
        'ic_launcher.png'
    ];
    
    foreach ($iconPaths as $iconPath) {
        $iconData = $zip->getFromName($iconPath);
        if ($iconData !== false) {
            $logoFilename = ($packageName ?: 'app') . '_logo.png';
            file_put_contents(LOGO_PATH . $logoFilename, $iconData);
            $zip->close();
            return $logoFilename;
        }
    }
    
    // Also check for webp format
    $webpPaths = [
        'res/mipmap-xxxhdpi-v4/ic_launcher.webp',
        'res/mipmap-xxhdpi-v4/ic_launcher.webp',
    ];
    
    foreach ($webpPaths as $webpPath) {
        $iconData = $zip->getFromName($webpPath);
        if ($iconData !== false) {
            $logoFilename = ($packageName ?: 'app') . '_logo.webp';
            file_put_contents(LOGO_PATH . $logoFilename, $iconData);
            $zip->close();
            return $logoFilename;
        }
    }
    
    $zip->close();
    return '';
}

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>
