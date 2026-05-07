<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'db_connect.php';

// --- Page Router Logic ---
 $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// --- NEW: EDIT & REMOVE HANDLERS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. EDIT STUDENT HANDLER
    if (isset($_POST['update_student'])) {
        $id = intval($_POST['user_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $dept = $conn->real_escape_string($_POST['department']);
        $year = $conn->real_escape_string($_POST['academic_year']);

        // Update Users Table
        $conn->query("UPDATE users SET name='$name', email='$email', phone='$phone' WHERE id=$id");
        // Update Students Table
        $conn->query("UPDATE students SET department='$dept', academic_year='$year' WHERE user_id=$id");
        
        $message = "Student details updated successfully!";
    }

    // 2. REMOVE STUDENT HANDLER
    if (isset($_POST['remove_student'])) {
        $id = intval($_POST['user_id']);
        // Delete from students first (Foreign Key Constraint)
        $conn->query("DELETE FROM students WHERE user_id = $id");
        // Delete from users
        $conn->query("DELETE FROM users WHERE id = $id");
        $message = "Student removed successfully!";
    }

    // 3. EDIT STAFF HANDLER
    if (isset($_POST['update_staff'])) {
        $id = intval($_POST['user_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $emp_id = $conn->real_escape_string($_POST['employee_id']);
        $designation = $conn->real_escape_string($_POST['designation']);
        $dept = $conn->real_escape_string($_POST['department']);
        $exp = $conn->real_escape_string($_POST['experience']);

        // Update Users Table
        $conn->query("UPDATE users SET name='$name', email='$email', phone='$phone' WHERE id=$id");
        // Update Staff Table
        $conn->query("UPDATE staff SET employee_id='$emp_id', designation='$designation', department='$dept', experience='$exp' WHERE user_id=$id");
        
        $message = "Staff details updated successfully!";
    }

    // 4. REMOVE STAFF HANDLER
    if (isset($_POST['remove_staff'])) {
        $id = intval($_POST['user_id']);
        // Delete from staff first
        $conn->query("DELETE FROM staff WHERE user_id = $id");
        // Delete from users
        $conn->query("DELETE FROM users WHERE id = $id");
        $message = "Staff member removed successfully!";
    }

    // --- EXISTING LOGIC FOR PENDING USERS & REPORTS ---
    
    // Handle Accepting a Student
    if (isset($_POST['accept_student'])) {
        $pendingId = $_POST['pending_id'];
        $query = "SELECT * FROM pending_students WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pendingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $pendingStudent = $result->fetch_assoc();

        if ($pendingStudent) {
            $insertUserQuery = "INSERT INTO users (username, password, user_type, name, email, phone, dob, gender) VALUES (?, ?, 'student', ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertUserQuery);
            $stmt->bind_param("sssssss", $pendingStudent['username'], $pendingStudent['password'], $pendingStudent['name'], $pendingStudent['email'], $pendingStudent['phone'], $pendingStudent['dob'], $pendingStudent['gender']);
            
            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                $insertStudentQuery = "INSERT INTO students (user_id, father_name, mother_name, joining_year, department, academic_year) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertStudentQuery);
                $stmt->bind_param("isssss", $userId, $pendingStudent['father_name'], $pendingStudent['mother_name'], $pendingStudent['joining_year'], $pendingStudent['department'], $pendingStudent['academic_year']);
                
                if ($stmt->execute()) {
                    $deleteQuery = "DELETE FROM pending_students WHERE id = ?";
                    $stmt = $conn->prepare($deleteQuery);
                    $stmt->bind_param("i", $pendingId);
                    $stmt->execute();
                    $message = "Student accepted successfully!";
                } else {
                    $error = "Failed to add student record.";
                }
            } else {
                $error = "Failed to create user.";
            }
        }
    }

    // Handle Deleting a Student (Pending)
    if (isset($_POST['delete_student'])) {
        $pendingId = $_POST['pending_id'];
        $deleteQuery = "DELETE FROM pending_students WHERE id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $pendingId);
        $stmt->execute();
        $message = "Student request deleted successfully!";
    }

    // Handle Accepting a Staff Member
    if (isset($_POST['accept_staff'])) {
        $pendingId = $_POST['pending_id'];
        $query = "SELECT * FROM pending_staff WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pendingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $pendingStaff = $result->fetch_assoc();

        if ($pendingStaff) {
            $insertUserQuery = "INSERT INTO users (username, password, user_type, name, email, phone, dob, gender) VALUES (?, ?, 'staff', ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertUserQuery);
            $stmt->bind_param("sssssss", $pendingStaff['username'], $pendingStaff['password'], $pendingStaff['name'], $pendingStaff['email'], $pendingStaff['phone'], $pendingStaff['dob'], $pendingStaff['gender']);
            
            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                $insertStaffQuery = "INSERT INTO staff (user_id, employee_id, designation, department, experience) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertStaffQuery);
                $stmt->bind_param("isssi", $userId, $pendingStaff['employee_id'], $pendingStaff['designation'], $pendingStaff['department'], $pendingStaff['experience']);
                
                if ($stmt->execute()) {
                    $deleteQuery = "DELETE FROM pending_staff WHERE id = ?";
                    $stmt = $conn->prepare($deleteQuery);
                    $stmt->bind_param("i", $pendingId);
                    $stmt->execute();
                    $message = "Staff member accepted successfully!";
                } else {
                    $error = "Failed to add staff record.";
                }
            } else {
                $error = "Failed to create user.";
            }
        }
    }

    // Handle Deleting a Staff Member (Pending)
    if (isset($_POST['delete_staff'])) {
        $pendingId = $_POST['pending_id'];
        $deleteQuery = "DELETE FROM pending_staff WHERE id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $pendingId);
        $stmt->execute();
        $message = "Staff request deleted successfully!";
    }

    // --- Existing Logic for adding admin and department ---
    if (isset($_POST['add_admin'])) {
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            $error = "Please fill all required fields.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = "Username or email already exists.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertQuery = "INSERT INTO users (username, password, user_type, name, email, phone) VALUES (?, ?, 'admin', ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("sssss", $username, $hashedPassword, $name, $email, $phone);
                if ($stmt->execute()) {
                    $userId = $conn->insert_id;
                    $insertAdminQuery = "INSERT INTO admin (user_id) VALUES (?)";
                    $stmt = $conn->prepare($insertAdminQuery);
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $message = "Admin added successfully!";
                } else {
                    $error = "Failed to add admin.";
                }
            }
        }
    } elseif (isset($_POST['add_department'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $error = "Department name is required.";
        } else {
            $checkQuery = "SELECT id FROM departments WHERE name = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = "Department already exists.";
            } else {
                $insertQuery = "INSERT INTO departments (name, description) VALUES (?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("ss", $name, $description);
                if ($stmt->execute()) {
                    $message = "Department added successfully!";
                } else {
                    $error = "Failed to add department.";
                }
            }
        }
    }
}

// Handle logout
if ($page == 'logout') {
    $_SESSION = array();
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- FETCH DATA ---
 $studentCount = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
 $staffCount = $conn->query("SELECT COUNT(*) as count FROM staff")->fetch_assoc()['count'];
 $examCount = $conn->query("SELECT COUNT(*) as count FROM exams")->fetch_assoc()['count'];
 $departmentCount = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];

// Fetch departments (Needed for Edit Modals)
 $departmentsList = [];
 $deptRes = $conn->query("SELECT * FROM departments");
if($deptRes) { while($d = $deptRes->fetch_assoc()) { $departmentsList[] = $d['name']; } }

 $yearsList = ['first_year', 'second_year', 'third_year', 'fourth_year'];

// Fetch approved students
 $students = [];
 $query = "SELECT u.id, u.name, u.username, u.email, u.phone, s.department, s.academic_year FROM users u JOIN students s ON u.id = s.user_id WHERE u.user_type = 'student' ORDER BY u.name";
 $result = $conn->query($query);
if ($result) { while ($row = $result->fetch_assoc()) { $students[] = $row; } }

// Fetch pending students
 $pendingStudents = [];
 $query = "SELECT id, name, username, email, phone, department, academic_year, created_at FROM pending_students ORDER BY created_at DESC";
 $result = $conn->query($query);
if ($result) { while ($row = $result->fetch_assoc()) { $pendingStudents[] = $row; } }

// Fetch approved staff
 $staff = [];
 $query = "SELECT u.id, u.name, u.username, u.email, u.phone, s.employee_id, s.designation, s.department, s.experience FROM users u JOIN staff s ON u.id = s.user_id WHERE u.user_type = 'staff' ORDER BY u.name";
 $result = $conn->query($query);
if ($result) { while ($row = $result->fetch_assoc()) { $staff[] = $row; } }

// Fetch pending staff
 $pendingStaff = [];
 $query = "SELECT id, name, username, email, phone, employee_id, designation, department, experience, created_at FROM pending_staff ORDER BY created_at DESC";
 $result = $conn->query($query);
if ($result) { while ($row = $result->fetch_assoc()) { $pendingStaff[] = $row; } }

 $exams = [];
 $query = "SELECT e.id, e.title, e.department, e.academic_year, e.created_at FROM exams e ORDER BY e.created_at DESC";
 $result = $conn->query($query);
if ($result) { while ($row = $result->fetch_assoc()) { $exams[] = $row; } }

 $examResults = [];
 $selectedExamId = '';
 $selectedExamTitle = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['view_results'])) {
    $selectedExamId = $_POST['exam_id'];
    foreach ($exams as $exam) { if ($exam['id'] == $selectedExamId) { $selectedExamTitle = $exam['title']; break; } }
    $query = "SELECT er.score, er.max_score, er.status, u.name as student_name, u.username FROM exam_results er JOIN users u ON er.student_id = u.id WHERE er.exam_id = ? ORDER BY u.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selectedExamId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) { while ($row = $result->fetch_assoc()) { $examResults[] = $row; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Exam System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- PDF LIBRARIES ADDED HERE -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); padding-top: 20px; transition: all 0.3s ease; }
        .sidebar:hover { box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15); }
        .sidebar h2 { text-align: center; padding: 0 20px 20px; border-bottom: 2px solid #667eea; margin-bottom: 0; font-size: 22px; color: #2c3e50; }
        .sidebar a { display: flex; align-items: center; padding: 15px 25px; color: #555; text-decoration: none; transition: all 0.3s ease; border-left: 3px solid transparent; }
        .sidebar a i { margin-right: 12px; width: 20px; text-align: center; }
        .sidebar a:hover, .sidebar a.active { background: linear-gradient(90deg, #a6e6ab 0%, #764ba2 100%); color: white; border-left-color: #fff; transform: translateX(5px); }
        .main-content { flex-grow: 1; padding: 30px; }
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 25px 30px; border-radius: 15px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; color: #2c3e50; font-size: 28px; }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); padding: 25px; margin-bottom: 25px; transition: all 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15); }
        .card h3 { margin-top: 0; margin-bottom: 20px; color: #2c3e50; font-size: 20px; display: flex; align-items: center; }
        .card h3 i { margin-right: 10px; color: #667eea; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: scale(1.05); }
        .stat-card i { font-size: 30px; margin-bottom: 10px; }
        .stat-card h4 { font-size: 32px; margin: 10px 0; }
        .stat-card p { margin: 0; opacity: 0.9; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #e0e0e0; }
        th, td { padding: 12px 15px; text-align: left; }
        th { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        tr:hover { background-color: #e8eaf6; }
        .btn { display: inline-flex; align-items: center; padding: 8px 15px; font-size: 12px; font-weight: 600; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; transition: all 0.3s ease; margin-right: 5px; }
        .btn i { margin-right: 5px; font-size: 11px; }
        .btn-danger { background: linear-gradient(135deg, #f93b1d 0%, #ea1e63 100%); }
        .btn-success { background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%); }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-info { background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%); }
        .btn-warning { background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 13px; }
        .form-control { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; box-sizing: border-box; font-size: 13px; }
        .success-message, .error-message { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; display: flex; align-items: center; }
        .success-message { background: #d4edda; color: #155724; }
        .error-message { background: #f8d7da; color: #721c24; }
        .action-form { display: inline-block; margin-right: 5px; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); backdrop-filter: blur(5px); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: slideDown 0.4s ease; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        @keyframes slideDown { from {transform: translateY(-50px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        
        .modal-header { border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; color: #2c3e50; }
        .modal-footer { margin-top: 20px; text-align: right; border-top: 1px solid #f0f0f0; padding-top: 15px; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2><i class="fas fa-graduation-cap"></i> Admin Panel</h2>
            <a href="?page=dashboard" class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="?page=students" class="<?php echo ($page == 'students') ? 'active' : ''; ?>"><i class="fas fa-users"></i> Students</a>
            <a href="?page=staff" class="<?php echo ($page == 'staff') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Staff</a>
            <a href="?page=reports" class="<?php echo ($page == 'reports') ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="?page=add_admin" class="<?php echo ($page == 'add_admin') ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Add Admin</a>
            <a href="?page=add_department" class="<?php echo ($page == 'add_department') ? 'active' : ''; ?>"><i class="fas fa-building"></i> Add Dept</a>
            <a href="?page=logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <div class="main-content">
            <?php if ($page == 'dashboard'): ?>
                <div class="header">
                    <div>
                        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
                        <p>Admin Dashboard - Manage your examination system</p>
                    </div>
                    <div><i class="fas fa-user-shield" style="font-size: 40px; color: #667eea;"></i></div>
                </div>
                <div class="card">
                    <h3><i class="fas fa-chart-line"></i> System Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-card"><i class="fas fa-user-graduate"></i><h4><?php echo $studentCount; ?></h4><p>Students</p></div>
                        <div class="stat-card"><i class="fas fa-chalkboard-teacher"></i><h4><?php echo $staffCount; ?></h4><p>Staff</p></div>
                        <div class="stat-card"><i class="fas fa-building"></i><h4><?php echo $departmentCount; ?></h4><p>Depts</p></div>
                        <div class="stat-card"><i class="fas fa-clipboard-list"></i><h4><?php echo $examCount; ?></h4><p>Exams</p></div>
                    </div>
                </div>
            
            <?php elseif ($page == 'students'): ?>
                <div class="header"><h1><i class="fas fa-users"></i> Students Management</h1></div>
                
                <!-- Pending Students -->
                <div class="card">
                    <h3><i class="fas fa-user-clock"></i> Pending Approvals</h3>
                    <?php if (isset($message)): ?><div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
                    <?php if (isset($error)): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
                    <?php if (empty($pendingStudents)): ?><p>No pending requests.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Name</th><th>Email</th><th>Dept</th><th>Year</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pendingStudents as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo htmlspecialchars($s['department']); ?></td>
                                    <td><?php echo htmlspecialchars($s['academic_year']); ?></td>
                                    <td>
                                        <form action="?page=students" method="post" class="action-form" onsubmit="return confirm('Accept?');">
                                            <input type="hidden" name="pending_id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" name="accept_student" class="btn btn-success"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form action="?page=students" method="post" class="action-form" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="pending_id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" name="delete_student" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Approved Students -->
                <div class="card">
                    <h3><i class="fas fa-user-check"></i> Approved Students</h3>
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Dept</th><th>Year</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo $s['id']; ?></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><?php echo htmlspecialchars($s['department']); ?></td>
                                <td><?php echo htmlspecialchars($s['academic_year']); ?></td>
                                <td>
                                    <!-- Edit Button (Opens Modal) -->
                                    <button onclick="openEditStudent('<?php echo $s['id']; ?>', '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['phone'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['department'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['academic_year'], ENT_QUOTES); ?>')" class="btn btn-info"><i class="fas fa-edit"></i> Edit</button>
                                    
                                    <!-- Remove Button -->
                                    <form action="?page=students" method="post" class="action-form" onsubmit="return confirm('Are you sure you want to REMOVE this student permanently?');">
                                        <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" name="remove_student" class="btn btn-danger"><i class="fas fa-user-minus"></i> Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page == 'staff'): ?>
                <div class="header"><h1><i class="fas fa-chalkboard-teacher"></i> Staff Management</h1></div>
                
                <!-- Pending Staff -->
                <div class="card">
                    <h3><i class="fas fa-user-clock"></i> Pending Approvals</h3>
                    <?php if (empty($pendingStaff)): ?><p>No pending requests.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Name</th><th>Email</th><th>Desig</th><th>Dept</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pendingStaff as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo htmlspecialchars($s['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($s['department']); ?></td>
                                    <td>
                                        <form action="?page=staff" method="post" class="action-form" onsubmit="return confirm('Accept?');">
                                            <input type="hidden" name="pending_id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" name="accept_staff" class="btn btn-success"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form action="?page=staff" method="post" class="action-form" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="pending_id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" name="delete_staff" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Approved Staff -->
                <div class="card">
                    <h3><i class="fas fa-user-check"></i> Approved Staff</h3>
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Emp ID</th><th>Desig</th><th>Dept</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($staff as $s): ?>
                            <tr>
                                <td><?php echo $s['id']; ?></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($s['designation']); ?></td>
                                <td><?php echo htmlspecialchars($s['department']); ?></td>
                                <td>
                                    <!-- Edit Button (Opens Modal) -->
                                    <button onclick="openEditStaff('<?php echo $s['id']; ?>', '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['phone'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['employee_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['designation'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['department'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['experience'], ENT_QUOTES); ?>')" class="btn btn-info"><i class="fas fa-edit"></i> Edit</button>
                                    
                                    <!-- Remove Button -->
                                    <form action="?page=staff" method="post" class="action-form" onsubmit="return confirm('Are you sure you want to REMOVE this staff member permanently?');">
                                        <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" name="remove_staff" class="btn btn-danger"><i class="fas fa-user-minus"></i> Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page == 'reports'): ?>
                <div class="header"><h1><i class="fas fa-chart-bar"></i> Reports</h1></div>
                <div class="card">
                    <h3>Download Reports (PDF)</h3>
                    <div class="stats-grid">
                        <button onclick="generatePDF('students')" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-file-pdf"></i> Students PDF</button>
                        <button onclick="generatePDF('staff')" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-file-pdf"></i> Staff PDF</button>
                        <button onclick="generatePDF('exams')" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-file-pdf"></i> Exams PDF</button>
                    </div>
                </div>
                
                <div class="card">
                    <h3>View Results</h3>
                    <form method="post">
                        <select name="exam_id" class="form-control" required style="margin-bottom:10px;">
                            <option value="">-- Select Exam --</option>
                            <?php foreach ($exams as $e): ?><option value="<?php echo $e['id']; ?>" <?php if($selectedExamId == $e['id']) echo 'selected'; ?>><?php echo $e['title']; ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" name="view_results" class="btn btn-info">View</button>
                        <?php if (!empty($examResults)): ?>
                            <button onclick="generatePDF('exam_results')" class="btn btn-danger" style="margin-left:10px;"><i class="fas fa-download"></i> Download Result PDF</button>
                        <?php endif; ?>
                    </form>
                    
                    <?php if (!empty($examResults)): ?>
                        <table id="table-exam-results-view">
                            <thead><tr><th>Student</th><th>Score</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($examResults as $r): ?>
                                <tr>
                                    <td><?php echo $r['student_name']; ?></td>
                                    <td><?php echo $r['score']; ?></td>
                                    <td><?php echo $r['status']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- HIDDEN TABLES FOR PDF GENERATION (These won't show on screen) -->
                <div style="display:none;">
                    
                    <!-- Students Table for PDF -->
                    <table id="table-students">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Department</th><th>Academic Year</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo $s['id']; ?></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><?php echo htmlspecialchars($s['department']); ?></td>
                                <td><?php echo htmlspecialchars($s['academic_year']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Staff Table for PDF -->
                    <table id="table-staff">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Emp ID</th><th>Designation</th><th>Department</th><th>Experience</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff as $s): ?>
                            <tr>
                                <td><?php echo $s['id']; ?></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><?php echo htmlspecialchars($s['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($s['designation']); ?></td>
                                <td><?php echo htmlspecialchars($s['department']); ?></td>
                                <td><?php echo htmlspecialchars($s['experience']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- All Exams Table for PDF -->
                    <table id="table-exams">
                        <thead>
                            <tr><th>ID</th><th>Title</th><th>Department</th><th>Year</th><th>Created Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $e): ?>
                            <tr>
                                <td><?php echo $e['id']; ?></td>
                                <td><?php echo htmlspecialchars($e['title']); ?></td>
                                <td><?php echo htmlspecialchars($e['department']); ?></td>
                                <td><?php echo htmlspecialchars($e['academic_year']); ?></td>
                                <td><?php echo $e['created_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page == 'add_admin' || $page == 'add_department'): ?>
                <div class="card">
                    <h3><?php echo ($page == 'add_admin') ? 'Add Admin' : 'Add Department'; ?></h3>
                    <form method="post">
                        <?php if($page == 'add_admin'): ?>
                            <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
                            <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                            <div class="form-group"><label>Confirm</label><input type="password" name="confirm_password" class="form-control" required></div>
                            <button type="submit" name="add_admin" class="btn btn-success">Add Admin</button>
                        <?php else: ?>
                            <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                            <div class="form-group"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
                            <button type="submit" name="add_department" class="btn btn-success">Add Dept</button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EDIT STUDENT MODAL -->
    <div id="editStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('editStudentModal')">&times;</span>
                <h3>Edit Student</h3>
            </div>
            <form method="post">
                <input type="hidden" name="user_id" id="edit_student_id">
                <div class="form-group"><label>Full Name</label><input type="text" name="name" id="edit_student_name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_student_email" class="form-control" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_student_phone" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="edit_student_dept" class="form-control" required>
                            <?php foreach($departmentsList as $d): ?>
                                <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <select name="academic_year" id="edit_student_year" class="form-control" required>
                            <?php foreach($yearsList as $y): ?>
                                <option value="<?php echo $y; ?>"><?php echo ucfirst(str_replace('_', ' ', $y)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('editStudentModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT STAFF MODAL -->
    <div id="editStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('editStaffModal')">&times;</span>
                <h3>Edit Staff</h3>
            </div>
            <form method="post">
                <input type="hidden" name="user_id" id="edit_staff_id">
                <div class="form-group"><label>Full Name</label><input type="text" name="name" id="edit_staff_name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_staff_email" class="form-control" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_staff_phone" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label>Employee ID</label><input type="text" name="employee_id" id="edit_staff_emp_id" class="form-control" required></div>
                    <div class="form-group"><label>Designation</label><input type="text" name="designation" id="edit_staff_desig" class="form-control" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="edit_staff_dept" class="form-control" required>
                            <?php foreach($departmentsList as $d): ?>
                                <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Experience (Yrs)</label><input type="text" name="experience" id="edit_staff_exp" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_staff" class="btn btn-primary">Update Staff</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('editStaffModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }

        function openEditStudent(id, name, email, phone, dept, year) {
            document.getElementById('edit_student_id').value = id;
            document.getElementById('edit_student_name').value = name;
            document.getElementById('edit_student_email').value = email;
            document.getElementById('edit_student_phone').value = phone;
            document.getElementById('edit_student_dept').value = dept;
            document.getElementById('edit_student_year').value = year;
            document.getElementById('editStudentModal').style.display = "block";
        }

        function openEditStaff(id, name, email, phone, empId, desig, dept, exp) {
            document.getElementById('edit_staff_id').value = id;
            document.getElementById('edit_staff_name').value = name;
            document.getElementById('edit_staff_email').value = email;
            document.getElementById('edit_staff_phone').value = phone;
            document.getElementById('edit_staff_emp_id').value = empId;
            document.getElementById('edit_staff_desig').value = desig;
            document.getElementById('edit_staff_dept').value = dept;
            document.getElementById('edit_staff_exp').value = exp;
            document.getElementById('editStaffModal').style.display = "block";
        }

        // PDF Generation Function
        function generatePDF(type) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            let tableId = '';
            let filename = '';
            let title = '';

            if (type === 'students') {
                tableId = 'table-students';
                filename = 'students_report.pdf';
                title = 'Students List Report';
            } else if (type === 'staff') {
                tableId = 'table-staff';
                filename = 'staff_report.pdf';
                title = 'Staff List Report';
            } else if (type === 'exams') {
                tableId = 'table-exams';
                filename = 'exams_report.pdf';
                title = 'All Exams Report';
            } else if (type === 'exam_results') {
                tableId = 'table-exam-results-view';
                filename = 'exam_results.pdf';
                title = 'Exam Results Report';
            }

            // Add Title to PDF
            doc.text(title, 14, 15);
            
            // Generate Table
            doc.autoTable({
                html: '#' + tableId,
                startY: 20,
                theme: 'grid',
                headStyles: { fillColor: [102, 126, 234] }, // Matches your purple theme
                styles: { fontSize: 10 }
            });

            // Save PDF
            doc.save(filename);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>
</body>
</html>