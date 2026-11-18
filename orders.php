<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $conn->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orderId = $row['id'];
            $itemsResult = $conn->query("SELECT * FROM order_items WHERE order_id = $orderId");
            $items = [];
            if ($itemsResult) {
                while ($itemRow = $itemsResult->fetch_assoc()) {
                    $items[] = [
                        "name" => $itemRow['item_name'],
                        "price" => floatval($itemRow['unit_price']),
                        "qty" => intval($itemRow['quantity'])
                    ];
                }
            }
            $row['items'] = $items;
            $row['total'] = floatval($row['total_amount'] ?? 0);
            $row['status'] = trim($row['status'] ?? 'Pending');
            
            $row['customer'] = 'Customer';
            $row['phone'] = '';
            $row['note'] = 'ASAP';
            if (!empty($row['customer_note'])) {
                if (preg_match('/Customer: (.*?) \| Phone: (.*?) \| Note: (.*)/', $row['customer_note'], $matches)) {
                    $row['customer'] = $matches[1];
                    $row['phone'] = $matches[2];
                    $row['note'] = $matches[3];
                }
            }

            $orders[] = $row;
        }
    }
    echo json_encode($orders);
    exit();
} 
else if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $customerName = isset($data['customer']) ? $conn->real_escape_string($data['customer']) : 'Guest';
    $phone = isset($data['phone']) ? $conn->real_escape_string($data['phone']) : '';
    $note = isset($data['note']) ? $conn->real_escape_string($data['note']) : 'ASAP';
    $total = isset($data['total']) ? floatval($data['total']) : 0;
    $orderNumber = 'QB-' . rand(1000, 9999);
    $status = 'Pending';
    $paymentStatus = 'Pending';
    $paymentMethod = 'Pay at Pickup';
    $customerNoteStr = "Customer: $customerName | Phone: $phone | Note: $note";

    $sql = "INSERT INTO orders (order_number, status, payment_status, payment_method, subtotal, total_amount, pickup_time, customer_note, created_at) 
            VALUES ('$orderNumber', '$status', '$paymentStatus', '$paymentMethod', $total, $total, NOW(), '$customerNoteStr', NOW())";
    
    if ($conn->query($sql)) {
        $orderId = $conn->insert_id;

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $itemName = $conn->real_escape_string($item['name']);
                $unitPrice = floatval($item['price']);
                $quantity = intval($item['qty']);
                $lineTotal = $unitPrice * $quantity;
                
                $conn->query("INSERT INTO order_items (order_id, item_name, unit_price, quantity, line_total, created_at) 
                              VALUES ($orderId, '$itemName', $unitPrice, $quantity, $lineTotal, NOW())");
            }
        }

        echo json_encode([
            "id" => $orderId,
            "order_number" => $orderNumber,
            "customer" => $customerName,
            "phone" => $phone,
            "note" => $note,
            "items" => $data['items'] ?? [],
            "total" => $total,
            "status" => $status
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => $conn->error]);
    }
    exit();
}
else if ($method === 'PATCH') {
    $id = intval($_GET['id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true);
    $status = $conn->real_escape_string($data['status'] ?? 'Pending');
    
    $conn->query("UPDATE orders SET status = '$status' WHERE id = $id");
    echo json_encode(["success" => true]);
    exit();
}
else if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    $conn->query("DELETE FROM order_items WHERE order_id = $id");
    $conn->query("DELETE FROM orders WHERE id = $id");
    echo json_encode(["success" => true]);
    exit();
}
?>