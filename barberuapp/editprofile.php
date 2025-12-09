<?php
header('Content-Type: application/json');

// Include shared DB connection
include 'connection.php';

// Cast the ID to an integer for safety. If 'id' is missing, it defaults to 0.
$id = (int) ($_POST['id'] ?? 0);
$firstname = $_POST['firstname'] ?? '';
$lastname  = $_POST['lastname'] ?? '';

// Check 1: Validate User ID
if ($id <= 0) {
    echo json_encode(["status" => "fail", "message" => "Invalid User ID. Please log in again."]);
    exit;
}

// Check 2: Validate First name
if (empty($firstname)) {
    echo json_encode(["status" => "fail", "message" => "First name is required"]);
    exit;
}

// --- NEW: Step 1: Get the user's current (old) name before updating ---
$check_stmt = $conn->prepare("SELECT firstname, lastname FROM appusers WHERE userID = ?");
if (!$check_stmt) {
    echo json_encode(["status" => "fail", "message" => "Database error (Check): " . $conn->error]);
    $conn->close();
    exit;
}
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "fail", "message" => "User ID not found in database. Please log in again."]);
    $check_stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
// Construct the old full name. Using trim() is important in case lastname is empty.
$old_fullname = trim($user['firstname'] . ' ' . $user['lastname']);
$check_stmt->close();


// --- Step 2: Perform the Update on the main 'appusers' table ---
$stmt = $conn->prepare("UPDATE appusers SET firstname=?, lastname=? WHERE userID=?");
if (!$stmt) {
    echo json_encode(["status" => "fail", "message" => "Database error (Update User): " . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("ssi", $firstname, $lastname, $id);

if ($stmt->execute()) {
    $user_updated = $stmt->affected_rows > 0;
    $stmt->close(); // Close the first statement

    // --- NEW: Step 3: Update the name in the 'sales' history table as well ---
    $new_fullname = trim($firstname . ' ' . $lastname);

    // Only run this second update if the name has actually changed to avoid unnecessary database work
    if ($old_fullname !== $new_fullname) {
        $sales_stmt = $conn->prepare("UPDATE sales SET sales_name = ? WHERE sales_name = ?");
        if ($sales_stmt) {
            $sales_stmt->bind_param("ss", $new_fullname, $old_fullname);
            $sales_stmt->execute();
            $sales_stmt->close();
        }
        // We can optionally add error handling here if the sales update fails,
        // but for now, we prioritize the main profile update.
    }
    
    if ($user_updated) {
        echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
    } else {
        echo json_encode(["status" => "success", "message" => "Profile details are already up to date"]);
    }
} else {
    echo json_encode(["status" => "fail", "message" => "Execution error: " . $stmt->error]);
    $stmt->close();
}

$conn->close();
?>