<?php
header('Content-Type: application/json');
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customername = trim($_POST['customername'] ?? '');

    if (empty($customername)) {
        echo json_encode([
            "status" => "fail",
            "message" => "Customer name is required"
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT 
            salesID AS id,
            sales_name AS customerName,
            haircut_name AS haircutName,
            COALESCE(color_name, 'None') AS colorName,
            COALESCE(barber, 'N/A') AS barberName,
            sales_price AS price,
            sales_dateTime AS dateTime,
            sales_status AS status,
            shopID
        FROM sales
        WHERE sales_name = ?
          AND LOWER(sales_status) IN ('completed', 'cancelled')
        ORDER BY sales_dateTime DESC
    ");
    $stmt->bind_param("s", $customername);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "history" => $history
    ]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
}
?>

