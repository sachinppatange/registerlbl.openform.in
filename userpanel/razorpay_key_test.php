<?php
// Simple test to verify Razorpay credentials from server.
// WARNING: Do NOT expose this script in production. Remove after debug.

$config = require __DIR__ . '/../config/razorpay_config.php';
$keyId = $config['key_id'] ?? '';
$keySecret = $config['key_secret'] ?? '';

header('Content-Type: text/plain; charset=utf-8');

if (empty($keyId) || empty($keySecret)) {
    echo "Configuration missing. Check config/razorpay_config.php or env vars.\n";
    exit;
}

echo "Using keyId: " . (strlen($keyId) ? $keyId : 'EMPTY') . "\n";

// Create a minimal order (100 paise = ₹1)
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

echo "HTTP code: " . $httpCode . "\n";
if ($errno) {
    echo "cURL error ({$errno}): {$errstr}\n";
}
echo "Response body:\n";
echo $resp . "\n";