<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the action from URL
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_apps':
        // Get all apps
        $infoData = readJSON(INFO_FILE);
        jsonResponse($infoData);
        break;
        
    case 'get_app':
        // Get single app by package name
        $pkg = isset($_GET['package']) ? cleanInput($_GET['package']) : '';
        if (empty($pkg)) {
            jsonResponse(['error' => 'Package name required'], 400);
        }
        
        $infoData = readJSON(INFO_FILE);
        $app = null;
        
        foreach ($infoData['apps'] as $a) {
            if ($a['package_name'] === $pkg) {
                $app = $a;
                break;
            }
        }
        
        if ($app) {
            // Get experience data
            $expData = readJSON(EXP_FILE);
            $exp = null;
            foreach ($expData['apps'] as $e) {
                if ($e['package_name'] === $pkg) {
                    $exp = $e;
                    break;
                }
            }
            
            jsonResponse([
                'app_info' => $app,
                'app_exp' => $exp
            ]);
        } else {
            jsonResponse(['error' => 'App not found'], 404);
        }
        break;
        
    case 'get_categories':
        // Get all categories
        $infoData = readJSON(INFO_FILE);
        $categories = [];
        
        foreach ($infoData['apps'] as $app) {
            $cat = $app['category'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'name' => $cat,
                    'count' => 0
                ];
            }
            $categories[$cat]['count']++;
        }
        
        jsonResponse(['categories' => array_values($categories)]);
        break;
        
    case 'add_comment':
        // Add review/comment
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'POST required'], 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $pkg = cleanInput($input['package_name'] ?? '');
        $review = [
            'id' => 'rev_' . uniqid(),
            'user' => cleanInput($input['user'] ?? 'Anonymous'),
            'rating' => intval($input['rating'] ?? 5),
            'date' => date('Y-m-d'),
            'title' => cleanInput($input['title'] ?? ''),
            'content' => cleanInput($input['content'] ?? ''),
            'helpful_count' => 0,
            'reported' => false
        ];
        
        $expData = readJSON(EXP_FILE);
        
        foreach ($expData['apps'] as $key => $app) {
            if ($app['package_name'] === $pkg) {
                // Add review
                $expData['apps'][$key]['reviews'][] = $review;
                $expData['apps'][$key]['total_reviews'] = count($expData['apps'][$key]['reviews']);
                
                // Update rating distribution
                $ratingKey = intval($review['rating']) . '_star';
                if (isset($expData['apps'][$key]['ratings_distribution'][$ratingKey])) {
                    $expData['apps'][$key]['ratings_distribution'][$ratingKey]++;
                }
                
                // Calculate new average rating
                $totalRatings = array_sum($expData['apps'][$key]['ratings_distribution']);
                $totalScore = 0;
                foreach ($expData['apps'][$key]['ratings_distribution'] as $star => $count) {
                    $totalScore += intval($star) * $count;
                }
                
                // Update info file rating
                $infoData = readJSON(INFO_FILE);
                foreach ($infoData['apps'] as $ik => $iapp) {
                    if ($iapp['package_name'] === $pkg) {
                        $infoData['apps'][$ik]['rating_count'] = $totalRatings;
                        $infoData['apps'][$ik]['rating'] = $totalRatings > 0 ? round($totalScore / $totalRatings, 1) : 0;
                        break;
                    }
                }
                writeJSON(INFO_FILE, $infoData);
                
                break;
            }
        }
        
        writeJSON(EXP_FILE, $expData);
        
        jsonResponse([
            'success' => true,
            'message' => 'Review added successfully',
            'review' => $review
        ]);
        break;
        
    case 'download':
        // Track download
        $pkg = isset($_GET['package']) ? cleanInput($_GET['package']) : '';
        
        if ($pkg) {
            $expData = readJSON(EXP_FILE);
            foreach ($expData['apps'] as $key => $app) {
                if ($app['package_name'] === $pkg) {
                    $expData['apps'][$key]['downloads']++;
                    $expData['apps'][$key]['weekly_downloads']++;
                    break;
                }
            }
            writeJSON(EXP_FILE, $expData);
            
            // Also update info file
            $infoData = readJSON(INFO_FILE);
            foreach ($infoData['apps'] as $key => $app) {
                if ($app['package_name'] === $pkg) {
                    $currentInstalls = intval($infoData['apps'][$key]['installs']);
                    $infoData['apps'][$key]['installs'] = ($currentInstalls + 1) . '+';
                    break;
                }
            }
            writeJSON(INFO_FILE, $infoData);
        }
        
        jsonResponse(['success' => true, 'message' => 'Download tracked']);
        break;
        
    case 'get_logo':
        // Get logo file
        $logoFile = isset($_GET['file']) ? cleanInput($_GET['file']) : '';
        $logoPath = LOGO_PATH . $logoFile;
        
        if (file_exists($logoPath)) {
            $mime = mime_content_type($logoPath);
            header('Content-Type: ' . $mime);
            readfile($logoPath);
            exit;
        } else {
            // Return default icon
            header('Content-Type: image/svg+xml');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
                <rect width="100" height="100" fill="#4CAF50" rx="20"/>
                <text x="50" y="65" font-size="50" text-anchor="middle" fill="white">A</text>
            </svg>';
            exit;
        }
        break;
        
    case 'get_apk':
        // Download APK file
        $apkFile = isset($_GET['file']) ? cleanInput($_GET['file']) : '';
        $apkPath = APK_PATH . $apkFile;
        
        if (file_exists($apkPath)) {
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Disposition: attachment; filename="' . basename($apkFile) . '"');
            header('Content-Length: ' . filesize($apkPath));
            readfile($apkPath);
            
            // Track download
            header('X-Download-Tracked: true');
            exit;
        } else {
            jsonResponse(['error' => 'APK file not found'], 404);
        }
        break;
        
    default:
        // Show API documentation
        jsonResponse([
            'api' => 'App Store Developer API',
            'version' => '1.0',
            'endpoints' => [
                'GET  ?action=get_apps' => 'Get all apps',
                'GET  ?action=get_app&package=PKG' => 'Get single app',
                'GET  ?action=get_categories' => 'Get categories',
                'POST ?action=add_comment' => 'Add review',
                'GET  ?action=download&package=PKG' => 'Track download',
                'GET  ?action=get_logo&file=FILENAME' => 'Get logo image',
                'GET  ?action=get_apk&file=FILENAME' => 'Download APK',
            ]
        ]);
        break;
}
?>
