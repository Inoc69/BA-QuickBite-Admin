<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $conn->query("SELECT * FROM menu ORDER BY id DESC");
    $menu = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $menu[] = [
                "id" => intval($row['id']),
                "name" => $row['name'],
                "category" => $row['category'],
                "description" => $row['description'],
                "price" => floatval($row['price']),
                "status" => $row['status'],
                "image" => $row['image'] ?? ''
            ];
        }
    }
    echo json_encode($menu);
    exit();
}
else if ($method === 'POST') {
    $id = intval($_GET['id'] ?? 0);
    
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $category = $conn->real_escape_string($_POST['category'] ?? 'Burgers');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $status = $conn->real_escape_string($_POST['status'] ?? 'Available');
    
    // Default to existing image if editing
    $imagePath = '';
    if ($id > 0) {
        $res = $conn->query("SELECT image FROM menu WHERE id = $id");
        if ($res && $row = $res->fetch_assoc()) {
            $imagePath = $row['image'];
        }
    }

    if (isset($_POST['existing_image']) && !empty($_POST['existing_image'])) {
        $imagePath = $_POST['existing_image'];
    }

    // Check if a new file was actually sent in upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Added 'jfif' to allowed extensions
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
            $uploadFileDir = __DIR__ . '/uploads/';
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $imagePath = 'uploads/' . $newFileName;
            }
        }
    }

    $image = $conn->real_escape_string($imagePath);

    if ($id > 0) {
        $sql = "UPDATE menu SET name='$name', category='$category', description='$description', price=$price, status='$status', image='$image' WHERE id=$id";
    } else {
        $sql = "INSERT INTO menu (name, category, description, price, status, image) 
                VALUES ('$name', '$category', '$description', $price, '$status', '$image')";
    }
    
    if ($conn->query($sql)) {
        echo json_encode(["success" => true, "id" => ($id > 0 ? $id : $conn->insert_id)]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => $conn->error]);
    }
    exit();
}
else if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    
    $res = $conn->query("SELECT image FROM menu WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['image']) && file_exists(__DIR__ . '/' . $row['image'])) {
            unlink(__DIR__ . '/' . $row['image']);
        }
    }
    
    if ($conn->query("DELETE FROM menu WHERE id = $id")) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => $conn->error]);
    }
    exit();
}
?>