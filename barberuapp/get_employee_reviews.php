<?php
header('Content-Type: application/json');
include 'connection.php'; // Your database connection file

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
    exit();
}

// Get the employeeID from the app's request
$employeeID = $_POST['employeeID'] ?? 0;

if (empty($employeeID)) {
    echo json_encode(["status" => "fail", "message" => "Employee ID is required."]);
    exit();
}

// This is our special query to get reviews and the customer's name
// It joins the 'review' and 'appusers' tables together!
$stmt = $conn->prepare("
    SELECT 
        r.stars,
        r.reviewcontent,
        r.reviewdate,
        CONCAT(u.firstname, ' ', u.lastname) AS customerName
    FROM 
        review r
    JOIN 
        appusers u ON r.userID = u.userID
    WHERE 
        r.EmployeeID = ?
    ORDER BY 
        r.reviewdate DESC
");

$stmt->bind_param("i", $employeeID);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

$stmt->close();
$conn->close();

// Send the beautiful data back to the app
echo json_encode([
    "status" => "success",
    "reviews" => $reviews
]);
?>