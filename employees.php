<?php

// --- Handle marking employee unavailable for a specific date ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_unavailable']) &&
    isset($_POST['unavailable_employee_id']) &&
    isset($_POST['unavailable_date'])
) {
    require_once "Connection.php";
    $empId = (int)$_POST['unavailable_employee_id'];
    $date = $conn->real_escape_string($_POST['unavailable_date']);
    // Only insert if not already unavailable for that date
    $check = $conn->query("SELECT id FROM employee_unavailability WHERE employee_id = $empId AND unavailable_date = '$date'");
    if ($check && $check->num_rows === 0) {
        $conn->query("INSERT INTO employee_unavailability (employee_id, unavailable_date) VALUES ($empId, '$date')");
    }
    // Respond for AJAX
    echo 'success';
    exit();
}

// --- New: Handle marking employee available for a specific date ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_available']) &&
    isset($_POST['available_employee_id']) &&
    isset($_POST['available_date'])
) {
    require_once "Connection.php";
    $empId = (int)$_POST['available_employee_id'];
    $date = $conn->real_escape_string($_POST['available_date']);
    // Remove the unavailable record for that date
    $conn->query("DELETE FROM employee_unavailability WHERE employee_id = $empId AND unavailable_date = '$date'");
    echo 'success';
    exit();
}

// Use built-in mailer via send_email.php
require_once 'send_email.php';

session_start();
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Database connection
require_once "Connection.php";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$AdminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : null;

// Fetch barbershop name for sidebar
$barbershopName = '';
if ($conn->connect_error) {
    $barbershopName = '';
} else {
    if (isset($_SESSION['barbershopID'])) {
        $barbershopID = (int)$_SESSION['barbershopID'];
        $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $barbershopID");
        if ($result && $row = $result->fetch_assoc()) {
            $barbershopName = $row['name'];
        }
    } elseif (isset($_SESSION['barberID'])) {
        $barberID = (int)$_SESSION['barberID'];
        $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $barberID");
        if ($result && $row = $result->fetch_assoc()) {
            $barbershopName = $row['name'];
        }
    }
    // Fetch count of new queue notifications (items with status 'In Queue')
    $newQueueCount = 0;
    $adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM queue WHERE status = 'In Queue' AND adminID = $adminID AND shopID = $barbershopID");
    if ($result && $row = $result->fetch_assoc()) {
        $newQueueCount = (int)$row['cnt'];
    }
}


// Handle add/edit employee
$error_message = '';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['emp_firstname']) &&
    $AdminID &&
    isset($_SESSION['barbershopID'])
) {
    $firstname = $conn->real_escape_string($_POST['emp_firstname']);
    $contactno = isset($_POST['emp_contactno']) ? $conn->real_escape_string($_POST['emp_contactno']) : '';
    $lastname = $conn->real_escape_string($_POST['emp_lastname']);
    $address = $conn->real_escape_string($_POST['emp_address']);
    $email = $conn->real_escape_string($_POST['emp_username']);
    $commission = isset($_POST['emp_commission']) ? floatval($_POST['emp_commission']) : 0;
    $shopID = (int)$_SESSION['barbershopID'];
    $editEmpID = isset($_POST['edit_employee_id']) && $_POST['edit_employee_id'] !== '' ? (int)$_POST['edit_employee_id'] : null;
    // Handle resume upload
    $resume_filename = '';
    if (isset($_FILES['emp_resume']) && $_FILES['emp_resume']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $file_info = pathinfo($_FILES['emp_resume']['name']);
        $ext = strtolower($file_info['extension']);
        if (in_array($ext, $allowed_ext)) {
            $resume_filename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', $file_info['basename']);
            $target_path = 'uploads/' . $resume_filename;
            move_uploaded_file($_FILES['emp_resume']['tmp_name'], $target_path);
        }
    }
    if ($editEmpID) {
        // Edit mode
    $updateFields = "FirstName='$firstname', LastName='$lastname', ContactNo='$contactno', Address='$address', Email='$email', Commission='$commission'";
        if ($resume_filename) {
            $updateFields .= ", resume='$resume_filename'";
        } // If no new file uploaded, do not update resume field (keep old value)
        if (!empty($_POST['emp_password'])) {
            $plain_password = $_POST['emp_password'];
            if (strlen($plain_password) < 8) {
                $error_message = 'Password must be at least 8 characters long!';
            } else if (!preg_match('/[A-Z]/', $plain_password)) {
                $error_message = 'Password must contain at least one uppercase letter!';
            } else if (!preg_match('/[a-z]/', $plain_password)) {
                $error_message = 'Password must contain at least one lowercase letter!';
            } else if (!preg_match('/[0-9]/', $plain_password)) {
                $error_message = 'Password must contain at least one number!';
            }
            if (!$error_message) {
                $password = password_hash($plain_password, PASSWORD_DEFAULT);
                $updateFields .= ", Password='$password'";
            }
        }
        if (!$error_message) {
            $sql = "UPDATE employee SET $updateFields WHERE EmployeeID=$editEmpID AND AdminID=$AdminID AND shopID=$shopID";
            if (!$conn->query($sql)) { die('Error: ' . $conn->error); }
            // Update availability
            $conn->query("DELETE FROM employee_availability WHERE employee_id=$editEmpID");
            $conn->query("DELETE FROM employee_workhours WHERE employee_id=$editEmpID");
            $workhours_start = isset($_POST['emp_workhours_start']) ? $conn->real_escape_string($_POST['emp_workhours_start']) : '09:00';
            $workhours_end = isset($_POST['emp_workhours_end']) ? $conn->real_escape_string($_POST['emp_workhours_end']) : '17:00';
            if (isset($_POST['available_days']) && is_array($_POST['available_days'])) {
                foreach ($_POST['available_days'] as $day) {
                    $day = $conn->real_escape_string($day);
                    $conn->query("INSERT INTO employee_availability (employee_id, day_of_week) VALUES ($editEmpID, '$day')");
                    $conn->query("INSERT INTO employee_workhours (employee_id, day_of_week, start_time, end_time) VALUES ($editEmpID, '$day', '$workhours_start', '$workhours_end')");
                }
            }
            // Send update email to employee
            if (function_exists('employee_update')) {
                employee_update($email, $firstname . ' ' . $lastname);
            }
            header("Location: employees.php");
            exit();
        }
    } else {
        // Add mode
        // Auto-generate 8-character password
        function generateRandomPassword($length = 8) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $password;
        }
        $plain_password = generateRandomPassword(8);
        $password = password_hash($plain_password, PASSWORD_DEFAULT);
        $insertSuccess = true;
        $sql = "INSERT INTO employee (FirstName, LastName, ContactNo, Address, Email, resume, Commission, Password, Status, AdminID, shopID) VALUES ('$firstname', '$lastname', '$contactno', '$address', '$email', '$resume_filename', '$commission', '$password', 'active', $AdminID, $shopID)";
        if (!$conn->query($sql)) {
            error_log('Error inserting into employee: ' . $conn->error);
            $insertSuccess = false;
        } else {
            $newEmpID = $conn->insert_id;
            // Save availability and work hours
            $workhours_start = isset($_POST['emp_workhours_start']) ? $conn->real_escape_string($_POST['emp_workhours_start']) : '09:00';
            $workhours_end = isset($_POST['emp_workhours_end']) ? $conn->real_escape_string($_POST['emp_workhours_end']) : '17:00';
            if (isset($_POST['available_days']) && is_array($_POST['available_days'])) {
                foreach ($_POST['available_days'] as $day) {
                    $day = $conn->real_escape_string($day);
                    $conn->query("INSERT INTO employee_availability (employee_id, day_of_week) VALUES ($newEmpID, '$day')");
                    $conn->query("INSERT INTO employee_workhours (employee_id, day_of_week, start_time, end_time) VALUES ($newEmpID, '$day', '$workhours_start', '$workhours_end')");
                }
            }
        }

        // Insert into appusers table
        $profilephoto = '';
        $role = 'employee';
        $sql2 = "INSERT INTO appusers (firstname, lastname, email, password, profilephoto, role) VALUES ('$firstname', '$lastname', '$email', '$password', '$profilephoto', '$role')";
        if (!$conn->query($sql2)) { error_log('Error inserting into appusers: ' . $conn->error); }

        // Debug logging for email parameters
        error_log('DEBUG: send_employee_acceptance_email params: email=' . $email . ', name=' . ($firstname . ' ' . $lastname) . ', username=' . $email . ', password=' . $plain_password);

        // Send employee acceptance email using send_email.php and check result
        require_once 'send_email.php';
        $email_sent = send_employee_acceptance_email(
            $email,
            $firstname . ' ' . $lastname,
            $email,
            $plain_password
        );
        if (!$email_sent) {
            error_log('Failed to send acceptance email to ' . $email);
            $error_message = 'Failed to send acceptance email.';
        }
        // Redirect only if at least one insert succeeded and email was sent
        if ($email_sent && $insertSuccess) {
            header("Location: employees.php");
            exit();
        }
    }
}

// Handle toggle employee status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_employee_id']) && $AdminID) {
    $empID = (int)$_POST['toggle_employee_id'];
    // Only allow 'active' or 'resigned' status from the button
    $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'resigned';
    $conn->query("UPDATE employee SET Status = '$newStatus' WHERE EmployeeID = $empID AND AdminID = $AdminID");
    header("Location: employees.php");
    exit();
}

// Handle remove employee
// Archive employee instead of removing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_employee_id']) && $AdminID) {
    $empID = (int)$_POST['archive_employee_id'];
    $conn->query("UPDATE employee SET Status = 'archived' WHERE EmployeeID = $empID AND AdminID = $AdminID");
    header("Location: employees.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['employees'] ?> | Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        background-color: #e6f7ff;
        font-family: 'Poppins', sans-serif;
    }
    .main-header {
        background: linear-gradient(90deg, #00aaff 60%, #cceeff 100%);
        padding: 10px 0 8px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        position:fixed; top:0; left:0; width:100vw; z-index:1000;
    }
    .sidebar {
        background:#fff;
        border-radius:16px;
        box-shadow:0 2px 12px rgba(0,0,0,0.07);
        padding:32px 24px;
        min-width:220px;
        max-width:260px;
        flex:0 0 220px;
        margin-top:0;
    }
    .sidebar-header {
        display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; width:100%;
    }
    .sidebar-header img {
        height:60px; display:block; margin:0 auto;
    }
    .nav-links {
        list-style:none; padding:0; margin:32px 0 0 0; display:flex; flex-direction:column; gap:18px;
    }
    .nav-links li a {
        color:#008bcc; font-weight:500; text-decoration:none; transition: background 0.2s, color 0.2s;
        padding:6px 18px; border-radius:8px;
    }
    .nav-links li a.active, .nav-links li a:hover {
        background:#e6f7ff; color:#0077b6;
    }
    .nav-links .logout a { color:#dc3545; }
    .main-content {
        flex:1; background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:32px 32px 40px 32px; min-width:0;
        margin-top:0;
    }
    /* Service card design from haircuts.php */
    .service-card-list {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin-top: 18px;
        width: 100%;
    }
    .service-card {
        background: linear-gradient(90deg, #e6f7ff 60%, #cceeff 100%);
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(0,170,255,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
        padding: 0;
        min-width: 0;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        border: none;
        transition: box-shadow 0.2s, transform 0.2s;
        margin-bottom: 2px;
        overflow: hidden;
    }
    .service-card:hover {
        box-shadow: 0 8px 24px rgba(0,170,255,0.18), 0 2px 12px rgba(0,0,0,0.08);
        transform: translateY(-2px) scale(1.01);
    }
    .service-card-title {
        font-size: 1.18rem;
        font-weight: 600;
        color: #000000ff;
        margin-left: 32px;
        margin-right: 0;
        flex: 1;
        text-align: left;
        padding: 18px 0;
        letter-spacing: 0.5px;
        text-shadow: 0 1px 2px #cceeff;
    }
    .service-card-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-right: 32px;
    }
    .service-action-btn {
        background: #00aaff;
        border: none;
        border-radius: 25%;
        width: 25px;
        height: 25px;
        padding: 0;
        cursor: pointer;
        font-size: 1.08rem;
        color: #fff;
        transition: background 0.2s, box-shadow 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,170,255,0.10);
    }
    .service-action-btn.info { background: #00aaff; }
    .service-action-btn.edit { background: #ffc107; color: #fff; }
    .service-action-btn.archive { background: #6c757d; color: #fff; }
    .service-action-btn:hover { filter: brightness(0.95); box-shadow: 0 4px 12px rgba(0,170,255,0.18); }
    .queue-container {
        width:100%;
    }
    .queue-list {
        background:#f7fbff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.04); padding:24px 18px; margin-top:18px;
    }
    table {
        width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,0.04);
    }
    th, td {
        padding:10px 8px; text-align:center; border-bottom:1px solid #e0e0e0;
    }
    th {
        background:#e6f7ff; color:#0077b6; font-weight:600;
    }
    tr:last-child td { border-bottom:none; }
    /* Modal styles for Add Employee */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100vw;
        height: 100vh;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    .modal-content {
        background-color: #fff;
        padding: 28px 36px 24px 36px;
        border: none;
        width: 540px;
        max-width: 98vw;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 4px 24px rgba(0,0,0,0.15);
        margin: 0;
    }
    .close {
        color: #aaa;
        position: absolute;
        right: 16px;
        top: 8px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover,
    .close:focus {
        color: #000;
        text-decoration: none;
        cursor: pointer;
    }
    .modal-content h2 {
        margin-top: 0;
        margin-bottom: 18px;
        font-size: 1.4rem;
        font-weight: 700;
        text-align: center;
    }
    .modal-content form {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 12px 10px;
        align-items: center;
        width: 100%;
    }
    .modal-content form label {
        text-align: right;
        font-weight: 500;
        font-size: 0.98rem;
        margin-right: 4px;
    }
    .modal-content form input[type="text"],
    .modal-content form input[type="password"],
    .modal-content form input[type="email"] {
        width: 100%;
        padding: 7px 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 1rem;
        margin-bottom: 2px;
        margin-left: 2px;
        box-sizing: border-box;
        grid-column: 2 / 3;
    }
    .modal-content form .days-label {
        text-align: right;
        font-weight: 500;
        font-size: 0.98rem;
        margin-bottom: 2px;
        align-self: start;
        padding-top: 2px;
    }
    .modal-content form .days-checkboxes {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 12px;
        padding-bottom: 6px;
        justify-content: flex-start;
        width: 100%;
        grid-column: 2 / 3;
    }
    .modal-content form .days-checkboxes label {
        text-align: left;
        font-weight: 400;
        margin: 0;
        font-size: 0.97rem;
    }
    .modal-content form button[type="submit"] {
        grid-column: 1 / 3;
        margin-top: 10px;
        background: #00aaff;
        color: #fff;
        border: none;
        padding: 10px 0;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        width: 100%;
        max-width: 260px;
        justify-self: center;
        transition: background 0.2s;
    }
    .modal-content form button[type="submit"]:hover {
        background: #0077b6;
    }
    .modal-content form .error-message {
        grid-column: 1 / 3;
        color: red;
        margin-bottom: 10px;
        font-weight: bold;
        text-align: center;
    }
    /* Center resign button at bottom of modal */
    #employeeDetailsModal .modal-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        min-height: 340px;
        padding-bottom: 70px;
    }
    #employeeDetailsContent {
        width: 100%;
    }
    #resignEmployeeBtn, #reemployEmployeeBtn {
        display: none;
        margin: 0 auto;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: 24px;
        width: 90%;
        max-width: 260px;
        border: none;
        padding: 10px 0;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        z-index: 2;
    }
    #resignEmployeeBtn {
        background: #ff9800;
        color: #fff;
    }
    #reemployEmployeeBtn {
        background: #28a745;
        color: #fff;
    }
    /* Employee card action buttons size and color */
    .employee-card button.view-details-btn,
    .employee-card button.edit-employee-btn,
    .employee-card button[type="submit"] {
        width: 25px !important;
        height: 25px !important;
        min-width: 25px !important;
        min-height: 25px !important;
        max-width: 25px !important;
        max-height: 25px !important;
        padding: 0 !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1em !important;
    }
    .employee-card button[type="submit"] {
        background: #888 !important;
        color: #fff !important;
    }
    /* Archive button override */
    .employee-card button[type="submit"] i.fa-box-archive {
        color: #fff !important;
    }
    /* Calendar and Search button size */
    #openCalendarModal,
    .search-button {
        width: 30px !important;
        height: 30px !important;
        min-width: 30px !important;
        min-height: 30px !important;
        max-width: 30px !important;
        max-height: 30px !important;
        padding: 0 !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px !important;
    }
    /* Search field and button alignment */
    .search-group {
        display: flex;
        align-items: center;
        gap: 0;
    }
    .search-group input[type="text"] {
        height: 30px;
        border-radius: 4px 0 0 4px;
        border-right: none;
    }
    .search-group .search-button {
        border-radius: 0 4px 4px 0;
        border-left: none;
    }
    /* Add Employee button height */
    #openEmployeeModal {
        height: 30px !important;
        min-height: 30px !important;
        max-height: 30px !important;
        display: flex;
        align-items: center;
    }
    /* Status filter height and width fix */
    select[name="search_status"] {
        height: 30px !important;
        min-height: 30px !important;
        max-height: 30px !important;
        min-width: 120px !important;
        padding: 0 30px 0 10px !important;
        font-size: 1rem !important;
        border-radius: 6px !important;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        background: #fff;
        border: 1.5px solid #222;
    }
    /* Mark Available button blue */
    #unavailableSubmitBtn {
        /* Default: Mark Unavailable (red) */
        background: #dc3545;
        color: #fff;
    }
    /* When Mark Available, override to blue */
    #unavailableSubmitBtn.mark-available {
        background: #00aaff !important;
        color: #fff !important;
    }

    /* Unavailable Modal Specific Styles */
    .unavailable-modal .unavailable-modal-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin: 0 auto;
        /* Add this to center the modal content horizontally */
        justify-content: center;
    }
    .unavailable-modal-info {
        margin-bottom: 16px;
        text-align: center;
        width: 100%;
        box-sizing: border-box;
        padding: 0 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center; /* Add this to center vertically in flex */
    }
    .unavailable-modal-name {
        font-weight: 700;
        font-size: 1.15em;
        margin-bottom: 4px;
        text-align: center;
        word-break: break-word;
        width: 100%;
        display: block;
    }
    .unavailable-modal-date {
        font-weight: 500;
        font-size: 1.05em;
        color: #444;
        text-align: center;
        word-break: break-word;
        width: 100%;
        display: block;
    }
    /* Center the form and its children */
    .unavailable-modal-content form {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        width: 100%;
    }
    /* Center the buttons */
    .unavailable-modal-content button[type="submit"] {
        margin-left: auto;
        margin-right: auto;
    }
    </style>
</head>
<body>
    <header class="main-header">
        <div style="max-width:1800px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;width:100%;">
            <div style="display:flex;align-items:center;gap:8px;">
                <img src="Resources/ab.png" alt="Logo" style="height:50px;width:50px;">
                <span style="font-size:2.3rem;font-weight:700;color:#fff;letter-spacing:1px;">BARBERU</span>
            </div>
        </div>
    </header>
    <div style="display:flex;max-width:1800px;width:98vw;margin:100px auto 0 auto;gap:32px;min-height:calc(100vh - 80px);">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="Resources/cd.png" alt="">
                <?php if (!empty($barbershopName)) { echo '<div style="font-weight:600;margin-top:10px;color:#00aaff;font-size:1.1em;">' . htmlspecialchars($barbershopName) . '</div>'; } ?>
            </div>
            <ul class="nav-links">
                <li style="position:relative;display:flex;align-items:center;">
                    <a href="queueing.php" class="<?= $current_page === 'queueing.php' ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-list"></i> Queue
                        <?php if ((isset($_SESSION['notifications_enabled']) && $_SESSION['notifications_enabled']) && isset($newQueueCount) && $newQueueCount > 0): ?>
                            <span style="min-width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;background:#dc3545;color:#fff;border-radius:50%;font-size:1em;font-weight:600;box-shadow:0 2px 8px rgba(220,53,69,0.12);margin-left:8px;"> <?= $newQueueCount ?> </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="management.php" class="<?= $current_page === 'management.php' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Management</a></li>
                <li><a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li><a href="options.php" class="<?= $current_page === 'options.php' ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i> Options</a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
            </ul>
        </aside>
        <main class="main-content" style="display:flex;flex-direction:column;justify-content:flex-start;min-height:calc(100vh - 120px);">
            <section class="queue-container" style="flex:1;display:flex;flex-direction:column;justify-content:flex-start;">
                <!-- Modal for Add Employee -->
                <div id="employeeModal" class="modal">
                    <div class="modal-content">
                        <span class="close" id="closeEmployeeModal">&times;</span>
                        <h2 id="employeeModalTitle">Add Employee</h2>
                        <form id="employeeForm" method="post" enctype="multipart/form-data">
                            <input type="hidden" id="edit_employee_id" name="edit_employee_id" value="">
                            <label for="emp_firstname">First Name:</label>
                            <input type="text" id="emp_firstname" name="emp_firstname" placeholder="Enter first name" required>
                            <label for="emp_lastname">Last Name:</label>
                            <input type="text" id="emp_lastname" name="emp_lastname" placeholder="Enter last name" required>
                            <label for="emp_contactno">Contact No.:</label>
                            <input type="text" id="emp_contactno" name="emp_contactno" placeholder="Enter contact number" pattern="[0-9]{11}" maxlength="11" minlength="11" required value="09" oninput="if(!this.value.startsWith('09')){this.value='09'+this.value.replace(/[^0-9]/g,'').replace(/^09/, '');}else{this.value=this.value.replace(/[^0-9]/g,'');}" style="ime-mode:disabled;">
                            <label for="emp_address">Address:</label>
                            <input type="text" id="emp_address" name="emp_address" placeholder="Enter address" required>

                            <label for="emp_username">Email:</label>
                            <input type="email" id="emp_username" name="emp_username" placeholder="Enter email" required>


                            <label for="emp_resume" style="text-align: right; font-weight: 500; font-size: 0.98rem; margin-right: 4px;">Resume:</label>
                            <div style="grid-column: 2 / 3; width: 100%;">
                                <input type="file" id="emp_resume" name="emp_resume" accept=".pdf,image/*" style="width: 100%; padding: 7px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 2px; margin-left: 2px; box-sizing: border-box;">
                                <div id="currentResumeContainer" style="margin-top: 4px; display: none;">
                                    <a id="currentResumeLink" href="#" target="_blank">View Current Resume</a>
                                </div>
                            </div>
                                <label for="emp_commission">Commission:</label>
                                <input type="number" id="emp_commission" name="emp_commission" min="0" max="100" step="0.01" placeholder="Enter commission percentage" required style="width: 100%; padding: 7px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 2px; margin-left: 2px; box-sizing: border-box; grid-column: 2 / 3;">
                                <!-- Work Hours Section -->
                                <label for="emp_workhours_start" style="text-align: right; font-weight: 500; font-size: 0.98rem; margin-right: 4px;">Work Hours:</label>
                                <div style="grid-column: 2 / 3; display: flex; gap: 10px; align-items: center;">
                                    <span style="font-weight: 500;">From</span>
                                    <input type="time" id="emp_workhours_start" name="emp_workhours_start" required style="padding: 7px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;">
                                    <span style="font-weight: 500;">To</span>
                                    <input type="time" id="emp_workhours_end" name="emp_workhours_end" required style="padding: 7px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;">
                                </div>
                            <label class="days-label">Available Days:</label>
                            <select id="available_days" name="available_days[]" multiple="multiple" style="width: 100%;">
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                            <!-- Select2 CSS -->
                            <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
                            <!-- Select2 JS -->
                            <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
                            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                            <script>
                            $(document).ready(function() {
                                $('#available_days').select2({
                                    placeholder: 'Select available days',
                                    allowClear: true
                                });
                            });
                            </script>
                            <?php if (!empty($error_message)) { ?>
                                <div class="error-message"> <?= htmlspecialchars($error_message) ?> </div>
                            <?php } ?>
                            <button type="submit" id="employeeFormSubmitBtn">Add Employee</button>
                        </form>
                    </div>
                </div>
                <!-- End Modal -->
                <div class="queue-list" style="margin-top:0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; display: flex; align-items: center;">
                            <?= $translations['employee_list'] ?>
                        </h2>
                        <form method="get" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                            <!-- Status filter first -->
                            <select name="search_status" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="active" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'active') ? 'selected' : '' ?>><?= $translations['active'] ?></option>
                                <option value="resigned" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'resigned') ? 'selected' : '' ?>><?= $translations['resigned'] ?></option>
                                <option value="archived" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'archived') ? 'selected' : '' ?>>Archived</option>
                            </select>
                            <!-- Search field and button grouped -->
                            <span class="search-group">
                                <input type="text" name="search_emp" placeholder="<?= $translations['first_name'] ?>/<?= $translations['last_name'] ?>/Email/<?= $translations['address'] ?>" value="<?= isset($_GET['search_emp']) ? htmlspecialchars($_GET['search_emp']) : '' ?>" style="padding: 8px; border-radius: 4px 0 0 4px; border: 1px solid #ccc; height:30px;">
                                <button type="submit" class="search-button" style="background: #28a745; color: #fff; border: none; border-radius: 0 4px 4px 0; cursor: pointer; font-size: 18px;display:flex;align-items:center;justify-content:center; height:30px;" title="Search">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </span>
                            <!-- Add Employee and Calendar buttons only -->
                            <button id="openEmployeeModal" type="button" style="background: #00aaff; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px; height:30px;display:flex;align-items:center;">Add Employee</button>
                            <button id="openCalendarModal" type="button" style="background: #6c63ff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-calendar-days" style="font-size: 18px;"></i>
                            </button>
                        </form>
                    </div>
                    <!-- Calendar Modal -->
                    <div id="calendarModal" class="modal">
                        <div class="modal-content" style="max-width:900px;width:98vw;">
                            <span class="close" id="closeCalendarModal">&times;</span>
                            <h2 style="text-align:center;margin-bottom:18px;">Employee Availability Calendar</h2>
                            <div id="fullCalendarContainer"></div>
                        </div>
                    </div>
                    <div class="service-card-list">
                    <?php
                    // --- FIX: Always define $where and $whereSql before use ---
$search = isset($_GET['search_emp']) ? $conn->real_escape_string($_GET['search_emp']) : '';
$status = isset($_GET['search_status']) ? $conn->real_escape_string($_GET['search_status']) : '';
$shopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
$where = [];
if ($AdminID) {
    $where[] = "AdminID = $AdminID";
}
if ($shopID) {
    $where[] = "shopID = $shopID";
}
if ($search) {
    $where[] = "(FirstName LIKE '%$search%' OR LastName LIKE '%$search%' OR Email LIKE '%$search%' OR Address LIKE '%$search%')";
}
if ($status) {
    $where[] = "Status = '$status'";
} else {
    $where[] = "Status != 'archived'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT * FROM employee $whereSql ORDER BY EmployeeID DESC";
$result = $conn->query($sql);
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $empID = (int)$row['EmployeeID'];
                            $fullName = htmlspecialchars(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
                            // Prepare all details for modal (JSON encode for JS)
                            $details = [
                                'EmployeeID' => $empID,
                                'FirstName' => $row['FirstName'] ?? '',
                                'LastName' => $row['LastName'] ?? '',
                                'ContactNo' => $row['ContactNo'] ?? '',
                                'Address' => $row['Address'] ?? '',
                                'Email' => $row['Email'] ?? '',
                                'Resume' => $row['Resume'] ?? '',
                                'Commission' => isset($row['Commission']) ? $row['Commission'] : '',
                                'Status' => $row['Status'] ?? '',
                                'Date_employed' => $row['Date_employed'] ?? '',
                            ];
                            // Get work hours per day
                            $workhours = [];
                            $whRes = $conn->query("SELECT day_of_week, start_time, end_time FROM employee_workhours WHERE employee_id = $empID");
                            if ($whRes && $whRes->num_rows > 0) {
                                while ($whRow = $whRes->fetch_assoc()) {
                                    $workhours[$whRow['day_of_week']] = [
                                        'start' => $whRow['start_time'],
                                        'end' => $whRow['end_time']
                                    ];
                                }
                            }
                            $details['WorkHours'] = $workhours;
                            // Get available days
                            $days = [];
                            $daysRes = $conn->query("SELECT day_of_week FROM employee_availability WHERE employee_id = $empID");
                            if ($daysRes) {
                                while ($d = $daysRes->fetch_assoc()) {
                                    $days[] = $d['day_of_week'];
                                }
                            }
                            $details['AvailableDays'] = $days;
                            $detailsJson = htmlspecialchars(json_encode($details), ENT_QUOTES, 'UTF-8');
                            echo '<div class="service-card">';
                            echo '<div class="service-card-title">' . $fullName . '</div>';
                            echo '<div class="service-card-actions">';
                            // More Info button (icon)
                            echo '<button type="button" class="service-action-btn info view-details-btn" data-details="' . $detailsJson . '" title="More Info"><i class="fa-solid fa-circle-info"></i></button>';
                            // Edit button (icon)
                            echo '<button type="button" class="service-action-btn edit edit-employee-btn" 
                                data-empid="' . $empID . '" 
                                data-firstname="' . htmlspecialchars($row['FirstName'] ?? '') . '" 
                                data-lastname="' . htmlspecialchars($row['LastName'] ?? '') . '" 
                                data-contactno="' . htmlspecialchars($row['ContactNo'] ?? '') . '" 
                                data-address="' . htmlspecialchars($row['Address'] ?? '') . '" 
                                data-username="' . htmlspecialchars($row['Email'] ?? '') . '" 
                                data-commission="' . htmlspecialchars($row['Commission'] ?? '') . '" 
                                data-days="' . htmlspecialchars(json_encode($days), ENT_QUOTES, 'UTF-8') . '"
                                title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>';
                            // Archive button (icon)
                            echo '<form method="post" style="margin:0;display:inline;">';
                            echo '<input type="hidden" name="archive_employee_id" value="' . $empID . '">';
                            echo '<button type="submit" class="service-action-btn archive" onclick="return confirm(\'Are you sure you want to archive this employee?\');" title="Archive"><i class="fa-solid fa-box-archive"></i></button>';
                            echo '</form>';
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="padding:24px;text-align:center;">No employees found.</div>';
                    }
                    ?>
                    </div>
                <!-- Employee Details Modal -->
                <div id="employeeDetailsModal" class="modal">
                    <div class="modal-content" style="max-width:480px;width:95vw;">
                        <span class="close" id="closeEmployeeDetailsModal">&times;</span>
                        <h2 style="text-align:center;margin-bottom:18px;">Employee Details</h2>
                        <div id="employeeDetailsContent">
                            <!-- Populated by JS -->
                        </div>
                        <!-- Resign and Re-Employ buttons -->
                        <button id="resignEmployeeBtn">
                            <i class="fa-solid fa-person-walking-arrow-right" style="margin-right:8px;"></i>Resign
                        </button>
                        <button id="reemployEmployeeBtn">
                            <i class="fa-solid fa-user-check" style="margin-right:8px;"></i>Re-Employ
                        </button>
                    </div>
                </div>
                <!-- New: Modal for Marking Unavailable/Available -->
                <div id="unavailableModal" class="modal unavailable-modal">
                    <div class="modal-content unavailable-modal-content" style="max-width:400px;width:95vw;">
                        <span class="close" id="closeUnavailableModal">&times;</span>
                        <h2 id="unavailableModalTitle" style="text-align:center;">Mark Employee Unavailable</h2>
                        <form id="unavailableForm" method="post">
                            <input type="hidden" id="unavailable_employee_id" name="unavailable_employee_id" value="">
                            <input type="hidden" id="unavailable_date" name="unavailable_date" value="">
                            <div id="unavailable_modal_info" class="unavailable-modal-info">
                                <div id="unavailable_employee_name" class="unavailable-modal-name"></div>
                                <div id="unavailable_employee_date" class="unavailable-modal-date"></div>
                            </div>
                            <button type="submit" id="unavailableSubmitBtn" style="background:#dc3545;color:#fff;border:none;padding:10px 0;border-radius:8px;cursor:pointer;font-size:1rem;font-weight:600;width:100%;">Mark Unavailable</button>
                            <button type="submit" id="availableSubmitBtn" style="display:none;background:#00aaff;color:#fff;border:none;padding:10px 0;border-radius:8px;cursor:pointer;font-size:1rem;font-weight:600;width:100%;">Mark Available</button>
                        </form>
                    </div>
                </div>
                </div>
            </section>
        </main>
    </div>
    <script>
    // --- Employee availability data for calendar ---
    <?php
    // Fetch active employees and their available days
    $calendarEmployees = [];
    $empNames = [];
    $shopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
    $sql = "SELECT EmployeeID, FirstName, LastName FROM employee WHERE Status = 'active' AND AdminID = $adminID AND shopID = $shopID";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $empID = (int)$row['EmployeeID'];
            $fullName = ($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '');
            $daysRes = $conn->query("SELECT day_of_week FROM employee_availability WHERE employee_id = $empID");
            $days = [];
            if ($daysRes) {
                while ($d = $daysRes->fetch_assoc()) {
                    $days[] = $d['day_of_week'];
                }
            }
            $calendarEmployees[] = [
                'id' => $empID,
                'name' => $fullName,
                'days' => $days
            ];
            $empNames[$empID] = $fullName;
        }
    }
    ?>
    var employeeAvailability = <?php echo json_encode($calendarEmployees); ?>;
    var empNames = <?php echo json_encode($empNames); ?>;

    // --- NEW: Output employee unavailability as JS variable ---
    <?php
    $unavailabilities = [];
    $sql = "SELECT employee_id, unavailable_date FROM employee_unavailability";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $unavailabilities[] = [
                'employee_id' => (int)$row['employee_id'],
                'date' => $row['unavailable_date']
            ];
        }
    }
    ?>
    var employeeUnavailabilities = <?php echo json_encode($unavailabilities); ?>;

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        // Modal JS for Add/Edit Employee (existing code)
        var employeeModal = document.getElementById('employeeModal');
        var openEmployeeBtn = document.getElementById('openEmployeeModal');
        var closeEmployeeSpan = document.getElementById('closeEmployeeModal');
        var employeeForm = document.getElementById('employeeForm');
        var employeeModalTitle = document.getElementById('employeeModalTitle');
        var employeeFormSubmitBtn = document.getElementById('employeeFormSubmitBtn');
        var editEmployeeIdInput = document.getElementById('edit_employee_id');
        var empFirstName = document.getElementById('emp_firstname');
        var empLastName = document.getElementById('emp_lastname');
        var empContactNo = document.getElementById('emp_contactno');
        var empAddress = document.getElementById('emp_address');
    var empUsername = document.getElementById('emp_username');
    var empResume = document.getElementById('emp_resume');
    var empWorkhoursStart = document.getElementById('emp_workhours_start');
    var empWorkhoursEnd = document.getElementById('emp_workhours_end');
    var currentResumeContainer = document.getElementById('currentResumeContainer');
    var currentResumeLink = document.getElementById('currentResumeLink');
        // Password input removed, so related JS removed

        function resetEmployeeForm() {
            employeeForm.reset();
            editEmployeeIdInput.value = '';
            employeeModalTitle.textContent = 'Add Employee';
            employeeFormSubmitBtn.textContent = 'Add Employee';
            empUsername.readOnly = false;
            if (currentResumeContainer) currentResumeContainer.style.display = 'none';
            if ($('#available_days').length) {
                $('#available_days').val(null).trigger('change');
            }
        }

        if (openEmployeeBtn && employeeModal && closeEmployeeSpan) {
            openEmployeeBtn.onclick = function() {
                resetEmployeeForm();
                employeeModal.style.display = 'flex';
            }
            closeEmployeeSpan.onclick = function() {
                employeeModal.style.display = 'none';
            }
            window.onclick = function(event) {
                if (event.target == employeeModal) {
                    employeeModal.style.display = 'none';
                }
            }
        }

        // Edit button logic (existing code)
        document.querySelectorAll('.edit-employee-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                resetEmployeeForm();
                var empId = btn.getAttribute('data-empid');
                var firstName = btn.getAttribute('data-firstname');
                var lastName = btn.getAttribute('data-lastname');
                var contactNo = btn.getAttribute('data-contactno');
                var address = btn.getAttribute('data-address');
                var username = btn.getAttribute('data-username');
                var commission = btn.getAttribute('data-commission');
                var days = JSON.parse(btn.getAttribute('data-days'));
                editEmployeeIdInput.value = empId;
                empFirstName.value = firstName;
                empLastName.value = lastName;
                empContactNo.value = contactNo;
                empAddress.value = address;
                empUsername.value = username;
                document.getElementById('emp_commission').value = commission;
                empUsername.readOnly = true;
                // Pre-fill resume if available
                var detailsBtn = btn.closest('.service-card').querySelector('.view-details-btn');
                if (detailsBtn) {
                    var details = JSON.parse(detailsBtn.getAttribute('data-details'));
                    if (details.Resume) {
                        if (currentResumeContainer && currentResumeLink) {
                            currentResumeLink.href = 'uploads/' + details.Resume;
                            currentResumeLink.textContent = 'View';
                            currentResumeContainer.style.display = 'block';
                        }
                    } else if (currentResumeContainer) {
                        currentResumeContainer.style.display = 'none';
                    }
                    // Pre-fill work hours (use first available day as default)
                    if (details.WorkHours && Object.keys(details.WorkHours).length > 0) {
                        var firstDay = Object.keys(details.WorkHours)[0];
                        var wh = details.WorkHours[firstDay];
                        if (wh) {
                            empWorkhoursStart.value = wh.start;
                            empWorkhoursEnd.value = wh.end;
                        }
                    }
                }
                // Reset Select2 available days
                if ($('#available_days').length) {
                    $('#available_days').val(days).trigger('change');
                }
                employeeModalTitle.textContent = 'Edit Details';
                employeeFormSubmitBtn.textContent = 'Save Changes';
                // Prevent details modal from showing
                if (typeof employeeDetailsModal !== 'undefined' && employeeDetailsModal) {
                    employeeDetailsModal.style.display = 'none';
                }
                employeeModal.style.display = 'flex';
            });
        });

        // --- New: Employee Details Modal logic ---
        var employeeDetailsModal = document.getElementById('employeeDetailsModal');
        var closeEmployeeDetailsModal = document.getElementById('closeEmployeeDetailsModal');
        var employeeDetailsContent = document.getElementById('employeeDetailsContent');
        var resignEmployeeBtn = document.getElementById('resignEmployeeBtn');
        var reemployEmployeeBtn = document.getElementById('reemployEmployeeBtn');
        var currentEmployeeId = null; // Track which employee is shown

        document.querySelectorAll('.view-details-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var details = JSON.parse(btn.getAttribute('data-details'));
                currentEmployeeId = details.EmployeeID;
                var html = '';
                html += '<div style="display:flex;flex-direction:column;gap:10px;">';
                html += '<div><strong>Name:</strong> ' +
                    (details.FirstName ? details.FirstName : '') + ' ' + (details.LastName ? details.LastName : '') + '</div>';
                // --- Date Employed below name ---
                html += '<div><strong>Date Employed:</strong> ' + (details.Date_employed ? details.Date_employed : '-') + '</div>';
                html += '<div><strong>Contact No.:</strong> ' + (details.ContactNo ? details.ContactNo : '-') + '</div>';
                html += '<div><strong>Address:</strong> ' + (details.Address ? details.Address : '-') + '</div>';
                html += '<div><strong>Email:</strong> ' + (details.Email ? details.Email : '-') + '</div>';
                if (details.Resume) {
                    var resumePath = 'uploads/' + details.Resume;
                    var ext = details.Resume.split('.').pop().toLowerCase();
                    if (["pdf","jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
                        html += '<div><strong>Resume:</strong> <a href="' + resumePath + '" target="_blank">View</a></div>';
                    } else {
                        html += '<div><strong>Resume:</strong> <a href="' + resumePath + '" target="_blank">View</a></div>';
                    }
                } else {
                    html += '<div><strong>Resume:</strong> -</div>';
                }
                html += '<div><strong>Commission:</strong> ' + (details.Commission !== undefined && details.Commission !== '' ? details.Commission + '%' : '-') + '</div>';
                html += '<div><strong>Available Days:</strong> ' + (details.AvailableDays && details.AvailableDays.length > 0 ? details.AvailableDays.join(', ') : '-') + '</div>';
                // Work Hours Section
                if (details.WorkHours && Object.keys(details.WorkHours).length > 0) {
                    html += '<div><strong>Work Hours:</strong><br>';
                    for (var day in details.WorkHours) {
                        var wh = details.WorkHours[day];
                        html += day + ': ' + (wh.start ? wh.start : '-') + ' to ' + (wh.end ? wh.end : '-') + '<br>';
                    }
                    html += '</div>';
                } else {
                    html += '<div><strong>Work Hours:</strong> -</div>';
                }
                html += '<div><strong>Status:</strong> ' + (details.Status ? details.Status : '-') + '</div>';
                html += '</div>';
                employeeDetailsContent.innerHTML = html;
                // Show resign or re-employ button based on status
                if (details.Status === 'resigned') {
                    resignEmployeeBtn.style.display = 'none';
                    reemployEmployeeBtn.style.display = 'block';
                } else if (details.Status && details.Status !== 'archived') {
                    resignEmployeeBtn.style.display = 'block';
                    reemployEmployeeBtn.style.display = 'none';
                } else {
                    resignEmployeeBtn.style.display = 'none';
                    reemployEmployeeBtn.style.display = 'none';
                }
                employeeDetailsModal.style.display = 'flex';
            });
        });
        if (closeEmployeeDetailsModal && employeeDetailsModal) {
            closeEmployeeDetailsModal.onclick = function() {
                employeeDetailsModal.style.display = 'none';
            }
            window.addEventListener('click', function(event) {
                if (event.target == employeeDetailsModal) {
                    employeeDetailsModal.style.display = 'none';
                }
            });
        }
        // --- Resign button logic ---
        if (resignEmployeeBtn) {
            resignEmployeeBtn.onclick = function() {
                if (!currentEmployeeId) return;
                if (!confirm('Are you sure you want to mark this employee as resigned?')) return;
                // AJAX POST to PHP
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'employees.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        employeeDetailsModal.style.display = 'none';
                        window.location.reload();
                    }
                };
                xhr.send('toggle_employee_id=' + encodeURIComponent(currentEmployeeId) + '&new_status=resigned');
            };
        }
        // --- Re-Employ button logic ---
        if (reemployEmployeeBtn) {
            reemployEmployeeBtn.onclick = function() {
                if (!currentEmployeeId) return;
                if (!confirm('Are you sure you want to re-employ this employee?')) return;
                // AJAX POST to PHP
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'employees.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        employeeDetailsModal.style.display = 'none';
                        window.location.reload();
                    }
                };
                xhr.send('toggle_employee_id=' + encodeURIComponent(currentEmployeeId) + '&new_status=active');
            };
        }

        // --- New: Mark Unavailable/Available Modal logic ---
        var unavailableModal = document.getElementById('unavailableModal');
        var closeUnavailableModal = document.getElementById('closeUnavailableModal');
        var unavailableForm = document.getElementById('unavailableForm');
        var unavailableSubmitBtn = document.getElementById('unavailableSubmitBtn');
        var availableSubmitBtn = document.getElementById('availableSubmitBtn');
        var unavailable_employee_id = document.getElementById('unavailable_employee_id');
        var unavailable_date = document.getElementById('unavailable_date');
        var unavailableModalTitle = document.getElementById('unavailableModalTitle');
        // --- updated: separate name and date elements ---
        var unavailable_employee_name = document.getElementById('unavailable_employee_name');
        var unavailable_employee_date = document.getElementById('unavailable_employee_date');

        if (closeUnavailableModal && unavailableModal) {
            closeUnavailableModal.onclick = function() {
                unavailableModal.style.display = 'none';
            }
            window.addEventListener('click', function(event) {
                if (event.target == unavailableModal) {
                    unavailableModal.style.display = 'none';
                }
            });
        }

        if (unavailableForm) {
            unavailableForm.onsubmit = function(e) {
                e.preventDefault();
                var empId = unavailable_employee_id.value;
                var date = unavailable_date.value;
                var isMarkAvailable = availableSubmitBtn.style.display === 'block';
                var postData = '';
                if (isMarkAvailable) {
                    postData = 'mark_available=1&available_employee_id=' + encodeURIComponent(empId) + '&available_date=' + encodeURIComponent(date);
                } else {
                    postData = 'mark_unavailable=1&unavailable_employee_id=' + encodeURIComponent(empId) + '&unavailable_date=' + encodeURIComponent(date);
                }
                unavailableSubmitBtn.disabled = true;
                availableSubmitBtn.disabled = true;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'employees.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        unavailableSubmitBtn.disabled = false;
                        availableSubmitBtn.disabled = false;
                        unavailableModal.style.display = 'none';
                        if (xhr.status === 200 && xhr.responseText.trim() === 'success') {
                            // --- Update event color in calendar without reload ---
                            if (window._fullCalendarInstance && window._lastClickedEventDate && window._lastClickedEmployeeId) {
                                var calendar = window._fullCalendarInstance;
                                var found = false;
                                calendar.getEvents().forEach(function(ev) {
                                    // Compare using Manila timezone and event start as YYYY-MM-DD
                                    var eventDate = new Date(ev.start.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                                    var y = eventDate.getFullYear();
                                    var m = String(eventDate.getMonth() + 1).padStart(2, '0');
                                    var d = String(eventDate.getDate()).padStart(2, '0');
                                    var eventDateStr = y + '-' + m + '-' + d;
                                    if (
                                        ev.extendedProps.employeeId == window._lastClickedEmployeeId &&
                                        eventDateStr === window._lastClickedEventDate
                                    ) {
                                        found = true;
                                        if (isMarkAvailable) {
                                            ev.setProp('backgroundColor', '#00aaff');
                                            ev.setProp('borderColor', '#00aaff');
                                            ev.setProp('title', empNames[window._lastClickedEmployeeId]);
                                            ev.setExtendedProp('isUnavailable', false);
                                            // Remove from employeeUnavailabilities array
                                            employeeUnavailabilities = employeeUnavailabilities.filter(function(u) {
                                                return !(String(u.employee_id) === String(window._lastClickedEmployeeId) && String(u.date) === window._lastClickedEventDate);
                                            });
                                        } else {
                                            ev.setProp('backgroundColor', '#dc3545');
                                            ev.setProp('borderColor', '#dc3545');
                                            ev.setProp('title', empNames[window._lastClickedEmployeeId] + ' (Unavailable)');
                                            ev.setExtendedProp('isUnavailable', true);
                                            // Add to employeeUnavailabilities array if not present
                                            var exists = employeeUnavailabilities.some(function(u) {
                                                return String(u.employee_id) === String(window._lastClickedEmployeeId) && String(u.date) === window._lastClickedEventDate;
                                            });
                                            if (!exists) {
                                                employeeUnavailabilities.push({
                                                    employee_id: window._lastClickedEmployeeId,
                                                    date: window._lastClickedEventDate
                                                });
                                            }
                                        }
                                    }
                                });
                                // Fallback: if not found, refetch all events (should not be needed, but ensures UI stays in sync)
                                if (!found) {
                                    calendar.refetchEvents && calendar.refetchEvents();
                                }
                            }
                        } else {
                            alert('Failed to update availability.');
                        }
                    }
                };
                xhr.send(postData);
            };
        }

        // FullCalendar initialization and event handling
        var calendarModal = document.getElementById('calendarModal');
        var openCalendarBtn = document.getElementById('openCalendarModal');
        var closeCalendarSpan = document.getElementById('closeCalendarModal');
        var fullCalendarContainer = document.getElementById('fullCalendarContainer');

        // --- FIX: Always attach event listeners for calendar modal, even if openCalendarBtn is inside a form ---
        if (openCalendarBtn && calendarModal && closeCalendarSpan) {
            openCalendarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                calendarModal.style.display = 'flex';
                fullCalendarContainer.innerHTML = '';
                var calendarEl = document.createElement('div');
                calendarEl.id = 'calendar';
                fullCalendarContainer.appendChild(calendarEl);
                var today = new Date();
                // --- Use Manila timezone for year ---
                var manilaNow = new Date(today.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                var year = manilaNow.getFullYear();
                var events = [];
                var dayMap = {
                    'Sunday': 0,
                    'Monday': 1,
                    'Tuesday': 2,
                    'Wednesday': 3,
                    'Thursday': 4,
                    'Friday': 5,
                    'Saturday': 6
                };
                // For each employee, add events for each available day in every month of the current year
                employeeAvailability.forEach(function(emp) {
                    emp.days.forEach(function(dayName) {
                        for (var month = 0; month < 12; month++) {
                            var firstDay = new Date(year, month, 1);
                            var lastDay = new Date(year, month + 1, 0);
                            for (var d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                                if (d.getDay() === dayMap[dayName]) {
                                    var empId = emp.id;
                                    // --- Use Manila timezone for date string ---
                                    var manilaDate = new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                                    var y = manilaDate.getFullYear();
                                    var m = String(manilaDate.getMonth() + 1).padStart(2, '0');
                                    var da = String(manilaDate.getDate()).padStart(2, '0');
                                    var dateStr = y + '-' + m + '-' + da;
                                    // --- FIX: Ensure unavailable check uses string comparison ---
                                    var isUnavailable = false;
                                    for (var i = 0; i < employeeUnavailabilities.length; i++) {
                                        if (
                                            String(employeeUnavailabilities[i].employee_id) === String(empId) &&
                                            String(employeeUnavailabilities[i].date) === dateStr
                                        ) {
                                            isUnavailable = true;
                                            break;
                                        }
                                    }
                                    events.push({
                                        title: emp.name + (isUnavailable ? ' (Unavailable)' : ''),
                                        start: dateStr, // Use YYYY-MM-DD string for timezone safety
                                        allDay: true,
                                        backgroundColor: isUnavailable ? '#dc3545' : '#00aaff',
                                        borderColor: isUnavailable ? '#dc3545' : '#00aaff',
                                        textColor: '#fff',
                                        extendedProps: {
                                            employeeId: empId,
                                            isUnavailable: isUnavailable
                                        }
                                    });
                                }
                            }
                        }
                    });
                });

                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next',
                        center: 'title',
                        right: 'today,dayGridMonth,dayGridWeek,dayGridDay' // <-- Add week and day view buttons
                    },
                    views: {
                        dayGridMonth: { buttonText: 'Month' },
                        dayGridWeek: { buttonText: 'Week' },   // <-- Add week view
                        dayGridDay: { buttonText: 'Day' }      // <-- Add day view
                    },
                    buttonText: {
                        today: 'Today',
                        month: 'Month',
                        week: 'Week',
                        day: 'Day'
                    },
                    events: events,
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        var event = info.event;
                        var employeeId = event.extendedProps.employeeId;
                        var isUnavailable = event.extendedProps.isUnavailable;
                        // --- Use Manila timezone for event date ---
                        var eventDate = new Date(event.start.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                        var y = eventDate.getFullYear();
                        var m = String(eventDate.getMonth() + 1).padStart(2, '0');
                        var d = String(eventDate.getDate()).padStart(2, '0');
                        var eventDateStr = y + '-' + m + '-' + d;
                        var today = new Date();
                        var manilaToday = new Date(today.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                        manilaToday.setHours(0,0,0,0);
                        var eventDay = new Date(eventDate);
                        eventDay.setHours(0,0,0,0);

                        // --- Prevent marking for past dates ---
                        if (eventDay < manilaToday) {
                            return; // Do nothing if date has passed
                        }

                        // --- New: Show Mark Available/Unavailable modal on event click ---
                        var modal = document.getElementById('unavailableModal');
                        var employeeName = empNames[employeeId];
                        var dateStr = eventDate.toLocaleDateString('en-PH', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }).replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();

                        // --- set name and date in separate elements ---
                        unavailable_employee_name.textContent = employeeName;
                        unavailable_employee_date.textContent = dateStr;
                        document.getElementById('unavailable_employee_id').value = employeeId;
                        document.getElementById('unavailable_date').value = eventDateStr;

                        // --- Save for updating event color after AJAX ---
                        window._fullCalendarInstance = calendar;
                        window._lastClickedEventDate = eventDateStr;
                        window._lastClickedEmployeeId = employeeId;

                        if (isUnavailable && eventDay >= manilaToday) {
                            unavailableModalTitle.textContent = 'Mark Employee Available';
                            unavailableSubmitBtn.style.display = 'none';
                            availableSubmitBtn.style.display = 'block';
                        } else if (!isUnavailable) {
                            unavailableModalTitle.textContent = 'Mark Employee Unavailable';
                            unavailableSubmitBtn.style.display = 'block';
                            availableSubmitBtn.style.display = 'none';
                        } else {
                            // Unavailable and date has passed, do nothing
                            return;
                        }
                        modal.style.display = 'flex';
                        var calendarModal = document.getElementById('calendarModal');
                        if (calendarModal) calendarModal.style.display = 'none';
                    },
                    editable: true,
                    selectable: true,
                    dayMaxEvents: true
                });

                calendar.render();
                // --- Save instance globally for event color update ---
                window._fullCalendarInstance = calendar;
            });
            closeCalendarSpan.onclick = function() {
                calendarModal.style.display = 'none';
            }
            window.addEventListener('click', function(event) {
                if (event.target == calendarModal) {
                    calendarModal.style.display = 'none';
                }
                if (event.target == unavailableModal) {
                    unavailableModal.style.display = 'none';
                }
            });
        }
    });
    </script>

    <!-- FullCalendar JS & CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
        <style>
        /* Make FullCalendar event names smaller */
        .fc-event-title, .fc-event-main {
            font-size: 0.85em !important;
        }
        /* Hide scrollbar in FullCalendar */
        .fc-scroller, .fc-daygrid-body, .fc-daygrid-body-natural {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE 10+ */
        }
        .fc-scroller::-webkit-scrollbar, .fc-daygrid-body::-webkit-scrollbar, .fc-daygrid-body-natural::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Calendar modal logic
        var calendarModal = document.getElementById('calendarModal');
        var openCalendarBtn = document.getElementById('openCalendarModal');
        var closeCalendarSpan = document.getElementById('closeCalendarModal');
        var fullCalendarContainer = document.getElementById('fullCalendarContainer');

        // --- FIX: Always attach event listeners for calendar modal, even if openCalendarBtn is inside a form ---
        if (openCalendarBtn && calendarModal && closeCalendarSpan) {
            openCalendarBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent form submission if inside a form
                calendarModal.style.display = 'flex';
                // Always clear and re-render calendar
                fullCalendarContainer.innerHTML = '';
                var calendarEl = document.createElement('div');
                calendarEl.id = 'calendar';
                fullCalendarContainer.appendChild(calendarEl);
                // Prepare events for FullCalendar
                var today = new Date();
                // --- Use Manila timezone for year ---
                var manilaNow = new Date(today.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                var year = manilaNow.getFullYear();
                var events = [];
                var dayMap = {
                    'Sunday': 0,
                    'Monday': 1,
                    'Tuesday': 2,
                    'Wednesday': 3,
                    'Thursday': 4,
                    'Friday': 5,
                    'Saturday': 6
                };
                // For each employee, add events for each available day in every month of the current year
                employeeAvailability.forEach(function(emp) {
                    emp.days.forEach(function(dayName) {
                        for (var month = 0; month < 12; month++) {
                            var firstDay = new Date(year, month, 1);
                            var lastDay = new Date(year, month + 1, 0);
                            for (var d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                                if (d.getDay() === dayMap[dayName]) {
                                    var empId = emp.id;
                                    // --- Use Manila timezone for date string ---
                                    var manilaDate = new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                                    var y = manilaDate.getFullYear();
                                    var m = String(manilaDate.getMonth() + 1).padStart(2, '0');
                                    var da = String(manilaDate.getDate()).padStart(2, '0');
                                    var dateStr = y + '-' + m + '-' + da;
                                    // --- FIX: Ensure unavailable check uses string comparison ---
                                    var isUnavailable = false;
                                    for (var i = 0; i < employeeUnavailabilities.length; i++) {
                                        if (
                                            String(employeeUnavailabilities[i].employee_id) === String(empId) &&
                                            String(employeeUnavailabilities[i].date) === dateStr
                                        ) {
                                            isUnavailable = true;
                                            break;
                                        }
                                    }
                                    events.push({
                                        title: emp.name + (isUnavailable ? ' (Unavailable)' : ''),
                                        start: dateStr, // Use YYYY-MM-DD string for timezone safety
                                        allDay: true,
                                        backgroundColor: isUnavailable ? '#dc3545' : '#00aaff',
                                        borderColor: isUnavailable ? '#dc3545' : '#00aaff',
                                        textColor: '#fff',
                                        extendedProps: {
                                            employeeId: empId,
                                            isUnavailable: isUnavailable
                                        }
                                    });
                                }
                            }
                        }
                    });
                });

                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next',
                        center: 'title',
                        right: 'today,dayGridMonth,dayGridWeek,dayGridDay' // <-- Add week and day view buttons
                    },
                    views: {
                        dayGridMonth: { buttonText: 'Month' },
                        dayGridWeek: { buttonText: 'Week' },   // <-- Add week view
                        dayGridDay: { buttonText: 'Day' }      // <-- Add day view
                    },
                    buttonText: {
                        today: 'Today',
                        month: 'Month',
                        week: 'Week',
                        day: 'Day'
                    },
                    events: events,
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        var event = info.event;
                        var employeeId = event.extendedProps.employeeId;
                        var isUnavailable = event.extendedProps.isUnavailable;
                        // --- Use Manila timezone for event date ---
                        var eventDate = new Date(event.start.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                        var y = eventDate.getFullYear();
                        var m = String(eventDate.getMonth() + 1).padStart(2, '0');
                        var d = String(eventDate.getDate()).padStart(2, '0');
                        var eventDateStr = y + '-' + m + '-' + d;
                        var today = new Date();
                        var manilaToday = new Date(today.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                        manilaToday.setHours(0,0,0,0);
                        var eventDay = new Date(eventDate);
                        eventDay.setHours(0,0,0,0);

                        // --- Prevent marking for past dates ---
                        if (eventDay < manilaToday) {
                            return; // Do nothing if date has passed
                        }

                        // --- New: Show Mark Available/Unavailable modal on event click ---
                        var modal = document.getElementById('unavailableModal');
                        var employeeName = empNames[employeeId];
                        var dateStr = eventDate.toLocaleDateString('en-PH', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }).replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();

                        // --- set name and date in separate elements ---
                        unavailable_employee_name.textContent = employeeName;
                        unavailable_employee_date.textContent = dateStr;
                        document.getElementById('unavailable_employee_id').value = employeeId;
                        document.getElementById('unavailable_date').value = eventDateStr;

                        // --- Save for updating event color after AJAX ---
                        window._fullCalendarInstance = calendar;
                        window._lastClickedEventDate = eventDateStr;
                        window._lastClickedEmployeeId = employeeId;

                        if (isUnavailable && eventDay >= manilaToday) {
                            unavailableModalTitle.textContent = 'Mark Employee Available';
                            unavailableSubmitBtn.style.display = 'none';
                            availableSubmitBtn.style.display = 'block';
                        } else if (!isUnavailable) {
                            unavailableModalTitle.textContent = 'Mark Employee Unavailable';
                            unavailableSubmitBtn.style.display = 'block';
                            availableSubmitBtn.style.display = 'none';
                        } else {
                            // Unavailable and date has passed, do nothing
                            return;
                        }
                        modal.style.display = 'flex';
                        var calendarModal = document.getElementById('calendarModal');
                        if (calendarModal) calendarModal.style.display = 'none';
                    },
                    editable: true,
                    selectable: true,
                    dayMaxEvents: true
                });

                calendar.render();
                // --- Save instance globally for event color update ---
                window._fullCalendarInstance = calendar;
            });
            closeCalendarSpan.onclick = function() {
                calendarModal.style.display = 'none';
            }
            window.addEventListener('click', function(event) {
                if (event.target == calendarModal) {
                    calendarModal.style.display = 'none';
                }
                if (event.target == unavailableModal) {
                    unavailableModal.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html>
