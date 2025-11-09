<?php
// Debug script: verifies config values and attempts to create a small Razorpay order.
// WARNING: Remove this file after debugging.

$configPath = __DIR__ . '/../config/razorpay_config.php';
header('Content-Type: text/plain; charset=utf-8');

if (!file_exists($configPath)) {
    echo "Configuration file missing: " . $configPath . PHP_EOL;
    echo "Expected: create file at project_root/config/razorpay_config.php" . PHP_EOL;
    exit;
}

$config = require $configPath;
$keyId = trim($config['key_id'] ?? '');
$keySecret = trim($config['key_secret'] ?? '');

function mask($s) {
    if (!$s) return '(empty)';
    if (strlen($s) <= 8) return str_repeat('*', strlen($s));
    return substr($s,0,4) . str_repeat('*', max(4, strlen($s)-8)) . substr($s,-4);
}

echo "Using config path: " . realpath($configPath) . PHP_EOL;
echo "Razorpay key_id: " . mask($keyId) . PHP_EOL;
echo "Razorpay key_secret: " . mask($keySecret) . PHP_EOL;

if (empty($keyId) || empty($keySecret)) {
    echo PHP_EOL . "Configuration missing. Check config/razorpay_config.php or env vars." . PHP_EOL;
    exit;
}

// Attempt to create a small order (100 paise = ₹1)
$payload = json_encode(['amount' => 100, 'currency' => 'INR', 'receipt' => 'test_conn_' . time(), 'payment_capture' => 1]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$errstr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo PHP_EOL . "HTTP code: {$httpCode}" . PHP_EOL;
if ($errno) {
    echo "cURL error ({$errno}): {$errstr}" . PHP_EOL;
}
echo "Response body:" . PHP_EOL;
echo $resp . PHP_EOL;