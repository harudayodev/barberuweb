<?php
header('Content-Type: application/json');
include 'connection.php';

$customername = $_POST['customername'] ?? '';
$haircut      = $_POST['haircut'] ?? '';
$color        = $_POST['color'] ?? '';    
$shave        = $_POST['shave'] ?? '';    
$date_time    = ($_POST['date'] ?? '') . ' ' . ($_POST['timeslot'] ?? '');
$branch       = $_POST['branch'] ?? '';
$barbername   = $_POST['barber'] ?? '';  

if (empty($customername) || empty($haircut) || empty($date_time) || empty($branch) || empty($barbername)) {
    echo json_encode(["status" => "fail", "message" => "Missing required data."]);
    exit();
}

$conn->begin_transaction();

try {
    // ➕ ADD THIS SECTION to get the customer's userID
    $stmt_user = $conn->prepare("SELECT userID FROM appusers WHERE CONCAT(firstname, ' ', lastname) = ?");
    if (!$stmt_user) {
        throw new Exception("Failed to prepare user statement: " . $conn->error);
    }
    $stmt_user->bind_param("s", $customername);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows > 0) {
        $user_data = $result_user->fetch_assoc();
        $userID = $user_data['userID'];
    } else {
        // Handle case where customer is not found, maybe they are a guest?
        // For now, we'll throw an exception.
        throw new Exception("Customer not found in appusers.");
    }
    $stmt_user->close();
    // ➕ END OF ADDED SECTION

    // Get shop info
    $stmt_shop = $conn->prepare("SELECT shopID, admin_id FROM barbershops WHERE name = ?");
    $stmt_shop->bind_param("s", $branch);
    $stmt_shop->execute();
    $result_shop = $stmt_shop->get_result();
    if ($result_shop->num_rows > 0) {
        $shop_data = $result_shop->fetch_assoc();
        $shopID = $shop_data['shopID'];
        $adminID = $shop_data['admin_id'];
    } else {
        throw new Exception("Branch not found.");
    }
    $stmt_shop->close();

    // Get haircut info
    $stmt_haircut = $conn->prepare("SELECT Service, Price FROM haircut WHERE Name = ? AND shopID = ?");
    $stmt_haircut->bind_param("si", $haircut, $shopID);
    $stmt_haircut->execute();
    $result_haircut = $stmt_haircut->get_result();
    if ($result_haircut->num_rows > 0) {
        $haircut_data = $result_haircut->fetch_assoc();
        $service = $haircut_data['Service'] ?? 'haircut'; 
        $price   = $haircut_data['Price'] ?? 0.00;
    } else {
        throw new Exception("Haircut not found for this branch.");
    }
    $stmt_haircut->close();

    $status = "In Queue";

    // Get next queue number for the specific barber
    $stmt_queue_count = $conn->prepare("SELECT COUNT(*) as queue_count FROM queue WHERE barber = ? AND status = 'In Queue'");
    $stmt_queue_count->bind_param("s", $barbername);
    $stmt_queue_count->execute();
    $result_queue_count = $stmt_queue_count->get_result();
    $row_queue_count = $result_queue_count->fetch_assoc();
    $queuenumber = $row_queue_count['queue_count'] + 1;
    $stmt_queue_count->close();

    // Fetch EmployeeID from appusers using the barber’s name
    $stmt_emp = $conn->prepare("SELECT employeeID FROM appusers WHERE CONCAT(firstname, ' ', lastname) = ?");
    $stmt_emp->bind_param("s", $barbername);
    $stmt_emp->execute();
    $result_emp = $stmt_emp->get_result();
    $emp_data = $result_emp->fetch_assoc();
    $employeeID = $emp_data['employeeID'] ?? null;
    $stmt_emp->close();

    // ❗️ MODIFY THIS INSERT STATEMENT
    $stmt = $conn->prepare("INSERT INTO queue  
        (name, service, barber, price, date_time, status, adminID, shopID, Haircut_Name, Color_Name, Shave_Name, queuelimit, EmployeeID, userID)  
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"); // Added userID and one more '?'
    
    // ❗️ MODIFY THIS BIND_PARAM CALL
    $stmt->bind_param("sssdssiisssiii",  // Added 'i' for userID at the end
        $customername, $service, $barbername, $price, $date_time, 
        $status, $adminID, $shopID, $haircut, $color, $shave, $queuenumber, $employeeID,
        $userID // Added the new $userID variable
    );

    if (!$stmt->execute()) {
        throw new Exception("Error inserting into queue: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Appointment saved successfully!"]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "fail", "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>