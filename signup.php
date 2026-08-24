<?php
require_once "db.php";

$first_name = trim($_POST["first_name"] ?? "");
$last_name = trim($_POST["last_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$role = trim($_POST["role"] ?? "EMPLOYEE");
$department_id = intval($_POST["department_id"] ?? 0);
$designation = trim($_POST["designation"] ?? "");
$salary = floatval($_POST["salary"] ?? 0.00);
$password = $_POST["password"] ?? "";

// Back-end validation
if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($designation) || $department_id <= 0) {
    $error_msg = "Please fill in all required fields.";
    $success = false;
} else {
    // Check if email already exists in employees or users
    $checkEmail = $conn->prepare("SELECT employee_id FROM employees WHERE email = ? UNION SELECT employee_id FROM users WHERE email = ?");
    $checkEmail->bind_param("ss", $email, $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    $emailExists = $checkEmail->num_rows > 0;
    $checkEmail->close();

    // Check if phone already exists
    $checkPhone = $conn->prepare("SELECT employee_id FROM employees WHERE phone = ?");
    $checkPhone->bind_param("s", $phone);
    $checkPhone->execute();
    $checkPhone->store_result();
    $phoneExists = $checkPhone->num_rows > 0;
    $checkPhone->close();

    if ($emailExists) {
        $error_msg = "Email address is already in use.";
        $success = false;
    } elseif ($phoneExists) {
        $error_msg = "Phone number is already in use.";
        $success = false;
    } else {
        // Start database transaction
        $conn->begin_transaction();

        try {
            // Determine default manager_id based on department
            // In a real ERP, we retrieve the department manager
            $stmtDept = $conn->prepare("SELECT manager_id FROM departments WHERE department_id = ?");
            $stmtDept->bind_param("i", $department_id);
            $stmtDept->execute();
            $resDept = $stmtDept->get_result();
            $manager_id = null;
            if ($rowDept = $resDept->fetch_assoc()) {
                if (!empty($rowDept["manager_id"])) {
                    $manager_id = intval($rowDept["manager_id"]);
                }
            }
            $stmtDept->close();

            // Insert into employees
            $hire_date = date("Y-m-d");
            $stmtEmp = $conn->prepare("INSERT INTO employees (first_name, last_name, email, phone, hire_date, designation, salary, department_id, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtEmp->bind_param("ssssssdii", $first_name, $last_name, $email, $phone, $hire_date, $designation, $salary, $department_id, $manager_id);
            $stmtEmp->execute();
            
            $employee_id = $conn->insert_id;
            $stmtEmp->close();

            // Generate unique username
            $base_username = strtolower($first_name . "_" . $last_name);
            $username = $base_username;
            $counter = 1;
            while (true) {
                $stmtCheckUser = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmtCheckUser->bind_param("s", $username);
                $stmtCheckUser->execute();
                $stmtCheckUser->store_result();
                $rows = $stmtCheckUser->num_rows;
                $stmtCheckUser->close();
                if ($rows == 0) {
                    break;
                }
                $username = $base_username . $counter;
                $counter++;
            }

            // Insert into users
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $conn->prepare("INSERT INTO users (username, email, password_hash, role, employee_id) VALUES (?, ?, ?, ?, ?)");
            $stmtUser->bind_param("ssssi", $username, $email, $hashedPassword, $role, $employee_id);
            $stmtUser->execute();
            $stmtUser->close();

            // Commit the transaction
            $conn->commit();
            $success = true;
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Database error occurred during onboarding: " . $e->getMessage();
            $success = false;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Result</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="page">
        <div class="card message">
            <?php if ($success): ?>
                <div class="status-icon success-icon">✓</div>
                <h1>Signup Successful</h1>
                <p>Welcome to the company, <?php echo htmlspecialchars($first_name . " " . $last_name); ?>!</p>
                <p class="sub-text">Your user account (Username: <strong><?php echo htmlspecialchars($username); ?></strong>) has been set up with the <strong><?php echo htmlspecialchars($role); ?></strong> role.</p>
                <a class="button-link" href="index.html">Proceed to Login</a>
            <?php else: ?>
                <div class="status-icon error-icon">✗</div>
                <h1>Signup Failed</h1>
                <p><?php echo htmlspecialchars($error_msg); ?></p>
                <a class="button-link" href="signup.html">Try Again</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>
