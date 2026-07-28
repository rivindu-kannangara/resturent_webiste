<?php

declare(strict_types=1);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_lifetime', '0');
ini_set('expose_php', '0');

header_remove('X-Powered-By');

header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    // Added facebook.com for the noscript 1x1 pixel tracking
    "img-src 'self' data: https: blob: https://www.facebook.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://code.ionicframework.com; " .
    "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://code.ionicframework.com; " .
    // Added connect.facebook.net for the main fbevents.js script
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://kit.fontawesome.com https://unpkg.com https://www.payhere.lk https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://ajax.googleapis.com https://maxcdn.bootstrapcdn.com https://code.ionicframework.com https://code.jquery.com https://maps.googleapis.com https://*.google.com https://*.gstatic.com https://connect.facebook.net; " .
    "frame-src 'self' https://www.payhere.lk https://sandbox.payhere.lk https://maps.google.com https://*.google.com; " .
    // Added facebook.com to allow the pixel to send event data back to Meta
    "connect-src 'self' https://sandbox.payhere.lk https://www.payhere.lk https://cdn.jsdelivr.net https://maps.googleapis.com https://*.googleapis.com https://*.gstatic.com https://*.ggpht.com https://www.facebook.com; " .
    "object-src 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self';"
);

header('Service-Worker-Allowed: /');

session_start();

require './../config/function.php';
require './../config/dbcon.php';


function validate_csrf(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("CSRF validation failed for IP: " . $_SERVER['REMOTE_ADDR']);
        header('HTTP/1.1 403 Forbidden');
        exit('Security violation detected');
    }
}

function secure_upload(string $tmp_path, string $dest_dir): string 
{
    // Get original extension safely
    $extension = pathinfo($_FILES['images']['name'][0], PATHINFO_EXTENSION);
    $extension = strtolower($extension);

    // Generate random filename
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;

    // Final path
    $dest_path = rtrim($dest_dir, '/') . '/' . $filename;

    // Move file
    if (!move_uploaded_file($tmp_path, $dest_path)) {
        throw new RuntimeException("File upload failed");
    }

    return $dest_path;
}

function secure_upload_update(string $tmp_path, string $dest_dir): string 
{
    // Get original extension safely
    $extension = pathinfo($_FILES['fileImg']['name'][0], PATHINFO_EXTENSION);
    $extension = strtolower($extension);

    // Generate random filename
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;

    // Final path
    $dest_path = rtrim($dest_dir, '/') . '/' . $filename;

    // Move file
    if (!move_uploaded_file($tmp_path, $dest_path)) {
        throw new RuntimeException("File upload failed");
    }

    return $dest_path;
}


function validate_string(string $input, int $max_length = 222255): string {
    $clean = trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    if (mb_strlen($clean) > $max_length) {
        throw new InvalidArgumentException("Input exceeds maximum length");
    }
    return $clean;
}

function validate_int(string $input): ?int {
    $input = trim($input);
    if ($input === '') return null;
    if (!ctype_digit($input)) throw new InvalidArgumentException("Invalid integer");
    return (int)$input;
}



function validate_url(string $input): string {
    $clean = trim($input);
    if ($clean === '') return '';

    // Allow Google Maps iframe embed code (both formats)
    if (stripos($clean, '<iframe') !== false && 
        (stripos($clean, 'src="https://www.google.com/maps/embed?') !== false || 
         stripos($clean, 'src="https://www.google.com/maps/embed/v1/') !== false)) {
        return $clean;
    }

    // Allow normal URLs
    $clean = filter_var($clean, FILTER_SANITIZE_URL);
    if (!filter_var($clean, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException("Invalid URL format or embed code");
    }
    return $clean;
}
// ========================
// AUTHENTICATION CHECK
// ========================
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_SESSION['auth_publisher']['publisher_id'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
        exit();
    } else {
        header("Location: ../adpublisherlogin.php");
        exit();
    }
}


$publisher_id = (int)$_SESSION['auth_publisher']['publisher_id'];
$admin_panel_url = "adminpanel.php";

// Generate CSRF token per request
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

use Google\Cloud\Vision\V1\ImageAnnotatorClient;

function checkImageForProhibitedContent($imagePath) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/adwire-464407-24d5097d526f.json');
    $imageAnnotator = new ImageAnnotatorClient();
    
    // Add resize here - only change to your code
    $imageData = resizeImage($imagePath);
    
    $response = $imageAnnotator->safeSearchDetection($imageData);
    $safe = $response->getSafeSearchAnnotation();
    $imageAnnotator->close();
    $flags = ['adult', 'violence', 'racy', 'medical', 'spoof'];
    foreach ($flags as $flag) {
        $level = $safe->{'get' . ucfirst($flag)}();
        if (in_array($level, [])) { // only block LIKELY or VERY_LIKELY
            return ucfirst($flag);
        }
    }
    return null; // no issue
}

// Add this helper function
// Image resize function - optimizes images for web
function resizeImage($imagePath, $maxWidth = 800, $quality = 75) {
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        return file_get_contents($imagePath);
    }
    
    list($width, $height, $type) = $imageInfo;
    
    // Skip if already small enough
    if ($width <= $maxWidth && filesize($imagePath) < 200000) {
        return file_get_contents($imagePath);
    }
    
    // Load image based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($imagePath);
            break;
        default:
            return file_get_contents($imagePath);
    }
    
    if (!$source) {
        return file_get_contents($imagePath);
    }
    
    // Calculate new dimensions maintaining aspect ratio
    $ratio = $width / $height;
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = (int)($maxWidth / $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    // Create resized image
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    
    // Handle PNG transparency
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
    }
    
    // Handle GIF transparency
    if ($type == IMAGETYPE_GIF) {
        $transparentIndex = imagecolortransparent($source);
        if ($transparentIndex >= 0) {
            $transparentColor = imagecolorsforindex($source, $transparentIndex);
            $transparentNew = imagecolorallocate($resized, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
            imagefill($resized, 0, 0, $transparentNew);
            imagecolortransparent($resized, $transparentNew);
        }
    }
    
    // Perform the resize
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($source);
    
    // Output to buffer
    ob_start();
    switch ($type) {
        case IMAGETYPE_PNG:
            imagepng($resized, null, (int)(9 - ($quality / 11)));
            break;
        case IMAGETYPE_GIF:
            imagegif($resized);
            break;
        default: // JPEG
            imagejpeg($resized, null, $quality);
            break;
    }
    $imageData = ob_get_clean();
    imagedestroy($resized);
    
    return $imageData;
}

?>

