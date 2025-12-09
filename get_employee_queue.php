<?php
header('Content-Type: application/json');
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeID = $_POST['employeeID'] ?? '';

    if (empty($employeeID)) {
        echo json_encode(["status" => "fail", "message" => "Employee ID is required."]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT QueueID, name, barber, date_time, Haircut_Name, Color_Name, Shave_Name
        FROM queue
        WHERE EmployeeID = ? AND status = 'In Queue'
        ORDER BY date_time ASC
    ");
    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $result = $stmt->get_result();

    $queue_items = [];
    while ($row = $result->fetch_assoc()) {
        $queue_items[] = $row;
    }

    if (!empty($queue_items)) {
        echo json_encode(["status" => "success", "data" => $queue_items]);
    } else {
        // --- DEBUGGING MESSAGE ADDED HERE ---
        $message = "No customers in queue for Employee ID: " . $employeeID;
        echo json_encode(["status" => "fail", "message" => $message]);
        // --- END OF DEBUGGING MESSAGE ---
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
}

$conn->close();
?>