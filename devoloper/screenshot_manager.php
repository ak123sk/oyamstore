<?php
/**
 * Screenshot Upload & Management
 */

header('Content-Type: application/json');

class ScreenshotManager {
    private $baseDir;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    public function __construct() {
        $this->baseDir = __DIR__ . '/apk/screenshots/';
    }
    
    public function uploadScreenshots($packageName, $files) {
        $appDir = $this->baseDir . $packageName . '/';
        
        // Create directory if not exists
        if (!file_exists($appDir)) {
            mkdir($appDir, 0755, true);
        }
        
        $uploadedFiles = [];
        
        foreach ($files['tmp_name'] as $key => $tmpName) {
            if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Validate file
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $tmpName);
            finfo_close($fileInfo);
            
            if (!in_array($mimeType, $this->allowedTypes)) {
                continue;
            }
            
            if ($files['size'][$key] > $this->maxFileSize) {
                continue;
            }
            
            // Generate unique filename
            $extension = pathinfo($files['name'][$key], PATHINFO_EXTENSION);
            $filename = 'screenshot_' . uniqid() . '_' . ($key + 1) . '.' . $extension;
            $destination = $appDir . $filename;
            
            // Optimize and save image
            $this->optimizeImage($tmpName, $destination, $mimeType);
            
            $uploadedFiles[] = $filename;
        }
        
        // Rename files sequentially
        $this->renameSequentially($appDir);
        
        return $uploadedFiles;
    }
    
    private function optimizeImage($source, $destination, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                // Resize if too large
                $this->resizeIfNeeded($image, 1920, 1080);
                imagejpeg($image, $destination, 85);
                break;
                
            case 'image/png':
                $image = imagecreatefrompng($source);
                $this->resizeIfNeeded($image, 1920, 1080);
                imagepng($image, $destination, 8);
                break;
                
            case 'image/webp':
                $image = imagecreatefromwebp($source);
                $this->resizeIfNeeded($image, 1920, 1080);
                imagewebp($image, $destination, 85);
                break;
                
            default:
                move_uploaded_file($source, $destination);
        }
        
        if (isset($image)) {
            imagedestroy($image);
        }
    }
    
    private function resizeIfNeeded(&$image, $maxWidth, $maxHeight) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            imagedestroy($image);
            $image = $resized;
        }
    }
    
    private function renameSequentially($directory) {
        $files = glob($directory . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        sort($files);
        
        $i = 1;
        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $newName = $directory . 'screenshot' . $i . '.' . $extension;
            
            if ($file !== $newName) {
                rename($file, $newName);
            }
            $i++;
        }
    }
    
    public function getScreenshots($packageName) {
        $appDir = $this->baseDir . $packageName . '/';
        
        if (!file_exists($appDir)) {
            return [];
        }
        
        $files = glob($appDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        return array_map('basename', $files);
    }
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageName = $_POST['package_name'] ?? '';
    
    if (empty($packageName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Package name is required']);
        exit;
    }
    
    if (!isset($_FILES['screenshots'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No screenshots uploaded']);
        exit;
    }
    
    $manager = new ScreenshotManager();
    $uploaded = $manager->uploadScreenshots($packageName, $_FILES['screenshots']);
    
    echo json_encode([
        'success' => true,
        'files' => $uploaded,
        'count' => count($uploaded)
    ]);
}

// Get screenshots
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $packageName = $_GET['package_name'] ?? '';
    
    if (empty($packageName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Package name is required']);
        exit;
    }
    
    $manager = new ScreenshotManager();
    $screenshots = $manager->getScreenshots($packageName);
    
    echo json_encode([
        'success' => true,
        'screenshots' => $screenshots
    ]);
}
?>
