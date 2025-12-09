<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost"; 
$user = "w3j8ifi6oqt8_barberuweb"; 
$pass = "barberu12342!";
$db   = "w3j8ifi6oqt8_barberu";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Connected successfully to database!";
?>
