<?php
header('Content-Type: application/json');
include 'connection.php';

$shopID = $_GET['shopID'] ?? '';
$haircutID = $_GET['haircutID'] ?? '';

if (empty($shopID) || empty($haircutID)) {
    echo json_encode(['available' => false, 'error' => 'Missing parameters']);
    exit;
}

$query = "SELECT * FROM haircut WHERE HaircutID = ? AND shopID = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $haircutID, $shopID);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo json_encode(['available' => true]);
} else {
    echo json_encode(['available' => false]);
}

mysqli_close($conn);
?>
