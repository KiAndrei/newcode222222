<?php
session_start();
@include 'config.php';

$show_otp_modal = false;
$error = '';
$success = '';
$pending = $_SESSION['password_reset'] ?? null;

// Check if user has a valid password reset session
if (!$pending) {
    header("Location: forgot_password.php");
    exit();
}

// OTP verification and password reset logic
if (isset($_POST['verify_otp'])) {
    $input_otp = $_POST['otp'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (time() > $pending['otp_expires']) {
        $_SESSION['error'] = 'OTP expired. Please request a new one.';
        unset($_SESSION['password_reset']);
        header("Location: forgot_password.php");
        exit();
    } elseif ($input_otp == $pending['otp']) {
        // Validate new password
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_@#*%])[A-Za-z\d_@#*%]{8,}$/', $new_password)) {
            $error = "Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one special character (_ @ # * %).";
            $show_otp_modal = true;
        } elseif ($new_password != $confirm_password) {
            $error = "Passwords do not match!";
            $show_otp_modal = true;
        } else {
            // Update password in database
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE user_form SET password = ? WHERE email = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $pending['email']);
            
            if (mysqli_stmt_execute($stmt)) {
                unset($_SESSION['password_reset']);
                $_SESSION['success'] = "Password reset successful! You can now login with your new password.";
                header("Location: login_form.php");
                exit();
            } else {
                $error = 'Password reset failed. Please try again.';
                $show_otp_modal = true;
            }
        }
    } else {
        $error = 'Invalid OTP. Please check your email and try again.';
        $show_otp_modal = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
            font-size: 28px;
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
            justify-content: center;
            margin-top: 20px;
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
        .login-box {
            position: relative;
            z-index: 1;
        }
        .login-btn {
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

        .login-box h1 {
            font-size: 56px;
            font-weight: 600;
            color: #800000;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .login-btn {
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

        .login-btn:hover {
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

            .login-box h1 {
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

            .login-box h1 {
                font-size: 24px;
            }

            .login-btn {
                padding: 12px 25px;
                font-size: 15px;
            }
        }

        .blurred > *:not(.otp-modal) {
            filter: blur(4px) !important;
            pointer-events: none;
            user-select: none;
        }

        /* Modal Styles */
        .otp-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
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
            max-width: 500px;
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

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c33;
        }

        .success-message {
            background: #efe;
            color: #363;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #363;
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
<body<?php if ($show_otp_modal || $pending) echo ' class="blurred"'; ?>>
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
            <h2 class="form-header">Reset Password</h2>
            
            <div style="color: #fff0f0; text-align: center; margin-bottom: 20px; font-size: 14px;">
                OTP sent to: <strong><?php echo htmlspecialchars($pending['email']); ?></strong>
            </div>

            <form action="" method="post">
                <label for="otp">OTP Code</label>
                <input type="text" name="otp" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit OTP" required>

                <label for="new_password">New Password</label>
                <div class="password-container">
                    <input type="password" name="new_password" id="new_password" required placeholder="Enter new password">
                    <i class="fas fa-eye" id="toggleNewPassword"></i>
                </div>
                <ul style="color:#fff; font-size:12px; margin-bottom:8px; margin-top:2px; padding-left:18px;">
                    <li>Password requirements:</li>
                    <li>At least 8 characters</li>
                    <li>Must include uppercase and lowercase letters</li>
                    <li>Must include at least one number</li>
                    <li>Must include at least one special character (_ @ # * %)</li>
                </ul>

                <label for="confirm_password">Confirm New Password</label>
                <div class="password-container">
                    <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
                    <i class="fas fa-eye" id="toggleConfirmPassword"></i>
                </div>

                <input type="submit" name="verify_otp" value="Reset Password" class="form-btn">
            </form>

            <div class="form-links">
                <a href="login_form.php">Back to Login</a>
            </div>
        </div>
    </div>

    <div class="right-container">
        <img src="images/atty2.jpg" alt="Justice" class="right-blur-img">
        <div class="login-box">
            <h1><span class="typing-effect">Set New Password</span></h1>
        </div>
        <a href="login_form.php" class="login-btn">Back to Login</a>
    </div>

    <?php if ($show_otp_modal || $pending): ?>
    <div class="otp-modal" id="otpModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            
            <div class="modal-header">
                <h2>Reset Your Password</h2>
                <p>Enter the 6-digit OTP sent to <strong><?= htmlspecialchars($pending['email']) ?></strong></p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="modal-form">
                <div class="form-group">
                    <label for="modal_otp">OTP Code</label>
                    <input type="text" name="otp" id="modal_otp" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit OTP" required autofocus>
                </div>

                <div class="form-group">
                    <label for="modal_new_password">New Password</label>
                    <div class="password-field">
                        <input type="password" name="new_password" id="modal_new_password" placeholder="Enter new password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('modal_new_password', this)">
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
                    <label for="modal_confirm_password">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" name="confirm_password" id="modal_confirm_password" placeholder="Confirm new password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('modal_confirm_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="verify_otp" class="modal-btn">
                    Reset Password
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        document.getElementById('toggleNewPassword').addEventListener('click', function () {
            let passwordField = document.getElementById('new_password');
            let icon = this;
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            let passwordField = document.getElementById('confirm_password');
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

        function closeModal() {
            window.location.href = 'forgot_password.php';
        }

        function togglePassword(fieldId, button) {
            let passwordField = document.getElementById(fieldId);
            let icon = button.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Password validation (client-side)
        document.querySelector('form').addEventListener('submit', function(e) {
            var newPass = document.getElementById('new_password').value;
            var confirmPass = document.getElementById('confirm_password').value;
            var requirements = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_@#*%])[A-Za-z\d_@#*%]{8,}$/;
            if (!requirements.test(newPass)) {
                alert('Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one special character (_ @ # * %).');
                e.preventDefault();
                return false;
            }
            if (newPass !== confirmPass) {
                alert('Confirm password does not match the new password.');
                e.preventDefault();
                return false;
            }
        });

        // Modal form validation
        document.querySelector('.modal-form').addEventListener('submit', function(e) {
            var newPass = document.getElementById('modal_new_password').value;
            var confirmPass = document.getElementById('modal_confirm_password').value;
            var requirements = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_@#*%])[A-Za-z\d_@#*%]{8,}$/;
            if (!requirements.test(newPass)) {
                alert('Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one special character (_ @ # * %).');
                e.preventDefault();
                return false;
            }
            if (newPass !== confirmPass) {
                alert('Confirm password does not match the new password.');
                e.preventDefault();
                return false;
            }
        });
    </script>

    <script src="https://kit.fontawesome.com/cc86d7b31d.js" crossorigin="anonymous"></script>
</body>
</html> 