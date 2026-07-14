<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { border-bottom: 3px solid #9C27B0; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #333; margin: 0; }
        .code-box { background-color: #f5f5f5; padding: 20px; text-align: center; border: 2px dashed #9C27B0; margin: 20px 0; border-radius: 4px; }
        .code { font-size: 32px; letter-spacing: 5px; color: #9C27B0; font-weight: bold; font-family: monospace; }
        .warning { background-color: #f3e5f5; padding: 15px; border-left: 4px solid #9C27B0; margin: 20px 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 OHAQRS Hospital Queue System</h1>
            <p style="margin: 10px 0 0 0; color: #666;">Two-Factor Authentication</p>
        </div>

        <div>
            <p>Hello <?php echo htmlspecialchars($name); ?>,</p>

            <p>Your two-factor authentication code is:</p>

            <div class="code-box">
                <div class="code"><?php echo htmlspecialchars($code); ?></div>
            </div>

            <p>This code will expire in <strong><?php echo htmlspecialchars($expires_in); ?></strong>.</p>

            <div class="warning">
                <strong>⚠️ Security Notice:</strong> Never share this code with anyone. We will never ask for this code in an email.
            </div>

            <p>If you did not request this code, please ignore this email.</p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; 2024 OHAQRS Hospital Queue System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>