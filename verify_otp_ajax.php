<?php
session_start();
@include 'config.php';

header('Content-Type: application/json');

if (isset($_POST['verify_otp']) && isset($_POST['otp'])) {
    $input_otp = $_POST['otp'];
    $pending = $_SESSION['password_reset'] ?? null;
    
    if (!$pending) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please request a new OTP.']);
        exit();
    }
    
    if (time() > $pending['otp_expires']) {
        unset($_SESSION['password_reset']);
        echo json_encode(['success' => false, 'error' => 'OTP expired. Please request a new one.']);
        exit();
    }
    
    if ($input_otp == $pending['otp']) {
        // OTP is correct, allow password reset
        $_SESSION['otp_verified'] = true;
        echo json_encode(['success' => true, 'message' => 'OTP verified successfully!', 'step' => 'password_reset']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP. Please check your email and try again.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
}
?> 