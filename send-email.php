<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$config = [
    'admin_email' => 'vishwapranith595@gmail.com', // CHANGE THIS to your email
    'site_name' => 'S.M. Vishwa Praneeth Portfolio', // CHANGE THIS
    'max_message_length' => 500,
    'required_fields' => ['name', 'email', 'subject', 'message']
];

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'errors' => []
];

try {
    // Check if form was submitted
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    // Validate required fields
    foreach ($config['required_fields'] as $field) {
        if (empty($$field)) {
            $response['errors'][$field] = ucfirst($field) . ' is required';
        }
    }

    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['errors']['email'] = 'Invalid email format';
    }

    // Validate message length
    if (!empty($message) && strlen($message) > $config['max_message_length']) {
        $response['errors']['message'] = 'Message is too long';
    }

    // If there are validation errors, return them
    if (!empty($response['errors'])) {
        $response['message'] = 'Please correct the errors below';
        echo json_encode($response);
        exit;
    }

    // Prepare email content
    $to = $config['admin_email'];
    $email_subject = "Portfolio Contact: " . $subject;

    $email_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #555; }
            .value { color: #333; margin-top: 5px; padding: 10px; background: white; border-radius: 5px; border-left: 4px solid #667eea; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Contact Form Submission</h2>
                <p>{$config['site_name']}</p>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='label'>Date & Time:</div>
                    <div class='value'>" . date('F j, Y, g:i a') . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Name:</div>
                    <div class='value'>{$name}</div>
                </div>
                <div class='field'>
                    <div class='label'>Email:</div>
                    <div class='value'>{$email}</div>
                </div>
                <div class='field'>
                    <div class='label'>Phone:</div>
                    <div class='value'>" . ($phone ?: 'Not provided') . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Subject:</div>
                    <div class='value'>{$subject}</div>
                </div>
                <div class='field'>
                    <div class='label'>Message:</div>
                    <div class='value'>{$message}</div>
                </div>
            </div>
            <div class='footer'>
                <p>This email was sent from the contact form on {$config['site_name']}</p>
                <p>IP Address: {$_SERVER['REMOTE_ADDR']} | User Agent: {$_SERVER['HTTP_USER_AGENT']}</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['site_name']} <noreply@{$_SERVER['HTTP_HOST']}>\r\n";
    $headers .= "Reply-To: {$name} <{$email}>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Send email
    if (mail($to, $email_subject, $email_body, $headers)) {
        $response['success'] = true;
        $response['message'] = 'Thank you! Your message has been sent successfully. We\'ll get back to you soon.';

        // Optional: Send auto-reply to user
        sendAutoReply($name, $email, $config);

        // Optional: Log the submission
        logSubmission($name, $email, $phone, $subject, $message);

    } else {
        throw new Exception('Failed to send email. Please try again later.');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

// Helper functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function sendAutoReply($name, $email, $config) {
    $auto_subject = "Thank you for contacting " . $config['site_name'];
    $auto_message = "
    <html>
    <body>
        <h2>Thank you for contacting us!</h2>
        <p>Dear {$name},</p>
        <p>We have received your message and will get back to you within 24-48 hours.</p>
        <p>Your inquiry is important to us.</p>
        <br>
        <p>Best regards,<br>{$config['site_name']} Team</p>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['site_name']} <{$config['admin_email']}>\r\n";

    @mail($email, $auto_subject, $auto_message, $headers);
}

function logSubmission($name, $email, $phone, $subject, $message) {
    $log_file = 'contact_log.csv';
    $log_data = [
        date('Y-m-d H:i:s'),
        $name,
        $email,
        $phone ?: 'N/A',
        $subject,
        substr($message, 0, 100) . '...',
        $_SERVER['REMOTE_ADDR']
    ];

    if (!file_exists($log_file)) {
        $headers = ['Date', 'Name', 'Email', 'Phone', 'Subject', 'Message Preview', 'IP Address'];
        file_put_contents($log_file, implode(',', $headers) . "\n");
    }

    $fp = fopen($log_file, 'a');
    fputcsv($fp, $log_data);
    fclose($fp);
}
?>