<?php
// Centralized Database Connection for ERP System
$conn = new mysqli(
    getenv("DB_HOST") ?: "localhost",
    getenv("DB_USER") ?: "root",
    getenv("DB_PASSWORD") ?: "",
    getenv("DB_NAME") ?: "login_system",
    getenv("DB_PORT") ?: 3306
);

if ($conn->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "Database Connection Failed: " . $conn->connect_error
    ]));
}

// Set charset to utf8mb4 for unicode compatibility
$conn->set_charset("utf8mb4");
?>
