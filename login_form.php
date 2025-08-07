<?php
session_start();
@include 'config.php';

if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass = $_POST['password'];

    // Check database connection
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // Prepare the query to avoid SQL injection
    $select = "SELECT * FROM user_form WHERE email = ?";
    $stmt = mysqli_prepare($conn, $select);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Check if account is locked
        if ($row['account_locked'] == 1) {
            // Check if lockout period has expired
            if (strtotime($row['lockout_until']) > time()) {
                $remaining_minutes = ceil((strtotime($row['lockout_until']) - time()) / 60);
                $remaining_seconds = (strtotime($row['lockout_until']) - time()) % 60;
                
                if ($remaining_minutes > 0) {
                    $_SESSION['error'] = "Account is locked. Please try again in {$remaining_minutes} minutes and {$remaining_seconds} seconds.";
                } else {
                    $_SESSION['error'] = "Account is locked. Please try again in {$remaining_seconds} seconds.";
                }
                header("Location: login_form.php");
                exit();
            } else {
                // Reset lockout if time has expired
                $reset_query = "UPDATE user_form SET account_locked = 0, login_attempts = 0, lockout_until = NULL WHERE email = ?";
                $reset_stmt = mysqli_prepare($conn, $reset_query);
                mysqli_stmt_bind_param($reset_stmt, "s", $email);
                mysqli_stmt_execute($reset_stmt);
            }
        }

        if (password_verify($pass, $row['password'])) {
            // Reset login attempts on successful login
            $reset_attempts = "UPDATE user_form SET login_attempts = 0, last_failed_login = NULL, last_login = NOW() WHERE email = ?";
            $reset_stmt = mysqli_prepare($conn, $reset_attempts);
            mysqli_stmt_bind_param($reset_stmt, "s", $email);
            mysqli_stmt_execute($reset_stmt);

            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_type'] = $row['user_type'];

            if ($row['user_type'] == 'admin') {
                $_SESSION['admin_name'] = $row['name'];
                header('Location: admin_dashboard.php');
                exit();
            } elseif ($row['user_type'] == 'attorney') {
                $_SESSION['attorney_name'] = $row['name'];
                header('Location: attorney_dashboard.php');
                exit();
            } else {
                $_SESSION['client_name'] = $row['name'];
                header('Location: client_dashboard.php');
                exit();
            }
        } else {
            // Increment failed login attempts
            $attempts = $row['login_attempts'] + 1;
            $lockout_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            if ($attempts >= 4) {
                // Lock the account
                $lock_query = "UPDATE user_form SET login_attempts = ?, last_failed_login = NOW(), account_locked = 1, lockout_until = ? WHERE email = ?";
                $lock_stmt = mysqli_prepare($conn, $lock_query);
                mysqli_stmt_bind_param($lock_stmt, "iss", $attempts, $lockout_time, $email);
                mysqli_stmt_execute($lock_stmt);
                
                $_SESSION['error'] = "Account locked due to multiple failed attempts. Please try again in 1 hour (60 minutes).";
            } else {
                // Update failed attempts
                $update_query = "UPDATE user_form SET login_attempts = ?, last_failed_login = NOW() WHERE email = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "is", $attempts, $email);
                mysqli_stmt_execute($update_stmt);
                
                $remaining_attempts = 4 - $attempts;
                $_SESSION['error'] = "Incorrect email or password! {$remaining_attempts} attempts remaining before account lockout.";
            }
            
            header("Location: login_form.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Account does not exist!";
        header("Location: login_form.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background: #f5f5f5;
        }

        .left-container {
            width: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,rgb(124, 13, 13),rgb(53, 20, 20));
            padding: 50px;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .title-container {
            display: flex;
            align-items: center;
            position: absolute;
            top: 20px;
            left: 30px;
        }

        .title-container img {
            width: 40px;
            height: 40px;
            margin-right: 8px;
        }

        .title {
            font-size: 24px;
            font-weight: 600;
            color: #fff0f0;
            letter-spacing: 1px;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
        }

        .header-container img {
            width: 50px;
            height: 50px;
        }

        .law-office-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            color: #fff0f0;
            font-family: 'Roboto Slab', serif;
            letter-spacing: 1px;
        }

        .form-header {
            font-size: 28px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
            color: #fff0f0;
        }

        .form-container {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        .form-container label {
            font-size: 14px;
            font-weight: 500;
            display: block;
            margin: 15px 0 5px;
            color: #fff0f0;
            text-align: left;
        }

        .form-container input {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: none;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            background: transparent;
            color: #fff0f0;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-container input:focus {
            border-bottom: 2px solid #fff0f0;
        }

        .form-container input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-container i:hover {
            color: #fff0f0;
        }

        .form-container .form-btn {
            background: linear-gradient(90deg, #800000 60%, #a94442 100%);
            color: #fff0f0;
            border: none;
            cursor: pointer;
            padding: 14px;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            margin-top: 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .form-container .form-btn:hover {
            background: linear-gradient(90deg, #a94442 60%, #800000 100%);
            color: #fff;
            transform: translateY(-2px);
        }

        .form-links {
            display: flex;
            justify-content: flex-start;
            margin-top: 10px;
        }

        .form-links a {
            font-size: 14px;
            text-decoration: none;
            color: #fff0f0;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-links a:hover {
            color: #b22222;
        }

        .right-container {
            width: 55%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #2c3e50;
            text-align: center;
            padding: 50px;
            background: #ffffff;
            overflow: hidden;
        }
        .right-blur-img {
            position: absolute;
            right: 0;
            top: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: blur(2px) brightness(0.7);
            z-index: 0;
        }
        .register-box {
            position: relative;
            z-index: 1;
        }
        .register-btn {
            position: relative;
            z-index: 1;
        }
        .typing-effect {
            display: inline-block;
            border-right: 2px solid #800000;
            white-space: nowrap;
            overflow: hidden;
            animation: typing 2.5s steps(30, end), blink-caret 0.75s step-end infinite;
        }
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: #800000; }
        }

        .error-popup {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff6b6b;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            z-index: 1000;
            width: 90%;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translate(-50%, -20px);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }

        .error-popup p {
            margin: 0;
            font-size: 14px;
        }

        .error-popup button {
            background: white;
            border: none;
            padding: 8px 15px;
            color: #ff6b6b;
            font-weight: 500;
            margin-top: 10px;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .error-popup button:hover {
            background: #f0f0f0;
        }

        .register-box h1 {
            font-size: 42px;
            font-weight: 600;
            color: #800000;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .register-btn {
            display: inline-block;
            background: linear-gradient(90deg, #800000 60%, #a94442 100%);
            color: #fff0f0;
            text-decoration: none;
            padding: 14px 30px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .register-btn:hover {
            background: linear-gradient(90deg, #a94442 60%, #800000 100%);
            color: #fff;
            transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .left-container {
                width: 50%;
            }

            .right-container {
                width: 50%;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .left-container, .right-container {
                width: 100%;
                padding: 40px 20px;
            }

            .form-header {
                font-size: 24px;
            }

            .register-box h1 {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .title {
                font-size: 20px;
            }

            .form-header {
                font-size: 22px;
            }

            .form-container input {
                font-size: 14px;
                padding: 10px 12px;
            }

            .form-container .form-btn {
                padding: 12px;
                font-size: 15px;
            }

            .register-box h1 {
                font-size: 24px;
            }

            .register-btn {
                padding: 12px 25px;
                font-size: 15px;
            }
        }

        /* Modal Styles */
        .blurred > *:not(.modal) {
            filter: blur(4px) !important;
            pointer-events: none;
            user-select: none;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 450px;
            width: 90%;
            position: relative;
            animation: modalSlideIn 0.4s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .modal-header h2 {
            color: #800000;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .modal-header p {
            color: #666;
            font-size: 16px;
            margin: 0;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #800000;
            background: white;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #800000;
        }

        .password-requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .password-requirements h4 {
            color: #333;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #666;
            font-size: 12px;
            line-height: 1.4;
        }

        .password-requirements li {
            margin-bottom: 4px;
        }

        .modal-btn {
            background: linear-gradient(135deg, #800000, #a94442);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(128, 0, 0, 0.3);
        }

        .modal-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #800000;
        }

        @media (max-width: 768px) {
            .modal-content {
                padding: 30px 20px;
                margin: 20px;
            }

            .modal-header h2 {
                font-size: 24px;
            }

            .modal-header p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-popup">
            <p><?php echo $_SESSION['error']; ?></p>
            <button onclick="closePopup()">OK</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="error-popup" style="background: #28a745;">
            <p><?php echo $_SESSION['success']; ?></p>
            <button onclick="closePopup()">OK</button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="left-container">
        <div class="title-container">
            <img src="images/logo.jpg" alt="Logo">
            <div class="title">LawFirm.</div>
        </div>

        <div class="header-container">
            <h1 class="law-office-title">Opiña Law<br>Office</h1>
            <img src="images/justice.png" alt="Attorney Icon">
        </div>

        <div class="form-container">
            <h2 class="form-header">Login</h2>

            <form action="" method="post">
                <label for="email">Email</label>
                <input type="email" name="email" required placeholder="Enter your email">

                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" name="password" id="password" required placeholder="Enter your password">
                    <i class="fas fa-eye" id="togglePassword"></i>
                </div>

                <div class="form-links">
                    <a href="#" onclick="showForgotPasswordModal()">Forgot Password?</a>
                </div>

                <input type="submit" name="submit" value="Login" class="form-btn">
            </form>
        </div>
    </div>

    <div class="right-container">
        <img src="images/atty2.jpg" alt="Justice" class="right-blur-img">
        <div class="register-box">
            <h1><span class="typing-effect">Don't have an account?</span></h1>
        </div>
        <a href="register_form.php" class="register-btn">Register Now</a>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            let passwordField = document.getElementById('password');
            let icon = this;
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        function closePopup() {
            document.querySelector('.error-popup').style.display = 'none';
        }

        function showForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'flex';
            document.body.classList.add('blurred');
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
            document.body.classList.remove('blurred');
        }

        function submitForgotPassword() {
            const email = document.getElementById('forgotEmail').value;
            if (!email) {
                alert('Please enter your email address');
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('email', email);
            formData.append('submit', 'true');

            // Send AJAX request
            fetch('forgot_password_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('OTP has been sent to your email address.');
                    showOtpVerificationModal();
                } else {
                    alert(data.error || 'An error occurred. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function showOtpVerificationModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
            document.getElementById('otpVerificationModal').style.display = 'flex';
        }

        function closeOtpVerificationModal() {
            document.getElementById('otpVerificationModal').style.display = 'none';
            document.body.classList.remove('blurred');
        }

        function verifyOtp() {
            const otp = document.getElementById('otpInput').value;
            if (!otp) {
                alert('Please enter the OTP');
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('otp', otp);
            formData.append('verify_otp', 'true');

            // Send AJAX request
            fetch('verify_otp_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('OTP verified successfully!');
                    showPasswordResetModal();
                } else {
                    alert(data.error || 'An error occurred. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function showPasswordResetModal() {
            document.getElementById('otpVerificationModal').style.display = 'none';
            document.getElementById('passwordResetModal').style.display = 'flex';
        }

        function closePasswordResetModal() {
            document.getElementById('passwordResetModal').style.display = 'none';
            document.body.classList.remove('blurred');
        }

        function resetPassword() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!newPassword || !confirmPassword) {
                alert('Please fill in all fields');
                return;
            }

            // Validate password requirements
            const requirements = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_@#*%])[A-Za-z\d_@#*%]{8,}$/;
            if (!requirements.test(newPassword)) {
                alert('Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one special character (_ @ # * %).');
                return;
            }

            if (newPassword !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            formData.append('reset_password', 'true');

            // Send AJAX request
            fetch('reset_password_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Password reset successful! You can now login with your new password.');
                    closePasswordResetModal();
                    window.location.reload();
                } else {
                    alert(data.error || 'An error occurred. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function togglePasswordVisibility(fieldId, button) {
            const field = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>

    <!-- Forgot Password Modal -->
    <div class="modal" id="forgotPasswordModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeForgotPasswordModal()">&times;</button>
            
            <div class="modal-header">
                <h2>Forgot Password</h2>
                <p>Enter your email address to receive a password reset OTP</p>
            </div>

            <div class="modal-form">
                <div class="form-group">
                    <label for="forgotEmail">Email Address</label>
                    <input type="email" id="forgotEmail" placeholder="Enter your email address" required>
                </div>

                <button type="button" class="modal-btn" onclick="submitForgotPassword()">
                    Send Reset Link
                </button>
            </div>
        </div>
    </div>

    <!-- OTP Verification Modal -->
    <div class="modal" id="otpVerificationModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeOtpVerificationModal()">&times;</button>
            
            <div class="modal-header">
                <h2>Verify OTP</h2>
                <p>Enter the 6-digit OTP sent to your email address</p>
            </div>

            <div class="modal-form">
                <div class="form-group">
                    <label for="otpInput">OTP Code</label>
                    <input type="text" id="otpInput" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit OTP" required>
                </div>

                <button type="button" class="modal-btn" onclick="verifyOtp()">
                    Verify OTP
                </button>
            </div>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div class="modal" id="passwordResetModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closePasswordResetModal()">&times;</button>
            
            <div class="modal-header">
                <h2>Reset Password</h2>
                <p>Enter your new password</p>
            </div>

            <div class="modal-form">
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <div class="password-field">
                        <input type="password" id="newPassword" placeholder="Enter new password" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li>At least 8 characters</li>
                            <li>Must include uppercase and lowercase letters</li>
                            <li>Must include at least one number</li>
                            <li>Must include at least one special character (_ @ # * %)</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" id="confirmPassword" placeholder="Confirm new password" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="button" class="modal-btn" onclick="resetPassword()">
                    Reset Password
                </button>
            </div>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/cc86d7b31d.js" crossorigin="anonymous"></script>
</body>
</html>
