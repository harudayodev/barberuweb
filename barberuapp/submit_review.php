<?php
header('Content-Type: application/json');
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
    exit();
}

// Get POST values safely
$userID = $_POST['userID'] ?? 0;
$shopID = $_POST['shopID'] ?? 0;
$stars = $_POST['stars'] ?? 0.0;
$reviewContent = trim($_POST['reviewcontent'] ?? '');
$barberName = trim($_POST['barber'] ?? '');

if (empty($userID) || empty($shopID) || empty($stars) || empty($barberName)) {
    echo json_encode([
        "status" => "fail",
        "message" => "Missing required fields: userID, shopID, stars, or barber."
    ]);
    exit();
}

// CHANGE: First, find the EmployeeID for the given barber. This is crucial for uniqueness.
$EmployeeID = null;
$empStmt = $conn->prepare("SELECT EmployeeID FROM employee WHERE CONCAT(FirstName, ' ', LastName) = ? AND shopID = ? LIMIT 1");
$empStmt->bind_param("si", $barberName, $shopID);
$empStmt->execute();
$empStmt->bind_result($foundEmployeeID);
if ($empStmt->fetch()) {
    $EmployeeID = $foundEmployeeID;
}
$empStmt->close();

if ($EmployeeID === null) {
    echo json_encode(["status" => "fail", "message" => "Could not find the specified barber."]);
    $conn->close();
    exit();
}

// CHANGE: Check for an existing review using userID, shopID, AND EmployeeID.
$checkStmt = $conn->prepare("SELECT reviewID FROM review WHERE userID = ? AND shopID = ? AND EmployeeID = ? LIMIT 1");
$checkStmt->bind_param("iii", $userID, $shopID, $EmployeeID);
$checkStmt->execute();
$checkStmt->store_result();
$hasExisting = $checkStmt->num_rows > 0;
$checkStmt->close();

if ($hasExisting) {
    // CHANGE: Update the existing review using the more specific WHERE clause.
    $updateStmt = $conn->prepare("
        UPDATE review 
        SET stars = ?, reviewcontent = ?, reviewdate = NOW()
        WHERE userID = ? AND shopID = ? AND EmployeeID = ?
    ");
    $updateStmt->bind_param("dsiii", $stars, $reviewContent, $userID, $shopID, $EmployeeID);

    if ($updateStmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Review updated successfully!",
            "data" => [ "stars" => $stars, "reviewcontent" => $reviewContent ]
        ]);
    } else {
        echo json_encode(["status" => "fail", "message" => "Could not update review."]);
    }
    $updateStmt->close();

} else {
    // Insert new review
    $insertStmt = $conn->prepare("
        INSERT INTO review (userID, shopID, stars, reviewcontent, reviewdate, EmployeeID)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $insertStmt->bind_param("iidsi", $userID, $shopID, $stars, $reviewContent, $EmployeeID);

    if ($insertStmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Review submitted successfully!",
            "data" => [ "stars" => $stars, "reviewcontent" => $reviewContent ]
        ]);
    } else {
        echo json_encode(["status" => "fail", "message" => "Could not submit review."]);
    }
    $insertStmt->close();
}

$conn->close();
?>