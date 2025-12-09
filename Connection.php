<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Connection.php
// =============================
// LOCAL DEVELOPMENT (XAMPP/phpMyAdmin)
// =============================
// Uncomment these lines for local development:
// $host = "localhost";
// $username = "root";
// $password = "";
// $database = "barberu";

// =============================
// GODADDY WEB HOSTING (Production)
// =============================
// Replace the values below with your actual GoDaddy database credentials:
$host = "localhost";
$username = "barberuweb";
$password = "barberu12342!";
$database = "barberu";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>