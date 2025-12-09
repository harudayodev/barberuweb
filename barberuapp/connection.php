<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = "localhost";   
$username = "barberuweb";   // <-- double check if needs cPanel prefix
$password = "barberu12342!";
$database = "barberu";      // <-- double check if needs cPanel prefix

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---- TEST MESSAGE ----
// Only show this if connection.php is accessed directly in the browser
if (basename($_SERVER['PHP_SELF']) == "connection.php") {
    echo "✅ Connected OK to database: " . $database;
}
?>
