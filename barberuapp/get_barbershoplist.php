<?php
header('Content-Type: application/json');
include 'connection.php';

// Select only the ID and name, and order them alphabetically
$query = "SELECT shopID, name FROM barbershops ORDER BY name ASC";
$result = mysqli_query($conn, $query);

$barbershops = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Add each barbershop to the array
        $barbershops[] = [
            'shopID' => $row['shopID'],
            'name' => $row['name']
        ];
    }
}

// Directly output the JSON array of barbershops
echo json_encode($barbershops);
mysqli_close($conn);
?>