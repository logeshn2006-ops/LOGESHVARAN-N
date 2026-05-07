<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: index.php");
    exit;
}

require_once 'db_connect.php';

// Handle AJAX requests (Must be at top)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'get_exam_questions' && isset($_GET['exam_id'])) {
        $exam_id = intval($_GET['exam_id']);
        
        $questions_query = "SELECT q.* FROM questions q 
                          JOIN exam_questions eq ON q.id = eq.question_id 
                          WHERE eq.exam_id = $exam_id";
        $questions_result = $conn->query($questions_query);
        
        $questions = array();
        while ($question = $questions_result->fetch_assoc()) {
            $questions[] = $question;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'questions' => $questions
        ]);
        exit;
    }
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'submit_exam') {
        $exam_id = intval($_POST['exam_id']);
        $student_id = $_SESSION['user_id'];
        $answers = json_decode($_POST['answers'], true);
        
        // FIXED: Get exam questions to calculate score (Same query as JS fetch)
        $questions_query = "SELECT q.* FROM questions q 
                          JOIN exam_questions eq ON q.id = eq.question_id 
                          WHERE eq.exam_id = $exam_id";
        $questions_result = $conn->query($questions_query);
        
        $score = 0;
        $max_score = 0;
        $question_index = 0;
        
        while ($question = $questions_result->fetch_assoc()) {
            $max_score++;
            
            // FIXED: Robust Answer Comparison
            // Database has 'A', 'B', 'C', 'D' (from CSV)
            // JS sends 0, 1, 2, 3
            $correct_answer_db = strtoupper(trim($question['correct_answer']));
            $correct_index = -1;
            
            if ($correct_answer_db == 'A') $correct_index = 0;
            elseif ($correct_answer_db == 'B') $correct_index = 1;
            elseif ($correct_answer_db == 'C') $correct_index = 2;
            elseif ($correct_answer_db == 'D') $correct_index = 3;
            
            // Removed 'question_type' check to support simple CSV uploads
            if (isset($answers[$question_index]) && $answers[$question_index] == $correct_index) {
                $score++;
            }
            
            $question_index++;
        }
        
        $status = ($score / $max_score) >= 0.5 ? 'passed' : 'failed';
        
        $insert_result_query = "INSERT INTO exam_results (exam_id, student_id, score, max_score, status, submitted_at) 
                              VALUES ($exam_id, $student_id, $score, $max_score, '$status', NOW())";
        
        if ($conn->query($insert_result_query)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'score' => $score,
                'max_score' => $max_score,
                'status' => $status
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $conn->error
            ]);
        }
        exit;
    }
}

// Standard Page Logic
 $student_id = $_SESSION['user_id'];
 $student_query = "SELECT u.*, s.department, s.academic_year, s.father_name, s.mother_name, s.joining_year 
                 FROM users u 
                 JOIN students s ON u.id = s.user_id 
                 WHERE u.id = $student_id";
 $student_result = $conn->query($student_query);
 $student = $student_result->fetch_assoc();

 $department = $student['department'];
 $academic_year = $student['academic_year'];

 $exams_query = "SELECT e.*, COUNT(eq.question_id) as question_count 
               FROM exams e 
               JOIN exam_questions eq ON e.id = eq.exam_id 
               WHERE e.department = '$department' 
               AND e.academic_year = '$academic_year'
               AND e.id NOT IN (
                   SELECT exam_id FROM exam_results WHERE student_id = $student_id
               )
               GROUP BY e.id 
               ORDER BY e.created_at DESC";
 $exam_results = $conn->query($exams_query);

 $completed_exams_query = "SELECT e.*, er.score, er.max_score, er.status, er.submitted_at 
                         FROM exams e 
                         JOIN exam_results er ON e.id = er.exam_id 
                         WHERE er.student_id = $student_id 
                         ORDER BY er.submitted_at DESC";
 $completed_exams_result = $conn->query($completed_exams_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Student Portal – Strict Exam Mode</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<!-- PDF LIBRARIES ADDED -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<style>
  :root {
    --primary: #4f46e5;
    --secondary: #818cf8;
    --bg: #f3f4f6;
    --text: #1f2937;
    --white: #ffffff;
    --danger: #ef4444;
    --success: #10b981;
  }

  * { box-sizing: border-box; outline: none; }
  
  body {
    margin: 0; padding: 0;
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* SIDEBAR LAYOUT */
  .sidebar {
    width: 260px;
    background: var(--white);
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100%;
    z-index: 100;
  }
  
  .logo-area {
    padding: 24px;
    font-size: 20px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #f3f4f6;
  }

  .nav-links {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .nav-item {
    padding: 12px 16px;
    border-radius: 8px;
    cursor: pointer;
    color: #6b7280;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .nav-item:hover, .nav-item.active {
    background: #eef2ff;
    color: var(--primary);
  }

  .user-profile-mini {
    margin-top: auto;
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .avatar-circle {
    width: 40px; height: 40px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex; justify-content: center; align-items: center;
    font-weight: bold;
  }

  /* MAIN CONTENT */
  .main-content {
    flex: 1;
    margin-left: 260px;
    padding: 30px;
  }

  .header-glass {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    padding: 20px 30px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
  }

  .stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    display: flex; flex-direction: column;
  }
  
  .stat-val { font-size: 28px; font-weight: 700; color: var(--primary); }
  .stat-lbl { font-size: 14px; color: #6b7280; margin-top: 4px; }

  .section-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 16px;
    color: #374151;
  }

  .exam-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    border-left: 4px solid var(--primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: transform 0.2s;
  }
  .exam-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }

  .btn {
    padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600;
    transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;
  }
  .btn-primary { background: var(--primary); color: white; }
  .btn-primary:hover { background: #4338ca; }
  .btn-danger { background: var(--danger); color: white; }
  .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
  .btn-outline:hover { background: #f3f4f6; }

  /* EXAM FULL SCREEN MODE */
  #exam-view {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: #1f2937; z-index: 2000; display: none;
    flex-direction: column; color: white;
  }
  
  .exam-ui-container {
    max-width: 800px; margin: 40px auto; width: 90%;
    background: #111827; padding: 30px; border-radius: 16px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  }

  .mcq-option {
    background: #374151; padding: 15px; border-radius: 8px; margin: 10px 0;
    cursor: pointer; transition: 0.2s; border: 1px solid transparent;
  }
  .mcq-option:hover { background: #4b5563; }
  .mcq-option.selected { border-color: var(--primary); background: #312e81; }

  .modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: none; justify-content: center; align-items: center; z-index: 3000;
  }
  .modal-box {
    background: white; padding: 30px; border-radius: 16px;
    width: 400px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  }

  /* PRINT STYLES */
  #print-area { display: none; }
  @media print {
    body * { visibility: hidden; }
    #print-area, #print-area * { visibility: visible; }
    #print-area {
      position: absolute; left: 0; top: 0; width: 100%;
      background: white; color: black; padding: 40px;
      display: block;
    }
  }
  
  @media (max-width: 768px) {
    .sidebar { width: 60px; }
    .nav-item span { display: none; }
    .nav-item { justify-content: center; padding: 12px; }
    .logo-area span, .user-profile-mini div { display: none; }
    .main-content { margin-left: 60px; }
    .stats-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="main-sidebar">
  <div class="logo-area">
    <i class="fas fa-graduation-cap"></i>
    <span>EduPortal</span>
  </div>
  
  <div class="nav-links">
    <div class="nav-item active" onclick="switchTab('available')" id="nav-available">
      <i class="fas fa-play-circle"></i> <span>Available Exams</span>
    </div>
    <div class="nav-item" onclick="switchTab('completed')" id="nav-completed">
      <i class="fas fa-check-circle"></i> <span>My Results</span>
    </div>
    <div class="nav-item" onclick="switchTab('profile')" id="nav-profile">
      <i class="fas fa-user"></i> <span>Profile</span>
    </div>
  </div>

  <div class="user-profile-mini">
    <div class="avatar-circle"><?php echo strtoupper(substr($student['name'], 0, 1)); ?></div>
    <div style="flex:1;">
      <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($student['name']); ?></div>
      <div style="font-size:12px; color:#6b7280;"><?php echo htmlspecialchars($student['department']); ?></div>
    </div>
    <i class="fas fa-sign-out-alt" style="cursor:pointer; color:#ef4444;" onclick="logout()"></i>
  </div>
</aside>

<!-- MAIN DASHBOARD -->
<main class="main-content">
  <div class="header-glass">
    <div>
      <h1 style="margin:0; font-size:24px;">Welcome Back, <?php echo htmlspecialchars(explode(' ', $student['name'])[0]); ?> 👋</h1>
      <p style="margin:5px 0 0; color:#6b7280; font-size:14px;">Here's your academic overview.</p>
    </div>
    <div style="text-align:right;">
      <div style="font-weight:bold; color:var(--primary);"><?php echo date('l, F j'); ?></div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-val"><?php echo $exam_results ? $exam_results->num_rows : 0; ?></div>
      <div class="stat-lbl">Pending Exams</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?php echo $completed_exams_result ? $completed_exams_result->num_rows : 0; ?></div>
      <div class="stat-lbl">Completed Exams</div>
    </div>
    <div class="stat-card">
      <div class="stat-val">
        <?php 
          $avg = 0;
          if($completed_exams_result && $completed_exams_result->num_rows > 0) {
            $completed_exams_result->data_seek(0);
            $tot = 0; $max = 0;
            while($r = $completed_exams_result->fetch_assoc()){ $tot+=$r['score']; $max+=$r['max_score']; }
            $avg = round(($tot/$max)*100);
            $completed_exams_result->data_seek(0);
          }
          echo $avg."%";
        ?>
      </div>
      <div class="stat-lbl">Avg Performance</div>
    </div>
  </div>

  <!-- TABS -->
  <div id="tab-available">
    <div class="section-title">Exams Available Now</div>
    <?php if ($exam_results && $exam_results->num_rows > 0): ?>
      <?php while ($exam = $exam_results->fetch_assoc()): ?>
        <div class="exam-card">
          <div>
            <h3 style="margin:0 0 5px 0;"><?php echo htmlspecialchars($exam['title']); ?></h3>
            <div style="font-size:13px; color:#6b7280; display:flex; gap:15px;">
              <span><i class="far fa-clock"></i> <?php echo $exam['duration']; ?> min</span>
              <span><i class="fas fa-layer-group"></i> <?php echo $exam['question_count']; ?> Qns</span>
            </div>
          </div>
          <button class="btn btn-primary" onclick="startExam(<?php echo $exam['id']; ?>, <?php echo $exam['duration']; ?>)">
            Start Exam
          </button>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div style="text-align:center; padding:40px; background:white; border-radius:12px; color:#6b7280;">
        No pending exams found.
      </div>
    <?php endif; ?>
  </div>

  <div id="tab-completed" style="display:none;">
    <div class="section-title">Exam History</div>
    <?php if ($completed_exams_result && $completed_exams_result->num_rows > 0): ?>
      <?php while ($exam = $completed_exams_result->fetch_assoc()): 
        $percent = round(($exam['score'] / $exam['max_score']) * 100);
        $statusColor = $exam['status'] == 'passed' ? 'var(--success)' : 'var(--danger)';
      ?>
        <div class="exam-card" style="border-left-color: <?php echo $statusColor; ?>;">
          <div>
            <h3 style="margin:0 0 5px 0;"><?php echo htmlspecialchars($exam['title']); ?></h3>
            <div style="font-size:14px; font-weight:600; color:<?php echo $statusColor; ?>;">
              Score: <?php echo $exam['score']; ?>/<?php echo $exam['max_score']; ?> (<?php echo $percent; ?>%)
            </div>
            <div style="font-size:12px; color:#9ca3af; margin-top:4px;">
              Submitted: <?php echo date('M d, Y', strtotime($exam['submitted_at'])); ?>
            </div>
          </div>
          <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" onclick="printReport(
              '<?php echo htmlspecialchars($exam['title']); ?>',
              '<?php echo $exam['score']; ?>',
              '<?php echo $exam['max_score']; ?>',
              '<?php echo $exam['status']; ?>',
              '<?php echo date('M d, Y', strtotime($exam['submitted_at'])); ?>'
            )">
              <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-primary" onclick="downloadResultPDF(
              '<?php echo htmlspecialchars($exam['title']); ?>',
              '<?php echo $exam['score']; ?>',
              '<?php echo $exam['max_score']; ?>',
              '<?php echo $exam['status']; ?>',
              '<?php echo date('M d, Y', strtotime($exam['submitted_at'])); ?>'
            )">
              <i class="fas fa-file-pdf"></i> PDF
            </button>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div style="text-align:center; padding:40px; background:white; border-radius:12px; color:#6b7280;">
        You haven't taken any exams yet.
      </div>
    <?php endif; ?>
  </div>

  <div id="tab-profile" style="display:none;">
    <div class="exam-card" style="display:block;">
      <h3>My Profile</h3>
      <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
      <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
      <p><strong>Department:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $student['department']))); ?></p>
      <p><strong>Year:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $student['academic_year']))); ?></p>
      <hr style="border:0; border-top:1px solid #e5e7eb; margin:20px 0;">
      <div style="display:flex; justify-content:flex-end;">
        <button class="btn btn-danger" onclick="logout()">Logout</button>
      </div>
    </div>
  </div>
</main>

<!-- EXAM OVERLAY (FULL SCREEN) -->
<div id="exam-view">
  <div style="padding:20px; background:#111827; border-bottom:1px solid #374151; display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h2 style="margin:0; font-size:18px;">🔒 Locked Mode</h2>
      <small style="color:#9ca3af;">Strict security active. Do not leave window.</small>
    </div>
    <div style="font-size:24px; font-weight:bold; font-family:monospace; color:var(--danger);" id="timer">00:00</div>
  </div>

  <div class="exam-ui-container">
    <div style="display:flex; justify-content:space-between; margin-bottom:20px; color:#9ca3af; font-size:14px;">
      <span id="q-counter">Question 1 of X</span>
      <span id="progress-text">0% Completed</span>
    </div>
    <div id="question-area">
      <!-- Question loads here -->
    </div>
    
    <div style="margin-top:30px; display:flex; justify-content:space-between;">
      <button class="btn btn-outline" id="prev-btn" onclick="navQuestion(-1)" style="color:white; border-color:#4b5563;">Previous</button>
      <button class="btn btn-primary" id="next-btn" onclick="navQuestion(1)">Next Question</button>
      <button class="btn btn-success" id="finish-btn" onclick="confirmSubmit()" style="display:none; background:var(--success);">Finish Exam</button>
    </div>
  </div>
</div>

<!-- MODALS -->

<!-- 1. ESC Key Exit Modal -->
<div class="modal-overlay" id="esc-modal">
  <div class="modal-box">
    <div style="color:#ef4444; font-size:40px; margin-bottom:10px;"><i class="fas fa-sign-out-alt"></i></div>
    <h3>Exit Exam?</h3>
    <p style="color:#6b7280; margin-bottom:20px;">Are you sure you want to end this session?</p>
    <div style="display:flex; gap:10px; justify-content:center;">
      <button class="btn btn-outline" onclick="closeEscModal()">Continue Exam</button>
      <button class="btn btn-danger" onclick="autoSubmitEsc()">Cancel (Submit)</button>
    </div>
  </div>
</div>

<!-- 2. Security Warning (Windows Key - 3 Strikes Logic) -->
<div class="modal-overlay" id="warning-modal" style="background:rgba(239, 68, 68, 0.95);">
  <div class="modal-box" style="background:white; color:black;">
    <h3 style="color:#ef4444;">⚠️ SECURITY ALERT</h3>
    <p>Windows Key Press Detected.</p>
    <p style="font-size:12px; color:#ef4444; font-weight:bold;">(3 Violations = Auto Submit)</p>
    <button class="btn btn-danger" onclick="closeWarningModal()" style="width:100%; margin-top:20px;">I Understand</button>
  </div>
</div>

<!-- 3. Standard Submit Confirm -->
<div class="modal-overlay" id="submit-modal">
  <div class="modal-box">
    <h3>Confirm Submission</h3>
    <p>Are you ready to submit your answers?</p>
    <div style="display:flex; gap:10px; justify-content:center;">
      <button class="btn btn-outline" onclick="closeSubmitModal()">Review</button>
      <button class="btn btn-primary" onclick="submitExam()">Yes, Submit</button>
    </div>
  </div>
</div>

<!-- PRINT AREA (HIDDEN) -->
<div id="print-area">
  <div style="border:2px solid #000; padding:20px;">
    <h1 style="text-align:center; border-bottom:1px solid #000; padding-bottom:10px;">Exam Result Report</h1>
    <div style="display:flex; justify-content:space-between; margin-top:20px;">
      <div><strong>Student:</strong> <span id="print-name"></span></div>
      <div><strong>Date:</strong> <span id="print-date"></span></div>
    </div>
    <div style="margin-top:20px; border:1px solid #ccc; padding:15px;">
      <h2 style="margin:0 0 10px 0; border-bottom:1px solid #eee;" id="print-title">Exam Title</h2>
      <div style="font-size:18px;"><strong>Score:</strong> <span id="print-score"></span> / <span id="print-max"></span></div>
      <div style="font-size:18px;"><strong>Status:</strong> <span id="print-status" style="font-weight:bold;"></span></div>
    </div>
    <div style="margin-top:20px; text-align:center; font-size:12px;">This is a computer-generated report.</div>
  </div>
</div>

<script>
// --- STATE ---
let questions = [];
let answers = [];
let currentIdx = 0;
let timerInt = null;
let timeLeft = 0;
let examRunning = false;
let currentExamId = null;

// --- SECURITY STATE ---
let violationCount = 0;
const MAX_VIOLATIONS = 3;

// --- FULLSCREEN FUNCTIONS ---
function enterFullscreen() {
  const elem = document.documentElement;
  if (elem.requestFullscreen) {
    elem.requestFullscreen();
  } else if (elem.mozRequestFullScreen) { 
    elem.mozRequestFullScreen();
  } else if (elem.webkitRequestFullscreen) { 
    elem.webkitRequestFullscreen();
  } else if (elem.msRequestFullscreen) { 
    elem.msRequestFullscreen();
  }
}

function exitFullscreen() {
  if (document.exitFullscreen) {
    document.exitFullscreen();
  } else if (document.mozCancelFullScreen) { 
    document.mozCancelFullScreen();
  } else if (document.webkitExitFullscreen) { 
    document.webkitExitFullscreen();
  } else if (document.msExitFullscreen) { 
    document.msExitFullscreen();
  }
}

// --- VIOLATION HANDLER ---
function handleViolation() {
  if (!examRunning) return;
  
  violationCount++;
  console.log("Violation Count: " + violationCount);

  if (violationCount >= MAX_VIOLATIONS) {
    submitExam();
  } else {
    document.getElementById('warning-modal').style.display = 'flex';
  }
}

function closeWarningModal() { document.getElementById('warning-modal').style.display = 'none'; }

// --- NAVIGATION ---
function switchTab(id) {
  document.getElementById('tab-available').style.display = 'none';
  document.getElementById('tab-completed').style.display = 'none';
  document.getElementById('tab-profile').style.display = 'none';
  
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  document.getElementById('nav-' + id).classList.add('active');
  
  document.getElementById('tab-' + id).style.display = 'block';
}

function logout() {
  if(confirm("Logout?")) window.location.href = 'index.php';
}

// --- REPORT PRINTING ---
function printReport(title, score, max, status, date) {
  document.getElementById('print-name').innerText = "<?php echo $student['name']; ?>";
  document.getElementById('print-date').innerText = date;
  document.getElementById('print-title').innerText = title;
  document.getElementById('print-score').innerText = score;
  document.getElementById('print-max').innerText = max;
  
  const statusEl = document.getElementById('print-status');
  statusEl.innerText = status.toUpperCase();
  statusEl.style.color = status === 'passed' ? 'green' : 'red';
  
  window.print();
}

// --- PDF DOWNLOAD FUNCTION ---
function downloadResultPDF(title, score, max, status, date) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Title
    doc.setFontSize(18);
    doc.setTextColor(79, 70, 229); // Primary Color
    doc.text("Exam Result Report", 105, 20, null, null, "center");
    
    // Student Info
    doc.setFontSize(12);
    doc.setTextColor(0, 0, 0);
    doc.text(`Student: ${"<?php echo $student['name']; ?>"}`, 20, 40);
    doc.text(`Department: ${"<?php echo $student['department']; ?>"}`, 20, 48);
    doc.text(`Date: ${date}`, 20, 56);
    
    // Exam Details Table
    const tableData = [
        ['Exam Title', title],
        ['Score', `${score} / ${max}`],
        ['Status', status.toUpperCase()],
    ];
    
    doc.autoTable({
        head: [['Field', 'Details']],
        body: tableData,
        startY: 70,
        theme: 'grid',
        headStyles: { fillColor: [79, 70, 229] },
        columnStyles: {
            0: { fontStyle: 'bold' }
        }
    });
    
    // Footer
    const pageCount = doc.internal.getNumberOfPages();
    doc.setFontSize(10);
    doc.setTextColor(150);
    for(let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.text('Generated by EduPortal', 105, 290, null, null, "center");
    }
    
    doc.save(`Result_${title.replace(/\s+/g, '_')}.pdf`);
}

// --- EXAM LOGIC ---
function startExam(id, duration) {
  currentExamId = id; 
  
  fetch(`student.php?action=get_exam_questions&exam_id=${id}`)
    .then(r => r.json())
    .then(data => {
      if(data.success) {
        questions = data.questions;
        answers = new Array(questions.length).fill(null);
        timeLeft = duration * 60;
        currentIdx = 0;
        examRunning = true;
        violationCount = 0; // Reset violations
        
        document.getElementById('main-sidebar').style.display = 'none';
        document.querySelector('.main-content').style.display = 'none';
        document.getElementById('exam-view').style.display = 'flex';
        
        enterFullscreen();
        startTimer();
        renderQuestion();
      }
    });
}

function renderQuestion() {
  const q = questions[currentIdx];
  const html = `
    <h3 style="margin-bottom:20px; font-size:20px;">${q.question_text}</h3>
    <div id="options-box">
      <div class="mcq-option" onclick="selectOption(0)">${q.option1}</div>
      <div class="mcq-option" onclick="selectOption(1)">${q.option2}</div>
      <div class="mcq-option" onclick="selectOption(2)">${q.option3}</div>
      <div class="mcq-option" onclick="selectOption(3)">${q.option4}</div>
    </div>
  `;
  document.getElementById('question-area').innerHTML = html;
  
  if(answers[currentIdx] !== null) selectOption(answers[currentIdx], false);
  
  document.getElementById('q-counter').innerText = `Question ${currentIdx+1} of ${questions.length}`;
  const pct = Math.round(((currentIdx+1)/questions.length)*100);
  document.getElementById('progress-text').innerText = `${pct}% Completed`;
  
  document.getElementById('prev-btn').disabled = currentIdx === 0;
  if(currentIdx === questions.length -1) {
    document.getElementById('next-btn').style.display = 'none';
    document.getElementById('finish-btn').style.display = 'inline-flex';
  } else {
    document.getElementById('next-btn').style.display = 'inline-flex';
    document.getElementById('finish-btn').style.display = 'none';
  }
}

function selectOption(idx, save = true) {
  document.querySelectorAll('.mcq-option').forEach(el => el.classList.remove('selected'));
  document.querySelectorAll('.mcq-option')[idx].classList.add('selected');
  if(save) answers[currentIdx] = idx;
}

function navQuestion(dir) {
  if(currentIdx + dir >= 0 && currentIdx + dir < questions.length) {
    currentIdx += dir;
    renderQuestion();
  }
}

function startTimer() {
  updateTimer();
  timerInt = setInterval(() => {
    timeLeft--;
    updateTimer();
    if(timeLeft <= 0) submitExam();
  }, 1000);
}

function updateTimer() {
  const m = Math.floor(timeLeft/60);
  const s = timeLeft%60;
  document.getElementById('timer').innerText = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
}

// --- SUBMISSION ---
function confirmSubmit() { document.getElementById('submit-modal').style.display = 'flex'; }
function closeSubmitModal() { document.getElementById('submit-modal').style.display = 'none'; }

function submitExam() {
  clearInterval(timerInt);
  examRunning = false;
  exitFullscreen();
  
  const formData = new FormData();
  formData.append('action', 'submit_exam');
  formData.append('exam_id', currentExamId);
  formData.append('answers', JSON.stringify(answers));
  
  fetch('student.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
      if(d.success) {
          alert(`Submitted! Score: ${d.score}/${d.max_score}`);
          location.reload();
      } else {
          alert("Error submitting: " + d.message);
          location.reload();
      }
    });
}

// --- MODAL HELPERS ---
function closeEscModal() { document.getElementById('esc-modal').style.display = 'none'; }
function autoSubmitEsc() { submitExam(); }

// --- SECURITY EVENT LISTENER (UPDATED) ---
window.addEventListener('keydown', e => {
  if (!examRunning) return;
  
  const k = e.key;
  const code = e.code;

  // 1. ALLOWED KEYS (Typing A-Z, 0-9, Backspace, Enter, Space)
  const isTyping = /^[a-zA-Z0-9 ]$/.test(k) || k === 'Backspace' || k === 'Enter' || k === ' ';
  if (isTyping) {
    return; // Allow action
  }

  // 2. WINDOWS KEY (Meta) -> COUNT & BLOCK
  if (k === 'Meta' || k === 'OS') {
    e.preventDefault();
    e.stopPropagation();
    handleViolation();
    return;
  }

  // 3. ESCAPE KEY -> EXIT MODAL
  if (k === 'Escape') {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('esc-modal').style.display = 'flex';
    return;
  }

  // 4. RESTRICTED KEYS (Arrows, F-Keys, Tab, Ctrl, Alt)
  // Logic: BLOCK BUT DO NOT COUNT
  if (
      ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(k) || // Arrows Blocked
      k.startsWith('F') || // F1-F12 Blocked
      k === 'Tab' || // Tab Blocked
      k === 'Control' || // Ctrl Blocked
      k === 'Alt' // Alt Blocked
  ) {
    e.preventDefault();
    e.stopPropagation();
    // NO handleViolation() call here
    console.log("Blocked Key (Silent): " + k);
    return;
  }
});

// --- BLUR / TAB SWITCHING (Counts towards 3 strikes) ---
window.addEventListener('blur', () => {
  if (examRunning && document.getElementById('warning-modal').style.display !== 'flex' && document.getElementById('esc-modal').style.display !== 'flex') {
    handleViolation();
  }
});

document.addEventListener('visibilitychange', () => {
  if (examRunning && document.hidden && document.getElementById('warning-modal').style.display !== 'flex' && document.getElementById('esc-modal').style.display !== 'flex') {
    handleViolation();
  }
});

// --- SILENT BLOCKING (Copy/Paste/Context) ---
document.addEventListener('contextmenu', e => {
  e.preventDefault();
  e.stopPropagation();
});
document.addEventListener('copy', e => {
  e.preventDefault();
  e.stopPropagation();
});
document.addEventListener('paste', e => {
  e.preventDefault();
  e.stopPropagation();
});

</script>
</body>
</html>