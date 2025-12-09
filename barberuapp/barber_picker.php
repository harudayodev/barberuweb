<?php
require 'connection.php'; // contains $conn

header('Content-Type: application/json');

$response = array();

$shopID = isset($_GET['shopID']) ? intval($_GET['shopID']) : 0;

// MODIFIED: This query now joins the review table to get the average rating and review count for each employee.
$sql = "
    SELECT 
        e.EmployeeID,
        e.FirstName,
        e.LastName,
        GROUP_CONCAT(DISTINCT ea.day_of_week ORDER BY FIELD(ea.day_of_week,
            'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') SEPARATOR ', ') AS available_days,
        e.Status,
        AVG(r.stars) AS average_rating,
        COUNT(DISTINCT r.reviewID) AS review_count
    FROM employee e
    LEFT JOIN employee_availability ea ON e.EmployeeID = ea.employee_id
    LEFT JOIN review r ON e.EmployeeID = r.EmployeeID
    WHERE e.shopID = ? AND e.Status = 'active'
    GROUP BY e.EmployeeID, e.FirstName, e.LastName, e.Status
    ORDER BY e.FirstName ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $shopID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $barbers = array();
    while ($row = $result->fetch_assoc()) {
        $barbers[] = array(
            "employeeID" => $row['EmployeeID'],
            "firstName" => $row['FirstName'],
            "lastName" => $row['LastName'],
            "availabilityDay" => $row['available_days'] ?: 'No schedule',
            "isAvailable" => ($row['Status'] === 'active'),
            // ADDED: New fields for rating. Handle NULL case for barbers with no reviews.
            "average_rating" => $row['average_rating'] ? (float)$row['average_rating'] : 0.0,
            "review_count" => (int)($row['review_count'] ?? 0)
        );
    }
    $response['count'] = count($barbers);
    $response['barbers'] = $barbers;
} else {
    $response['count'] = 0;
    $response['barbers'] = [];
}

echo json_encode($response);
$conn->close();
?>