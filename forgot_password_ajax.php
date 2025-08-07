<?php
session_start();
@include 'config.php';

header('Content-Type: application/json');

if (isset($_POST['submit']) && isset($_POST['email'])) {
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
        
        echo json_encode(['success' => true, 'message' => 'OTP has been sent to your email address.', 'step' => 'otp_verification']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Email address not found in our records.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
}
?> 