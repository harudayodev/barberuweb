<?php
header('Content-Type: application/json');

// Include shared DB connection
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customername = $_POST['customername'] ?? '';

    if (empty($customername)) {
        echo json_encode(["status" => "fail", "message" => "Customer name is required."]);
        $conn->close();
        exit();
    }

    // 1) Get latest active (In Queue) appointment for this customer
    $stmt = $conn->prepare("
        SELECT QueueID, name, service, barber, price, date_time, adminID, shopID, Haircut_Name, Color_Name
        FROM queue
        WHERE name = ? AND status = 'In Queue'
        ORDER BY QueueID DESC
        LIMIT 1
    ");
    if (!$stmt) {
        echo json_encode(["status" => "fail", "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("s", $customername);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        echo json_encode(["status" => "fail", "message" => "No active appointment found to cancel."]);
        $conn->close();
        exit();
    }

    // Start transaction (atomic move)
    $conn->begin_transaction();

    // 2) Insert into sales with sales_status = 'Cancelled'
    $insert = $conn->prepare("
        INSERT INTO sales 
            (sales_name, sales_service, barber, sales_price, sales_dateTime, sales_status, adminID, shopID, haircut_name, color_name)
        VALUES (?, ?, ?, ?, ?, 'Cancelled', ?, ?, ?, ?)
    ");
    if (!$insert) {
        $conn->rollback();
        echo json_encode(["status" => "fail", "message" => "Prepare insert failed: " . $conn->error]);
        $conn->close();
        exit();
    }

    $types = "sssdsiiss";
    $sales_name = $appointment['name'];
    $sales_service = $appointment['service'];
    $barber = $appointment['barber'];
    $sales_price = is_null($appointment['price']) || $appointment['price'] === '' ? 0.0 : (float)$appointment['price'];
    $sales_dateTime = $appointment['date_time'];
    $adminID = is_null($appointment['adminID']) ? null : (int)$appointment['adminID'];
    $shopID = is_null($appointment['shopID']) ? null : (int)$appointment['shopID'];
    $haircut_name = $appointment['Haircut_Name'];
    $color_name = $appointment['Color_Name'];

    $insert->bind_param(
        $types,
        $sales_name,
        $sales_service,
        $barber,
        $sales_price,
        $sales_dateTime,
        $adminID,
        $shopID,
        $haircut_name,
        $color_name
    );

    if (!$insert->execute()) {
        $insert->close();
        $conn->rollback();
        echo json_encode(["status" => "fail", "message" => "Error inserting into sales: " . $insert->error]);
        $conn->close();
        exit();
    }
    $insert->close();

    // 3) Delete the row from queue
    $delete = $conn->prepare("DELETE FROM queue WHERE QueueID = ?");
    if (!$delete) {
        $conn->rollback();
        echo json_encode(["status" => "fail", "message" => "Prepare delete failed: " . $conn->error]);
        $conn->close();
        exit();
    }
    $queueId = (int)$appointment['QueueID'];
    $delete->bind_param("i", $queueId);

    if (!$delete->execute()) {
        $delete->close();
        $conn->rollback();
        echo json_encode(["status" => "fail", "message" => "Error deleting queue entry: " . $delete->error]);
        $conn->close();
        exit();
    }

    $delete->close();
    $conn->commit();

    echo json_encode(["status" => "success", "message" => "Appointment canceled successfully."]);
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
}

$conn->close();
?>
