<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

$action = $_POST["action"] ?? $_GET["action"] ?? "";
$employee_id = $_SESSION["employee_id"];
$user_role = $_SESSION["role"];

function redirect_with_status($status, $msg) {
    header("Location: dashboard.php?status=" . urlencode($status) . "&msg=" . urlencode($msg));
    exit();
}

switch ($action) {
    case "check_in":
        if (empty($employee_id)) {
            redirect_with_status("error", "No employee record associated with this login account.");
        }
        $today = date("Y-m-d");
        $now = date("H:i:s");

        // Check if already checked in today
        $stmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->bind_param("is", $employee_id, $today);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            redirect_with_status("error", "You have already checked in today.");
        }
        $stmt->close();

        // Insert check-in record
        $stmtInsert = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, status, remarks) VALUES (?, ?, ?, 'PRESENT', 'Checked in online')");
        $stmtInsert->bind_param("iss", $employee_id, $today, $now);
        if ($stmtInsert->execute()) {
            $stmtInsert->close();
            redirect_with_status("success", "Checked in successfully at " . $now);
        } else {
            $stmtInsert->close();
            redirect_with_status("error", "Failed to check in.");
        }
        break;

    case "check_out":
        if (empty($employee_id)) {
            redirect_with_status("error", "No employee record associated with this login account.");
        }
        $today = date("Y-m-d");
        $now = date("H:i:s");

        // Get today's attendance record
        $stmt = $conn->prepare("SELECT attendance_id, check_in FROM attendance WHERE employee_id = ? AND attendance_date = ? AND check_out IS NULL");
        $stmt->bind_param("is", $employee_id, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $check_in = $row["check_in"];
            $attendance_id = $row["attendance_id"];
            $stmt->close();

            // Calculate working hours
            $time_in = strtotime($check_in);
            $time_out = strtotime($now);
            $working_hours = round(($time_out - $time_in) / 3600, 2);

            // Update attendance
            $stmtUpdate = $conn->prepare("UPDATE attendance SET check_out = ?, working_hours = ? WHERE attendance_id = ?");
            $stmtUpdate->bind_param("sdi", $now, $working_hours, $attendance_id);
            if ($stmtUpdate->execute()) {
                $stmtUpdate->close();
                redirect_with_status("success", "Checked out successfully at " . $now . ". Total hours: " . $working_hours);
            } else {
                $stmtUpdate->close();
                redirect_with_status("error", "Failed to check out.");
            }
        } else {
            $stmt->close();
            redirect_with_status("error", "No active check-in record found for today, or you already checked out.");
        }
        break;

    case "apply_leave":
        if (empty($employee_id)) {
            redirect_with_status("error", "No employee record associated with this login account.");
        }
        $start_date = $_POST["start_date"] ?? "";
        $end_date = $_POST["end_date"] ?? "";
        $leave_type = $_POST["leave_type"] ?? "";
        $reason = trim($_POST["reason"] ?? "");

        if (empty($start_date) || empty($end_date) || empty($leave_type) || empty($reason)) {
            redirect_with_status("error", "Please fill in all leave request fields.");
        }

        if (strtotime($start_date) > strtotime($end_date)) {
            redirect_with_status("error", "Start date cannot be after end date.");
        }

        $stmtLeave = $conn->prepare("INSERT INTO leaves (employee_id, start_date, end_date, leave_type, reason, status) VALUES (?, ?, ?, ?, ?, 'PENDING')");
        $stmtLeave->bind_param("issss", $employee_id, $start_date, $end_date, $leave_type, $reason);
        if ($stmtLeave->execute()) {
            $stmtLeave->close();
            redirect_with_status("success", "Leave application submitted successfully.");
        } else {
            $stmtLeave->close();
            redirect_with_status("error", "Failed to submit leave application.");
        }
        break;

    case "update_leave_status":
        if ($user_role !== "MANAGER" && $user_role !== "HR" && $user_role !== "ADMIN") {
            redirect_with_status("error", "Unauthorized to approve/reject leaves.");
        }
        $leave_id = intval($_POST["leave_id"] ?? 0);
        $status = $_POST["status"] ?? ""; // APPROVED or REJECTED

        if ($leave_id <= 0 || ($status !== "APPROVED" && $status !== "REJECTED")) {
            redirect_with_status("error", "Invalid parameters.");
        }

        $approved_by = !empty($employee_id) ? $employee_id : null;
        $approved_at = date("Y-m-d H:i:s");

        // Update leave
        $stmtLeave = $conn->prepare("UPDATE leaves SET status = ?, approved_by = ?, approved_at = ? WHERE leave_id = ?");
        $stmtLeave->bind_param("sisi", $status, $approved_by, $approved_at, $leave_id);
        
        if ($stmtLeave->execute()) {
            // Optional trigger effect: If leave is approved, we could insert attendance rows with status 'LEAVE' for those days
            if ($status === "APPROVED") {
                // Fetch leave details to add to attendance
                $stmtFetch = $conn->prepare("SELECT employee_id, start_date, end_date, leave_type FROM leaves WHERE leave_id = ?");
                $stmtFetch->bind_param("i", $leave_id);
                $stmtFetch->execute();
                $resFetch = $stmtFetch->get_result();
                if ($leaveRow = $resFetch->fetch_assoc()) {
                    $l_emp_id = $leaveRow["employee_id"];
                    $l_start = strtotime($leaveRow["start_date"]);
                    $l_end = strtotime($leaveRow["end_date"]);
                    $l_type = $leaveRow["leave_type"];

                    for ($curr = $l_start; $curr <= $l_end; $curr = strtotime("+1 day", $curr)) {
                        $currDate = date("Y-m-d", $curr);
                        // Try to insert an attendance row for each leave day
                        $stmtAtt = $conn->prepare("INSERT IGNORE INTO attendance (employee_id, attendance_date, status, remarks) VALUES (?, ?, 'LEAVE', ?)");
                        $remarkStr = "Approved Leave (" . $l_type . ")";
                        $stmtAtt->bind_param("iss", $l_emp_id, $currDate, $remarkStr);
                        $stmtAtt->execute();
                        $stmtAtt->close();
                    }
                }
                $stmtFetch->close();
            }

            $stmtLeave->close();
            redirect_with_status("success", "Leave request has been " . strtolower($status) . ".");
        } else {
            $stmtLeave->close();
            redirect_with_status("error", "Failed to update leave request.");
        }
        break;

    case "generate_payroll":
        if ($user_role !== "HR" && $user_role !== "ADMIN") {
            redirect_with_status("error", "Unauthorized to manage payroll.");
        }
        $emp_to_pay = intval($_POST["employee_id"] ?? 0);
        $salary_month_input = $_POST["salary_month"] ?? ""; // 'YYYY-MM'
        $allowance = floatval($_POST["allowance"] ?? 0.00);
        $bonus = floatval($_POST["bonus"] ?? 0.00);
        $deduction = floatval($_POST["deduction"] ?? 0.00);

        if ($emp_to_pay <= 0 || empty($salary_month_input)) {
            redirect_with_status("error", "Please select employee and month.");
        }

        // Format to first day of the month
        $salary_month = $salary_month_input . "-01";

        // Retrieve employee's basic salary
        $stmtEmp = $conn->prepare("SELECT salary FROM employees WHERE employee_id = ?");
        $stmtEmp->bind_param("i", $emp_to_pay);
        $stmtEmp->execute();
        $resEmp = $stmtEmp->get_result();
        if ($resEmp->num_rows !== 1) {
            $stmtEmp->close();
            redirect_with_status("error", "Employee not found.");
        }
        $empData = $resEmp->fetch_assoc();
        $basic_salary = floatval($empData["salary"]);
        $stmtEmp->close();

        // Calculate tax: 8% of (basic + allowance + bonus)
        $tax = round(($basic_salary + $allowance + $bonus) * 0.08, 2);
        // Net salary
        $net_salary = $basic_salary + $allowance + $bonus - $deduction - $tax;

        // Insert payroll record
        $stmtPay = $conn->prepare("INSERT INTO payroll (employee_id, salary_month, basic_salary, allowance, bonus, deduction, tax, net_salary, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmtPay->bind_param("isdddddd", $emp_to_pay, $salary_month, $basic_salary, $allowance, $bonus, $deduction, $tax, $net_salary);
        if ($stmtPay->execute()) {
            $stmtPay->close();
            redirect_with_status("success", "Payroll generated successfully.");
        } else {
            $stmtPay->close();
            redirect_with_status("error", "Failed to generate payroll. Check if payroll for this employee and month already exists.");
        }
        break;

    case "pay_payroll":
        if ($user_role !== "HR" && $user_role !== "ADMIN") {
            redirect_with_status("error", "Unauthorized to update payroll payments.");
        }
        $payroll_id = intval($_POST["payroll_id"] ?? 0);
        if ($payroll_id <= 0) {
            redirect_with_status("error", "Invalid payroll ID.");
        }

        $pay_date = date("Y-m-d");
        $stmtPay = $conn->prepare("UPDATE payroll SET payment_status = 'PAID', payment_date = ? WHERE payroll_id = ?");
        $stmtPay->bind_param("si", $pay_date, $payroll_id);
        if ($stmtPay->execute()) {
            $stmtPay->close();
            redirect_with_status("success", "Payroll record updated to PAID status.");
        } else {
            $stmtPay->close();
            redirect_with_status("error", "Failed to execute payroll payment.");
        }
        break;

    case "create_project":
        if ($user_role !== "ADMIN" && $user_role !== "MANAGER") {
            redirect_with_status("error", "Unauthorized to create projects.");
        }
        $project_name = trim($_POST["project_name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $start_date = $_POST["start_date"] ?? "";
        $end_date = $_POST["end_date"] ?? "";
        $budget = floatval($_POST["budget"] ?? 0.00);
        $manager_id = intval($_POST["manager_id"] ?? 0);

        if (empty($project_name) || empty($start_date) || $budget < 0) {
            redirect_with_status("error", "Please fill in required fields (Name, Start Date, Budget).");
        }

        $mgr_id = ($manager_id > 0) ? $manager_id : null;
        $end_d = (!empty($end_date)) ? $end_date : null;

        $stmtProj = $conn->prepare("INSERT INTO projects (project_name, description, start_date, end_date, budget, manager_id, status) VALUES (?, ?, ?, ?, ?, ?, 'PLANNED')");
        $stmtProj->bind_param("ssssdi", $project_name, $description, $start_date, $end_d, $budget, $mgr_id);
        if ($stmtProj->execute()) {
            $stmtProj->close();
            redirect_with_status("success", "Project created successfully.");
        } else {
            $stmtProj->close();
            redirect_with_status("error", "Failed to create project. Name might already be in use.");
        }
        break;

    case "assign_employee_project":
        if ($user_role !== "ADMIN" && $user_role !== "MANAGER") {
            redirect_with_status("error", "Unauthorized to assign employees to projects.");
        }
        $project_id = intval($_POST["project_id"] ?? 0);
        $emp_to_assign = intval($_POST["employee_id"] ?? 0);
        $role_in_proj = trim($_POST["role"] ?? "");

        if ($project_id <= 0 || $emp_to_assign <= 0 || empty($role_in_proj)) {
            redirect_with_status("error", "Please select project, employee, and assign a role.");
        }

        $assigned_date = date("Y-m-d");

        $stmtAssign = $conn->prepare("INSERT INTO employee_projects (employee_id, project_id, role, assigned_date, assignment_status) VALUES (?, ?, ?, ?, 'ACTIVE')");
        $stmtAssign->bind_param("iiss", $emp_to_assign, $project_id, $role_in_proj, $assigned_date);
        if ($stmtAssign->execute()) {
            $stmtAssign->close();
            redirect_with_status("success", "Employee successfully assigned to project.");
        } else {
            $stmtAssign->close();
            redirect_with_status("error", "Employee might already be assigned to this project.");
        }
        break;

    case "create_department":
        if ($user_role !== "ADMIN") {
            redirect_with_status("error", "Only System Administrators can create departments.");
        }
        $department_name = trim($_POST["department_name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $location = trim($_POST["location"] ?? "");
        $manager_id = intval($_POST["manager_id"] ?? 0);

        if (empty($department_name)) {
            redirect_with_status("error", "Department name is required.");
        }

        $mgr_id = ($manager_id > 0) ? $manager_id : null;

        $stmtDept = $conn->prepare("INSERT INTO departments (department_name, description, location, manager_id) VALUES (?, ?, ?, ?)");
        $stmtDept->bind_param("sssi", $department_name, $description, $location, $mgr_id);
        if ($stmtDept->execute()) {
            $stmtDept->close();
            redirect_with_status("success", "Department created successfully.");
        } else {
            $stmtDept->close();
            redirect_with_status("error", "Failed to create department. Name may already exist.");
        }
        break;

    case "update_employee":
        if ($user_role !== "ADMIN" && $user_role !== "HR") {
            redirect_with_status("error", "Unauthorized to update employee profiles.");
        }
        $emp_id = intval($_POST["employee_id"] ?? 0);
        $first_name = trim($_POST["first_name"] ?? "");
        $last_name = trim($_POST["last_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $designation = trim($_POST["designation"] ?? "");
        $salary = floatval($_POST["salary"] ?? 0.00);
        $department_id = intval($_POST["department_id"] ?? 0);
        $manager_id = intval($_POST["manager_id"] ?? 0);
        $employment_status = $_POST["employment_status"] ?? "ACTIVE";

        if ($emp_id <= 0 || empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($designation) || $department_id <= 0) {
            redirect_with_status("error", "Missing required fields for employee edit.");
        }

        $mgr_id = ($manager_id > 0) ? $manager_id : null;

        $conn->begin_transaction();
        try {
            $stmtEmp = $conn->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, designation = ?, salary = ?, department_id = ?, manager_id = ?, employment_status = ? WHERE employee_id = ?");
            $stmtEmp->bind_param("sssssdiisi", $first_name, $last_name, $email, $phone, $designation, $salary, $department_id, $mgr_id, $employment_status, $emp_id);
            $stmtEmp->execute();
            $stmtEmp->close();

            // Synch users table email if applicable
            $stmtUser = $conn->prepare("UPDATE users SET email = ? WHERE employee_id = ?");
            $stmtUser->bind_param("si", $email, $emp_id);
            $stmtUser->execute();
            $stmtUser->close();

            $conn->commit();
            redirect_with_status("success", "Employee record updated successfully.");
        } catch (Exception $e) {
            $conn->rollback();
            redirect_with_status("error", "Failed to update employee details: " . $e->getMessage());
        }
        break;

    case "update_user_status":
        if ($user_role !== "ADMIN") {
            redirect_with_status("error", "Only System Administrators can manage user profiles.");
        }
        $target_user_id = intval($_POST["target_user_id"] ?? 0);
        $role_select = $_POST["role"] ?? "";
        $status_select = $_POST["account_status"] ?? "";

        if ($target_user_id <= 0 || empty($role_select) || empty($status_select)) {
            redirect_with_status("error", "Missing parameter details.");
        }

        if ($target_user_id === intval($_SESSION["user_id"]) && $status_select === "INACTIVE") {
            redirect_with_status("error", "You cannot set your own account to inactive.");
        }

        $stmtUser = $conn->prepare("UPDATE users SET role = ?, account_status = ? WHERE user_id = ?");
        $stmtUser->bind_param("ssi", $role_select, $status_select, $target_user_id);
        if ($stmtUser->execute()) {
            $stmtUser->close();
            redirect_with_status("success", "User login credentials and role updated successfully.");
        } else {
            $stmtUser->close();
            redirect_with_status("error", "Failed to update user login profile.");
        }
        break;

    default:
        redirect_with_status("error", "Invalid request action.");
        break;
}

$conn->close();
?>
