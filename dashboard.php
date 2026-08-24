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
                    <span class="icon">☖</span> Overview
                </a>

                <?php if ($role === "EMPLOYEE"): ?>
                    <a href="#" class="menu-item" data-tab="emp-attendance">
                        <span class="icon">📅</span> Attendance
                    </a>
                    <a href="#" class="menu-item" data-tab="emp-leaves">
                        <span class="icon">✉</span> Leave Requests
                    </a>
                    <a href="#" class="menu-item" data-tab="emp-payroll">
                        <span class="icon">💵</span> Pay Slips
                    </a>
                    <a href="#" class="menu-item" data-tab="emp-projects">
                        <span class="icon">💼</span> My Projects
                    </a>
                <?php endif; ?>

                <?php if ($role === "MANAGER"): ?>
                    <a href="#" class="menu-item" data-tab="mgr-leaves">
                        <span class="icon">✉</span> Team Leaves
                    </a>
                    <a href="#" class="menu-item" data-tab="mgr-attendance">
                        <span class="icon">📅</span> Team Attendance
                    </a>
                    <a href="#" class="menu-item" data-tab="mgr-projects">
                        <span class="icon">💼</span> Project Control
                    </a>
                <?php endif; ?>

                <?php if ($role === "HR"): ?>
                    <a href="#" class="menu-item" data-tab="hr-employees">
                        <span class="icon">👥</span> Employees
                    </a>
                    <a href="#" class="menu-item" data-tab="hr-leaves">
                        <span class="icon">✉</span> All Leaves
                    </a>
                    <a href="#" class="menu-item" data-tab="hr-payroll">
                        <span class="icon">💵</span> Manage Payroll
                    </a>
                    <a href="#" class="menu-item" data-tab="hr-attendance">
                        <span class="icon">📅</span> Attendance Tracker
                    </a>
                <?php endif; ?>

                <?php if ($role === "ADMIN"): ?>
                    <a href="#" class="menu-item" data-tab="admin-users">
                        <span class="icon">🔒</span> User Accounts
                    </a>
                    <a href="#" class="menu-item" data-tab="admin-departments">
                        <span class="icon">🏢</span> Departments
                    </a>
                    <a href="#" class="menu-item" data-tab="admin-projects">
                        <span class="icon">💼</span> Project Management
                    </a>
                    <a href="#" class="menu-item" data-tab="admin-employees">
                        <span class="icon">👥</span> All Employees
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="menu-item logout-item">
                    <span class="icon">➔</span> Logout
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
                    <span class="alert-icon"><?php echo $status_type === "success" ? "✓" : "⚠"; ?></span>
                    <span class="alert-text"><?php echo htmlspecialchars($status_msg); ?></span>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
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
                                        <td><?php echo $att["check_in"] ?? "—"; ?></td>
                                        <td><?php echo $att["check_out"] ?? "—"; ?></td>
                                        <td><?php echo $att["working_hours"] ? ($att["working_hours"] . " hrs") : "—"; ?></td>
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
                                        <td><?php echo $p["payment_date"] ?? "—"; ?></td>
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

            <!-- ---------------------------------------------------- -->
            <!-- MANAGER TABS -->
            <!-- ---------------------------------------------------- -->
            <?php if ($role === "MANAGER"): ?>
                <!-- Team Leaves Tab -->
                <section id="mgr-leaves" class="tab-content">
                    <div class="section-header">
                        <h2>Team Leaves Approvals</h2>
                        <p>Approve or Reject leave requests submitted by employees reporting to you.</p>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Pending Leave Requests</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Designation</th>
                                    <th>Date range</th>
                                    <th>Leave Type</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT l.*, e.first_name, e.last_name, e.designation 
                                                        FROM leaves l 
                                                        JOIN employees e ON l.employee_id = e.employee_id 
                                                        WHERE e.manager_id = ? AND l.status = 'PENDING' 
                                                        ORDER BY l.applied_at ASC");
                                $stmt->bind_param("i", $employee_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res->num_rows > 0):
                                    while ($l = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($l["first_name"] . " " . $l["last_name"]); ?></strong></td>
                                        <td><small><?php echo htmlspecialchars($l["designation"]); ?></small></td>
                                        <td><?php echo $l["start_date"]; ?> to <?php echo $l["end_date"]; ?></td>
                                        <td><?php echo $l["leave_type"]; ?></td>
                                        <td><small><?php echo htmlspecialchars($l["reason"]); ?></small></td>
                                        <td>
                                            <div class="flex-row">
                                                <form action="actions.php" method="POST" style="margin-right: 5px;">
                                                    <input type="hidden" name="action" value="update_leave_status">
                                                    <input type="hidden" name="leave_id" value="<?php echo $l["leave_id"]; ?>">
                                                    <input type="hidden" name="status" value="APPROVED">
                                                    <button class="small-btn approve-btn" type="submit">Approve</button>
                                                </form>
                                                <form action="actions.php" method="POST">
                                                    <input type="hidden" name="action" value="update_leave_status">
                                                    <input type="hidden" name="leave_id" value="<?php echo $l["leave_id"]; ?>">
                                                    <input type="hidden" name="status" value="REJECTED">
                                                    <button class="small-btn reject-btn" type="submit">Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="center-text">No pending leaves for your team.</td>
                                    </tr>
                                <?php endif; $stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Team Attendance Tab -->
                <section id="mgr-attendance" class="tab-content">
                    <div class="section-header">
                        <h2>Team Daily Attendance Tracking</h2>
                        <p>Real-time view of attendance logs for your direct reports today (<?php echo $today; ?>).</p>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Team Presence Status</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Designation</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Working Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT e.first_name, e.last_name, e.designation, a.check_in, a.check_out, a.working_hours, a.status 
                                                        FROM employees e 
                                                        LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ? 
                                                        WHERE e.manager_id = ? AND e.employment_status = 'ACTIVE'");
                                $stmt->bind_param("si", $today, $employee_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res->num_rows > 0):
                                    while ($t = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($t["first_name"] . " " . $t["last_name"]); ?></strong></td>
                                        <td><small><?php echo htmlspecialchars($t["designation"]); ?></small></td>
                                        <td><?php echo $t["check_in"] ?? "—"; ?></td>
                                        <td><?php echo $t["check_out"] ?? "—"; ?></td>
                                        <td><?php echo $t["working_hours"] ? ($t["working_hours"] . " hrs") : "—"; ?></td>
                                        <td>
                                            <span class="badge status-<?php echo strtolower($t["status"] ?? "ABSENT"); ?>">
                                                <?php echo $t["status"] ?? "ABSENT"; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="center-text">No active team members reported under your manager profile.</td>
                                    </tr>
                                <?php endif; $stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Project Control Tab -->
                <section id="mgr-projects" class="tab-content">
                    <div class="section-header">
                        <h2>Team Project & Task Manager</h2>
                        <p>Manage project resources and employee project assignments.</p>
                    </div>

                    <div class="grid-2col">
                        <!-- Project Assign form -->
                        <div class="card content-panel">
                            <h3>Assign Team Member to Project</h3>
                            <form action="actions.php" method="POST" class="standard-form">
                                <input type="hidden" name="action" value="assign_employee_project">

                                <div class="form-group">
                                    <label>Select Project</label>
                                    <select name="project_id" required>
                                        <option value="">-- Choose Project --</option>
                                        <?php
                                        // Retrieve projects managed by this employee
                                        $stmt = $conn->prepare("SELECT project_id, project_name FROM projects WHERE manager_id = ? AND status='ACTIVE'");
                                        $stmt->bind_param("i", $employee_id);
                                        $stmt->execute();
                                        $pRes = $stmt->get_result();
                                        while ($pRow = $pRes->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $pRow["project_id"]; ?>"><?php echo htmlspecialchars($pRow["project_name"]); ?></option>
                                        <?php endwhile; $stmt->close(); ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Team Member</label>
                                    <select name="employee_id" required>
                                        <option value="">-- Choose Employee --</option>
                                        <?php
                                        // Employees reporting to this manager
                                        $stmt = $conn->prepare("SELECT employee_id, first_name, last_name FROM employees WHERE manager_id = ? AND employment_status = 'ACTIVE'");
                                        $stmt->bind_param("i", $employee_id);
                                        $stmt->execute();
                                        $eRes = $stmt->get_result();
                                        while ($eRow = $eRes->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $eRow["employee_id"]; ?>"><?php echo htmlspecialchars($eRow["first_name"] . " " . $eRow["last_name"]); ?></option>
                                        <?php endwhile; $stmt->close(); ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Role in Project</label>
                                    <input type="text" name="role" placeholder="e.g. Lead Analyst, Developer, QA Engineer" required>
                                </div>

                                <button class="action-btn submit-btn" type="submit">Assign to Project</button>
                            </form>
                        </div>

                        <!-- Current Project Resource Allocations -->
                        <div class="table-container content-panel">
                            <h3>Active Project Team Mappings</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Team Member</th>
                                        <th>Project Role</th>
                                        <th>Assigned Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("SELECT p.project_name, e.first_name, e.last_name, ep.role, ep.assigned_date 
                                                            FROM employee_projects ep 
                                                            JOIN projects p ON ep.project_id = p.project_id 
                                                            JOIN employees e ON ep.employee_id = e.employee_id 
                                                            WHERE p.manager_id = ? AND ep.assignment_status = 'ACTIVE'");
                                    $stmt->bind_param("i", $employee_id);
                                    $stmt->execute();
                                    $res = $stmt->get_result();
                                    if ($res->num_rows > 0):
                                        while ($epRow = $res->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($epRow["project_name"]); ?></strong></td>
                                            <td><?php echo htmlspecialchars($epRow["first_name"] . " " . $epRow["last_name"]); ?></td>
                                            <td><small><?php echo htmlspecialchars($epRow["role"]); ?></small></td>
                                            <td><?php echo $epRow["assigned_date"]; ?></td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="4" class="center-text">No active assignments found for your managed projects.</td>
                                        </tr>
                                    <?php endif; $stmt->close(); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ---------------------------------------------------- -->
            <!-- HR TABS -->
            <!-- ---------------------------------------------------- -->
            <?php if ($role === "HR"): ?>
                <!-- Employees Tab -->
                <section id="hr-employees" class="tab-content">
                    <div class="section-header">
                        <h2>Staff Directory Management</h2>
                        <p>View, onboard, and modify employee profiles and departments.</p>
                    </div>

                    <!-- Add Employee Note -->
                    <div class="alert-box content-panel info">
                        <h3>Need to onboard a new employee?</h3>
                        <p>Please use the <a href="signup.html" target="_blank" style="text-decoration: underline; color: inherit; font-weight: 600;">ERP Registration Link</a> to add employees and set up their active login profile concurrently.</p>
                    </div>

                    <!-- Update Employee Form -->
                    <div class="card content-panel">
                        <h3>Update Employee Details</h3>
                        <form action="actions.php" method="POST" class="standard-form horizontal-form">
                            <input type="hidden" name="action" value="update_employee">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Select Employee</label>
                                    <select name="employee_id" id="edit_emp_select" onchange="populateEmployeeEditForm(this.value)" required>
                                        <option value="">-- Choose Employee to Modify --</option>
                                        <?php foreach ($all_employees as $emp): ?>
                                            <option value="<?php echo $emp["employee_id"]; ?>"><?php echo htmlspecialchars($emp["first_name"] . " " . $emp["last_name"]); ?> (#<?php echo $emp["employee_id"]; ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" id="edit_emp_fname" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" id="edit_emp_lname" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" id="edit_emp_email" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" id="edit_emp_phone" required>
                                </div>
                                <div class="form-group">
                                    <label>Salary (USD)</label>
                                    <input type="number" name="salary" id="edit_emp_salary" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Designation</label>
                                    <input type="text" name="designation" id="edit_emp_desig" required>
                                </div>
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="department_id" id="edit_emp_dept" required>
                                        <?php foreach ($all_departments as $d): ?>
                                            <option value="<?php echo $d["department_id"]; ?>"><?php echo htmlspecialchars($d["department_name"]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Reporting Manager</label>
                                    <select name="manager_id" id="edit_emp_mgr">
                                        <option value="0">None</option>
                                        <?php foreach ($all_employees as $mgr): ?>
                                            <option value="<?php echo $mgr["employee_id"]; ?>"><?php echo htmlspecialchars($mgr["first_name"] . " " . $mgr["last_name"]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Employment Status</label>
                                    <select name="employment_status" id="edit_emp_status" required>
                                        <option value="ACTIVE">Active Employee</option>
                                        <option value="INACTIVE">Inactive / Suspended</option>
                                        <option value="RESIGNED">Resigned / Terminated</option>
                                    </select>
                                </div>
                                <div class="form-group" style="justify-content: flex-end; align-items: flex-end; display: flex;">
                                    <button class="action-btn submit-btn" type="submit" style="width: 100%;">Save Modifications</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Employee Table List -->
                    <div class="table-container content-panel">
                        <h3>Employee Records Database</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Email & Phone</th>
                                    <th>Department</th>
                                    <th>Designation</th>
                                    <th>Salary</th>
                                    <th>Manager</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = $conn->query("SELECT e.*, d.department_name, m.first_name as m_fname, m.last_name as m_lname 
                                                     FROM employees e 
                                                     LEFT JOIN departments d ON e.department_id = d.department_id 
                                                     LEFT JOIN employees m ON e.manager_id = m.employee_id 
                                                     ORDER BY e.employee_id ASC");
                                while ($e = $res->fetch_assoc()):
                                ?>
                                    <tr id="emp_row_<?php echo $e["employee_id"]; ?>" 
                                        data-fname="<?php echo htmlspecialchars($e["first_name"]); ?>"
                                        data-lname="<?php echo htmlspecialchars($e["last_name"]); ?>"
                                        data-email="<?php echo htmlspecialchars($e["email"]); ?>"
                                        data-phone="<?php echo htmlspecialchars($e["phone"]); ?>"
                                        data-salary="<?php echo htmlspecialchars($e["salary"]); ?>"
                                        data-desig="<?php echo htmlspecialchars($e["designation"]); ?>"
                                        data-dept="<?php echo htmlspecialchars($e["department_id"]); ?>"
                                        data-mgr="<?php echo htmlspecialchars($e["manager_id"] ?? 0); ?>"
                                        data-status="<?php echo htmlspecialchars($e["employment_status"]); ?>">
                                        <td>#<?php echo $e["employee_id"]; ?></td>
                                        <td><strong><?php echo htmlspecialchars($e["first_name"] . " " . $e["last_name"]); ?></strong></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($e["email"]); ?></small><br>
                                            <small class="muted-text"><?php echo htmlspecialchars($e["phone"]); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($e["department_name"] ?? "Not Assigned"); ?></td>
                                        <td><?php echo htmlspecialchars($e["designation"]); ?></td>
                                        <td>$<?php echo number_format($e["salary"], 2); ?></td>
                                        <td><?php echo $e["manager_id"] ? htmlspecialchars($e["m_fname"] . " " . $e["m_lname"]) : "—"; ?></td>
                                        <td><span class="badge status-<?php echo strtolower($e["employment_status"]); ?>"><?php echo $e["employment_status"]; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- All Leaves Tab -->
                <section id="hr-leaves" class="tab-content">
                    <div class="section-header">
                        <h2>Leave System Overview</h2>
                        <p>View leave request statuses from all departments.</p>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Employee Leave Registers</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Period</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Handled By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = $conn->query("SELECT l.*, e.first_name, e.last_name, mgr.first_name as m_fname, mgr.last_name as m_lname 
                                                     FROM leaves l 
                                                     JOIN employees e ON l.employee_id = e.employee_id 
                                                     LEFT JOIN employees mgr ON l.approved_by = mgr.employee_id 
                                                     ORDER BY l.applied_at DESC");
                                if ($res->num_rows > 0):
                                    while ($l = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($l["first_name"] . " " . $l["last_name"]); ?></strong></td>
                                        <td><?php echo $l["start_date"]; ?> to <?php echo $l["end_date"]; ?></td>
                                        <td><?php echo $l["leave_type"]; ?></td>
                                        <td><small><?php echo htmlspecialchars($l["reason"]); ?></small></td>
                                        <td><span class="badge status-<?php echo strtolower($l["status"]); ?>"><?php echo $l["status"]; ?></span></td>
                                        <td><?php echo $l["approved_by"] ? htmlspecialchars($l["m_fname"] . " " . $l["m_lname"]) : "—"; ?></td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="center-text">No leaves filed.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Manage Payroll Tab -->
                <section id="hr-payroll" class="tab-content">
                    <div class="section-header">
                        <h2>Payroll & Salary Run Management</h2>
                        <p>Calculate salaries, tax components, and authorize monthly payouts.</p>
                    </div>

                    <div class="grid-2col">
                        <!-- Create Payroll Form -->
                        <div class="card content-panel">
                            <h3>Generate Monthly Payroll Slips</h3>
                            <p class="sub-text">Calculates tax (8% of gross salary) and writes records with UNIQUE(employee, month) verification.</p>
                            <form action="actions.php" method="POST" class="standard-form">
                                <input type="hidden" name="action" value="generate_payroll">

                                <div class="form-group">
                                    <label>Select Employee</label>
                                    <select name="employee_id" required>
                                        <option value="">-- Choose Employee --</option>
                                        <?php foreach ($all_employees as $emp): ?>
                                            <option value="<?php echo $emp["employee_id"]; ?>"><?php echo htmlspecialchars($emp["first_name"] . " " . $emp["last_name"]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Select Month</label>
                                    <input type="month" name="salary_month" required value="<?php echo date("Y-m"); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Allowance (USD)</label>
                                    <input type="number" name="allowance" min="0" value="0" step="0.01">
                                </div>

                                <div class="form-group">
                                    <label>Bonus (USD)</label>
                                    <input type="number" name="bonus" min="0" value="0" step="0.01">
                                </div>

                                <div class="form-group">
                                    <label>Deductions (USD)</label>
                                    <input type="number" name="deduction" min="0" value="0" step="0.01">
                                </div>

                                <button class="action-btn submit-btn" type="submit">Generate Payroll Sheet</button>
                            </form>
                        </div>

                        <!-- All Payroll Sheet List -->
                        <div class="table-container content-panel">
                            <h3>Salaries and Payments Register</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Month</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $res = $conn->query("SELECT p.*, e.first_name, e.last_name 
                                                         FROM payroll p 
                                                         JOIN employees e ON p.employee_id = e.employee_id 
                                                         ORDER BY p.salary_month DESC");
                                    if ($res->num_rows > 0):
                                        while ($p = $res->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($p["first_name"] . " " . $p["last_name"]); ?></strong><br>
                                                <small class="muted-text">Basic: $<?php echo number_format($p["basic_salary"], 2); ?></small>
                                            </td>
                                            <td><?php echo date("M-Y", strtotime($p["salary_month"])); ?></td>
                                            <td>$<strong><?php echo number_format($p["net_salary"], 2); ?></strong></td>
                                            <td><span class="badge status-<?php echo strtolower($p["payment_status"]); ?>"><?php echo $p["payment_status"]; ?></span></td>
                                            <td>
                                                <?php if ($p["payment_status"] === "PENDING"): ?>
                                                    <form action="actions.php" method="POST">
                                                        <input type="hidden" name="action" value="pay_payroll">
                                                        <input type="hidden" name="payroll_id" value="<?php echo $p["payroll_id"]; ?>">
                                                        <button class="small-btn approve-btn" type="submit">Mark Paid</button>
                                                    </form>
                                                <?php else: ?>
                                                    Paid on:<br><small><?php echo $p["payment_date"]; ?></small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="5" class="center-text">No payroll records logged.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Attendance Tracker Tab -->
                <section id="hr-attendance" class="tab-content">
                    <div class="section-header">
                        <h2>Overall Attendance Log</h2>
                        <p>Review daily punch cards across all company personnel.</p>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Complete Attendance History</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Worked Hours</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = $conn->query("SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON a.employee_id = e.employee_id ORDER BY a.attendance_date DESC, a.check_in DESC LIMIT 50");
                                if ($res->num_rows > 0):
                                    while ($att = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><strong><?php echo $att["attendance_date"]; ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($att["first_name"] . " " . $att["last_name"]); ?></strong></td>
                                        <td><?php echo $att["check_in"] ?? "—"; ?></td>
                                        <td><?php echo $att["check_out"] ?? "—"; ?></td>
                                        <td><?php echo $att["working_hours"] ? ($att["working_hours"] . " hrs") : "—"; ?></td>
                                        <td><span class="badge status-<?php echo strtolower($att["status"]); ?>"><?php echo $att["status"]; ?></span></td>
                                        <td><small><?php echo htmlspecialchars($att["remarks"] ?? ""); ?></small></td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="7" class="center-text">No attendance records stored in database yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ---------------------------------------------------- -->
            <!-- ADMIN TABS -->
            <!-- ---------------------------------------------------- -->
            <?php if ($role === "ADMIN"): ?>
                <!-- User Accounts Tab -->
                <section id="admin-users" class="tab-content">
                    <div class="section-header">
                        <h2>User Credentials and Role Manager</h2>
                        <p>Modify application roles and lock/unlock login system access profiles.</p>
                    </div>

                    <div class="card content-panel">
                        <h3>Modify System Account Access</h3>
                        <form action="actions.php" method="POST" class="standard-form horizontal-form">
                            <input type="hidden" name="action" value="update_user_status">

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Select Login Account</label>
                                    <select name="target_user_id" id="edit_user_select" onchange="populateUserEditForm(this.value)" required>
                                        <option value="">-- Choose User Account --</option>
                                        <?php
                                        $uRes = $conn->query("SELECT user_id, username, email FROM users ORDER BY username");
                                        while ($uRow = $uRes->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $uRow["user_id"]; ?>"><?php echo htmlspecialchars($uRow["username"]); ?> (<?php echo htmlspecialchars($uRow["email"]); ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>System Permission Role</label>
                                    <select name="role" id="edit_user_role" required>
                                        <option value="EMPLOYEE">Employee (Standard Access)</option>
                                        <option value="MANAGER">Manager (Team Leader)</option>
                                        <option value="HR">HR Specialist</option>
                                        <option value="ADMIN">System Administrator</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Account Status</label>
                                    <select name="account_status" id="edit_user_status" required>
                                        <option value="ACTIVE">ACTIVE (Access Granted)</option>
                                        <option value="INACTIVE">INACTIVE (Locked/Suspended)</option>
                                    </select>
                                </div>

                                <div class="form-group" style="justify-content: flex-end; align-items: flex-end; display: flex;">
                                    <button class="action-btn submit-btn" type="submit" style="width: 100%;">Save Access Profile</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Registered ERP Login Users</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Username</th>
                                    <th>Email Address</th>
                                    <th>Assigned Role</th>
                                    <th>Employee ID</th>
                                    <th>Status</th>
                                    <th>Last Login Session</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $uRes = $conn->query("SELECT u.*, e.first_name, e.last_name FROM users u LEFT JOIN employees e ON u.employee_id = e.employee_id ORDER BY u.user_id ASC");
                                while ($u = $uRes->fetch_assoc()):
                                ?>
                                    <tr id="user_row_<?php echo $u["user_id"]; ?>"
                                        data-role="<?php echo htmlspecialchars($u["role"]); ?>"
                                        data-status="<?php echo htmlspecialchars($u["account_status"]); ?>">
                                        <td>#<?php echo $u["user_id"]; ?></td>
                                        <td><strong><?php echo htmlspecialchars($u["username"]); ?></strong></td>
                                        <td><?php echo htmlspecialchars($u["email"]); ?></td>
                                        <td><span class="badge role-badge <?php echo strtolower($u["role"]); ?>-badge"><?php echo $u["role"]; ?></span></td>
                                        <td><?php echo $u["employee_id"] ? ("#" . $u["employee_id"] . " (" . htmlspecialchars($u["first_name"] . " " . $u["last_name"]) . ")") : "<span class='muted-text'>None (SysAdmin Only)</span>"; ?></td>
                                        <td><span class="badge status-<?php echo strtolower($u["account_status"]); ?>"><?php echo $u["account_status"]; ?></span></td>
                                        <td><small><?php echo $u["last_login"] ?? "Never logged in"; ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Departments Tab -->
                <section id="admin-departments" class="tab-content">
                    <div class="section-header">
                        <h2>Departments Management</h2>
                        <p>Create corporate divisions and assign management personnel.</p>
                    </div>

                    <div class="grid-2col">
                        <!-- Add Department Form -->
                        <div class="card content-panel">
                            <h3>Create New Department</h3>
                            <form action="actions.php" method="POST" class="standard-form">
                                <input type="hidden" name="action" value="create_department">

                                <div class="form-group">
                                    <label>Department Name</label>
                                    <input type="text" name="department_name" placeholder="e.g. Quality Assurance" required>
                                </div>

                                <div class="form-group">
                                    <label>Description</label>
                                    <input type="text" name="description" placeholder="Corporate description of division">
                                </div>

                                <div class="form-group">
                                    <label>Office Location / Desk Space</label>
                                    <input type="text" name="location" placeholder="e.g. Block C, Floor 4">
                                </div>

                                <div class="form-group">
                                    <label>Assigned Department Manager</label>
                                    <select name="manager_id">
                                        <option value="0">-- Select Manager (Optional) --</option>
                                        <?php foreach ($all_employees as $emp): ?>
                                            <option value="<?php echo $emp["employee_id"]; ?>"><?php echo htmlspecialchars($emp["first_name"] . " " . $emp["last_name"]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button class="action-btn submit-btn" type="submit">Create Division</button>
                            </form>
                        </div>

                        <!-- Department registers -->
                        <div class="table-container content-panel">
                            <h3>Corporate Division List</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Location</th>
                                        <th>Assigned Manager</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $dRes = $conn->query("SELECT d.*, e.first_name, e.last_name FROM departments d LEFT JOIN employees e ON d.manager_id = e.employee_id ORDER BY d.department_name");
                                    while ($d = $dRes->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($d["department_name"]); ?></strong><br>
                                                <small class="muted-text"><?php echo htmlspecialchars($d["description"] ?? ""); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($d["location"] ?? "—"); ?></td>
                                            <td><?php echo $d["manager_id"] ? htmlspecialchars($d["first_name"] . " " . $d["last_name"]) : "Vacant"; ?></td>
                                            <td><small><?php echo date("Y-m-d", strtotime($d["created_at"])); ?></small></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Projects Tab (Admin) -->
                <section id="admin-projects" class="tab-content">
                    <div class="section-header">
                        <h2>Projects and Assignments Engine</h2>
                        <p>Deploy company initiatives, budget allowances, and allocate resources (M:N mapping).</p>
                    </div>

                    <div class="grid-2col">
                        <!-- Add Project Form -->
                        <div class="card content-panel">
                            <h3>Initiate Corporate Project</h3>
                            <form action="actions.php" method="POST" class="standard-form">
                                <input type="hidden" name="action" value="create_project">

                                <div class="form-group">
                                    <label>Project Name</label>
                                    <input type="text" name="project_name" placeholder="ERP Automation Module" required>
                                </div>

                                <div class="form-group">
                                    <label>Project Description</label>
                                    <textarea name="description" placeholder="Project goals and technical brief" rows="3"></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" required value="<?php echo date("Y-m-d"); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Target End Date</label>
                                    <input type="date" name="end_date">
                                </div>

                                <div class="form-group">
                                    <label>Financial Budget Allowance (USD)</label>
                                    <input type="number" name="budget" placeholder="100000" min="0" step="0.01" required>
                                </div>

                                <div class="form-group">
                                    <label>Responsible Lead Manager</label>
                                    <select name="manager_id">
                                        <option value="0">-- Select Manager --</option>
                                        <?php foreach ($all_employees as $emp): ?>
                                            <option value="<?php echo $emp["employee_id"]; ?>"><?php echo htmlspecialchars($emp["first_name"] . " " . $emp["last_name"]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button class="action-btn submit-btn" type="submit">Register Project</button>
                            </form>
                        </div>

                        <!-- Project Assign form (Admin version) -->
                        <div class="card content-panel">
                            <h3>Map Employee to Project (M:N Relationship)</h3>
                            <p class="sub-text">Resolves many-to-many relationships, recording active roles.</p>
                            <form action="actions.php" method="POST" class="standard-form">
                                <input type="hidden" name="action" value="assign_employee_project">

                                <div class="form-group">
                                    <label>Select Project</label>
                                    <select name="project_id" required>
                                        <option value="">-- Choose Project --</option>
                                        <?php
                                        $stmt = $conn->query("SELECT project_id, project_name FROM projects ORDER BY project_name");
                                        while ($pRow = $stmt->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $pRow["project_id"]; ?>"><?php echo htmlspecialchars($pRow["project_name"]); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Select Employee</label>
                                    <select name="employee_id" required>
                                        <option value="">-- Choose Employee --</option>
                                        <?php foreach ($all_employees as $eRow): ?>
                                            <option value="<?php echo $eRow["employee_id"]; ?>"><?php echo htmlspecialchars($eRow["first_name"] . " " . $eRow["last_name"]); ?> (<?php echo htmlspecialchars($eRow["designation"]); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Role in Project Group</label>
                                    <input type="text" name="role" placeholder="e.g. Lead Architect, QA Specialist" required>
                                </div>

                                <button class="action-btn submit-btn" type="submit">Map Staff Allocation</button>
                            </form>
                        </div>
                    </div>

                    <!-- Projects and Assignments Table -->
                    <div class="table-container content-panel">
                        <h3>Active Projects and Allocated Team Size</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Project Name</th>
                                    <th>Timeline</th>
                                    <th>Budget</th>
                                    <th>Manager</th>
                                    <th>Status</th>
                                    <th>Team Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $pRes = $conn->query("SELECT p.*, e.first_name, e.last_name, (SELECT COUNT(*) FROM employee_projects ep WHERE ep.project_id = p.project_id) as team_size 
                                                      FROM projects p 
                                                      LEFT JOIN employees e ON p.manager_id = e.employee_id 
                                                      ORDER BY p.start_date DESC");
                                if ($pRes->num_rows > 0):
                                    while ($pr = $pRes->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pr["project_name"]); ?></strong><br>
                                            <small class="muted-text"><?php echo htmlspecialchars($pr["description"]); ?></small>
                                        </td>
                                        <td>
                                            <small>Start: <?php echo $pr["start_date"]; ?></small><br>
                                            <small>End: <?php echo $pr["end_date"] ?? "Ongoing"; ?></small>
                                        </td>
                                        <td>$<?php echo number_format($pr["budget"], 2); ?></td>
                                        <td><?php echo $pr["manager_id"] ? htmlspecialchars($pr["first_name"] . " " . $pr["last_name"]) : "Admin Direct"; ?></td>
                                        <td><span class="badge status-<?php echo strtolower($pr["status"]); ?>"><?php echo $pr["status"]; ?></span></td>
                                        <td><strong><?php echo $pr["team_size"]; ?></strong> assigned</td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="center-text">No corporate projects registered yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Admin Employees (CRUD access shortcut) -->
                <section id="admin-employees" class="tab-content">
                    <div class="section-header">
                        <h2>Directory Lookup & Control</h2>
                        <p>Complete directory catalog lists. (Admin View)</p>
                    </div>

                    <div class="table-container content-panel">
                        <h3>Company Personnel Directory</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Email & Phone</th>
                                    <th>Department</th>
                                    <th>Salary</th>
                                    <th>Date Hired</th>
                                    <th>Contract</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = $conn->query("SELECT e.*, d.department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id ORDER BY e.employee_id ASC");
                                while ($e = $res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td>#<?php echo $e["employee_id"]; ?></td>
                                        <td><strong><?php echo htmlspecialchars($e["first_name"] . " " . $e["last_name"]); ?></strong></td>
                                        <td><?php echo htmlspecialchars($e["designation"]); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($e["email"]); ?></small><br>
                                            <small class="muted-text"><?php echo htmlspecialchars($e["phone"]); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($e["department_name"] ?? "Not Assigned"); ?></td>
                                        <td>$<?php echo number_format($e["salary"], 2); ?></td>
                                        <td><small><?php echo $e["hire_date"]; ?></small></td>
                                        <td><span class="badge status-<?php echo strtolower($e["employment_status"]); ?>"><?php echo $e["employment_status"]; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

        </main>
    </div>

    <!-- UI Interactions Script -->
    <script src="script.js"></script>
</body>
</html>
<?php
$conn->close();
?>
