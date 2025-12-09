<?php
header('Content-Type: application/json');
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeEmail = trim($_POST['email'] ?? '');
    $day = trim($_POST['day'] ?? '');
    $date = trim($_POST['date'] ?? '');

    if (empty($employeeEmail) || empty($day) || empty($date)) {
        echo json_encode(["status" => "fail", "message" => "Missing required data"]);
        exit();
    }

    // Get employee ID
    $stmt = $conn->prepare("
        SELECT e.EmployeeID 
        FROM employee e
        INNER JOIN appusers a ON e.EmployeeID = a.employeeID
        WHERE a.email = ? OR e.Email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $employeeEmail, $employeeEmail);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status"=>"fail", "message"=>"Employee not found"]);
        exit();
    }

    $employeeID = $res->fetch_assoc()['EmployeeID'];

    // Remove from availability
    $stmt = $conn->prepare("DELETE FROM employee_availability WHERE employee_id=? AND day_of_week=?");
    $stmt->bind_param("is", $employeeID, $day);
    $stmt->execute();

    // Add to unavailability
    $stmt = $conn->prepare("INSERT INTO employee_unavailability (employee_id, unavailable_date) VALUES (?, ?)");
    $stmt->bind_param("is", $employeeID, $date);
    $stmt->execute();

    echo json_encode(["status"=>"success", "message"=>"$day ($date) marked as unavailable."]);
    $stmt->close();
    $conn->close();
}
?>
