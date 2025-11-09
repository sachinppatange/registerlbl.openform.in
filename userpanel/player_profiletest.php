<?php
/**
 * userpanel/player_profiletest.php
 *
 * Minimal test page to create a Razorpay order for ₹1 (100 paise) and open Checkout.
 * - Reads keys from ../config/razorpay_config.php (preferred) or env vars.
 * - Use test keys (rzp_test_...) for dev. Do NOT commit live secrets to git.
 * - Remove this file after testing.
 */

session_start();

// Load config (project_root/config/razorpay_config.php)
$configPath = __DIR__ . '/../config/razorpay_config.php';
$config = [];
if (file_exists($configPath)) {
    $config = require $configPath;
}

// fallback to environment variables
$keyId     = getenv('RAZORPAY_KEY_ID') ?: ($config['key_id'] ?? '');
$keySecret = getenv('RAZORPAY_KEY_SECRET') ?: ($config['key_secret'] ?? '');

// Ensure logs dir
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

// debug log helper
function rp_log($msg) {
    $dir = __DIR__ . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/razorpay_test.log', date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// small mask helper for display
function mask_key($s) {
    if (!$s) return '(empty)';
    if (strlen($s) <= 8) return str_repeat('*', strlen($s));
    return substr($s,0,4) . str_repeat('*', max(4, strlen($s)-8)) . substr($s,-4);
}

// default demo amount: ₹1 = 100 paise
$amount_paise = 100;
$currency = 'INR';

$create_error = '';
$razorpay_order = null;

// Try to create a server-side order (GET request creates the order)
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

        rp_log("CREATE ORDER RESP http={$httpCode} err={$errno} errstr={$errstr} resp=" . substr((string)$resp,0,2000));

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
                    $_SESSION['rp_test_order'] = $json;
                    rp_log("Order created id=" . ($json['id'] ?? ''));
                } else {
                    $apiErr = $json['error']['description'] ?? json_encode($json);
                    $create_error = "Razorpay API error ({$httpCode}): " . $apiErr;
                    rp_log("API ERROR: " . $create_error);
                }
            }
        }
    }
}

// small escape helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Player Profile Test — Razorpay ₹1 Demo</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#f7fafc;color:#0f172a;padding:20px}
    .card{max-width:760px;margin:28px auto;background:#fff;padding:22px;border-radius:12px;box-shadow:0 10px 30px rgba(37,99,235,0.06)}
    h1{color:#2563eb;margin:0 0 8px}
    .muted{color:#64748b}
    .btn{background:#2563eb;color:#fff;padding:12px 16px;border-radius:10px;border:0;font-weight:700;cursor:pointer}
    .btn.secondary{background:#0ea5e9}
    pre{background:#0b1220;color:#d1e7ff;padding:12px;border-radius:8px;overflow:auto}
    .err{background:#fff1f2;color:#7f1d1d;padding:10px;border-radius:8px}
    .ok{background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px}
    .meta{font-size:13px;color:#475569;margin-top:8px}
    .dbg {font-size:13px;color:#1f2937;background:#f1f5f9;padding:8px;border-radius:6px;margin-top:8px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Razorpay ₹1 Test</h1>
    <p class="muted">Creates a Razorpay order for ₹1 (100 paise) and opens Checkout. Use test keys for dev.</p>

<?php if ($create_error): ?>
    <div class="err"><strong>Error:</strong> <?php echo h($create_error); ?></div>
    <div class="meta">Check logs: <?php echo h(__DIR__ . '/storage/logs/razorpay_test.log'); ?></div>
    <div class="dbg">
      <strong>Debug:</strong><br>
      Config path: <?php echo h($configPath); ?><br>
      key_id: <?php echo h(mask_key($keyId)); ?><br>
      key_secret: <?php echo h(mask_key($keySecret)); ?><br>
      If keys are empty, create <code>config/razorpay_config.php</code> with:
      <pre><?php echo h("<?php\nreturn [\n  'key_id' => 'rzp_test_YOUR_KEY_ID',\n  'key_secret' => 'YOUR_KEY_SECRET',\n];\n"); ?></pre>
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

    <div class="dbg">
      <strong>Debug:</strong><br>
      key_id: <?php echo h(mask_key($keyId)); ?><br>
      key_secret: <?php echo h(mask_key($keySecret)); ?><br>
      Logs: <?php echo h(__DIR__ . '/storage/logs/razorpay_test.log'); ?>
    </div>
<?php endif; ?>

    <hr>
    <p class="muted">After payment the client handler will attempt to POST verification data to /userpanel/razorpay_callback.php if present. If you don't have that endpoint, the page will still show the payment response in an alert.</p>
  </div>

  <!-- Razorpay checkout -->
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
    (function(){
      const order = <?php echo json_encode($razorpay_order ?: null); ?>;
      const publicKey = <?php echo json_encode($keyId ?: ''); ?>;
      const amountPaise = <?php echo json_encode($amount_paise); ?>;
      const callbackEndpoint = '/userpanel/razorpay_callback.php'; // optional verification endpoint

      function openRzp() {
        if (!order) {
          console.error('Order missing in JS (server did not create it).');
          alert('Order not created on server. Check server logs.');
          return;
        }
        if (!publicKey) {
          console.error('Public key missing in JS (server config not loaded).');
          alert('Payment cannot start: public key missing. Check server config.');
          return;
        }

        console.info('Opening Razorpay checkout with', { order, publicKey });

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
            const msg = 'Payment completed.\nPayment ID: ' + (res.razorpay_payment_id||'') + '\nOrder ID: ' + (res.razorpay_order_id||'') + '\nSignature: ' + (res.razorpay_signature||'');
            try {
              const fd = new FormData();
              fd.append('razorpay_payment_id', res.razorpay_payment_id || '');
              fd.append('razorpay_order_id', res.razorpay_order_id || '');
              fd.append('razorpay_signature', res.razorpay_signature || '');
              fetch(callbackEndpoint, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(r => r.json().catch(()=>({})))
                .then(data => {
                  if (data && data.success) {
                    alert('Payment verified on server and successful.');
                    if (data.redirect) window.location.href = data.redirect;
                  } else {
                    alert(msg + '\nServer verification not available or failed. See console.');
                    console.log('Server verification response:', data);
                  }
                }).catch(err => {
                  console.warn('Verification fetch failed', err);
                  alert(msg + '\nServer verification fetch failed; check network/console.');
                });
            } catch (e) {
              alert(msg + '\n(Verification not attempted)');
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

      function openCheckout() {
        try {
          openRzp();
        } catch (e) {
          console.error('Error while opening checkout:', e);
          alert('Failed to open checkout. See console for details.');
        }
      }

      document.getElementById('payBtn')?.addEventListener('click', openCheckout);

      // Auto-open when both order and publicKey present
      if (order && publicKey) {
        // small delay to allow UI to render
        setTimeout(openCheckout, 350);
      } else {
        if (!order) console.debug('Auto-open skipped: order not available');
        if (!publicKey) console.debug('Auto-open skipped: publicKey not available');
      }
    })();
  </script>
</body>
</html>