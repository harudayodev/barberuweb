<?php
header('Content-Type: application/json');
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeEmail = trim($_POST['email'] ?? '');
    $day = trim($_POST['day'] ?? '');
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');
    $available = trim($_POST['available'] ?? 'false');

    if (empty($employeeEmail) || empty($day)) {
        echo json_encode(["status"=>"fail", "message"=>"Email and day required"]);
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
        echo json_encode(["status"=>"fail","message"=>"Employee not found"]);
        exit();
    }

    $employeeID = $res->fetch_assoc()['EmployeeID'];

    if ($available === 'false') {
        // Remove availability
        $stmt = $conn->prepare("DELETE FROM employee_workhours WHERE employee_id=? AND day_of_week=?");
        $stmt->bind_param("is", $employeeID, $day);
        $stmt->execute();

        echo json_encode(["status"=>"success","message"=>"$day marked as unavailable"]);
    } else {
        // Upsert (insert or update)
        $stmt = $conn->prepare("SELECT id FROM employee_workhours WHERE employee_id=? AND day_of_week=?");
        $stmt->bind_param("is", $employeeID, $day);
        $stmt->execute();
        $check = $stmt->get_result();

        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("
                UPDATE employee_workhours 
                SET start_time=?, end_time=? 
                WHERE employee_id=? AND day_of_week=?
            ");
            $stmt->bind_param("ssis", $start, $end, $employeeID, $day);
            $stmt->execute();
            echo json_encode(["status"=>"success","message"=>"$day schedule updated"]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO employee_workhours (employee_id, day_of_week, start_time, end_time)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $employeeID, $day, $start, $end);
            $stmt->execute();
            echo json_encode(["status"=>"success","message"=>"$day schedule added"]);
        }
    }

    $stmt->close();
    $conn->close();
}
?>
