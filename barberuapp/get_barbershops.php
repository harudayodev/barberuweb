<?php
header('Content-Type: application/json');

// Include shared DB connection
include 'connection.php';

$sql = "SELECT shopID, name FROM barbershops WHERE status = 'active'";
$result = $conn->query($sql);

$barbershops = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $barbershops[] = $row;
    }
}

echo json_encode($barbershops);

$conn->close();
?>
