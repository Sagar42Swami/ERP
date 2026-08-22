<?php
// Centralized Database Connection for ERP System
$conn = new mysqli("localhost", "root", "", "login_system");

if ($conn->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "Database Connection Failed: " . $conn->connect_error
    ]));
}

// Set charset to utf8mb4 for unicode compatibility
$conn->set_charset("utf8mb4");
?>
