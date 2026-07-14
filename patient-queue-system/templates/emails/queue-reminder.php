<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { border-bottom: 3px solid #2196F3; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #333; margin: 0; }
        .content { line-height: 1.6; color: #555; }
        .queue-info { background-color: #e3f2fd; padding: 20px; border-left: 4px solid #2196F3; margin: 20px 0; text-align: center; }
        .position { font-size: 48px; color: #2196F3; font-weight: bold; margin: 10px 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 OHAQRS Hospital Queue System</h1>
            <p style="margin: 10px 0 0 0; color: #666;">Queue Update</p>
        </div>

        <div class="content">
            <p>Hello <?php echo htmlspecialchars($patient_name); ?>,</p>

            <p>Here's an update on your position in the queue:</p>

            <div class="queue-info">
                <p>Your Ticket: <?php echo htmlspecialchars($ticket_code); ?></p>
                <div class="position">#<?php echo (int)$position; ?></div>
                <p>You are <strong><?php echo (int)$position; ?></strong> in line</p>
            </div>

            <p>Please continue to monitor your queue status through the OHAQRS patient portal. We will notify you when you are called.</p>

            <p>Thank you for your patience!</p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; 2024 OHAQRS Hospital Queue System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>