<?php
header('Content-Type: application/json');
include 'connection.php';

// Fetch haircut services only
$query = "SELECT HaircutID, Name FROM haircut WHERE Service = 'haircut'";
$result = mysqli_query($conn, $query);

$haircuts = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $haircuts[] = [
            'id' => $row['HaircutID'],
            'name' => $row['Name']
        ];
    }
}

echo json_encode(['haircuts' => $haircuts]);
mysqli_close($conn);
?>
