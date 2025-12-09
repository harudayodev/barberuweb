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

    $stmt = $conn->prepare("SELECT Name, Service FROM haircut WHERE shopID = ? AND status = 'active'");
    $stmt->bind_param("i", $shopID);
    $stmt->execute();
    $result = $stmt->get_result();

    $haircut_names = []; 
    $color_names   = []; 

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $name    = $row['Name'];
            $service = $row['Service'];

            if (!in_array($name, $haircut_names)) {
                $haircut_names[] = $name;
            }
            if (stripos($service, 'color') !== false) {
                if (!in_array($name, $color_names)) {
                    $color_names[] = $name;
                }
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "haircut_names" => $haircut_names,
        "color_names"   => $color_names
    ]);

    $stmt->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
}

$conn->close();
?>
