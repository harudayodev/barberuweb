<?php
header('Content-Type: application/json');
include 'connection.php';

$barbername = $_POST['barber'] ?? '';

if (empty($barbername)) {
    echo json_encode(["status" => "fail", "message" => "Barber name is required."]);
    exit();
}

// Count active queues for the specific barber
$stmt = $conn->prepare("SELECT COUNT(*) AS active_queues FROM queue WHERE barber = ? AND status = 'In Queue'");
$stmt->bind_param("s", $barbername);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$active_queues = $row['active_queues'];
$stmt->close();

// Set the queue limit here
$queue_limit = 2;

if ($active_queues >= $queue_limit) {
    echo json_encode(["status" => "full", "message" => "Queue full. Try another barber or come back later."]);
} else {
    echo json_encode(["status" => "available", "message" => "Barber is available to take a new customer."]);
}

$conn->close();
?>