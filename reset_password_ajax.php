<?php
session_start();
@include 'config.php';

header('Content-Type: application/json');

if (isset($_POST['reset_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $pending = $_SESSION['password_reset'] ?? null;
    
    // Check if OTP was verified
    if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
        echo json_encode(['success' => false, 'error' => 'Please verify OTP first.']);
        exit();
    }
    
    if (!$pending) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please start over.']);
        exit();
    }
    
    // Validate new password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_@#*%])[A-Za-z\d_@#*%]{8,}$/', $new_password)) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one special character (_ @ # * %).']);
        exit();
    }
    
    if ($new_password != $confirm_password) {
        echo json_encode(['success' => false, 'error' => 'Passwords do not match!']);
        exit();
    }
    
    // Update password in database
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_query = "UPDATE user_form SET password = ? WHERE email = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $pending['email']);
    
    if (mysqli_stmt_execute($stmt)) {
        // Clear all reset sessions
        unset($_SESSION['password_reset']);
        unset($_SESSION['otp_verified']);
        echo json_encode(['success' => true, 'message' => 'Password reset successful! You can now login with your new password.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Password reset failed. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
}
?> 