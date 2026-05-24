<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Only POST method allowed'], 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['error' => 'Invalid JSON data'], 400);
}

// Required fields
$required = ['package_name', 'app_name', 'description', 'category', 'filename'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        jsonResponse(['error' => "Missing required field: $field"], 400);
    }
}

// Prepare app data
$appData = [
    'id' => 'app_' . uniqid(),
    'package_name' => cleanInput($input['package_name']),
    'name' => cleanInput($input['app_name']),
    'version' => cleanInput($input['version'] ?? '1.0'),
    'version_code' => intval($input['version_code'] ?? 1),
    'description' => cleanInput($input['description']),
    'short_description' => cleanInput($input['short_description'] ?? ''),
    'category' => cleanInput($input['category']),
    'subcategory' => cleanInput($input['subcategory'] ?? ''),
    'developer' => [
        'name' => cleanInput($input['developer_name'] ?? 'Unknown Developer'),
        'email' => cleanInput($input['developer_email'] ?? ''),
        'website' => cleanInput($input['developer_website'] ?? ''),
        'privacy_policy' => cleanInput($input['privacy_policy'] ?? '')
    ],
    'size' => intval($input['size'] ?? 0),
    'installs' => '0+',
    'rating' => 0,
    'rating_count' => 0,
    'content_rating' => cleanInput($input['content_rating'] ?? 'Everyone'),
    'requires_os' => cleanInput($input['requires_os'] ?? '5.0'),
    'updated_date' => date('Y-m-d'),
    'released_date' => date('Y-m-d'),
    'is_featured' => false,
    'is_trending' => false,
    'is_free' => boolval($input['is_free'] ?? true),
    'price' => floatval($input['price'] ?? 0),
    'currency' => 'USD',
    'permissions' => $input['permissions'] ?? [],
    'screenshots' => $input['screenshots'] ?? [],
    'logo' => cleanInput($input['logo'] ?? ''),
    'apk_file' => cleanInput($input['filename']),
    'apk_hash' => [
        'md5' => $input['md5'] ?? '',
        'sha1' => $input['sha1'] ?? '',
        'sha256' => $input['sha256'] ?? ''
    ],
    'features' => $input['features'] ?? [],
    'languages' => $input['languages'] ?? ['en'],
    'in_app_purchases' => boolval($input['in_app_purchases'] ?? false),
    'status' => 'active'
];

// ========== SAVE TO apk_info.json ==========
$infoData = readJSON(INFO_FILE);
if (!$infoData) {
    $infoData = ['apps' => []];
}

// Check if app already exists (update)
$found = false;
foreach ($infoData['apps'] as $key => $app) {
    if ($app['package_name'] === $appData['package_name']) {
        $infoData['apps'][$key] = array_merge($app, $appData);
        $found = true;
        break;
    }
}

// Add new app
if (!$found) {
    $infoData['apps'][] = $appData;
}

// Update metadata
$infoData['metadata']['last_updated'] = date('Y-m-d H:i:s');
$infoData['metadata']['total_apps'] = count($infoData['apps']);

// Save
writeJSON(INFO_FILE, $infoData);

// ========== SAVE TO apk_exp.json ==========
$expData = readJSON(EXP_FILE);
if (!$expData) {
    $expData = ['apps' => []];
}

// Initialize experience data
$experienceEntry = [
    'package_name' => $appData['package_name'],
    'downloads' => 0,
    'weekly_downloads' => 0,
    'daily_active_users' => 0,
    'total_reviews' => 0,
    'average_session_time' => 0,
    'user_retention' => [
        'day_1' => 0,
        'day_7' => 0,
        'day_30' => 0
    ],
    'ratings_distribution' => [
        '1_star' => 0,
        '2_star' => 0,
        '3_star' => 0,
        '4_star' => 0,
        '5_star' => 0
    ],
    'reviews' => [],
    'changelog' => [
        [
            'version' => $appData['version'],
            'date' => date('Y-m-d'),
            'changes' => ['Initial release']
        ]
    ]
];

// Check if already exists
$expFound = false;
foreach ($expData['apps'] as $key => $app) {
    if ($app['package_name'] === $appData['package_name']) {
        $expFound = true;
        break;
    }
}

if (!$expFound) {
    $expData['apps'][] = $experienceEntry;
}

$expData['metadata']['last_updated'] = date('Y-m-d H:i:s');

writeJSON(EXP_FILE, $expData);

// ========== SAVE DEVELOPER ==========
$devData = readJSON(DEVELOPER_FILE);
if ($devData) {
    $devFound = false;
    foreach ($devData['developers'] as $key => $dev) {
        if ($dev['name'] === $appData['developer']['name']) {
            if (!in_array($appData['package_name'], $dev['apps'])) {
                $devData['developers'][$key]['apps'][] = $appData['package_name'];
                $devData['developers'][$key]['total_apps'] = count($devData['developers'][$key]['apps']);
            }
            $devFound = true;
            break;
        }
    }
    
    if (!$devFound) {
        $devData['developers'][] = [
            'id' => 'dev_' . uniqid(),
            'name' => $appData['developer']['name'],
            'email' => $appData['developer']['email'],
            'website' => $appData['developer']['website'],
            'privacy_policy' => $appData['developer']['privacy_policy'],
            'total_apps' => 1,
            'total_downloads' => '0',
            'apps' => [$appData['package_name']]
        ];
    }
    
    writeJSON(DEVELOPER_FILE, $devData);
}

jsonResponse([
    'success' => true,
    'message' => 'App data saved successfully',
    'data' => $appData
]);
?>
