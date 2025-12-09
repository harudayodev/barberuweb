<?php
require_once "Connection.php";
$haircutID = isset($_GET['haircut_id']) ? (int)$_GET['haircut_id'] : 0;
$barbers = [];
if ($haircutID > 0) {
    $result = $conn->query("SELECT EmployeeID FROM haircut_barbers WHERE HaircutID = $haircutID");
    while ($row = $result->fetch_assoc()) {
        $barbers[] = $row['EmployeeID'];
    }
}
header('Content-Type: application/json');
echo json_encode($barbers);