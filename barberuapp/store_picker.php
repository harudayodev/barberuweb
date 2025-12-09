<?php
header('Content-Type: application/json');
include 'connection.php';

// MODIFIED: This query now joins the review table to get the average rating and review count.
$query = "
    SELECT 
        b.shopID, 
        b.name, 
        b.address, 
        b.status, 
        b.latitude, 
        b.longitude,
        AVG(r.stars) AS average_rating,
        COUNT(r.reviewID) AS review_count
    FROM 
        barbershops b
    LEFT JOIN 
        review r ON b.shopID = r.shopID
    GROUP BY 
        b.shopID, b.name, b.address, b.status, b.latitude, b.longitude
";

$result = mysqli_query($conn, $query);

$barbershops = [];
$count = 0;

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $barbershops[] = [
            'shopID' => $row['shopID'] ?? '',
            'name' => $row['name'] ?? 'N/A',
            'address' => $row['address'] ?? 'N/A',
            'status' => $row['status'] ?? 'inactive',
            'latitude' => $row['latitude'] ?? '',
            'longitude' => $row['longitude'] ?? '',
            // ADDED: New fields for rating. The ternary handles shops with no reviews (NULL becomes 0.0).
            'average_rating' => $row['average_rating'] ? (float)$row['average_rating'] : 0.0,
            'review_count' => (int)($row['review_count'] ?? 0)
        ];
        $count++;
    }
}

$response = [
    'count' => $count,
    'barbershops' => $barbershops
];

echo json_encode($response);
mysqli_close($conn);
?>