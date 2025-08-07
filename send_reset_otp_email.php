<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'config.php';

function send_reset_otp_email($to, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - Opiña Law Office';
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #800000, #a94442); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                <h2 style="margin: 0;">Opiña Law Office</h2>
                <p style="margin: 10px 0 0 0;">Password Reset Request</p>
            </div>
            <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
                <h3 style="color: #800000; margin-bottom: 20px;">Your Password Reset OTP</h3>
                <p style="color: #333; margin-bottom: 20px;">You have requested to reset your password. Please use the following OTP code to complete the process:</p>
                <div style="background: #800000; color: white; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <h1 style="margin: 0; font-size: 32px; letter-spacing: 5px;">' . $otp . '</h1>
                </div>
                <p style="color: #666; font-size: 14px; margin-top: 20px;">
                    <strong>Important:</strong><br>
                    • This OTP will expire in 5 minutes<br>
                    • Do not share this code with anyone<br>
                    • If you did not request this reset, please ignore this email
                </p>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <p style="color: #666; font-size: 12px; margin: 0;">
                        This is an automated message from Opiña Law Office. Please do not reply to this email.
                    </p>
                </div>
            </div>
        </div>';
        $mail->send();
    } catch (Exception $e) {
        // For debugging: echo 'Mailer Error: ' . $mail->ErrorInfo;
    }
} 