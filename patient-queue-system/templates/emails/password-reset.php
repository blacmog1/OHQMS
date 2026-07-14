<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { border-bottom: 3px solid #FF9800; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #333; margin: 0; }
        .content { line-height: 1.6; color: #555; }
        .button { display: inline-block; background-color: #FF9800; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .warning { background-color: #fff3e0; padding: 15px; border-left: 4px solid #FF9800; margin: 20px 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 OHAQRS Hospital Queue System</h1>
            <p style="margin: 10px 0 0 0; color: #666;">Password Reset Request</p>
        </div>

        <div class="content">
            <p>Hello <?php echo htmlspecialchars($name); ?>,</p>

            <p>We received a request to reset your password. If you did not make this request, you can safely ignore this email.</p>

            <p>To reset your password, click the button below:</p>

            <center>
                <a href="<?php echo htmlspecialchars($reset_url); ?>" class="button">Reset Password</a>
            </center>

            <p>Or copy and paste this link in your browser:<br>
            <code><?php echo htmlspecialchars($reset_url); ?></code></p>

            <div class="warning">
                <strong>⚠️ Important:</strong> This link will expire in <?php echo htmlspecialchars($expires_in); ?>. Do not share this link with anyone.
            </div>

            <p>If you did not request a password reset, please contact our support team immediately.</p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; 2024 OHAQRS Hospital Queue System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>