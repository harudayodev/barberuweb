<?php
// send_email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Added for SMTPSecure constant
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Sends the account acceptance email with login credentials.
 * * @param string $to Recipient email address.
 * @param string $business_name User's name or business name.
 * @param string $username User's login username (email).
 * @param string $password User's registered password.
 * @return bool True on success, false on failure.
 */
function send_account_email($to, $business_name, $username, $password) {

    // Use PHP's built-in mail() function
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'Your Application Has Been Approved!';
    $message = "Hello $business_name,\n\n" .
        "Congratulations! Your application has been accepted.\n" .
        "You can now log in to your admin account.\n\n" .
        "Username: $username\n" .
        "Password: $password\n\n" .
        "Please change your password after your first login for security.\n\n" .
        "Thank you!\nBarberu Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
    error_log('mail() failed for account approval email');
    return false;
    }
}

function other_shop_application($to, $business_name, $username) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'Another Barbershop Has Been Accepted!';
    $message = "Hello,\n\n" .
        "Your account ($username) has been linked to another barbershop: $business_name.\n" .
        "You can now manage multiple shops under your Barberu account.\n\n" .
        "Thank you!\nBarberu Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        error_log('mail() failed for other shop application email');
        return false;
    }
}

function send_rejection_email($to, $business_name) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'Your Application Has Been Rejected';
    $message = "Hello $business_name,\n\n" .
        "We regret to inform you that your application has been rejected.\n" .
        "If you have any questions or wish to reapply, please contact us.\n\n" .
        "Thank you for your interest in BarberU.\nBarberu Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
    error_log('mail() failed for rejection email');
    return false;
    }
}

function send_password_reset_email($to, $reset_link) {
    // Use PHP's built-in mail() function
    $subject = 'Password Reset Request';
    $message = "Hello,\n\nWe received a request to reset your password. Click the link below to set a new password:\n$reset_link\n\nIf you did not request this, you can ignore this email.\n\nBarberU Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        error_log('mail() failed for password reset email');
        return false;
    }
}

function send_employee_acceptance_email($to, $employee_name, $username, $password) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'Welcome to Barberu!';
    $message = "Hello $employee_name,\n\n" .
        "You have been added as an employee to Barberu.\n" .
        "You can now log in to your employee account.\n\n" .
        "Username: $username\n" .
        "Password: $password\n\n" .
        "Please change your password after your first login for security.\n\n" .
        "Thank you!\nBarberu Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        error_log('mail() failed for employee acceptance email');
        return false;
    }
}

function send_employee_acceptance_email_2($to, $employee_name) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'You Have Been Accepted as an Employee!';
    $message = "Hello $employee_name,\n\n" .
        "Congratulations! You have been accepted as an employee at Barberu.\n" .
        "You can now log in to your employee account using your existing credentials and\n
        come to the barnershop to talk to the barbershop owner for your schedule and commission arrangements.\n\n" .
        "Thank you!\nBarberu Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        error_log('mail() failed for employee acceptance notification (existing account)');
        return false;
    }
}

function send_employee_rejection_email($to, $employee_name) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'Your Employee Application Has Been Declined';
    $message = "Hello $employee_name,\n\n" .
        "We regret to inform you that your application to join Barberu as an employee has been declined.\n" .
        "If you have any questions or wish to reapply, please contact us.\n\n" .
        "Thank you for your interest in Barberu.\nBarberu Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        error_log('mail() failed for employee rejection email');
        return false;
    }
}

function send_password_change_notification($to, $business_name) {
    error_log('send_password_change_notification called with: to=' . $to . ', business_name=' . $business_name);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'Your Password Has Been Changed';
    $message = "Hello $business_name,\n\nThis is a notification that the password for your admin account has been changed.\nIf you did not make this change, please contact BarberU support immediately.\n\nThank you!\nBarberu Team";
    $headers = "From: barberucuts.site\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    error_log('mail() params: to=' . $to . ', subject=' . $subject . ', headers=' . $headers);
    $result = mail($to, $subject, $message, $headers);
    error_log('mail() result: ' . ($result ? 'success' : 'failure'));
    if ($result) {
        return true;
    } else {
        error_log('mail() failed for password change notification email');
        return false;
    }
}

function employee_update($to_email, $employee_name) {
    $subject = "Your Employee Record Has Been Updated";
    $message = '
        <html>
        <head>
            <title>Employee Record Updated</title>
        </head>
        <body style="font-family: Arial, sans-serif;">
            <h2>Hello ' . htmlspecialchars($employee_name) . ',</h2>
            <p>Your employee record has been updated by the administrator.</p>
            <p>If you have any questions, please contact your administrator.</p>
        </body>
        </html>
    ';
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    // Additional headers
    $headers .= 'From: noreply@barberu.com' . "\r\n";
    // Send email
    return mail($to_email, $subject, $message, $headers);
}

function send_sadmin_password_change_success($to, $name) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Invalid recipient email: ' . $to);
        return false;
    }
    $subject = 'Password Change Successful';
    $message = "Hello $name,\n\nYour password has been changed successfully.\nIf you did not make this change, please contact Barberu support immediately.\n\nThank you!\nBarberU Team";
    $headers = "From: Barberu Team <barberucuts.site>\r\n";
    $headers .= "Reply-To: barberucuts.site\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}
?>