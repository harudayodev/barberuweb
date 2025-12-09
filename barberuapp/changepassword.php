<?php
header('Content-Type: application/json');

// Include shared DB connection
include 'connection.php';

if (!isset($_POST['id'], $_POST['current_password'], $_POST['new_password'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$id       = intval($_POST['id']); 
$current  = $_POST['current_password'];
$new      = $_POST['new_password'];

$stmt = $conn->prepare("SELECT Password FROM appusers WHERE userID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

if (!password_verify($current, $row['Password'])) {
    echo json_encode(["status" => "error", "message" => "Current password is incorrect"]);
    exit;
}

$hashed = password_hash($new, PASSWORD_BCRYPT);

$update = $conn->prepare("UPDATE appusers SET Password = ? WHERE userID = ?");
$update->bind_param("si", $hashed, $id);

if ($update->execute()) {
    echo json_encode(["status" => "success", "message" => "Password updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update password"]);
}

$update->close();
$conn->close();
?>
