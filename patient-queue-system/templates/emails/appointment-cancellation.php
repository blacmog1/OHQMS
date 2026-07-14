<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { border-bottom: 3px solid #f44336; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #333; margin: 0; }
        .content { line-height: 1.6; color: #555; }
        .alert { background-color: #ffebee; padding: 15px; border-left: 4px solid #f44336; margin: 20px 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 OHAQRS Hospital Queue System</h1>
            <p style="margin: 10px 0 0 0; color: #666;">Appointment Cancellation</p>
        </div>

        <div class="content">
            <p>Dear <?php echo htmlspecialchars($patient_name); ?>,</p>

            <div class="alert">
                <strong>Your appointment (<?php echo htmlspecialchars($ticket_code); ?>) has been cancelled.</strong>
            </div>

            <p><strong>Reason:</strong></p>
            <p><?php echo htmlspecialchars($reason); ?></p>

            <p>If you would like to book another appointment, please log in to your OHAQRS account and select a new time slot.</p>

            <p>If you have any questions, please contact the reception desk.</p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; 2024 OHAQRS Hospital Queue System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>