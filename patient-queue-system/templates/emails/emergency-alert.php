<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #fff3cd; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { border-bottom: 3px solid #dc3545; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #dc3545; margin: 0; font-size: 28px; }
        .alert { background-color: #f8d7da; padding: 15px; border: 2px solid #dc3545; margin: 20px 0; border-radius: 4px; }
        .patient-info { background-color: #f9f9f9; padding: 15px; margin: 20px 0; }
        .content { line-height: 1.6; color: #333; }
        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 EMERGENCY ALERT 🚨</h1>
        </div>

        <div class="alert">
            <h2 style="color: #dc3545; margin-top: 0;">Acuity Level: <?php echo htmlspecialchars($acuity); ?></h2>
        </div>

        <div class="content">
            <p>Dr. <?php echo htmlspecialchars($doctor_name); ?>,</p>

            <p><strong>An emergency patient has arrived and requires immediate attention:</strong></p>

            <div class="patient-info">
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($patient_name); ?></p>
                <p><strong>Primary Symptom:</strong> <?php echo htmlspecialchars($symptoms); ?></p>
                <p><strong>Triage ID:</strong> <?php echo htmlspecialchars($triage_id); ?></p>
            </div>

            <p><strong>Action Required:</strong> Please respond to this emergency as soon as possible.</p>

            <p>Log in to the OHAQRS system for more details.</p>
        </div>

        <div class="footer">
            <p>This is an automated alert. Do not reply to this message.</p>
            <p>&copy; 2024 OHAQRS Hospital Queue System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>