<?php
header('Content-Type: application/json');
include 'connection.php';

$firstname = $_POST['firstname'] ?? '';
$lastname  = $_POST['lastname'] ?? '';
$email     = $_POST['email'] ?? '';
$password  = $_POST['password'] ?? '';

if (!$firstname || !$lastname || !$email || !$password) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// ✅ Check if email already exists in appusers
$check = $conn->prepare("SELECT userID FROM appusers WHERE email=? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// ✅ Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// ✅ Check if the email matches an existing employee
$empQuery = $conn->prepare("SELECT EmployeeID FROM employee WHERE Email=? LIMIT 1");
$empQuery->bind_param("s", $email);
$empQuery->execute();
$empResult = $empQuery->get_result();

$employeeID = null;
if ($row = $empResult->fetch_assoc()) {
    $employeeID = $row['EmployeeID'];
}
$empQuery->close();

// ✅ Assign role accordingly
$role = $employeeID ? "employee" : "user";

// ✅ Insert user with employeeID (if exists)
$stmt = $conn->prepare("
    INSERT INTO appusers (firstname, lastname, email, password, role, employeeID)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("sssssi", $firstname, $lastname, $email, $hashedPassword, $role, $employeeID);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => $employeeID
            ? "Registration successful as Employee"
            : "Registration successful as User",
        "role" => $role
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
