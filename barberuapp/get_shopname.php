<?php
header('Content-Type: application/json');
include 'connection.php';

$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["status" => "fail", "message" => "Email and password required"]);
    exit;
}

// 1️⃣ Check appusers
$stmt = $conn->prepare("SELECT userID, firstname, lastname, password, role 
                        FROM appusers 
                        WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (password_verify($password, $row['password'])) {
        echo json_encode([
            "status"    => "success",
            "id"        => $row['userID'],
            "firstname" => $row['firstname'],
            "lastname"  => $row['lastname'],
            "role"      => $row['role']
        ]);
    } else {
        echo json_encode(["status" => "fail", "message" => "Invalid password"]);
    }
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// 2️⃣ Check employees
$sql = "SELECT e.EmployeeID, e.FirstName, e.LastName, e.Username, e.Password, e.shopID, s.shopName
        FROM employee e
        JOIN barbershops s ON e.shopID = s.shopID
        WHERE e.Username=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $emp = $result->fetch_assoc();
    if (password_verify($password, $emp['Password'])) {
        echo json_encode([
            "status"      => "success",
            "id"          => $emp['EmployeeID'],
            "firstname"   => $emp['FirstName'],
            "lastname"    => $emp['LastName'],
            "role"        => "admin",
            "employee_id" => $emp['EmployeeID'],
            "shop_id"     => $emp['shopID'],
            "shop_name"   => $emp['shopName']
        ]);
    } else {
        echo json_encode(["status" => "fail", "message" => "Invalid password"]);
    }
} else {
    echo json_encode(["status" => "fail", "message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>
