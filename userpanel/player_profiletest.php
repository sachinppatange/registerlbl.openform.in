<?php
/**
 * player_profiletest.php
 *
 * Minimal test page to create a Razorpay order for ₹1 (100 paise)
 * and open Razorpay Checkout. Intended for local/dev testing.
 *
 * Usage:
 * - Put this file in /userpanel/ (same place as your other pages)
 * - Ensure config/razorpay_config.php exists and exports ['key_id','key_secret']
 *   OR set environment variables RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.
 * - Open in browser and click "Pay ₹1 (Demo)" (or it will auto-open if order created).
 *
 * Security notes:
 * - Use Razorpay test keys (rzp_test_...) for development.
 * - Do NOT commit live keys into version control.
 * - This test page is intentionally minimal for debugging. In production always
 *   verify payment signature server-side (see your razorpay_callback.php).
 */

session_start();

// Try to load config file if available
$config_path = __DIR__ . '../config/razorpay_config.php';
$cfg = [];
if (file_exists($config_path)) {
    $cfg = require $config_path;
}

// Prefer environment variables, then config file, then placeholders
$keyId     = getenv('RAZORPAY_KEY_ID') ?: ($cfg['key_id'] ?? '');
$keySecret = getenv('RAZORPAY_KEY_SECRET') ?: ($cfg['key_secret'] ?? '');

// For demo, amount is 1 INR = 100 paise
$amount_paise = 100;
$currency = 'INR';

$create_error = '';
$razorpay_order = null;

// simple logger for debug
function rp_log($line) {
    $logdir = __DIR__ . '/storage/logs';
    if (!is_dir($logdir)) @mkdir($logdir, 0755, true);
    @file_put_contents($logdir . '/razorpay_test.log', date('c') . ' ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Create order server-side via cURL (no SDK required)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($keyId) || empty($keySecret)) {
        $create_error = 'Razorpay key_id or key_secret not configured. Set environment variables or config/razorpay_config.php';
        rp_log("CONFIG ERROR: " . $create_error);
    } else {
        $receipt = 'test_receipt_' . time();
        $payload = json_encode([
            'amount' => (int)$amount_paise,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1
        ]);

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

        rp_log("CREATE ORDER RESP http={$httpCode} err={$errno} errstr={$errstr} resp=" . substr((string)$resp,0,1000));

        if ($errno !== 0) {
            $create_error = "cURL error while creating order: {$errstr}";
        } else {
            $json = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $create_error = "Invalid JSON from Razorpay: " . json_last_error_msg();
                rp_log("INVALID JSON: " . substr((string)$resp,0,1000));
            } else {
                if ($httpCode >= 200 && $httpCode < 300) {
                    $razorpay_order = $json;
                    // store in session for later verification or debug if needed
                    $_SESSION['rp_test_order'] = $json;
                    rp_log("Order created id=" . ($json['id'] ?? ''));
                } else {
                    // API error: include message if present
                    $apiErr = $json['error']['description'] ?? json_encode($json);
                    $create_error = "Razorpay API error ({$httpCode}): " . $apiErr;
                    rp_log("API ERROR: " . $create_error);
                }
            }
        }
    }
}

// Helper for escaping
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Player Profile Test — Razorpay ₹1 Demo</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#f7fafc;color:#0f172a;padding:18px}
    .card{max-width:640px;margin:28px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 30px rgba(37,99,235,0.06)}
    h1{color:#2563eb;margin:0 0 10px}
    .muted{color:#64748b}
    .btn{background:#2563eb;color:#fff;padding:12px 16px;border-radius:10px;border:0;font-weight:700;cursor:pointer}
    .btn.secondary{background:#0ea5e9}
    pre{background:#0b1220;color:#d1e7ff;padding:12px;border-radius:8px;overflow:auto}
    .err{background:#fff1f2;color:#7f1d1d;padding:10px;border-radius:8px}
    .ok{background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px}
    .meta{font-size:13px;color:#475569;margin-top:8px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Razorpay ₹1 Test</h1>
    <p class="muted">This page creates a Razorpay order for ₹1 (100 paise) and opens Checkout. Use test keys (rzp_test_...)</p>

<?php if ($create_error): ?>
    <div class="err"><strong>Error:</strong> <?php echo h($create_error); ?></div>
    <div class="meta">
      <p>Check logs: <?php echo h(__DIR__ . '/storage/logs/razorpay_test.log'); ?></p>
    </div>
<?php else: ?>
    <div class="ok"><strong>Order created:</strong> <?php echo h($razorpay_order['id'] ?? ''); ?></div>

    <div style="margin-top:12px;">
      <button id="payBtn" class="btn">Pay ₹1 (Demo)</button>
      <button id="openAuto" class="btn secondary" onclick="openCheckout()">Open Checkout Now</button>
    </div>

    <div style="margin-top:12px">
      <strong>Order details:</strong>
      <pre><?php echo h(json_encode($razorpay_order, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); ?></pre>
    </div>
<?php endif; ?>

    <hr>
    <p class="muted">Notes: After payment, the JS handler will try to POST verification data to /userpanel/razorpay_callback.php if present. If you don't have that endpoint, the page will still show the payment response in an alert.</p>
  </div>

  <!-- Razorpay checkout -->
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
    (function(){
      const order = <?php echo json_encode($razorpay_order ?: null); ?>;
      const publicKey = <?php echo json_encode($keyId ?: ''); ?>;
      const amountPaise = <?php echo json_encode($amount_paise); ?>;
      const callbackEndpoint = '/userpanel/razorpay_callback.php'; // optional server verification endpoint

      function openRzp() {
        if (!order || !publicKey) {
          alert('Order or public key missing. Check server logs.');
          return;
        }
        const opts = {
          key: publicKey,
          amount: order.amount || amountPaise,
          currency: order.currency || 'INR',
          name: 'LBL Demo',
          description: 'Registration fee (₹1 demo)',
          order_id: order.id,
          prefill: { contact: '' },
          theme: { color: '#2563eb' },
          handler: function (res) {
            // res contains: razorpay_payment_id, razorpay_order_id, razorpay_signature
            const msg = 'Payment completed.\\nPayment ID: ' + (res.razorpay_payment_id||'') + '\\nOrder ID: ' + (res.razorpay_order_id||'') + '\\nSignature: ' + (res.razorpay_signature||'');
            try {
              // Try server-side verification if endpoint exists
              const fd = new FormData();
              fd.append('razorpay_payment_id', res.razorpay_payment_id || '');
              fd.append('razorpay_order_id', res.razorpay_order_id || '');
              fd.append('razorpay_signature', res.razorpay_signature || '');
              // send request
              fetch(callbackEndpoint, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(r => r.json().catch(()=>({})) )
                .then(data => {
                  if (data && data.success) {
                    alert('Payment verified on server and successful. Redirecting...');
                    if (data.redirect) window.location.href = data.redirect;
                    else window.location.reload();
                  } else {
                    alert(msg + '\\nServer verification not available or failed. See console/network for details.');
                    console.log('Server verification response:', data);
                  }
                }).catch(err => {
                  console.warn('Verification fetch failed', err);
                  alert(msg + '\\nServer verification fetch failed; check network/console.');
                });
            } catch (e) {
              alert(msg + '\\n(Verification not attempted)');
            }
          },
          modal: {
            ondismiss: function() {
              console.info('Checkout closed by user');
            }
          }
        };
        const rzp = new Razorpay(opts);
        rzp.open();
      }

      // Button bindings
      document.getElementById('payBtn')?.addEventListener('click', function(){ openRzp(); });
      // Auto-open if order already created
      // Uncomment next line to auto-open on page load:
      // if (order) setTimeout(openRzp, 400);
    })();
  </script>
</body>
</html>