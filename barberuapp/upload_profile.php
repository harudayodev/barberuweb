<?php
header('Content-Type: application/json');
include 'connection.php';

$userID = $_POST['id'] ?? '';
if (empty($userID)) {
    echo json_encode(["status" => "fail", "message" => "User ID missing"]);
    exit;
}

$encodedData = $_POST['profile'] ?? '';
if (empty($encodedData)) {
    echo json_encode(["status" => "fail", "message" => "No image data"]);
    exit;
}

// Decode Base64 image
$decodedData = base64_decode($encodedData);
$filename = "user_" . $userID . "_" . time() . ".jpg";
$targetDir = "uploads/";
$targetFile = $targetDir . $filename;

// Delete old photo if exists
$query = $conn->prepare("SELECT profilephoto FROM appusers WHERE userID=?");
$query->bind_param("i", $userID);
$query->execute();
$result = $query->get_result();
if ($row = $result->fetch_assoc()) {
    $oldPhoto = $row['profilephoto'];
    if (!empty($oldPhoto) && file_exists($targetDir . $oldPhoto)) {
        unlink($targetDir . $oldPhoto);
    }
}
$query->close();

// Save new photo
if (file_put_contents($targetFile, $decodedData)) {
    $stmt = $conn->prepare("UPDATE appusers SET profilephoto=? WHERE userID=?");
    $stmt->bind_param("si", $filename, $userID);
    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Profile uploaded successfully",
            "url" => "https://barberucuts.site/barberuapp/uploads/" . $filename
        ]);
    } else {
        echo json_encode(["status" => "fail", "message" => "DB update failed"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Failed to save image"]);
}

$conn->close();
?>
