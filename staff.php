<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1. DATABASE & CONFIGURATION ---
require_once 'db_connect.php';

// Custom Toast Helper
function setToast($type, $message) {
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
}

// --- 2. AJAX HANDLERS (Top of file) ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $staff_id = $_SESSION['user_id'];
    $action = $_GET['action'];
    $response = [];

    if ($action == 'get_question' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $q = $conn->query("SELECT * FROM questions WHERE id = $id AND created_by = $staff_id");
        if ($q && $q->num_rows > 0) { $response = $q->fetch_assoc(); } else { $response['success'] = false; }
        echo json_encode($response); exit;
    }

    if ($action == 'add_question' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $qt = $conn->real_escape_string($_POST['question_text']);
        $o1 = $conn->real_escape_string($_POST['option1']);
        $o2 = $conn->real_escape_string($_POST['option2']);
        $o3 = $conn->real_escape_string($_POST['option3']);
        $o4 = $conn->real_escape_string($_POST['option4']);
        $ca = $conn->real_escape_string($_POST['correct_answer']);
        $sql = "INSERT INTO questions (question_text, option1, option2, option3, option4, correct_answer, created_by) VALUES ('$qt', '$o1', '$o2', '$o3', '$o4', '$ca', $staff_id)";
        $response['success'] = $conn->query($sql);
        if (!$response['success']) $response['message'] = $conn->error;
        echo json_encode($response); exit;
    }

    if ($action == 'update_question' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $id = intval($_POST['question_id']);
        $qt = $conn->real_escape_string($_POST['question_text']);
        $o1 = $conn->real_escape_string($_POST['option1']);
        $o2 = $conn->real_escape_string($_POST['option2']);
        $o3 = $conn->real_escape_string($_POST['option3']);
        $o4 = $conn->real_escape_string($_POST['option4']);
        $ca = $conn->real_escape_string($_POST['correct_answer']);
        $sql = "UPDATE questions SET question_text='$qt', option1='$o1', option2='$o2', option3='$o3', option4='$o4', correct_answer='$ca' WHERE id = $id AND created_by = $staff_id";
        $response['success'] = $conn->query($sql);
        if (!$response['success']) $response['message'] = $conn->error;
        echo json_encode($response); exit;
    }

    if ($action == 'delete_question' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $conn->query("DELETE FROM questions WHERE id = $id AND created_by = $staff_id");
        echo json_encode(['success' => true]); exit;
    }

    // NEW: DELETE MULTIPLE QUESTIONS
    if ($action == 'delete_questions' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'];
        if (!empty($ids)) {
            $ids_str = implode(',', array_map('intval', $ids));
            $sql = "DELETE FROM questions WHERE id IN ($ids_str) AND created_by = $staff_id";
            $response['success'] = $conn->query($sql);
        } else {
            $response['success'] = false;
        }
        echo json_encode($response); exit;
    }

    if ($action == 'delete_exam' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $conn->query("DELETE FROM exam_questions WHERE exam_id = $id");
        $conn->query("DELETE FROM exams WHERE id = $id AND created_by = $staff_id");
        echo json_encode(['success' => true]); exit;
    }

    if ($action == 'get_exam_results' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $res = $conn->query("SELECT er.*, u.name as student_name FROM exam_results er JOIN users u ON er.student_id = u.id WHERE er.exam_id = $id");
        $data = [];
        while($row = $res->fetch_assoc()) {
            $row['percent'] = round(($row['score'] / $row['max_score']) * 100, 1);
            $data[] = $row;
        }
        echo json_encode($data); exit;
    }
}

// --- 3. AUTH CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header("Location: index.php"); exit;
}
 $staff_id = $_SESSION['user_id'];
 $staff = $conn->query("SELECT * FROM users WHERE id = $staff_id")->fetch_assoc();
 $dept_data = $conn->query("SELECT department FROM staff WHERE user_id = $staff_id")->fetch_assoc();
 $staff_dept = $dept_data ? $dept_data['department'] : 'General';

// --- 4. FORM HANDLERS ---
 $toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);
 $csv_preview = isset($_SESSION['csv_preview']) ? $_SESSION['csv_preview'] : [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['upload_csv'])) {
        if (!isset($_FILES['csv_file'])) { setToast('error', 'No file selected.'); header("Location: staff.php"); exit; }
        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Upload Error: ' . $_FILES['csv_file']['error'];
            setToast('error', $msg); header("Location: staff.php"); exit;
        }
        $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($file_ext != 'csv') { setToast('error', 'Invalid format. Only CSV allowed.'); header("Location: staff.php"); exit; }
        
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle === FALSE) { setToast('error', 'Could not read file.'); header("Location: staff.php"); exit; }

        $has_header = isset($_POST['has_header']);
        $temp_data = []; $row_count = 0;
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (array_filter($data) === []) continue;
            if ($row_count == 0 && $has_header) { $row_count++; continue; }
            if (count($data) >= 6) {
                $temp_data[] = [
                    'question' => $data[0],
                    'options' => [$data[1], $data[2], $data[3], $data[4]],
                    'correct' => strtoupper(trim($data[5]))
                ];
            }
            $row_count++;
        }
        fclose($handle);

        if (!empty($temp_data)) {
            $_SESSION['csv_preview'] = $temp_data;
            setToast('success', "Found " . count($temp_data) . " questions. Review & Confirm.");
            header("Location: staff.php?tab=questions"); exit;
        } else {
            setToast('error', "No valid questions found. Check format."); header("Location: staff.php"); exit;
        }
    }

    if (isset($_POST['confirm_csv'])) {
        if (!empty($_SESSION['csv_preview'])) {
            $success_count = 0;
            foreach ($_SESSION['csv_preview'] as $q) {
                $qt = $conn->real_escape_string($q['question']);
                $o1 = $conn->real_escape_string($q['options'][0]);
                $o2 = $conn->real_escape_string($q['options'][1]);
                $o3 = $conn->real_escape_string($q['options'][2]);
                $o4 = $conn->real_escape_string($q['options'][3]);
                $ca = $conn->real_escape_string($q['correct']);
                $sql = "INSERT INTO questions (question_text, option1, option2, option3, option4, correct_answer, created_by) VALUES ('$qt', '$o1', '$o2', '$o3', '$o4', '$ca', $staff_id)";
                if ($conn->query($sql)) $success_count++;
            }
            unset($_SESSION['csv_preview']);
            setToast('success', "$success_count questions added!");
            header("Location: staff.php?tab=questions"); exit;
        }
    }

    if (isset($_POST['create_exam'])) {
        $title = $conn->real_escape_string($_POST['exam_title']);
        $dept = $conn->real_escape_string($_POST['exam_department']);
        $year = $conn->real_escape_string($_POST['exam_year']);
        $dur = intval($_POST['exam_duration']);
        $q_ids = isset($_POST['selected_questions']) ? $_POST['selected_questions'] : [];
        if ($title && $dept && $year && $dur && !empty($q_ids)) {
            $sql = "INSERT INTO exams (title, department, academic_year, duration, created_by) VALUES ('$title', '$dept', '$year', $dur, $staff_id)";
            if ($conn->query($sql)) {
                $exam_id = $conn->insert_id;
                foreach ($q_ids as $qid) { $conn->query("INSERT INTO exam_questions (exam_id, question_id) VALUES ($exam_id, " . intval($qid) . ")"); }
                setToast('success', 'Exam created!'); header("Location: staff.php?tab=exams"); exit;
            }
        }
    }
}

// --- 5. FETCH DATA ---
 $questions_res = $conn->query("SELECT * FROM questions WHERE created_by = $staff_id ORDER BY id DESC");
 $exams_res = $conn->query("SELECT e.*, COUNT(eq.question_id) as q_count FROM exams e LEFT JOIN exam_questions eq ON e.id = eq.exam_id WHERE e.created_by = $staff_id GROUP BY e.id ORDER BY e.id DESC");
 $depts_res = $conn->query("SELECT * FROM departments");
 $years = ['first_year', 'second_year', 'third_year', 'fourth_year'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Modern Exam System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- PDF LIBRARIES -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <style>
        :root { --primary: #4f46e5; --primary-dark: #4338ca; --bg-body: #f1f5f9; --bg-card: #ffffff; --text-main: #1e293b; --text-muted: #64748b; --danger: #ef4444; --success: #10b981; --border: #e2e8f0; --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        * { box-sizing: border-box; outline: none; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; }

        .sidebar { width: 260px; background: #1e293b; color: white; display: flex; flex-direction: column; position: fixed; height: 100vh; transition: 0.3s; z-index: 100; }
        .brand { padding: 24px; font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #334155; }
        .brand i { color: var(--primary); }
        .nav-links { flex: 1; padding: 20px 0; list-style: none; margin: 0; }
        .nav-item { padding: 12px 24px; cursor: pointer; display: flex; align-items: center; gap: 12px; color: #94a3b8; transition: 0.2s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { background: #0f172a; color: white; border-left-color: var(--primary); }
        .user-profile { padding: 20px; border-top: 1px solid #334155; display: flex; align-items: center; gap: 10px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
        .main-content { flex: 1; margin-left: 260px; padding: 30px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-icon.blue { background: #e0e7ff; color: var(--primary); } .stat-icon.green { background: #dcfce7; color: var(--success); } .stat-icon.orange { background: #ffedd5; color: #f97316; }
        .stat-info h3 { margin: 0; font-size: 28px; font-weight: 700; } .stat-info p { margin: 4px 0 0; color: var(--text-muted); font-size: 14px; }

        .section-card { background: var(--bg-card); border-radius: 16px; box-shadow: var(--shadow); padding: 24px; display: none; animation: fadeIn 0.3s ease; }
        .section-card.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
        .section-title { margin: 0; font-size: 20px; font-weight: 600; }

        .filter-bar { display: flex; gap: 15px; }
        .search-input { padding: 10px 16px; border: 1px solid var(--border); border-radius: 8px; width: 250px; font-size: 14px; }
        .search-input:focus { border-color: var(--primary); }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: white; } .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: white; border: 1px solid var(--border); color: var(--text-main); }
        .btn-danger { background: #fee2e2; color: var(--danger); }

        .table-container { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { text-align: left; padding: 16px; background: #f8fafc; font-size: 13px; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border); }
        td { padding: 16px; border-bottom: 1px solid var(--border); font-size: 14px; }
        tr:hover { background: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-dept { background: #e0f2fe; color: #0284c7; }

        .upload-area { border: 2px dashed var(--border); border-radius: 12px; padding: 30px; text-align: center; background: #f8fafc; transition: 0.2s; }
        .upload-area:hover { border-color: var(--primary); background: #eef2ff; }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal { background: white; width: 600px; max-width: 90%; border-radius: 16px; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); animation: modalPop 0.3s; }
        @keyframes modalPop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .form-group { margin-bottom: 16px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; }
        .q-select-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px; max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 10px; }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 2000; display: flex; flex-direction: column; gap: 10px; }
        .toast { min-width: 300px; padding: 16px; border-radius: 10px; background: white; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 12px; border-left: 4px solid var(--primary); animation: slideIn 0.3s ease; }
        .toast.success { border-left-color: var(--success); } .toast.error { border-left-color: var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); width: 260px; } .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; } .menu-toggle { display: block !important; }
        }
        .menu-toggle { display: none; position: fixed; top: 20px; right: 20px; z-index: 101; background: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>

    <div class="toast-container" id="toastContainer"></div>

    <nav class="sidebar" id="sidebar">
        <div class="brand"><i class="fas fa-layer-group"></i> ExamSys</div>
        <ul class="nav-links">
            <li class="nav-item active" onclick="switchTab('dashboard', this)"><i class="fas fa-home"></i> Dashboard</li>
            <li class="nav-item" onclick="switchTab('questions', this)"><i class="fas fa-question-circle"></i> Questions</li>
            <li class="nav-item" onclick="switchTab('exams', this)"><i class="fas fa-file-alt"></i> Exams</li>
            <li class="nav-item" onclick="switchTab('reports', this)"><i class="fas fa-chart-bar"></i> Reports</li>
        </ul>
        <div class="user-profile">
            <div class="avatar"><?php echo strtoupper(substr($staff['name'], 0, 1)); ?></div>
            <div>
                <div style="font-weight: 600;"><?php echo htmlspecialchars($staff['name']); ?></div>
                <div style="font-size: 12px; color: #94a3b8;"><?php echo htmlspecialchars($staff_dept); ?></div>
            </div>
            <a href="index.php" style="margin-left: auto; color: #94a3b8;"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </nav>

    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

    <main class="main-content">
        
        <!-- DASHBOARD TAB -->
        <div id="dashboard" class="section-card active">
            <div class="section-header">
                <h2 class="section-title">Dashboard Overview</h2>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-question"></i></div>
                    <div class="stat-info"><h3><?php echo $questions_res->num_rows; ?></h3><p>Total Questions</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-file-signature"></i></div>
                    <div class="stat-info"><h3><?php echo $exams_res->num_rows; ?></h3><p>Exams Created</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-users"></i></div>
                    <div class="stat-info"><h3>0</h3><p>Active Students</p></div>
                </div>
            </div>

            <!-- QUICK CSV UPLOAD (Visible on Dashboard) -->
            <?php if(empty($csv_preview)): ?>
            <div style="background: #e0f2fe; padding: 20px; border-radius: 12px; border: 1px solid #bae6fd; margin-bottom: 20px;">
                <h3 style="margin-top:0; color: #0369a1; font-size: 18px;"><i class="fas fa-cloud-upload-alt"></i> Quick Upload CSV</h3>
                <p style="margin-bottom: 15px; font-size: 14px; color: #0c4a6e;">Format: Question, OptA, OptB, OptC, OptD, Answer</p>
                <form method="post" enctype="multipart/form-data" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required style="width: auto;">
                    <label style="display: flex; align-items: center; gap: 5px; font-size: 14px;">
                        <input type="checkbox" name="has_header" value="1" checked> Has Header?
                    </label>
                    <button type="submit" name="upload_csv" class="btn btn-primary">Upload</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
                <h3 style="margin-top:0; font-size: 16px; color: var(--text-muted);">Quick Tips</h3>
                <ul style="padding-left: 20px; line-height: 1.8; font-size: 14px;">
                    <li>Upload CSV files from the box above or in the <strong>Questions</strong> tab.</li>
                    <li>Ensure CSV format: <code>Question, OptionA, OptionB, OptionC, OptionD, Answer</code></li>
                </ul>
            </div>
        </div>

        <!-- QUESTIONS TAB -->
        <div id="questions" class="section-card">
            <div class="section-header">
                <h2 class="section-title">Question Bank</h2>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-danger" onclick="deleteSelectedQuestions()"><i class="fas fa-trash"></i> Delete Selected</button>
                    <button class="btn btn-primary" onclick="openQuestionModal()"><i class="fas fa-plus"></i> Add New</button>
                </div>
            </div>

            <!-- PREVIEW MODE -->
            <?php if (!empty($csv_preview)): ?>
                <div style="margin-bottom: 30px; padding: 20px; background: #eff6ff; border-radius: 12px; border: 1px solid #dbeafe;">
                    <h3 style="margin-top: 0; color: #1e3a8a;"><i class="fas fa-file-csv"></i> Confirm CSV Import</h3>
                    <p style="margin-bottom: 15px;">Found <?php echo count($csv_preview); ?> questions. Review below:</p>
                    <div style="max-height: 300px; overflow-y: auto; margin-bottom: 20px;">
                        <?php foreach($csv_preview as $i => $q): ?>
                            <div style="background: white; border: 1px solid #e2e8f0; padding: 15px; margin-bottom: 10px; border-radius: 8px;">
                                <strong>Q<?php echo $i+1; ?>:</strong> <?php echo htmlspecialchars($q['question']); ?>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-top: 8px; font-size: 13px; color: #64748b;">
                                    <div>A: <?php echo htmlspecialchars($q['options'][0]); ?></div>
                                    <div>B: <?php echo htmlspecialchars($q['options'][1]); ?></div>
                                    <div>C: <?php echo htmlspecialchars($q['options'][2]); ?></div>
                                    <div>D: <?php echo htmlspecialchars($q['options'][3]); ?></div>
                                </div>
                                <div style="font-size: 12px; color: var(--success); font-weight: 600;">Answer: <?php echo $q['correct']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post">
                        <button type="submit" name="confirm_csv" class="btn btn-primary"><i class="fas fa-check"></i> Confirm & Save All</button>
                        <a href="staff.php?tab=questions" class="btn btn-outline">Cancel</a>
                    </form>
                </div>
            <?php else: ?>
                <!-- STANDARD UPLOAD IN QUESTIONS TAB -->
                <div style="margin-bottom: 20px; padding: 15px; border: 1px dashed var(--border); border-radius: 12px;">
                     <form method="post" enctype="multipart/form-data" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <input type="file" name="csv_file" accept=".csv" required style="padding: 8px; border-radius: 6px;">
                        <label style="display: flex; align-items: center; gap: 5px;"><input type="checkbox" name="has_header" value="1" checked> Header?</label>
                        <button type="submit" name="upload_csv" class="btn btn-outline" style="font-size: 12px;">Upload CSV</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="filter-bar">
                <input type="text" id="questionSearch" class="search-input" placeholder="Search questions..." onkeyup="filterTable('tableQuestions', 1)">
            </div>

            <div class="table-container">
                <table id="tableQuestions">
                    <thead><tr><th style="width:40px;"><input type="checkbox" id="selectAllQ" onchange="toggleSelectAll('selectAllQ', 'q_checkbox')"></th><th>ID</th><th>Question</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($questions_res && $questions_res->num_rows > 0): ?>
                            <?php $questions_res->data_seek(0); ?>
                            <?php while ($q = $questions_res->fetch_assoc()): ?>
                            <tr id="row-q-<?php echo $q['id']; ?>">
                                <td><input type="checkbox" class="q_checkbox" value="<?php echo $q['id']; ?>"></td>
                                <td>#<?php echo $q['id']; ?></td>
                                <td><?php echo htmlspecialchars(substr($q['question_text'], 0, 80)) . '...'; ?></td>
                                <td>
                                    <button class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="editQuestion(<?php echo $q['id']; ?>)">Edit</button>
                                    <button class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="deleteQuestion(<?php echo $q['id']; ?>)">Delete</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No questions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- EXAMS TAB -->
        <div id="exams" class="section-card">
            <div class="section-header"><h2 class="section-title">Manage Exams</h2></div>
            <div style="margin-bottom: 40px; padding: 20px; background: #f8fafc; border-radius: 12px;">
                <h3 style="margin-top: 0;">Create New Exam</h3>
                <form method="post" id="createExamForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div><label>Title</label><input type="text" name="exam_title" class="form-control" required placeholder="e.g. Physics Mid-Term"></div>
                        <div><label>Department</label>
                            <select name="exam_department" class="form-control" required>
                                <option value="">Select</option>
                                <?php if($depts_res): while($d = $depts_res->fetch_assoc()): ?>
                                    <option value="<?php echo $d['name']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div><label>Year</label>
                            <select name="exam_year" class="form-control" required>
                                <?php foreach($years as $y): ?><option value="<?php echo $y; ?>"><?php echo ucwords(str_replace('_', ' ', $y)); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div><label>Duration (min)</label><input type="number" name="exam_duration" class="form-control" min="10" max="180" required></div>
                    </div>
                    <div class="form-group">
                        <label>Select Questions</label>
                        <div class="q-select-grid">
                            <?php if($questions_res): $questions_res->data_seek(0); while($q = $questions_res->fetch_assoc()): ?>
                                <label style="display: flex; gap: 10px; padding: 5px;">
                                    <input type="checkbox" name="selected_questions[]" value="<?php echo $q['id']; ?>">
                                    <span style="font-size: 13px;"><?php echo htmlspecialchars(substr($q['question_text'], 0, 60)); ?>...</span>
                                </label>
                            <?php endwhile; endif; ?>
                        </div>
                    </div>
                    <button type="submit" name="create_exam" class="btn btn-primary">Create Exam</button>
                </form>
            </div>

            <div class="table-container">
                <table id="tableExams">
                    <thead><tr><th>Title</th><th>Dept</th><th>Year</th><th>Qns</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ($exams_res && $exams_res->num_rows > 0): ?>
                            <?php $exams_res->data_seek(0); ?>
                            <?php while ($e = $exams_res->fetch_assoc()): ?>
                            <tr id="row-e-<?php echo $e['id']; ?>" data-dept="<?php echo htmlspecialchars($e['department']); ?>">
                                <td><?php echo htmlspecialchars($e['title']); ?></td>
                                <td><span class="badge badge-dept"><?php echo htmlspecialchars($e['department']); ?></span></td>
                                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $e['academic_year']))); ?></td>
                                <td><?php echo $e['q_count']; ?></td>
                                <td>
                                    <button class="btn btn-outline" style="padding: 6px 10px;" onclick="viewExam(<?php echo $e['id']; ?>)">View</button>
                                    <button class="btn btn-danger" style="padding: 6px 10px;" onclick="deleteExam(<?php echo $e['id']; ?>)">Del</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center;">No exams.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- REPORTS TAB -->
        <div id="reports" class="section-card">
            <div class="section-header">
                <h2 class="section-title">Performance Reports</h2>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-outline" onclick="downloadReportPDF()"><i class="fas fa-file-pdf"></i> Download PDF</button>
                    <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
            <div style="max-width: 300px;">
                <label>Select Exam</label>
                <select id="reportExamSelect" class="form-control" onchange="loadReport()">
                    <option value="">-- Choose --</option>
                    <?php if($exams_res): $exams_res->data_seek(0); while($e = $exams_res->fetch_assoc()): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['title']); ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div id="reportResults" style="margin-top: 20px;"></div>
        </div>
    </main>

    <!-- QUESTION MODAL -->
    <div class="modal-overlay" id="qModal">
        <div class="modal">
            <div class="section-header">
                <h3 class="section-title" id="modalTitle">Add Question</h3>
                <button class="btn btn-outline" onclick="closeModal()" style="border:none;"><i class="fas fa-times"></i></button>
            </div>
            <form id="qForm">
                <input type="hidden" name="question_id" id="q_id">
                <div class="form-group"><label>Question Text</label><textarea name="question_text" id="q_text" rows="3" class="form-control" required></textarea></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><input type="text" name="option1" id="q_o1" placeholder="Option A" class="form-control" required></div>
                    <div><input type="text" name="option2" id="q_o2" placeholder="Option B" class="form-control" required></div>
                    <div><input type="text" name="option3" id="q_o3" placeholder="Option C" class="form-control" required></div>
                    <div><input type="text" name="option4" id="q_o4" placeholder="Option D" class="form-control" required></div>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>Correct Answer</label>
                    <select name="correct_answer" id="q_ca" class="form-control">
                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(id, item) {
            document.querySelectorAll('.section-card').forEach(e => e.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            document.querySelectorAll('.nav-item').forEach(e => e.classList.remove('active'));
            item.classList.add('active');
        }
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
        function showToast(t, m) {
            const c = document.getElementById('toastContainer');
            const n = document.createElement('div');
            n.className = `toast ${t}`;
            n.innerHTML = `<i class="fas fa-${t=='success'?'check-circle':'exclamation-circle'}" style="color:var(--${t})"></i> <span>${m}</span>`;
            c.appendChild(n); setTimeout(() => n.remove(), 3000);
        }
        <?php if($toast): ?>showToast('<?php echo $toast['type']; ?>', '<?php echo $toast['message']; ?>');<?php endif; ?>
        function filterTable(id, col) {
            const v = document.getElementById('questionSearch').value.toLowerCase();
            const t = document.getElementById(id).getElementsByTagName('tr');
            for(let i=1; i<t.length; i++) {
                const td = t[i].getElementsByTagName('td')[col];
                if(td) t[i].style.display = td.innerText.toLowerCase().indexOf(v) > -1 ? "" : "none";
            }
        }
        function openQuestionModal() { document.getElementById('qForm').reset(); document.getElementById('q_id').value=''; document.getElementById('modalTitle').textContent='Add Question'; document.getElementById('qModal').style.display='flex'; }
        function closeModal() { document.getElementById('qModal').style.display='none'; }
        
        // --- UPDATED DELETE FUNCTIONS (NO RELOAD) ---
        function deleteQuestion(id) {
            if(confirm('Delete this question?')) {
                fetch(`staff.php?action=delete_question&id=${id}`).then(r=>r.json()).then(d=>{
                    if(d.success) {
                        document.getElementById(`row-q-${id}`).remove();
                        showToast('success','Deleted');
                    } else {
                        showToast('error','Failed');
                    }
                });
            }
        }

        function deleteExam(id) {
            if(confirm('Delete this exam?')) {
                fetch(`staff.php?action=delete_exam&id=${id}`).then(r=>r.json()).then(d=>{
                    if(d.success) {
                        document.getElementById(`row-e-${id}`).remove();
                        showToast('success','Deleted');
                    } else {
                        showToast('error','Failed');
                    }
                });
            }
        }

        // --- MULTI SELECT DELETE ---
        function toggleSelectAll(source, targetClass) {
            const checkboxes = document.getElementsByClassName(targetClass);
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function deleteSelectedQuestions() {
            const checkboxes = document.querySelectorAll('.q_checkbox:checked');
            if (checkboxes.length === 0) {
                showToast('error', 'No questions selected');
                return;
            }
            if (confirm(`Delete ${checkboxes.length} selected questions?`)) {
                const ids = Array.from(checkboxes).map(cb => cb.value);
                fetch('staff.php?action=delete_questions', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ids: ids})
                }).then(r=>r.json()).then(d=>{
                    if(d.success) {
                        ids.forEach(id => {
                            const row = document.getElementById(`row-q-${id}`);
                            if(row) row.remove();
                        });
                        document.getElementById('selectAllQ').checked = false;
                        showToast('success', 'Deleted Successfully');
                    } else {
                        showToast('error', 'Failed to delete');
                    }
                });
            }
        }

        function editQuestion(id) {
            fetch(`staff.php?action=get_question&id=${id}`).then(r=>r.json()).then(d=>{
                if(d && d.id) {
                    document.getElementById('q_id').value=d.id; document.getElementById('q_text').value=d.question_text;
                    document.getElementById('q_o1').value=d.option1; document.getElementById('q_o2').value=d.option2;
                    document.getElementById('q_o3').value=d.option3; document.getElementById('q_o4').value=d.option4;
                    document.getElementById('q_ca').value=d.correct_answer; document.getElementById('modalTitle').textContent='Edit Question';
                    document.getElementById('qModal').style.display='flex';
                }
            });
        }
        function viewExam(id) { window.location.href = `exam_details.php?id=${id}`; }
        document.getElementById('qForm').addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(this);
            const id = document.getElementById('q_id').value;
            fetch(`staff.php?action=${id?'update_question':'add_question'}`,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
                if(d.success) { showToast('success','Saved'); closeModal(); location.reload(); } else showToast('error','Error');
            });
        });
        function loadReport() {
            const id = document.getElementById('reportExamSelect').value;
            const c = document.getElementById('reportResults');
            if(!id) { c.innerHTML=''; return; }
            c.innerHTML='<p style="text-align:center;">Loading...</p>';
            fetch(`staff.php?action=get_exam_results&id=${id}`).then(r=>r.json()).then(d=>{
                if(!d || d.length==0) { c.innerHTML='<p style="text-align:center;">No results.</p>'; return; }
                let h = '<table id="reportTablePDF"><thead><tr><th>Student</th><th>Score</th><th>%</th><th>Status</th></tr></thead><tbody>';
                d.forEach(r=>{
                    h+=`<tr><td>${r.student_name}</td><td>${r.score}/${r.max_score}</td><td>${r.percent}%</td><td>${r.percent>=50?'Pass':'Fail'}</td></tr>`;
                });
                c.innerHTML=h+'</tbody></table>';
            });
        }

        // --- PDF DOWNLOAD FUNCTION ---
        function downloadReportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            const examTitle = document.getElementById('reportExamSelect').options[document.getElementById('reportExamSelect').selectedIndex].text;
            const tableId = 'reportTablePDF';

            if(!document.getElementById(tableId)) {
                showToast('error', 'Please select an exam first.');
                return;
            }

            doc.text(`Performance Report: ${examTitle}`, 14, 15);
            
            doc.autoTable({
                html: '#' + tableId,
                startY: 20,
                theme: 'grid',
                headStyles: { fillColor: [79, 70, 229] }, // Primary color
                styles: { fontSize: 10 }
            });

            doc.save(`report_${examTitle}.pdf`);
        }

        window.onclick = function(e) { if(e.target==document.getElementById('qModal')) closeModal(); }
    </script>
</body> 
</html>