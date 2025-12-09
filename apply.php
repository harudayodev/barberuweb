<?php
session_start();

$success_message = "";
$error_message = "";

include 'Connection.php'; // Use shared DB connection

// --- NOTE: Nominatim is a free service, but respect its usage policy (max 1 request/second) ---
// Define a User-Agent to identify your application (required by Nominatim policy)
define('NOMINATIM_USER_AGENT', 'BarberuApp/1.0 (contact@example.com)'); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $business_name = $_POST['business_name'];
    $business_email = $_POST['business_email'];
    $owner_name = $_POST['owner_name'];
    $contact = isset($_POST['contact']) ? $_POST['contact'] : '';
    $business_address = trim($_POST['business_address']);

    // Initialize lat/lon variables
    $latitude = null;
    $longitude = null;

    // Server-side validation for contact number (exactly 11 digits, only numbers)
    if (!preg_match('/^09\d{9}$/', $contact)) {
        $error_message = "Invalid contact number. It must start with '09' and be exactly 11 digits (numbers only).";
    } else {
        // Step 1: Geocode the address using Nominatim (OpenStreetMap)
        $geocode_url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($business_address) . "&format=json&limit=1";
        
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: " . NOMINATIM_USER_AGENT
            ]
        ]);
        
        $geocode_response = @file_get_contents($geocode_url, false, $context);

        if ($geocode_response === FALSE) {
            $error_message = "Could not connect to the geocoding service (Nominatim). Registration failed.";
        } else {
            $geocode_data = json_decode($geocode_response, true);

            // Nominatim returns an array of results. We take the first one.
            if (!empty($geocode_data)) {
                $latitude = $geocode_data[0]['lat'];
                $longitude = $geocode_data[0]['lon'];
            } else {
                // Handle case where address is not found by the API
                $error_message = "Address not found or geocoding failed. Please verify the business address.";
            }
        }

        // Check if an error occurred during geocoding or file upload
        if (empty($error_message)) {
            // Handle file uploads
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $permit_file = $target_dir . basename($_FILES["business_permit"]["name"]);
            $valid_id_file = $target_dir . basename($_FILES["valid_id"]["name"]);
            $dti_clearance_file = $target_dir . basename($_FILES["dti_clearance"]["name"]);
            $police_clearance_file = $target_dir . basename($_FILES["police_clearance"]["name"]);

            $permit_ok = move_uploaded_file($_FILES["business_permit"]["tmp_name"], $permit_file);
            $valid_id_ok = move_uploaded_file($_FILES["valid_id"]["tmp_name"], $valid_id_file);
            $dti_ok = move_uploaded_file($_FILES["dti_clearance"]["tmp_name"], $dti_clearance_file);
            $police_ok = move_uploaded_file($_FILES["police_clearance"]["tmp_name"], $police_clearance_file);

            // Get password from POST

            if ($permit_ok && $valid_id_ok && $dti_ok && $police_ok) {
                // Step 2: Insert into database with all required fields (no password)
                $stmt = $conn->prepare("INSERT INTO application (business_name, business_email, owner_name, contact, business_address, business_permit_image, valid_id_image, latitude, longitude, dti_clearance, police_clearance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param("sssssssssss", $business_name, $business_email, $owner_name, $contact, $business_address, $permit_file, $valid_id_file, $latitude, $longitude, $dti_clearance_file, $police_clearance_file);

                if ($stmt->execute()) {
                    $success_message = "Business registration successful! You can now <a href='login.php'>login</a> after admin approval.";
                } else {
                    $error_message = "Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error uploading files. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registration | Barberu</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="Resources/icon.png">
    <link rel="shortcut icon" type="image/png" href="Resources/brand.png">
    <script>
        // Prevent back button from working
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function () {
            window.history.pushState(null, "", window.location.href);
        };
    </script>
</head>
<body>
    <div class="bg-shape shape1"></div>
    <div class="bg-shape shape2"></div>
    <style>
        /* CSS styles remain the same for layout */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        body {
            min-height: 100vh;
            height: 100vh;
            width: 100vw;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-sizing: border-box;
        }
        .container {
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0;
        }
        main {
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-box {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 24px 18px;
            max-width: 700px;
            width: 95vw;
            text-align: center;
            box-sizing: border-box;
            max-height: 80vh;
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
        }
        .login-box::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        .login-box img {
            width: 60px;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .login-box h1 {
            height: 25px;
            font-weight: 700;
            margin-bottom: 50px; /* Increased margin for spacing */
            color: #2c3e50;
        }
        .login-box form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 24px;
            align-items: start;
            box-sizing: border-box;
        }
        .login-box input[type="text"],
        .login-box input[type="password"],
        .login-box input[type="email"],
        .login-box input[type="tel"],
        .login-box input[type="file"] {
            width: 100%;
            padding: 4px 7px; /* Reduced vertical padding */
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 0.95em;
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
            height: 32px; /* Explicitly set a smaller height */
        }
        .login-box label {
            display: block;
            text-align: left;
            margin-bottom: 4px;
            font-size: 0.98em;
            color: #555;
            word-break: break-word;
        }
        .login-box .forgot-password {
            text-align: right;
            margin-bottom: 0;
        }
        .login-box .forgot-password a {
            color: #007bff;
            text-decoration: none;
            font-size: 0.95em;
        }
        .login-box .forgot-password a:hover {
            text-decoration: underline;
        }
        .login-box button[type="submit"],
        .login-box .login-btn {
            grid-column: 1 / -1;
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-box button[type="submit"]:hover,
        .login-box .login-btn:hover {
            background: #1a242f;
        }
        .login-box .signup-link {
            grid-column: 1 / -1;
            margin-top: 10px;
            font-size: 0.98em;
        }
        .login-box .signup-link a {
            color: #007bff;
            text-decoration: none;
        }
        .login-box .signup-link a:hover {
            text-decoration: underline;
        }
        .login-box p[style*="color: red"],
        .login-box p[style*="color: green"] {
            grid-column: 1 / -1;
            margin: 0 0 8px 0;
            font-size: 0.98em;
        }
        .login-box input[type="file"] {
            margin-top: 8px;
        }
                .global-back-button {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1000;
}

.btn-outline {
    font-family: 'Poppins', sans-serif;
    display: inline-block;
    padding: 10px 20px;
    border: 2px solid #3498db;
    background-color: transparent;
    color: #3498db;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.btn-outline:hover {
    background-color: #3498db;
    color: white;
}
    </style>
    <div class="bg-shape bg-shape-top-left"></div>
    <div class="bg-shape bg-shape-top-right"></div>
    <div class="container">
        <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div class="login-box">
                <img src="Resources/ab.png" alt="Brand Logo">
                <h1>Business Registration</h1>
                <form method="POST" action="apply.php" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 24px; align-items: start; box-sizing: border-box;">
                    <div style="text-align:left;">
                        <label for="business_name">Business Name</label>
                        <input type="text" id="business_name" name="business_name" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="business_email">Business Email</label>
                        <input type="email" id="business_email" name="business_email" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="owner_name">Owner Name</label>
                        <input type="text" id="owner_name" name="owner_name" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="contact">Contact Number</label>
                        <input type="tel" id="contact" name="contact" required pattern="^09\d{9}$" maxlength="11" title="Contact number must start with '09' and be exactly 11 digits" value="09">
                    </div>
                    <div style="text-align:left; grid-column: 1 / -1;">
                        <label for="business_address">Business Address</label>
                        <input type="text" id="business_address" name="business_address" required style="width: 100%; font-size: 1em;">
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                    </div>
                    <div style="text-align:left;">
                        <label for="business_permit">Business Permit</label>
                        <input type="file" id="business_permit" name="business_permit" accept="image/*,application/pdf" class="custom-file-input" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="dti_certificate">DTI Certificate</label>
                        <input type="file" id="dti_clearance" name="dti_clearance" accept="image/*,application/pdf" class="custom-file-input" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="valid_id">Valid ID</label>
                        <input type="file" id="valid_id" name="valid_id" accept="image/*,application/pdf" class="custom-file-input" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="police_clearance">BIR Registration</label>
                        <input type="file" id="police_clearance" name="police_clearance" accept="image/*,application/pdf" class="custom-file-input" required>
                    </div>
                    <?php if (!empty($error_message)) { ?>
                        <p style="color: red; text-align:left; grid-column: 1 / -1;"> <?php echo $error_message; ?> </p>
                    <?php } ?>
                    <?php if (!empty($success_message)) { ?>
                        <p style="color: green; text-align:left; grid-column: 1 / -1;"> <?php echo $success_message; ?> </p>
                    <?php } ?>
                    <button type="submit" class="login-btn" style="grid-column: 1 / -1;">Register Business</button>
                    <p class="signup-link" style="grid-column: 1 / -1;">Already a Member? <a href="login.php">Login</a></p>
                </form>
            </div>
        </main>
    </div>
    <div class="global-back-button">
    <a href="login.php" class="btn-outline">← Back</a>
</div>
    <script>
    
    // --- Basic Form Scripts ---
    document.getElementById('business_permit').addEventListener('change', function(e) {
        var input = e.target;
        var fileName = input.files[0] ? input.files[0].name : "No file chosen";
        console.log("Business Permit file selected:", fileName);
    });

                    <div style="text-align:left;">
                        <label for="dti_clearance">DTI Certificate</label>
                        <input type="file" id="dti_clearance" name="dti_clearance" accept="image/*,application/pdf" class="custom-file-input" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="police_clearance">Police Clearance</label>
                        <input type="file" id="police_clearance" name="police_clearance" accept="image/*,application/pdf" class="custom-file-input" required>
                    </div>
    document.getElementById('valid_id').addEventListener('change', function(e) {
        var input = e.target;
        var fileName = input.files[0] ? input.files[0].name : "No file chosen";
        console.log("Valid ID file selected:", fileName);
    });

    // Restrict contact input to only numbers and max 11 digits
    document.getElementById('contact').addEventListener('input', function(e) {
        let value = this.value;
        // Always start with '09'
        if (!value.startsWith('09')) {
            value = '09' + value.replace(/[^0-9]/g, '').replace(/^09/, '');
        } else {
            value = value.replace(/[^0-9]/g, '');
        }
        if (value.length > 11) value = value.slice(0, 11);
        this.value = value;
    });

    </script>
</body>
</html>