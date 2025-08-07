<?php
session_start();
@include 'config.php';

if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists in database
    $select = "SELECT * FROM user_form WHERE email = ?";
    $stmt = mysqli_prepare($conn, $select);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Generate OTP
        require_once __DIR__ . '/vendor/autoload.php';
        $otp = rand(100000, 999999);
        
        // Store reset data in session
        $_SESSION['password_reset'] = [
            'email' => $email,
            'otp' => $otp,
            'otp_expires' => time() + 300 // 5 minutes
        ];
        
        // Send OTP email
        require_once 'send_reset_otp_email.php';
        send_reset_otp_email($email, $otp);
        
        $_SESSION['success'] = "OTP has been sent to your email address.";
        header("Location: reset_password.php");
        exit();
    } else {
        $_SESSION['error'] = "Email address not found in our records.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
            <h2 class="form-header">Forgot Password</h2>

            <form action="" method="post">
                <label for="email">Email Address</label>
                <input type="email" name="email" required placeholder="Enter your email address">

                <input type="submit" name="submit" value="Send Reset Link" class="form-btn">
            </form>

            <div class="form-links">
                <a href="login_form.php">Back to Login</a>
            </div>
        </div>
    </div>

    <div class="right-container">
        <img src="images/atty2.jpg" alt="Justice" class="right-blur-img">
        <div class="login-box">
            <h1><span class="typing-effect">Reset Your Password</span></h1>
        </div>
        <a href="login_form.php" class="login-btn">Back to Login</a>
    </div>

    <script>
        function closePopup() {
            document.querySelector('.error-popup').style.display = 'none';
        }
    </script>

    <script src="https://kit.fontawesome.com/cc86d7b31d.js" crossorigin="anonymous"></script>
</body>
</html> 