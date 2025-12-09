<?php
header('Content-Type: application/json');

// Include shared DB connection
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopID = $_POST['shopID'] ?? '';

    if (empty($shopID)) {
        echo json_encode(["status" => "fail", "message" => "shopID is required."]);
        $conn->close();
        exit();
    }

    $stmt = $conn->prepare("SELECT FirstName, LastName FROM employee WHERE shopID = ? AND Status = 'active'");
    $stmt->bind_param("i", $shopID);
    $stmt->execute();
    $result = $stmt->get_result();

    $barbers = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $barbers[] = $row;
        }
    }

    echo json_encode($barbers);

    $stmt->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
}

$conn->close();
?>
