<?php
header('Content-Type: application/json');
include 'connection.php';

// --- Get data from POST request ---
$firstname = $_POST['app_firstname'] ?? '';
$lastname = $_POST['app_lastname'] ?? '';
$contact = $_POST['app_contact'] ?? '';
$address = $_POST['app_address'] ?? '';
$email = $_POST['app_emailadd'] ?? '';
$shopID = $_POST['shopID'] ?? '';
$encodedResume = $_POST['app_resume'] ?? ''; // Base64 encoded image string

// --- Basic Validation ---
if (empty($firstname) || empty($lastname) || empty($contact) || empty($address) || empty($email) || empty($shopID) || empty($encodedResume)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

// --- Image Handling ---
$targetDir = "resumebarber/";

// Create the directory if it doesn't exist
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

// Decode the Base64 string to an image
$decodedResume = base64_decode($encodedResume);

// Generate a unique filename to prevent overwriting
$filename = "resume_" . uniqid() . "_" . time() . ".jpg";
$targetFile = $targetDir . $filename;

// Save the file to the server
if (file_put_contents($targetFile, $decodedResume)) {
    // --- Database Insertion ---
    // The status will be NULL by default as requested
    $stmt = $conn->prepare(
        "INSERT INTO employee_applications (app_firstname, app_lastname, app_resume, app_contact, app_address, app_emailadd, shopID) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    // 'ssssssi' corresponds to the data types: String, String, String, String, String, String, Integer
    $stmt->bind_param("ssssssi", $firstname, $lastname, $filename, $contact, $address, $email, $shopID);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Application submitted successfully!']);
    } else {
        // If DB insert fails, delete the uploaded file to clean up
        unlink($targetFile);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload resume file.']);
}

$conn->close();
?>