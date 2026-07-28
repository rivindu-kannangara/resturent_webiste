<?php

include '../config/dbcon.php';
function validate($inputData){
    global $conn;
    $inputData = trim($inputData);                         // Remove extra whitespace
    $inputData = stripslashes($inputData);                 // Remove backslashes
    $inputData = str_replace('/', '', $inputData);         // Remove forward slashes
    $inputData = htmlspecialchars($inputData, ENT_QUOTES, 'UTF-8'); // Convert special characters
    return mysqli_real_escape_string($conn, $inputData);   // Escape for SQL
}


function redirect($url, $vehicle_ad_status)
{
    $_SESSION['vehicle_ad_status'] = $vehicle_ad_status;
    header('Location:'.$url);
    exit(0);
}

function alertMessage()
{
    if(isset($_SESSION['vehicle_ad_status']))
    {
        echo '<div class="alert alert-success">
        <h6>'.$_SESSION['vehicle_ad_status'].'</h6>
        </div>';
        unset($_SESSION['vehicle_ad_status']);
    }
}



function checkParamId($paramType){
    if(isset($_GET[$paramType])){
     if($_GET[$paramType] != null){
         return $_GET[$paramType];
    }else{
        return 'no id found';
    }

}else{
    return 'no id given';
}
}

function getAll($tableName) 
{
    global $conn, $publisher_id; // Ensure publisher_id is included

    $table = validate($tableName);
    $publisher_id = mysqli_real_escape_string($conn, $publisher_id);

    $query = "SELECT * FROM $table WHERE publisher_id='$publisher_id'";
    $result = mysqli_query($conn, $query);
    return $result;
}
function getAllenquaries($tableName) 
{
    global $conn, $worker_id ; // Ensure worker_id  is included

    $table = validate($tableName);

    $query = "SELECT * FROM $table";
    $result = mysqli_query($conn, $query);
    return $result;
}


function getAllADDS($tableName) 
{
    global $conn, $publisher_id;

    $table = validate($tableName);
    $publisher_id = mysqli_real_escape_string($conn, $publisher_id);

    $query = "SELECT * FROM $table WHERE publisher_id='$publisher_id' ORDER BY ad_id DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

function getAllADDScard($tableName, $category_code) 
{
    global $conn, $publisher_id;

    // Basic sanitization
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $category_code = mysqli_real_escape_string($conn, $category_code);
    $publisher_id = mysqli_real_escape_string($conn, $publisher_id);

    // Build query safely
    $query = "
        SELECT allads.*, $category_code.*
        FROM allads
        INNER JOIN $category_code 
        ON allads.each_category_id = $category_code.{$category_code}_ad_id
        WHERE allads.publisher_id = '$publisher_id'
        ORDER BY date_insert DESC
    ";

    $result = mysqli_query($conn, $query);
    return $result;
}


function getById($tableName, $id, $category = null) {
    global $conn;

    // Validate input
    $table = validate($tableName);
    $id = validate($id);

    // Escape the inputs to prevent SQL injection
    $table = mysqli_real_escape_string($conn, $table);
    $id = mysqli_real_escape_string($conn, $id);

    // Check if category is provided and decide dynamic vehicle_ad_id condition
    if ($category !== null) {
        // Sanitize category input
        $category = mysqli_real_escape_string($conn, $category);

        // Map category to its respective vehicle_ad_id field dynamically
        $vehicleAddIdField = getVehicleAddIdByCategory($category);  // This function should return the field name based on category

        if ($vehicleAddIdField === null) {
            return [
                'status' => 400,
                'message' => 'Invalid category provided'
            ];
        }

        $query = "SELECT * FROM `$table` WHERE " . $table . "_ad_id = '$id' LIMIT 1";
    } else {
        $query = "SELECT * FROM `$table` WHERE " . $table . "_ad_id = '$id' LIMIT 1";

    }

    $result = mysqli_query($conn, $query);

    if ($result) {
        if (mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
            return [
                'status' => 200,
                'message' => 'Fetched data',
                'data' => $row
            ];
        } else {
            return [
                'status' => 404,
                'message' => 'Data not found'
            ];
        }
    } else {
        return [
            'status' => 500,
            'message' => 'Database error: ' . mysqli_error($conn)
        ];
    }
}

function getByIdenquary($tableName, $id) {
    global $conn; 

    $table = validate($tableName);
    $id = validate($id);

    $query = "SELECT * FROM $table WHERE id='$id' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result) 
    {
        if (mysqli_num_rows($result) == 1)
         {
            $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
            $response = [
                'status' => 200,
                'message' => 'Fetched data',
                'data' => $row
            ];
            return $response;
        } else {
            $response = [
                'status' => 404,
                'message' => 'Data not found'
            ];
            return $response;
        }
    } else {
        $response = [
            'status' => 500,
            'message' => 'Database error'
        ];
        return $response;
    }
}

// Example of a function that maps the category to a specific vehicle_ad_id field.
function getVehicleAddIdByCategory($category) {
    // Define the category to field mapping
    $categoryMap = [
        'category1' => 'vehicle_ad_id_field_1', // replace with actual field names
        'category2' => 'vehicle_ad_id_field_2',
        // Add more categories and their corresponding fields here
    ];

    return isset($categoryMap[$category]) ? $categoryMap[$category] : null;
}



function deleteQuery($tableName, $id) {
    global $conn;

    // Validate input
    $table = validate($tableName);
    $id = validate($id);

    // Start a transaction
    mysqli_begin_transaction($conn);

    try {
        // 🔹 Delete from the given table (Example: 'vehicle')
        $query1 = "DELETE FROM $table WHERE vehicle_ad_id = ? LIMIT 1";
        $stmt1 = mysqli_prepare($conn, $query1);
        mysqli_stmt_bind_param($stmt1, "i", $id);
        mysqli_stmt_execute($stmt1);
        $rowsAffected1 = mysqli_stmt_affected_rows($stmt1);

        if ($rowsAffected1 == 0) {
            throw new Exception("No rows deleted from $table. ID may not exist.");
        }

        // 🔹 Delete from `allads` table where `each_category_id` matches `vehicle_ad_id`
        $query2 = "DELETE FROM allads WHERE each_category_id = ? AND category = $table";
        $stmt2 = mysqli_prepare($conn, $query2);
        mysqli_stmt_bind_param($stmt2, "i", $id);
        mysqli_stmt_execute($stmt2);
        $rowsAffected2 = mysqli_stmt_affected_rows($stmt2);

        if ($rowsAffected2 == 0) {
            throw new Exception("No matching record found in allads to delete.");
        }

        // ✅ Commit transaction if both deletions succeed
        mysqli_commit($conn);
        return true;

    } catch (Exception $e) {
        // ❌ Rollback transaction on failure
        mysqli_rollback($conn);
        error_log("Delete error: " . $e->getMessage()); // Logs error for debugging
        return false;
    }
}


function saveAd($userId, $adId, $adType) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO saved_ads (user_id, ad_id, ad_type) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $adId, $adType);
    return $stmt->execute();
}

function unsaveAd($userId, $adId, $adType) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM saved_ads WHERE user_id = ? AND ad_id = ? AND ad_type = ?");
    $stmt->bind_param("iss", $userId, $adId, $adType);
    return $stmt->execute();
}

function isAdSaved($userId, $adId, $adType) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id FROM saved_ads WHERE user_id = ? AND ad_id = ? AND ad_type = ? LIMIT 1");
    $stmt->bind_param("iss", $userId, $adId, $adType);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}
function add_image_watermark($targetImagePath, $watermarkImagePath) {
    $mimeType = mime_content_type($targetImagePath);

    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($targetImagePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($targetImagePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($targetImagePath);
            break;
        default:
            return false;
    }

    if (!$image) return false;

    if (!file_exists($watermarkImagePath)) {
        error_log("Watermark image not found: $watermarkImagePath");
        return false;
    }

    $watermark = imagecreatefrompng($watermarkImagePath);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $imageWidth  = imagesx($image);
    $imageHeight = imagesy($image);

    $wmOrigW = imagesx($watermark);
    $wmOrigH = imagesy($watermark);

    // 🔹 Scale watermark to 15% of image width
    $wmNewW = (int)($imageWidth * 0.15);
    $wmNewH = (int)(($wmOrigH / $wmOrigW) * $wmNewW);

    // Create resized watermark
    $resizedWatermark = imagecreatetruecolor($wmNewW, $wmNewH);
    imagealphablending($resizedWatermark, false);
    imagesavealpha($resizedWatermark, true);

    imagecopyresampled(
        $resizedWatermark, $watermark,
        0, 0, 0, 0,
        $wmNewW, $wmNewH,
        $wmOrigW, $wmOrigH
    );

    // Position bottom right with 10px padding
    $destX = $imageWidth - $wmNewW - 10;
    $destY = $imageHeight - $wmNewH - 10;

    imagecopy($image, $resizedWatermark, $destX, $destY, 0, 0, $wmNewW, $wmNewH);

    switch ($mimeType) {
        case 'image/jpeg':
            imagejpeg($image, $targetImagePath, 90);
            break;
        case 'image/png':
            imagepng($image, $targetImagePath, 9);
            break;
        case 'image/gif':
            imagegif($image, $targetImagePath);
            break;
    }

    imagedestroy($image);
    imagedestroy($watermark);
    imagedestroy($resizedWatermark);

    return true;
}

function generateSlug($name, $distric = '') {
    $str = strtolower($name . ' ' . $distric);
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', trim($str));
    return $str;
}

function makeUniqueSlug($slug, $conn) {
    $originalSlug = $slug;
    $count = 1;

    while (true) {
        $query = "SELECT COUNT(*) as count FROM allads WHERE slug='$slug'";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);

        if ($row['count'] == 0) {
            return $slug;
        }

        $slug = $originalSlug . '-' . $count;
        $count++;
    }
}

?>