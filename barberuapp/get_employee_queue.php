<?php
header('Content-Type: application/json');
include 'connection.php'; // Your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeID = $_POST['employeeID'] ?? '';

    if (empty($employeeID)) {
        echo json_encode(['status' => 'fail', 'message' => 'Employee ID is required.']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT 
            QueueID, 
            name, 
            barber,
            price, -- FIXED: Added the 'price' column
            date_time, 
            Haircut_Name, 
            Color_Name, 
            Shave_Name
        FROM queue
        WHERE EmployeeID = ? AND status = 'In Queue'
        ORDER BY date_time ASC
    ");

    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $result = $stmt->get_result();

    $queueItems = [];
    while ($row = $result->fetch_assoc()) {
        $queueItems[] = $row;
    }

    if (!empty($queueItems)) {
        echo json_encode(['status' => 'success', 'data' => $queueItems]);
    } else {
        $message = "No customers in queue for Employee ID: " . $employeeID;
        echo json_encode(['status' => 'fail', 'message' => $message]);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Invalid request method.']);
}

$conn->close();
?>