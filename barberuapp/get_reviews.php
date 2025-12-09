<?php
header('Content-Type: application/json');
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = $_POST['userID'] ?? 0;

    if (empty($userID)) {
        echo json_encode(["status" => "fail", "message" => "Missing userID."]);
        exit();
    }

    // CHANGE: Join with the employee table to get the barber's name for each review.
    $stmt = $conn->prepare("
        SELECT 
            r.shopID, 
            r.stars, 
            r.reviewcontent,
            CONCAT(e.FirstName, ' ', e.LastName) AS barberName
        FROM review r
        LEFT JOIN employee e ON r.EmployeeID = e.EmployeeID
        WHERE r.userID = ?
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['barberName'])) {
            // CHANGE: Create a unique composite key (e.g., "1-John Doe").
            $key = $row['shopID'] . '-' . $row['barberName'];
            $reviews[$key] = [
                "stars" => (float)$row['stars'],
                "reviewcontent" => $row['reviewcontent']
            ];
        }
    }

    echo json_encode([
        "status" => "success",
        "reviews" => $reviews
    ]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method."]);
}
?>