<?php
header('Content-Type: application/json');
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}
$empId = (int)$_GET['id'];
require_once "Connection.php";

// Get resume filename
$resume = '';
$res = $conn->query("SELECT Resume FROM employee WHERE EmployeeID = $empId LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $resume = $row['Resume'] ?? '';
}

// Get work hours (all days)
$workhours = [];
$res2 = $conn->query("SELECT day_of_week, start_time, end_time FROM employee_workhours WHERE employee_id = $empId");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $workhours[$row['day_of_week']] = [
            'start' => $row['start_time'],
            'end' => $row['end_time']
        ];
    }
}

echo json_encode([
    'resume' => $resume,
    'workhours' => $workhours
]);
