<?php 
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'exam_system');

// Create database connection
 $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch departments for the dropdowns
 $departments = [];
 $query = "SELECT name FROM departments ORDER BY name";
 $result = $conn->query($query);
if ($result) { 
    while ($row = $result->fetch_assoc()) { 
        $departments[] = $row['name']; 
    } 
}

// Initialize variables to store form data
 $name = $dob = $gender = $phone = $email = $username = $password = $confirmPassword = "";
 $registrationSuccess = false;
 $userType = "student"; // Default to student

// Student specific fields
 $fatherName = $motherName = $joiningYear = $department = $academicYear = "";

// Staff specific fields
 $employeeId = $designation = $staffDepartment = $experience = "";

// OTP related variables
 $otp = $resetEmail = $resetPhone = $resetUserid = $resetRole = "";
 $otpSent = false;
 $otpVerified = false;
 $otpError = "";
 $resetSuccess = false;

// Process form submission - CORRECTED LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Get all registration form values
    $userType = $_POST["userType"];
    $name = $_POST["regName"];
    $dob = $_POST["regDob"];
    $gender = $_POST["regGender"];
    $phone = $_POST["regPhone"];
    $email = $_POST["regEmail"];
    $username = $_POST["regUsername"];
    $password = $_POST["regPassword"];
    $confirmPassword = $_POST["regConfirmPassword"];
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    if ($userType == "student") {
        // Get student specific fields
        $fatherName = $_POST["regFatherName"];
        $motherName = $_POST["regMotherName"];
        $joiningYear = $_POST["regJoiningYear"];
        $department = $_POST["regDepartment"];
        $academicYear = $_POST["regAcademicYear"];
        
        // Validation
        if (empty($name) || empty($dob) || empty($gender) || empty($phone) || empty($email) || 
            empty($username) || empty($password) || empty($confirmPassword) || 
            empty($fatherName) || empty($motherName) || empty($joiningYear) || empty($department) || empty($academicYear)) {
            $errorMsg = "Please fill in all fields.";
        } elseif ($password !== $confirmPassword) {
            $errorMsg = "Passwords do not match!";
        } elseif (strlen($password) < 6) {
            $errorMsg = "Password must be at least 6 characters long.";
        } else {
            // Check if username or email already exists in pending_students
            $checkQuery = "SELECT username, email FROM pending_students WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errorMsg = "Username or email already exists!";
            } else {
                // Insert into pending_students table
                $insertQuery = "INSERT INTO pending_students (name, username, email, phone, password, dob, gender, father_name, mother_name, joining_year, department, academic_year) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("ssssssssssss", $name, $username, $email, $phone, $hashedPassword, $dob, $gender, $fatherName, $motherName, $joiningYear, $department, $academicYear);
                
                if ($stmt->execute()) {
                    $registrationSuccess = true;
                    $successMsg = "Registration successful! Your account is pending approval by the admin.";
                } else {
                    $errorMsg = "Registration failed. Please try again.";
                }
            }
        }
    } else if ($userType == "staff") {
        // Get staff specific fields
        $employeeId = $_POST["regEmployeeId"];
        $designation = $_POST["regDesignation"];
        $staffDepartment = $_POST["regStaffDepartment"];
        $experience = $_POST["regExperience"];
        
        // Validation
        if (empty($name) || empty($dob) || empty($gender) || empty($phone) || empty($email) || 
            empty($username) || empty($password) || empty($confirmPassword) || 
            empty($employeeId) || empty($designation) || empty($staffDepartment) || empty($experience)) {
            $errorMsg = "Please fill in all fields.";
        } elseif ($password !== $confirmPassword) {
            $errorMsg = "Passwords do not match!";
        } elseif (strlen($password) < 6) {
            $errorMsg = "Password must be at least 6 characters long.";
        } else {
            // Check if username or email already exists in pending_staff
            $checkQuery = "SELECT username, email FROM pending_staff WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errorMsg = "Username or email already exists!";
            } else {
                // Insert into pending_staff table
                $insertQuery = "INSERT INTO pending_staff (name, username, email, phone, password, dob, gender, employee_id, designation, department, experience) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("sssssssssss", $name, $username, $email, $phone, $hashedPassword, $dob, $gender, $employeeId, $designation, $staffDepartment, $experience);
                
                if ($stmt->execute()) {
                    $registrationSuccess = true;
                    $successMsg = "Registration successful! Your account is pending approval by the admin.";
                } else {
                    $errorMsg = "Registration failed. Please try again.";
                }
            }
        }
    }
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $userid = $_POST["userid"];
    $password = $_POST["password"];
    $role = $_POST["role"];
    
    if (empty($userid) || empty($password)) {
        $loginError = "Please enter User ID and Password";
    } else {
        // Query to get user data
        $loginQuery = "SELECT id, username, password, user_type FROM users WHERE username = ? AND user_type = ?";
        $stmt = $conn->prepare($loginQuery);
        $stmt->bind_param("ss", $userid, $role);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Start session and store user data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect based on role
                if ($role === "admin") {
                    header("Location: admin.php");
                    exit;
                } else if ($role === "staff") {
                    header("Location: staff.php");
                    exit;
                } else if ($role === "student") {
                    header("Location: student.php");
                    exit;
                }
            } else {
                $loginError = "Invalid username or password";
            }
        } else {
            $loginError = "Invalid username or password";
        }
    }
}

// Process OTP request for password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['requestOtp'])) {
    $resetUserid = $_POST["resetUserid"];
    $resetEmail = $_POST["resetEmail"];
    $resetPhone = $_POST["resetPhone"];
    $resetRole = $_POST["resetRole"];
    
    // Validation
    if (empty($resetUserid) || (empty($resetEmail) && empty($resetPhone))) {
        $otpError = "Please enter User ID and either Email or Phone number";
    } else {
        // Check if user exists in database
        $checkUser = "SELECT id, email, phone FROM users WHERE username = ? AND user_type = ?";
        $stmt = $conn->prepare($checkUser);
        $stmt->bind_param("ss", $resetUserid, $resetRole);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify email or phone matches
            if ((!empty($resetEmail) && $user['email'] === $resetEmail) || 
                (!empty($resetPhone) && $user['phone'] === $resetPhone)) {
                
                // Generate 6-digit OTP
                $otp = rand(100000, 999999);
                
                // Store OTP in database with expiry time (current time + 15 minutes)
                $expiryTime = date('Y-m-d H:i:s', time() + 900); // 15 minutes
                $storeOtp = "INSERT INTO password_resets (user_id, user_role, otp, expiry_time) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE otp = ?, expiry_time = ?";
                $stmt = $conn->prepare($storeOtp);
                $stmt->bind_param("ssssss", $resetUserid, $resetRole, $otp, $expiryTime, $otp, $expiryTime);
                $stmt->execute();
                
                // Send OTP via email or SMS
                if (!empty($resetEmail)) {
                    $otpSent = true;
                    $successMsg = "OTP has been sent to your email: " . substr($resetEmail, 0, 3) . "****" . substr($resetEmail, strpos($resetEmail, "@"));
                } else {
                    $otpSent = true;
                    $successMsg = "OTP has been sent to your phone: " . substr($resetPhone, 0, 3) . "******" . substr($resetPhone, -4);
                }
            } else {
                $otpError = "Email or Phone does not match our records";
            }
        } else {
            $otpError = "User ID not found";
        }
    }
}

// Process OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verifyOtp'])) {
    $resetUserid = $_POST["resetUserid"];
    $resetRole = $_POST["resetRole"];
    $enteredOtp = $_POST["enteredOtp"];
    
    // Verify OTP in database
    $verifyOtp = "SELECT * FROM password_resets WHERE user_id = ? AND user_role = ? AND otp = ? AND expiry_time > NOW()";
    $stmt = $conn->prepare($verifyOtp);
    $stmt->bind_param("sss", $resetUserid, $resetRole, $enteredOtp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $otpVerified = true;
        $successMsg = "OTP verified successfully. You can now reset your password.";
    } else {
        $otpError = "Invalid or expired OTP. Please try again.";
    }
}

// Process password reset form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resetPassword'])) {
    $resetUserid = $_POST["resetUserid"];
    $resetRole = $_POST["resetRole"];
    $newPassword = $_POST["newPassword"];
    $confirmPassword = $_POST["confirmNewPassword"];
    
    // Validation
    if (empty($newPassword) || empty($confirmPassword)) {
        $resetError = "Please fill all fields";
    } elseif ($newPassword !== $confirmPassword) {
        $resetError = "New passwords do not match!";
    } elseif (strlen($newPassword) < 6) {
        $resetError = "New password must be at least 6 characters long";
    } else {
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in database
        $updatePassword = "UPDATE users SET password = ? WHERE username = ? AND user_type = ?";
        $stmt = $conn->prepare($updatePassword);
        $stmt->bind_param("sss", $hashedPassword, $resetUserid, $resetRole);
        
        if ($stmt->execute()) {
            // Delete used OTP
            $deleteOtp = "DELETE FROM password_resets WHERE user_id = ? AND user_role = ?";
            $stmt = $conn->prepare($deleteOtp);
            $stmt->bind_param("ss", $resetUserid, $resetRole);
            $stmt->execute();
            
            $resetSuccess = true;
        } else {
            $resetError = "Failed to reset password. Please try again.";
        }
    }
}

// Determine which form to show
 $formToShow = "login"; // Default
if (isset($_GET['form'])) {
    $formToShow = $_GET['form'];
}

if (isset($registrationSuccess) && $registrationSuccess) {
    $formToShow = "login";
    $successMsg = "Registration successful! Your account is pending approval by the admin.";
}

if (isset($resetSuccess) && $resetSuccess) {
    $formToShow = "login";
    $successMsg = "Password changed successfully! Please login with your new password.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login – Secure Online Exam System</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #5A60FF, #9A5CFF);
            font-family: 'Segoe UI', sans-serif;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 350px;
            background: rgba(255, 255, 255, 0.15);
            padding: 25px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        h2 {
            text-align: center;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: none;
            margin: 10px 0;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            box-sizing: border-box;
        }
        input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        input:focus, select:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.3);
        }
        input[type="date"], input[type="number"], input[type="tel"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        select {
            cursor: pointer;
        }
        select option {
            background: #5A60FF;
            color: white;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #fff;
            color: #5A60FF;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            opacity: 0.8;
            transform: translateY(-2px);
            transition: all 0.3s;
        }
        .form-links {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
        }
        .form-links a {
            color: white;
            text-decoration: underline;
            cursor: pointer;
        }
        .form-links a:hover {
            opacity: 0.8;
        }
        .hidden {
            display: none;
        }
        .error-message {
            color: #ffcccc;
            background-color: rgba(255, 0, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .success-message {
            color: #ccffcc;
            background-color: rgba(0, 255, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .user-type-selector {
            display: flex;
            margin: 15px 0;
            justify-content: space-between;
        }
        .user-type-option {
            flex: 1;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: background 0.3s;
        }
        .user-type-option:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .user-type-option.active {
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid white;
        }
        .student-fields, .staff-only {
            margin-bottom: 10px;
        }
        .otp-container {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .otp-container input {
            flex: 1;
        }
        .otp-container button {
            flex: 0 0 auto;
            width: auto;
            padding: 0 15px;
        }
    </style>
</head>

<body>

    <!-- Login Form -->
    <div class="container <?php echo ($formToShow == 'login') ? '' : 'hidden'; ?>" id="loginForm">
        <h2>Login</h2>
        
        <?php if (isset($loginError)): ?>
            <div class="error-message"><?php echo $loginError; ?></div>
        <?php endif; ?>
        
        <?php if (isset($successMsg)): ?>
            <div class="success-message"><?php echo $successMsg; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <select name="role">
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
                <option value="student">Student</option>
            </select>

            <input name="userid" placeholder="User ID">
            <input name="password" type="password" placeholder="Password">

            <button type="submit" name="login">Login</button>
        </form>

        <div class="form-links">
            <a href="?form=forgot">Forgot Password?</a>
        </div>
        <div class="form-links">
            Don't have an account? <a href="?form=register">Register Now</a>
        </div>
    </div>

    <!-- Registration Form -->
    <div class="container <?php echo ($formToShow == 'register') ? '' : 'hidden'; ?>" id="registrationForm">
        <h2>Register</h2>
        
        <?php if (isset($errorMsg)): ?>
            <div class="error-message"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="user-type-selector">
                <div class="user-type-option <?php echo ($userType == 'student') ? 'active' : ''; ?>" onclick="setUserType('student')">Student</div>
                <div class="user-type-option <?php echo ($userType == 'staff') ? 'active' : ''; ?>" onclick="setUserType('staff')">Staff</div>
            </div>
            
            <input type="hidden" name="userType" id="userType" value="<?php echo $userType; ?>">
            
            <input name="regName" type="text" placeholder="Full Name" value="<?php echo $name; ?>">
            <input name="regDob" type="date" placeholder="Date of Birth" value="<?php echo $dob; ?>">
            <select name="regGender">
                <option value="">Select Gender</option>
                <option value="male" <?php echo ($gender == 'male') ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo ($gender == 'female') ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo ($gender == 'other') ? 'selected' : ''; ?>>Other</option>
            </select>
            <input name="regPhone" type="tel" placeholder="Phone Number" value="<?php echo $phone; ?>">
            <input name="regEmail" type="email" placeholder="Email ID" value="<?php echo $email; ?>">
            <input name="regUsername" placeholder="Username" value="<?php echo $username; ?>">
            <input name="regPassword" type="password" placeholder="Password">
            <input name="regConfirmPassword" type="password" placeholder="Confirm Password">
            
            <div class="student-fields" <?php echo ($userType == 'student') ? '' : 'style="display: none;"'; ?>>
                <input name="regFatherName" type="text" placeholder="Father's Name" value="<?php echo $fatherName; ?>">
                <input name="regMotherName" type="text" placeholder="Mother's Name" value="<?php echo $motherName; ?>">
                
                <input name="regJoiningYear" type="number" placeholder="Joining Year" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo $joiningYear; ?>">
                <select name="regDepartment">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept; ?>" <?php echo ($department == $dept) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $dept))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="regAcademicYear">
                    <option value="">Select Academic Year</option>
                    <option value="first_year" <?php echo ($academicYear == 'first_year') ? 'selected' : ''; ?>>First Year</option>
                    <option value="second_year" <?php echo ($academicYear == 'second_year') ? 'selected' : ''; ?>>Second Year</option>
                    <option value="third_year" <?php echo ($academicYear == 'third_year') ? 'selected' : ''; ?>>Third Year</option>
                    <option value="fourth_year" <?php echo ($academicYear == 'fourth_year') ? 'selected' : ''; ?>>Fourth Year</option>
                </select>
            </div>
            
            <div class="staff-only" <?php echo ($userType == 'staff') ? '' : 'style="display: none;"'; ?>>
                <input name="regEmployeeId" type="text" placeholder="Employee ID" value="<?php echo $employeeId; ?>">
                <input name="regDesignation" type="text" placeholder="Designation" value="<?php echo $designation; ?>">
                <select name="regStaffDepartment">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept; ?>" <?php echo ($staffDepartment == $dept) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $dept))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input name="regExperience" type="number" placeholder="Years of Experience" min="0" value="<?php echo $experience; ?>">
            </div>

            <button type="submit" name="register">Register</button>
        </form>

        <div class="form-links">
            <a href="?form=login">Back to Login</a>
        </div>
    </div>

    <!-- Forgot Password Form -->
    <div class="container <?php echo ($formToShow == 'forgot') ? '' : 'hidden'; ?>" id="forgotPasswordForm">
        <h2>Reset Password</h2>
        
        <?php if (isset($otpError)): ?>
            <div class="error-message"><?php echo $otpError; ?></div>
        <?php endif; ?>
        
        <?php if (isset($resetError)): ?>
            <div class="error-message"><?php echo $resetError; ?></div>
        <?php endif; ?>
        
        <?php if (isset($successMsg)): ?>
            <div class="success-message"><?php echo $successMsg; ?></div>
        <?php endif; ?>

        <!-- Step 1: Request OTP -->
        <div id="otpRequestStep" <?php echo ($otpVerified) ? 'style="display:none;"' : ''; ?>>
            <form method="post" action="">
                <input type="hidden" name="resetRole" value="<?php echo $resetRole ?: 'student'; ?>">
                <input name="resetUserid" placeholder="User ID" value="<?php echo $resetUserid; ?>">
                <input name="resetEmail" type="email" placeholder="Email ID" value="<?php echo $resetEmail; ?>">
                <input name="resetPhone" type="tel" placeholder="Phone Number" value="<?php echo $resetPhone; ?>">
                <small style="font-size: 12px; opacity: 0.8;">Enter either Email or Phone Number</small>
                
                <button type="submit" name="requestOtp">Send OTP</button>
            </form>
        </div>
        
        <!-- Step 2: Verify OTP -->
        <div id="otpVerifyStep" <?php echo (!$otpSent || $otpVerified) ? 'style="display:none;"' : ''; ?>>
            <form method="post" action="">
                <input type="hidden" name="resetUserid" value="<?php echo $resetUserid; ?>">
                <input type="hidden" name="resetRole" value="<?php echo $resetRole; ?>">
                
                <div class="otp-container">
                    <input name="enteredOtp" type="text" placeholder="Enter OTP" maxlength="6">
                    <button type="submit" name="verifyOtp">Verify</button>
                </div>
                
                <button type="button" onclick="resendOtp()">Resend OTP</button>
            </form>
        </div>
        
        <!-- Step 3: Reset Password -->
        <div id="resetPasswordStep" <?php echo (!$otpVerified) ? 'style="display:none;"' : ''; ?>>
            <form method="post" action="">
                <input type="hidden" name="resetUserid" value="<?php echo $resetUserid; ?>">
                <input type="hidden" name="resetRole" value="<?php echo $resetRole; ?>">
                
                <input name="newPassword" type="password" placeholder="New Password">
                <input name="confirmNewPassword" type="password" placeholder="Confirm New Password">
                
                <button type="submit" name="resetPassword">Reset Password</button>
            </form>
        </div>

        <div class="form-links">
            <a href="?form=login">Back to Login</a>
        </div>
    </div>

    <script>
        function setUserType(type) {
            document.getElementById('userType').value = type;
            
            // Update UI
            document.querySelectorAll('.user-type-option').forEach(option => {
                option.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide relevant fields
            if (type === 'student') {
                document.querySelector('.student-fields').style.display = 'block';
                document.querySelector('.staff-only').style.display = 'none';
            } else {
                document.querySelector('.student-fields').style.display = 'none';
                document.querySelector('.staff-only').style.display = 'block';
            }
        }
        
        function resendOtp() {
            // Submit the form with requestOtp button
            const form = document.querySelector('#otpVerifyStep form');
            const requestOtpButton = document.createElement('input');
            requestOtpButton.type = 'hidden';
            requestOtpButton.name = 'requestOtp';
            form.appendChild(requestOtpButton);
            form.submit();
        }
        
        // Initialize based on current user type
        document.addEventListener('DOMContentLoaded', function() {
            const userType = document.getElementById('userType').value;
            if (userType === 'staff') {
                document.querySelector('.student-fields').style.display = 'none';
                document.querySelector('.staff-only').style.display = 'block';
            }
        });
    </script>
</body>
</html>