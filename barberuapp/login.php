<?php
header('Content-Type: application/json');
include 'connection.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["status" => "fail", "message" => "Email and password are required"]);
    exit;
}

// A single, unified query to get user and potential employee data
$stmt = $conn->prepare("
    SELECT 
        u.userID, 
        u.firstname, 
        u.lastname, 
        u.password, 
        u.role,
        u.employeeID AS appuser_employeeID,
        e.shopID,
        b.name as shop_name
    FROM appusers u
    LEFT JOIN employee e ON u.employeeID = e.EmployeeID
    LEFT JOIN barbershops b ON e.shopID = b.shopID
    WHERE u.email = ?
    LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // Verify the password
    if (password_verify($password, $user['password'])) {
        $response = [
            "status"    => "success",
            "id"        => $user['userID'], // Always return the primary userID
            "firstname" => $user['firstname'],
            "lastname"  => $user['lastname'],
            "role"      => $user['role']
        ];

        // If the user is an employee, add employee-specific details
        if (strtolower($user['role']) === 'employee') {
            $response["employee_id"] = $user['appuser_employeeID'];
            $response["shop_id"] = $user['shopID'];
            $response["shop_name"] = $user['shop_name'];
        }

        echo json_encode($response);

    } else {
        echo json_encode(["status" => "fail", "message" => "Invalid credentials"]);
    }
} else {
    echo json_encode(["status" => "fail", "message" => "Account not found"]);
}

$stmt->close();
$conn->close();
?>