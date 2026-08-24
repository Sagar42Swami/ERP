<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];
$user_email = $_SESSION["user_email"];
$role = $_SESSION["role"];
$employee_id = $_SESSION["employee_id"];

// Status message from URL parameters
$status_type = $_GET["status"] ?? "";
$status_msg = $_GET["msg"] ?? "";

// Fetch logged-in user's employee details if linked
$emp_details = null;
if (!empty($employee_id)) {
    $stmt = $conn->prepare("SELECT e.*, d.department_name, m.first_name as mgr_first, m.last_name as mgr_last 
                            FROM employees e 
                            LEFT JOIN departments d ON e.department_id = d.department_id 
                            LEFT JOIN employees m ON e.manager_id = m.employee_id 
                            WHERE e.employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$today = date("Y-m-d");

// Check today's attendance status for current employee
$attendance_today = null;
if (!empty($employee_id)) {
    $stmtAtt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $stmtAtt->bind_param("is", $employee_id, $today);
    $stmtAtt->execute();
    $attendance_today = $stmtAtt->get_result()->fetch_assoc();
    $stmtAtt->close();
}

// Global lists needed for forms
$all_employees = [];
$all_departments = [];
$all_projects = [];
$all_managers = []; // Employees who are potential managers (designation contains Manager or lead)

// Populate lists for Admin/HR/Manager forms
if (in_array($role, ["ADMIN", "HR", "MANAGER"])) {
    // Get all employees
    $res = $conn->query("SELECT employee_id, first_name, last_name, designation, email FROM employees WHERE employment_status = 'ACTIVE' ORDER BY first_name");
    while ($row = $res->fetch_assoc()) {
        $all_employees[] = $row;
        if (stripos($row["designation"], "Manager") !== false || stripos($row["designation"], "Lead") !== false || stripos($row["designation"], "Director") !== false || stripos($row["designation"], "Administrator") !== false) {
            $all_managers[] = $row;
        }
    }

    // Get all departments
    $res = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
    while ($row = $res->fetch_assoc()) {
        $all_departments[] = $row;
    }

    // Get all projects
    $res = $conn->query("SELECT project_id, project_name FROM projects WHERE status IN ('PLANNED', 'ACTIVE') ORDER BY project_name");
    while ($row = $res->fetch_assoc()) {
        $all_projects[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise ERP - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">ERP</div>
                <div>
                    <p class="brand-name">Corporate ERP</p>
                    <p class="brand-subtitle"><?php echo htmlspecialchars($role); ?> Workspace</p>
                </div>
            </div>

            <!-- Role Badge -->
            <div class="sidebar-user">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 2)); ?></div>
                <div class="user-info">
                    <p class="user-fullname"><?php echo htmlspecialchars($emp_details ? ($emp_details["first_name"] . " " . $emp_details["last_name"]) : $username); ?></p>
                    <span class="badge role-badge <?php echo strtolower($role); ?>-badge"><?php echo $role; ?></span>
                </div>
            </div>

            <nav class="menu">
                <a href="#" class="menu-item active" data-tab="overview">
                    <span class="icon">â˜–</span> Overview
                </a>

                <?php if ($role === "EMPLOYEE"): ?>
                    <a href="#" class="menu-item" data-tab="emp-attendance">
                        <span class="icon">ðŸ“…</span> Attendance
                    </a>
                    <a href="#" class="menu-item" data-tab="emp-leaves">
                        <span class="icon">âœ‰</span> Leave Requests
                    </a>
                    <a href="#" class="menu-item" data-tab="emp-payroll">
                        <span class="icon">ðŸ’µ</span> Pay Slips
                    </a>
                    <a href="#" class="menu-item" data-tab="emp-projects">
                        <span class="icon">ðŸ’¼</span> My Projects
                    </a>
                <?php endif; ?>

                <?php if ($role === "MANAGER"): ?>
                    <a href="#" class="menu-item" data-tab="mgr-leaves">
                        <span class="icon">âœ‰</span> Team Leaves
                    </a>
                    <a href="#" class="menu-item" data-tab="mgr-attendance">
                        <span class="icon">ðŸ“…</span> Team Attendance
                    </a>
                    <a href="#" class="menu-item" data-tab="mgr-projects">
                        <span class="icon">ðŸ’¼</span> Project Control
                    </a>
                <?php endif; ?>

                <?php if ($role === "HR"): ?>
                    <a href="#" class="menu-item" data-tab="hr-employees">
                        <span class="icon">ðŸ‘¥</span> Employees
                    </a>
                    <a href="#" class="menu-item" data-tab="hr-leaves">
                        <span class="icon">âœ‰</span> All Leaves
                    </a>
                    <a href="#" class="menu-item" data-tab="hr-payroll">
                        <span class="icon">ðŸ’µ</span> Manage Payroll
                    </a>
                    <a href="#" class="menu-item" data-tab="hr-attendance">
                        <span class="icon">ðŸ“…</span> Attendance Tracker
                    </a>
                <?php endif; ?>

                <?php if ($role === "ADMIN"): ?>
                    <a href="#" class="menu-item" data-tab="admin-users">
                        <span class="icon">ðŸ”’</span> User Accounts
                    </a>
                    <a href="#" class="menu-item" data-tab="admin-departments">
                        <span class="icon">ðŸ¢</span> Departments
                    </a>
                    <a href="#" class="menu-item" data-tab="admin-projects">
                        <span class="icon">ðŸ’¼</span> Project Management
                    </a>
                    <a href="#" class="menu-item" data-tab="admin-employees">
                        <span class="icon">ðŸ‘¥</span> All Employees
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="menu-item logout-item">
                    <span class="icon">âž”</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="dashboard-main">
            <!-- Topbar -->
            <header class="topbar">
                <div>
                    <p class="eyebrow">Enterprise Resource Planning</p>
                    <h1>Welcome, <?php echo htmlspecialchars($emp_details ? $emp_details["first_name"] : $username); ?>!</h1>
                </div>
                <div class="topbar-actions">
                    <span class="server-time">Date: <?php echo date("l, d-M-Y"); ?></span>
                </div>
            </header>

            <!-- Status Banner alerts -->
            <?php if (!empty($status_type)): ?>
                <div class="alert-banner <?php echo $status_type === "success" ? "alert-success" : "alert-error"; ?>">
                    <span class="alert-icon"><?php echo $status_type === "success" ? "âœ“" : "âš "; ?></span>
                    <span class="alert-text"><?php echo htmlspecialchars($status_msg); ?></span>
                    <button class="alert-close" onclick="this.parentElement.remove()">Ã—</button>
                </div>
            <?php endif; ?>

            <!-- ---------------------------------------------------- -->
            <!-- TAB: OVERVIEW -->
            <!-- ---------------------------------------------------- -->
            <section id="overview" class="tab-content active">
                <div class="section-header">
                    <h2>Workspace Overview</h2>
                    <p>Quick statistics and system summary.</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <?php if ($role === "EMPLOYEE"): ?>
                        <!-- Employee quick stats -->
                        <div class="stat-card">
                            <p class="stat-label">Today's Shift Status</p>
                            <?php if (empty($attendance_today)): ?>
                                <h3 class="danger-text">Absent / Not Checked-In</h3>
                                <form action="actions.php" method="POST">
                                    <input type="hidden" name="action" value="check_in">
                                    <button class="action-btn checkin-btn" type="submit">Check In Now</button>
                                </form>
                            <?php elseif (empty($attendance_today["check_out"])): ?>
                                <h3 class="warning-text">Checked In (<?php echo $attendance_today["check_in"]; ?>)</h3>
                                <form action="actions.php" method="POST">
                                    <input type="hidden" name="action" value="check_out">
                                    <button class="action-btn checkout-btn" type="submit">Check Out Now</button>
                                </form>
                            <?php else: ?>
                                <h3 class="success-text">Shift Completed</h3>
                                <p class="sub-text">Out: <?php echo $attendance_today["check_out"]; ?> (Hours: <?php echo $attendance_today["working_hours"]; ?>)</p>
                            <?php endif; ?>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">Pending Leave Applications</p>
                            <?php
                            $stmtL = $conn->prepare("SELECT COUNT(*) as pending_cnt FROM leaves WHERE employee_id = ? AND status = 'PENDING'");
                            $stmtL->bind_param("i", $employee_id);
                            $stmtL->execute();
                            $pCount = $stmtL->get_result()->fetch_assoc()["pending_cnt"];
                            $stmtL->close();
                            ?>
                            <h3><?php echo $pCount; ?> Requests</h3>
                            <p class="sub-text">Awaiting Manager approval</p>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">Assigned Projects</p>
                            <?php
                            $stmtP = $conn->prepare("SELECT COUNT(*) as proj_cnt FROM employee_projects WHERE employee_id = ? AND assignment_status = 'ACTIVE'");
                            $stmtP->bind_param("i", $employee_id);
                            $stmtP->execute();
                            $prCount = $stmtP->get_result()->fetch_assoc()["proj_cnt"];
                            $stmtP->close();
                            ?>
                            <h3><?php echo $prCount; ?> Active</h3>
                            <p class="sub-text">Projects in progress</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($role === "MANAGER"): ?>
                        <!-- Manager stats -->
                        <div class="stat-card">
                            <p class="stat-label">Pending Team Leaves</p>
                            <?php
                            $stmtM = $conn->prepare("SELECT COUNT(*) as cnt FROM leaves l JOIN employees e ON l.employee_id = e.employee_id WHERE e.manager_id = ? AND l.status = 'PENDING'");
                            $stmtM->bind_param("i", $employee_id);
                            $stmtM->execute();
                            $pLeave = $stmtM->get_result()->fetch_assoc()["cnt"];
                            $stmtM->close();
                            ?>
                            <h3 class="<?php echo $pLeave > 0 ? "warning-text" : ""; ?>"><?php echo $pLeave; ?> Leaves</h3>
                            <p class="sub-text">Awaiting your approval decision</p>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">Team Members Present Today</p>
                            <?php
                            $stmtT = $conn->prepare("SELECT COUNT(DISTINCT a.employee_id) as present_cnt FROM attendance a JOIN employees e ON a.employee_id = e.employee_id WHERE e.manager_id = ? AND a.attendance_date = ? AND a.status = 'PRESENT'");
                            $stmtT->bind_param("is", $employee_id, $today);
                            $stmtT->execute();
                            $teamPres = $stmtT->get_result()->fetch_assoc()["present_cnt"];
                            $stmtT->close();
                            ?>
                            <h3><?php echo $teamPres; ?> Present</h3>
                            <p class="sub-text">Out of team members active</p>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">My Managed Projects</p>
                            <?php
                            $stmtPr = $conn->prepare("SELECT COUNT(*) as cnt FROM projects WHERE manager_id = ? AND status = 'ACTIVE'");
                            $stmtPr->bind_param("i", $employee_id);
                            $stmtPr->execute();
                            $manProj = $stmtPr->get_result()->fetch_assoc()["cnt"];
                            $stmtPr->close();
                            ?>
                            <h3><?php echo $manProj; ?> Running</h3>
                            <p class="sub-text">Active projects under charge</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($role === "HR"): ?>
                        <!-- HR quick stats -->
                        <div class="stat-card">
                            <p class="stat-label">Active Company Workforce</p>
                            <?php
                            $activeCount = $conn->query("SELECT COUNT(*) as cnt FROM employees WHERE employment_status = 'ACTIVE'")->fetch_assoc()["cnt"];
                            ?>
                            <h3><?php echo $activeCount; ?> Employees</h3>
                            <p class="sub-text">Active employee contracts</p>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">Unpaid Payroll Slips</p>
                            <?php
                            $unpaidCount = $conn->query("SELECT COUNT(*) as cnt FROM payroll WHERE payment_status = 'PENDING'")->fetch_assoc()["cnt"];
                            ?>
                            <h3 class="warning-text"><?php echo $unpaidCount; ?> Pending</h3>
                            <p class="sub-text">Monthly pay runs awaiting payment</p>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">Total Leave Applications</p>
                            <?php
                            $allLeaveCount = $conn->query("SELECT COUNT(*) as cnt FROM leaves WHERE status = 'PENDING'")->fetch_assoc()["cnt"];
                            ?>
                            <h3><?php echo $allLeaveCount; ?> Pending Approval</h3>
                            <p class="sub-text">Across the entire company</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($role === "ADMIN"): ?>
                        <!-- Admin statistics -->
                        <div class="stat-card">
                            <p class="stat-label">Active Users</p>
                            <?php
                            $usersCount = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE account_status = 'ACTIVE'")->fetch_assoc()["cnt"];
                            ?>
                            <h3><?php echo $usersCount; ?> Accounts</h3>
                            <p class="sub-text">Users registered with credentials</p>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">Corporate Departments</p>
                            <?php
                            $deptCount = $conn->query("SELECT COUNT(*) as cnt FROM departments")->fetch_assoc()["cnt"];
                            ?>
                            <h3><?php echo $deptCount; ?> Divisions</h3>
                            <p class="sub-text">Company business divisions</p>
                        </div>

                        <div class="stat-card">
                            <p class="stat-label">Active Projects</p>
                            <?php
                            $projCount = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE status = 'ACTIVE'")->fetch_assoc()["cnt"];
                            ?>
                            <h3><?php echo $projCount; ?> Projects</h3>
                            <p class="sub-text">Currently running initiatives</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Profile Info Card -->
                <?php if ($emp_details): ?>
                    <div class="profile-card content-panel">
                        <div class="profile-header">
                            <div>
                                <h3>Employee Information File</h3>
                                <p class="sub-text">Your official record registered in the employees table.</p>
                            </div>
                        </div>
                        <div class="profile-details">
                            <div class="detail-group">
                                <span class="detail-lbl">Employee ID</span>
                                <span class="detail-val">#<?php echo $emp_details["employee_id"]; ?></span>
                            </div>
                            <div class="detail-group">
                                <span class="detail-lbl">Job Designation</span>
                                <span class="detail-val"><?php echo htmlspecialchars($emp_details["designation"]); ?></span>
                            </div>
                            <div class="detail-group">
                                <span class="detail-lbl">Department</span>
                                <span class="detail-val"><?php echo htmlspecialchars($emp_details["department_name"] ?? "Not Assigned"); ?></span>
                            </div>
                            <div class="detail-group">
                                <span class="detail-lbl">Email Address</span>
                                <span class="detail-val"><?php echo htmlspecialchars($emp_details["email"]); ?></span>
                            </div>
                            <div class="detail-group">
                                <span class="detail-lbl">Phone Number</span>
                                <span class="detail-val"><?php echo htmlspecialchars($emp_details["phone"]); ?></span>
                            </div>
                            <div class="detail-group">
                                <span class="detail-lbl">Reporting Manager</span>
                                <span class="detail-val"><?php echo $emp_details["manager_id"] ? htmlspecialchars($emp_details["mgr_first"] . " " . $emp_details["mgr_last"]) : "None (Direct/Admin)"; ?></span>
                            </div>
                            <div class="detail-group">
                                <span class="detail-lbl">Base Salary</span>
                                <span class="detail-val">$<?php echo number_format($emp_details["salary"], 2); ?></span>
                            </div>
                            <div class="detail-group">
                                <span class="detail-lbl">Hire Date</span>
                                <span class="detail-val"><?php echo $emp_details["hire_date"]; ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert-box content-panel warning">
                        <h3>No Employee Attachment</h3>
                        <p>This is a system administrator-only account and does not link to an employee profile. Certain features are disabled.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ---------------------------------------------------- -->
            <!-- EMPLOYEE TABS -->
            <!-- ---------------------------------------------------- -->
            <?php if ($role === "EMPLOYEE"): ?>
                <!-- Attendance Tab -->
                <section id="emp-attendance" class="tab-content">
                    <div class="section-header">
                        <h2>My Attendance Log</h2>
                        <p>Daily shift records, check-in, check-out and working hours.</p>
                    </div>

                    <div class="attendance-control content-panel">
                        <h3>Shift Attendance Action</h3>
                        <p>Check in when you start your working day, and check out when you complete your hours.</p>
                        
                        <div class="btn-row">
                            <form action="actions.php" method="POST">
                                <input type="hidden" name="action" value="check_in">
                                <button class="action-btn checkin-btn" type="submit" <?php echo !empty($attendance_today) ? "disabled" : ""; ?>>
                                    Check-In
                                </button>
                            </form>
                            
                            <form action="actions.php" method="POST">
                                <input type="hidden" name="action" value="check_out">
                                <button class="action-btn checkout-btn" type="submit" <?php echo (empty($attendance_today) || !empty($attendance_today["check_out"])) ? "disabled" : ""; ?>>
                                    Check-Out
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Past Attendance Records</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Working Hours</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 30");
                                $stmt->bind_param("i", $employee_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res->num_rows > 0):
                                    while ($att = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><strong><?php echo $att["attendance_date"]; ?></strong></td>
                                        <td><?php echo $att["check_in"] ?? "â€”"; ?></td>
                                        <td><?php echo $att["check_out"] ?? "â€”"; ?></td>
                                        <td><?php echo $att["working_hours"] ? ($att["working_hours"] . " hrs") : "â€”"; ?></td>
                                        <td><span class="badge status-<?php echo strtolower($att["status"]); ?>"><?php echo $att["status"]; ?></span></td>
                                        <td><?php echo htmlspecialchars($att["remarks"] ?? ""); ?></td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="center-text">No attendance records found yet.</td>
                                    </tr>
                                <?php endif; $stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Leaves Tab -->
                <section id="emp-leaves" class="tab-content">
                    <div class="section-header">
                        <h2>Leave Application Panel</h2>
                        <p>Submit and review your time-off applications.</p>
                    </div>

                    <div class="grid-2col">
                        <!-- Application Form -->
                        <div class="card content-panel">
                            <h3>Request Leave</h3>
                            <form action="actions.php" method="POST" class="standard-form">
                                <input type="hidden" name="action" value="apply_leave">

                                <div class="form-group">
                                    <label>Leave Type</label>
                                    <select name="leave_type" required>
                                        <option value="CASUAL">Casual Leave</option>
                                        <option value="SICK">Sick Leave</option>
                                        <option value="EARNED">Earned Leave</option>
                                        <option value="UNPAID">Unpaid Leave</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" required min="<?php echo date("Y-m-d"); ?>">
                                </div>

                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" required min="<?php echo date("Y-m-d"); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Reason / Comments</label>
                                    <textarea name="reason" placeholder="State reason for leave request" required rows="3"></textarea>
                                </div>

                                <button class="action-btn submit-btn" type="submit">Submit Leave Request</button>
                            </form>
                        </div>

                        <!-- Leaves History -->
                        <div class="table-container content-panel">
                            <h3>Leave Request History</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Dates</th>
                                        <th>Type</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("SELECT * FROM leaves WHERE employee_id = ? ORDER BY applied_at DESC");
                                    $stmt->bind_param("i", $employee_id);
                                    $stmt->execute();
                                    $res = $stmt->get_result();
                                    if ($res->num_rows > 0):
                                        while ($l = $res->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $l["start_date"]; ?></strong> to<br>
                                                <small><?php echo $l["end_date"]; ?></small>
                                            </td>
                                            <td><?php echo $l["leave_type"]; ?></td>
                                            <td><small><?php echo htmlspecialchars($l["reason"]); ?></small></td>
                                            <td><span class="badge status-<?php echo strtolower($l["status"]); ?>"><?php echo $l["status"]; ?></span></td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="4" class="center-text">No leave requests logged yet.</td>
                                        </tr>
                                    <?php endif; $stmt->close(); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Payroll Tab -->
                <section id="emp-payroll" class="tab-content">
                    <div class="section-header">
                        <h2>My Pay Slips</h2>
                        <p>Monthly payroll calculations and payment confirmations.</p>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Salary Credit History</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Basic</th>
                                    <th>Allowances (+)</th>
                                    <th>Bonus (+)</th>
                                    <th>Deductions (-)</th>
                                    <th>Tax (-)</th>
                                    <th>Net Salary</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY salary_month DESC");
                                $stmt->bind_param("i", $employee_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res->num_rows > 0):
                                    while ($p = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><strong><?php echo date("F Y", strtotime($p["salary_month"])); ?></strong></td>
                                        <td>$<?php echo number_format($p["basic_salary"], 2); ?></td>
                                        <td>$<?php echo number_format($p["allowance"], 2); ?></td>
                                        <td>$<?php echo number_format($p["bonus"], 2); ?></td>
                                        <td>$<?php echo number_format($p["deduction"], 2); ?></td>
                                        <td>$<?php echo number_format($p["tax"], 2); ?></td>
                                        <td class="highlight-net">$<strong><?php echo number_format($p["net_salary"], 2); ?></strong></td>
                                        <td><span class="badge status-<?php echo strtolower($p["payment_status"]); ?>"><?php echo $p["payment_status"]; ?></span></td>
                                        <td><?php echo $p["payment_date"] ?? "â€”"; ?></td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="9" class="center-text">No payroll sheets generated for your profile yet.</td>
                                    </tr>
                                <?php endif; $stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Projects Tab -->
                <section id="emp-projects" class="tab-content">
                    <div class="section-header">
                        <h2>My Assigned Projects</h2>
                        <p>Detailed list of initiatives you are mapped to.</p>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Current Assignments</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Project Name</th>
                                    <th>Description</th>
                                    <th>Your Role</th>
                                    <th>Assigned Date</th>
                                    <th>Manager</th>
                                    <th>Project Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT p.project_name, p.description, p.status as p_status, ep.role, ep.assigned_date, mgr.first_name as m_first, mgr.last_name as m_last 
                                                        FROM employee_projects ep 
                                                        JOIN projects p ON ep.project_id = p.project_id 
                                                        LEFT JOIN employees mgr ON p.manager_id = mgr.employee_id 
                                                        WHERE ep.employee_id = ? AND ep.assignment_status = 'ACTIVE'");
                                $stmt->bind_param("i", $employee_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res->num_rows > 0):
                                    while ($pr = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($pr["project_name"]); ?></strong></td>
                                        <td><small><?php echo htmlspecialchars($pr["description"]); ?></small></td>
                                        <td><?php echo htmlspecialchars($pr["role"]); ?></td>
                                        <td><?php echo $pr["assigned_date"]; ?></td>
                                        <td><?php echo $pr["m_first"] ? htmlspecialchars($pr["m_first"] . " " . $pr["m_last"]) : "Admin Managed"; ?></td>
                                        <td><span class="badge status-<?php echo strtolower($pr["p_status"]); ?>"><?php echo $pr["p_status"]; ?></span></td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="center-text">You are not actively assigned to any projects.</td>
                                    </tr>
                                <?php endif; $stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

</main></div><script src="script.js"></script></body></html>