<?php
header('Content-Type: application/json');
include 'connection.php'; // Your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueID = $_POST['QueueID'] ?? '';
    $receivedStatus = $_POST['new_status'] ?? ''; // Expects 'Done' or 'Cancelled'

    if (empty($queueID) || empty($receivedStatus)) {
        echo json_encode(["status" => "fail", "message" => "QueueID and new status are required."]);
        exit();
    }
    
    // Ensure the status is one of the allowed values
    if ($receivedStatus !== 'Done' && $receivedStatus !== 'Cancelled') {
        echo json_encode(["status" => "fail", "message" => "Invalid status provided."]);
        exit();
    }

    // Convert "Done" to "Completed" for the database
    $finalStatus = ($receivedStatus === 'Done') ? 'Completed' : 'Cancelled';

    // 1. Get the appointment details from the queue table
    $stmt = $conn->prepare("SELECT * FROM queue WHERE QueueID = ?");
    $stmt->bind_param("i", $queueID);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        echo json_encode(["status" => "fail", "message" => "Appointment not found."]);
        exit();
    }

    // Start a transaction for data integrity
    $conn->begin_transaction();

    try {
        // 2. Insert into the sales table with the new status
        $insert = $conn->prepare("
            INSERT INTO sales (sales_name, sales_service, barber, sales_price, sales_dateTime, sales_status, adminID, shopID, haircut_name, color_name, shave_name, massage_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $price = $appointment['price'] ?? 0.0;

        // Use $finalStatus here instead of the original status
        $insert->bind_param("sssdssiissss",
            $appointment['name'], $appointment['service'], $appointment['barber'], $price,
            $appointment['date_time'], $finalStatus, $appointment['adminID'], $appointment['shopID'],
            $appointment['Haircut_Name'], $appointment['Color_Name'], $appointment['Shave_Name'], $appointment['Massage_Name']
        );
        $insert->execute();
        $insert->close();

        // 3. Delete from the queue table
        $delete = $conn->prepare("DELETE FROM queue WHERE QueueID = ?");
        $delete->bind_param("i", $queueID);
        $delete->execute();
        $delete->close();
        
        // Commit the changes
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Appointment has been updated."]);

    } catch (Exception $e) {
        // An error occurred, roll back the changes
        $conn->rollback();
        echo json_encode(["status" => "fail", "message" => "Database error: " . $e->getMessage()]);
    }

} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
}
$conn->close();
?>