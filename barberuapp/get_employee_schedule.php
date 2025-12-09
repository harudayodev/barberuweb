<?php
header('Content-Type: application/json');
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeEmail = trim($_POST['email'] ?? '');

    if (empty($employeeEmail)) {
        echo json_encode(["status" => "fail", "message" => "Email required"]);
        exit();
    }

    // Get EmployeeID from appusers or employee
    $stmt = $conn->prepare("
        SELECT e.EmployeeID 
        FROM employee e
        INNER JOIN appusers a ON e.EmployeeID = a.employeeID
        WHERE a.email = ? OR e.Email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $employeeEmail, $employeeEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "fail", "message" => "Employee not found"]);
        exit();
    }

    $employee = $result->fetch_assoc();
    $employeeID = $employee['EmployeeID'];

    // Define days
    $days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
    $schedule = [];

    foreach ($days as $day) {
        $schedule[$day] = [
            "available" => false,
            "start_time" => "none",
            "end_time" => "none"
        ];
    }

    // Fetch work hours
    $stmt = $conn->prepare("
        SELECT day_of_week, start_time, end_time 
        FROM employee_workhours 
        WHERE employee_id = ?
    ");
    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $day = $row['day_of_week'];
        $schedule[$day] = [
            "available" => true,
            "start_time" => $row['start_time'],
            "end_time" => $row['end_time']
        ];
    }

    echo json_encode(["status" => "success", "schedule" => $schedule]);
    $stmt->close();
    $conn->close();
}
?>
