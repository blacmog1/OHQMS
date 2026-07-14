<?php
declare(strict_types=1);

/**
 * OHAQRS SMS Service
 * Supports multiple SMS providers: Twilio, AWS SNS, generic SMTP-to-SMS gateways
 */

class SmsService {
    private string $provider;
    private ?string $apiKey;
    private ?string $apiSecret;
    private ?string $fromNumber;
    private ?string $awsRegion;
    private ?string $awsSnsTopicArn;

    public function __construct() {
        $this->provider = strtolower((string)(getenv('SMS_PROVIDER') ?: 'log'));
        $this->apiKey = getenv('SMS_API_KEY') ?: null;
        $this->apiSecret = getenv('SMS_API_SECRET') ?: null;
        $this->fromNumber = getenv('SMS_FROM_NUMBER') ?: null;
        $this->awsRegion = getenv('AWS_REGION') ?: null;
        $this->awsSnsTopicArn = getenv('AWS_SNS_TOPIC_ARN') ?: null;
    }

    /**
     * Send SMS message
     * @param string|array $recipients Phone number(s) in E.164 format (+254...)
     * @param string $message Message body (max 160 chars for GSM, 70 for UCS-2)
     * @return array Result array with success/failure counts
     */
    public function send(string|array $recipients, string $message): array {
        $recipients = (array) $recipients;
        $results = [];

        foreach ($recipients as $recipient) {
            $recipient = $this->normalizePhone($recipient);
            if (!$recipient) {
                $results[] = ['recipient' => $recipient, 'success' => false, 'error' => 'Invalid phone number'];
                continue;
            }

            try {
                switch ($this->provider) {
                    case 'twilio':
                        $result = $this->sendViaTwilio($recipient, $message);
                        break;
                    case 'aws_sns':
                        $result = $this->sendViaAwsSns($recipient, $message);
                        break;
                    case 'smtp_gateway':
                        $result = $this->sendViaSmtpGateway($recipient, $message);
                        break;
                    default:
                        $result = $this->sendViaLog($recipient, $message);
                }
                $results[] = $result;
            } catch (Throwable $e) {
                $results[] = ['recipient' => $recipient, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        return [
            'success' => $successCount > 0,
            'sent' => $successCount,
            'failed' => count($recipients) - $successCount,
            'details' => $results,
        ];
    }

    /**
     * Send appointment confirmation SMS
     */
    public function sendAppointmentConfirmation(string $phone, string $patientName, string $ticketCode, string $doctorName, string $scheduledAt): array {
        $message = "Hello {$patientName}, your appointment is confirmed. Ticket: {$ticketCode} with {$doctorName} at {$scheduledAt}. - OHAQRS";
        return $this->send($phone, $message);
    }

    /**
     * Send queue position update SMS
     */
    public function sendQueueUpdate(string $phone, string $ticketCode, int $position, int $estimatedWait): array {
        $message = "OHAQRS: Ticket {$ticketCode}, you are now #{$position}. Estimated wait: {$estimatedWait} mins. Please be ready.";
        return $this->send($phone, $message);
    }

    /**
     * Send "your turn" call SMS
     */
    public function sendYourTurnSms(string $phone, string $ticketCode, string $room, string $doctorName): array {
        $message = "OHAQRS: Ticket {$ticketCode}, it's your turn! Please proceed to {$room} to see {$doctorName}.";
        return $this->send($phone, $message);
    }

    /**
     * Send emergency notification to doctor
     */
    public function sendEmergencyAlert(string $phone, string $doctorName, int $acuityLevel, string $symptom): array {
        $message = "EMERGENCY ALERT: Acuity Level {$acuityLevel} - {$symptom}. Please respond immediately. - OHAQRS";
        return $this->send($phone, $message);
    }

    private function normalizePhone(string $phone): ?string {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone) < 10) return null;
        if (str_starts_with($phone, '0')) {
            $phone = '+254' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        return $phone;
    }

    private function sendViaTwilio(string $to, string $message): array {
        if (!$this->apiKey || !$this->apiSecret || !$this->fromNumber) {
            return ['recipient' => $to, 'success' => false, 'error' => 'Twilio credentials not configured'];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->apiKey}/Messages.json";
        $data = [
            'To' => $to,
            'From' => $this->fromNumber,
            'Body' => $message,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->apiKey}:{$this->apiSecret}");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['recipient' => $to, 'success' => true, 'provider' => 'twilio', 'response' => json_decode($response, true)];
        }

        return ['recipient' => $to, 'success' => false, 'error' => "Twilio error: {$response}", 'http_code' => $httpCode];
    }

    private function sendViaAwsSns(string $to, string $message): array {
        if (!$this->awsRegion) {
            return ['recipient' => $to, 'success' => false, 'error' => 'AWS region not configured'];
        }

        $payload = [
            'Message' => $message,
            'PhoneNumber' => $to,
            'MessageAttributes' => [
                'AWS.SNS.SMS.SMSType' => ['DataType' => 'String', 'StringValue' => 'Transactional'],
            ],
        ];

        $result = Aws\Sns\SnsClient::publish($payload);
        return ['recipient' => $to, 'success' => true, 'provider' => 'aws_sns', 'message_id' => $result['MessageId']];
    }

    private function sendViaSmtpGateway(string $to, string $message): array {
        $gateway = getenv('SMS_SMTP_GATEWAY') ?: '';
        if (!$gateway) {
            return ['recipient' => $to, 'success' => false, 'error' => 'SMTP gateway not configured'];
        }

        $gatewayAddress = str_replace(['+', '-'], '', $to) . $gateway;
        $headers = [
            'To: ' . $gatewayAddress,
            'From: ' . ($this->fromNumber ?: 'noreply@hospital.local'),
            'Subject: OHAQRS Notification',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = mail($gatewayAddress, 'OHAQRS Notification', $message, implode("\r\n", $headers));

        return ['recipient' => $to, 'success' => $sent, 'provider' => 'smtp_gateway'];
    }

    private function sendViaLog(string $to, string $message): array {
        error_log("OHAQRS SMS [{$to}]: {$message}");
        return ['recipient' => $to, 'success' => true, 'provider' => 'log', 'message' => $message];
    }
}
