<?php
header('Content-Type: application/json');
error_reporting(0);

// Include shared DB connection
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customername = $_POST['customername'] ?? '';
    if (empty($customername)) {
        echo json_encode(["status" => "fail", "message" => "Customer name is required"]);
        exit();
    }

    $stmt = $conn->prepare("
    SELECT q.name, q.queuelimit AS queuenumber, q.Haircut_Name, q.Color_Name, q.Shave_Name,
           q.date_time, b.name AS branch, q.barber AS barbername,
           (SELECT MIN(queuelimit) FROM queue WHERE barber = q.barber AND status = 'In Queue' AND shopID = q.shopID) AS currentqueue
    FROM queue q
    JOIN barbershops b ON q.shopID = b.shopID
    WHERE q.name = ? AND q.status = 'In Queue'
    ORDER BY q.queuelimit ASC
    LIMIT 1
");

    $stmt->bind_param("s", $customername);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $datetime_parts = explode(' ', $row['date_time']);
        $row['date'] = $datetime_parts[0] ?? '';
        $row['timeslot'] = $datetime_parts[1] ?? '';
        unset($row['date_time']);

        echo json_encode(["status" => "success", "appointment" => $row]);
    } else {
        echo json_encode(["status" => "fail", "message" => "No active appointment found"]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method"]);
}

$conn->close();
?>