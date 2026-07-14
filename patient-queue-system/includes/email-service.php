<?php
declare(strict_types=1);

/**
 * OHAQRS - Email Notification Service
 * Handles all email notifications in the system
 */

class EmailNotificationService {
    private string $mailDriver;
    private string $fromAddress;
    private string $fromName;
    private array $mailConfig;

    public function __construct() {
        require_once __DIR__ . '/dotenv.php';
        
        $this->mailDriver = getenv('MAIL_DRIVER') ?: 'log';
        $this->fromAddress = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@ohaqrs.local';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'OHAQRS Hospital Queue System';

        $this->mailConfig = [
            'host' => getenv('MAIL_HOST'),
            'port' => (int)(getenv('MAIL_PORT') ?: 465),
            'username' => getenv('MAIL_USERNAME'),
            'password' => getenv('MAIL_PASSWORD'),
        ];
    }

    /**
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmation(array $appointment, array $patient, array $doctor, array $department): bool {
        $subject = 'Appointment Confirmation - ' . $appointment['ticket_code'];
        
        $html = $this->renderTemplate('appointment-confirmation', [
            'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'doctor_name' => $doctor['first_name'] . ' ' . $doctor['last_name'],
            'department' => $department['department_name'],
            'date' => date('F d, Y', strtotime($appointment['scheduled_slot_at'])),
            'time' => date('H:i A', strtotime($appointment['scheduled_slot_at'])),
            'ticket_code' => $appointment['ticket_code'],
            'room_number' => $doctor['room_number'] ?? 'TBD',
        ]);

        return $this->send(
            $patient['email'],
            $patient['first_name'],
            $subject,
            $html
        );
    }

    /**
     * Send appointment cancellation email
     */
    public function sendAppointmentCancellation(array $appointment, array $patient, string $reason = ''): bool {
        $subject = 'Appointment Cancelled - ' . $appointment['ticket_code'];
        
        $html = $this->renderTemplate('appointment-cancellation', [
            'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'ticket_code' => $appointment['ticket_code'],
            'reason' => $reason ?: 'Your appointment has been cancelled.',
        ]);

        return $this->send(
            $patient['email'],
            $patient['first_name'],
            $subject,
            $html
        );
    }

    /**
     * Send queue reminder email
     */
    public function sendQueueReminder(array $patient, array $appointment, int $positionInQueue): bool {
        $subject = 'Queue Update - You are #' . $positionInQueue . ' in line';
        
        $html = $this->renderTemplate('queue-reminder', [
            'patient_name' => $patient['first_name'],
            'position' => $positionInQueue,
            'ticket_code' => $appointment['ticket_code'],
        ]);

        return $this->send(
            $patient['email'],
            $patient['first_name'],
            $subject,
            $html
        );
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $email, string $firstName, string $resetUrl): bool {
        $subject = 'Password Reset Request - OHAQRS';
        
        $html = $this->renderTemplate('password-reset', [
            'name' => $firstName,
            'reset_url' => $resetUrl,
            'expires_in' => '24 hours',
        ]);

        return $this->send($email, $firstName, $subject, $html);
    }

    /**
     * Send emergency triage notification to doctor
     */
    public function sendEmergencyNotification(array $doctor, array $patient, array $emergency): bool {
        $acuityLevel = $emergency['acuity_level'];
        $acuityText = match($acuityLevel) {
            1 => 'CRITICAL',
            2 => 'EMERGENCY',
            3 => 'URGENT',
            4 => 'MODERATE',
            5 => 'LOW',
            default => 'UNKNOWN'
        };

        $subject = "🚨 EMERGENCY ALERT - $acuityText - {$patient['first_name']} {$patient['last_name']}";
        
        $html = $this->renderTemplate('emergency-alert', [
            'doctor_name' => $doctor['first_name'],
            'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'acuity' => $acuityText,
            'symptoms' => $emergency['primary_symptom'],
            'triage_id' => $emergency['triage_id'],
        ]);

        return $this->send(
            $doctor['email'] ?? $doctor['user_email'],
            $doctor['first_name'],
            $subject,
            $html
        );
    }

    /**
     * Send 2FA code email
     */
    public function sendTwoFactorCode(string $email, string $name, string $code): bool {
        $subject = 'Two-Factor Authentication Code - OHAQRS';
        
        $html = $this->renderTemplate('two-factor-code', [
            'name' => $name,
            'code' => $code,
            'expires_in' => '10 minutes',
        ]);

        return $this->send($email, $name, $subject, $html);
    }

    /**
     * Send email
     */
    private function send(string $to, string $toName, string $subject, string $htmlBody): bool {
        if ($this->mailDriver === 'log') {
            return $this->logEmail($to, $subject, $htmlBody);
        }

        if ($this->mailDriver === 'smtp') {
            return $this->sendViaSMTP($to, $toName, $subject, $htmlBody);
        }

        if ($this->mailDriver === 'php') {
            return $this->sendViaPhpMail($to, $subject, $htmlBody);
        }

        return false;
    }

    /**
     * Send via SMTP (Mailtrap, SendGrid, etc.)
     */
    private function sendViaSMTP(string $to, string $toName, string $subject, string $htmlBody): bool {
        try {
            $headers = [
                'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
                'To: ' . $toName . ' <' . $to . '>',
                'Subject: ' . $subject,
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Reply-To: ' . $this->fromAddress,
            ];

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            // Create SMTP connection
            $socket = fsockopen(
                'ssl://' . $this->mailConfig['host'],
                $this->mailConfig['port'],
                $errno,
                $errstr,
                10,
                $context
            );

            if (!$socket) {
                throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
            }

            $response = fgets($socket);
            if (strpos($response, '220') === false) {
                throw new Exception("Invalid SMTP response: $response");
            }

            // Send credentials
            $this->smtpCommand($socket, 'AUTH LOGIN');
            $this->smtpCommand($socket, base64_encode($this->mailConfig['username']));
            $this->smtpCommand($socket, base64_encode($this->mailConfig['password']));

            // Send message
            $this->smtpCommand($socket, 'MAIL FROM:<' . $this->fromAddress . '>');
            $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>');
            $this->smtpCommand($socket, 'DATA');

            fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.\r\n");
            $response = fgets($socket);

            $this->smtpCommand($socket, 'QUIT');
            fclose($socket);

            return strpos($response, '250') !== false;

        } catch (Exception $e) {
            error_log('SMTP email error: ' . $e->getMessage());
            return $this->logEmail($to, $subject, $htmlBody);
        }
    }

    /**
     * Send via PHP mail function
     */
    private function sendViaPhpMail(string $to, string $subject, string $htmlBody): bool {
        $headers = [
            'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
            'Reply-To: ' . $this->fromAddress,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];

        return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    /**
     * Log email (fallback for development)
     */
    private function logEmail(string $to, string $subject, string $htmlBody): bool {
        $logPath = sys_get_temp_dir() . '/ohaqrs_emails.log';
        
        $entry = sprintf(
            "[%s] TO: %s | SUBJECT: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('=', 80),
            $htmlBody
        );

        return file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * SMTP command helper
     */
    private function smtpCommand($socket, string $command): string {
        fwrite($socket, $command . "\r\n");
        return fgets($socket);
    }

    /**
     * Render email template
     */
    private function renderTemplate(string $template, array $data): string {
        ob_start();
        
        extract($data);
        include __DIR__ . '/../templates/emails/' . $template . '.php';
        
        return ob_get_clean() ?: '';
    }
}
