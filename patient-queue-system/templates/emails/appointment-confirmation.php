<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { border-bottom: 3px solid #4CAF50; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #333; margin: 0; }
        .content { line-height: 1.6; color: #555; }
        .details { background-color: #f9f9f9; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0; }
        .button { display: inline-block; background-color: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 OHAQRS Hospital Queue System</h1>
            <p style="margin: 10px 0 0 0; color: #666;">Appointment Confirmation</p>
        </div>

        <div class="content">
            <p>Dear <?php echo htmlspecialchars($patient_name); ?>,</p>

            <p>Your appointment has been successfully booked. Here are your appointment details:</p>

            <div class="details">
                <strong>Appointment Details:</strong><br><br>
                <strong>Ticket Code:</strong> <?php echo htmlspecialchars($ticket_code); ?><br>
                <strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($doctor_name); ?><br>
                <strong>Department:</strong> <?php echo htmlspecialchars($department); ?><br>
                <strong>Date:</strong> <?php echo htmlspecialchars($date); ?><br>
                <strong>Time:</strong> <?php echo htmlspecialchars($time); ?><br>
                <strong>Room Number:</strong> <?php echo htmlspecialchars($room_number); ?>
            </div>

            <p><strong>What to do next:</strong></p>
            <ul>
                <li>Arrive at least 10 minutes before your appointment time</li>
                <li>Bring your ticket code with you</li>
                <li>Keep this email for your records</li>
            </ul>

            <p>If you need to cancel or reschedule your appointment, please log in to your OHAQRS account or contact the reception desk at least 24 hours before your appointment.</p>

            <p>Thank you for choosing our hospital!</p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; 2024 OHAQRS Hospital Queue System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>