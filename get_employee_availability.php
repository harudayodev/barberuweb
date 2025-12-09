<?php
require_once 'Connection.php';
header('Content-Type: application/json');

// Fetch active employees and their available days
$sql = "SELECT id, CONCAT(first_name, ' ', last_name) AS name, available_days FROM employees WHERE status = 'active'";
$result = $conn->query($sql);
$employees = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Assume available_days is stored as comma-separated values (e.g., '2025-10-10,2025-10-12')
        $days = array_map('trim', explode(',', $row['available_days']));
        $employees[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'available_days' => $days
        ];
    }
}
echo json_encode($employees);
?>
