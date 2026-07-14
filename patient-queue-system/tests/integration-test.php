<?php
/**
 * OHAQRS Integration Test Suite
 * Run: php tests/integration-test.php
 */

declare(strict_types=1);

$baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'ohaqrs_cookies');

function request(string $method, string $path, ?array $body = null, bool $useCookies = true): array
{
    global $baseUrl, $cookieJar;

    $ch = curl_init($baseUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_HEADER         => false,
    ]);

    if ($useCookies) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $code,
        'body'   => json_decode((string)$raw, true) ?? [],
        'raw'    => (string)$raw,
    ];
}

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    echo "  OK: $msg\n";
}

$passed = 0;
$failed = 0;

function runTest(string $name, callable $fn): void
{
    global $passed, $failed;
    echo "\n== $name ==\n";
    try {
        $fn();
        $passed++;
        echo "PASS: $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL: $name — " . $e->getMessage() . "\n";
    }
}

echo "OHAQRS Integration Tests\nBase URL: $baseUrl\n";

runTest('Seed demo accounts', function () {
    $res = request('GET', '/actions/seed.php', null, false);
    assertTrue($res['status'] === 200, 'seed returns 200');
    assertTrue(($res['body']['success'] ?? false) === true, 'seed success');
});

runTest('Patient login', function () {
    $res = request('POST', '/actions/login.php', [
        'email'    => 'patient@demo.com',
        'password' => 'demo1234',
    ], false);
    assertTrue($res['status'] === 200, 'login returns 200');
    assertTrue(($res['body']['user']['role'] ?? '') === 'patient', 'role is patient');
});

runTest('Get departments', function () {
    $res = request('GET', '/actions/get-departments.php');
    assertTrue($res['status'] === 200, 'departments returns 200');
    assertTrue(count($res['body']['departments'] ?? []) >= 4, 'has seeded departments');
});

runTest('Book online appointment', function () {
    $slot = (new DateTime('+1 day'))->format('Y-m-d\T10:00:00');
    $res = request('POST', '/actions/book-appointment.php', [
        'department_id'     => 1,
        'entry_channel'     => 'online',
        'scheduled_slot_at' => $slot,
    ]);
    assertTrue(in_array($res['status'], [200, 201, 409], true), 'book returns valid status');
    if ($res['status'] === 201) {
        assertTrue(isset($res['body']['ticket']['ticket_code']), 'ticket code returned');
    }
});

runTest('Get patient appointments', function () {
    $res = request('GET', '/actions/get-patient-appointments.php');
    assertTrue($res['status'] === 200, 'appointments returns 200');
    assertTrue(is_array($res['body']['appointments'] ?? null), 'appointments array');
});

runTest('Reception walk-in booking', function () {
    request('POST', '/actions/logout.php');
    request('POST', '/actions/login.php', [
        'email'    => 'reception@demo.com',
        'password' => 'demo1234',
    ], false);

    $res = request('POST', '/actions/book-appointment.php', [
        'department_id' => 1,
        'entry_channel' => 'walk_in',
        'patient_name'  => 'Walk In Test',
        'phone'         => '09998887777',
    ]);
    assertTrue(in_array($res['status'], [201, 409], true), 'walk-in book status');
});

runTest('Reception active tickets', function () {
    $res = request('GET', '/actions/get-active-tickets.php');
    assertTrue($res['status'] === 200, 'active tickets 200');
    assertTrue(is_array($res['body']['tickets'] ?? null), 'tickets array');
});

runTest('Doctor serve next patient', function () {
    request('POST', '/actions/logout.php');
    request('POST', '/actions/login.php', [
        'email'    => 'doctor.gen@demo.com',
        'password' => 'demo1234',
    ], false);

    $res = request('POST', '/actions/serve-next-patient.php', []);
    assertTrue($res['status'] === 200, 'serve next 200');
});

runTest('Admin dashboard stats', function () {
    request('POST', '/actions/logout.php');
    request('POST', '/actions/login.php', [
        'email'    => 'admin@demo.com',
        'password' => 'demo1234',
    ], false);

    $res = request('GET', '/actions/get-dashboard-stats.php');
    assertTrue($res['status'] === 200, 'stats 200');
    assertTrue(isset($res['body']['stats']['total_patients']), 'has total_patients');
    assertTrue(isset($res['body']['stats']['completion_rate']), 'has completion_rate');
});

@unlink($cookieJar);

echo "\n=============================\n";
echo "Results: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
