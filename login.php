<?php
session_start();
require_once "db.php";

$login_input = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

$success = false;
$error_msg = "";

if (empty($login_input) || empty($password)) {
    $error_msg = "Please fill in all fields.";
} else {
    // Check by email or username
    $stmt = $conn->prepare("SELECT user_id, username, email, password_hash, role, employee_id, account_status FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user["account_status"] !== "ACTIVE") {
            $error_msg = "Your account is currently suspended/inactive. Please contact the administrator.";
        } elseif (password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["employee_id"] = $user["employee_id"];

            // Update last login timestamp
            $updateStmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
            $updateStmt->bind_param("i", $user["user_id"]);
            $updateStmt->execute();
            $updateStmt->close();

            $success = true;
            header("Location: dashboard.php");
            exit();
        } else {
            $error_msg = "Invalid email/username or password.";
        }
    } else {
        $error_msg = "Invalid email/username or password.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Failed</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="page">
        <div class="card message">
            <div class="status-icon error-icon">✗</div>
            <h1>Login Failed</h1>
            <p><?php echo htmlspecialchars($error_msg); ?></p>
            <a class="button-link" href="index.html">Try Again</a>
        </div>
    </div>
</body>
</html>
