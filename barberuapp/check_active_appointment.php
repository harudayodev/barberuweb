<?php
header('Content-Type: application/json');

// Include shared DB connection
include 'connection.php';

$customername = $_POST['customername'] ?? '';

if (empty($customername)) {
    echo json_encode(["status" => "fail", "message" => "Customer name is required."]);
    $conn->close();
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM queue WHERE name = ? AND status = 'In Queue'");
$stmt->bind_param("s", $customername);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(["status" => "exists", "message" => "You already have an active appointment."]);
} else {
    echo json_encode(["status" => "not_exists", "message" => "No active appointment found."]);
}

$stmt->close();
$conn->close();
?>