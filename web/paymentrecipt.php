<?php
/**
 * web/paymentrecipt.php
 *
 * Robust Razorpay payment verification and local recording.
 * Tries multiple locations for web/config.php and will create a DB connection
 * from constants if config file defines DB constants instead of $GLOBALS['conn'].
 *
 * Place this file at: web/paymentrecipt.php
 *
 * Important:
 * - Ensure web/config.php exists (it should define DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_CHARSET
 *   or set up $GLOBALS['conn'] as a working mysqli connection).
 * - Ensure config/razorpay_config.php exists with ['key_id'=>..., 'key_secret'=>...]
 *   or set environment variable RAZORPAY_KEY_SECRET.
 *
 * This page accepts POST with:
 * - razorpay_payment_id
 * - razorpay_order_id
 * - razorpay_signature
 * - optional: studmaxid
 *
 * It verifies signature and records a payments row (best-effort) and updates student.status.
 */

session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function log_server($m){ @file_put_contents(__DIR__ . '/logs/paymentrecipt.log', date('c') . ' ' . $m . PHP_EOL, FILE_APPEND|LOCK_EX); }

/* ---------- Locate and include web/config.php (try several likely paths) ---------- */
$config_candidates = [
    __DIR__ . '/config.php',               // web/config.php (preferred)
    __DIR__ . '/../web/config.php',        // one level up
    __DIR__ . '/../config/config.php',     // older layout
    __DIR__ . '/../../config/config.php',  // two levels up
];

$config_loaded = false;
foreach ($config_candidates as $cfg) {
    if (file_exists($cfg)) {
        include_once $cfg;
        $config_loaded = true;
        break;
    }
}

/* If config not loaded, show helpful HTML with instructions */
if (!$config_loaded) {
    http_response_code(500);
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Missing web/config.php</title></head><body>";
    echo "<h3>Missing configuration</h3>";
    echo "<p>web/config.php was not found. I looked in:</p><ul>";
    foreach ($config_candidates as $p) echo "<li>" . h($p) . "</li>";
    echo "</ul>";
    echo "<p>Create <code>web/config.php</code> or make sure one of the above files exists. Example content:</p>";
    echo "<pre>&lt;?php\n// web/config.php\ndefine('DB_HOST','localhost');\ndefine('DB_NAME','registerlblnew');\ndefine('DB_USER','root');\ndefine('DB_PASSWORD','');\ndefine('DB_CHARSET','utf8mb4');\n\$GLOBALS['conn'] = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME) or die(mysqli_connect_error());\nmysqli_set_charset(\$GLOBALS['conn'], DB_CHARSET);\n</pre>";
    echo "</body></html>";
    exit;
}

/* ---------- Ensure we have a mysqli connection in $GLOBALS['conn'] ---------- */
$db = $GLOBALS['conn'] ?? null;
if (!($db instanceof mysqli)) {
    // Try to construct from DB_* constants if present
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
        $host = DB_HOST; $user = DB_USER; $pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
        $name = DB_NAME; $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        $conn = @mysqli_connect($host, $user, $pass, $name);
        if ($conn) {
            mysqli_set_charset($conn, $charset);
            $GLOBALS['conn'] = $conn;
            $db = $conn;
        } else {
            log_server("Failed to connect using DB_* constants: " . mysqli_connect_error());
        }
    }
}

/* ---------- Load Razorpay secret (from config/razorpay_config.php or environment) ---------- */
$rz_cfg_paths = [
    __DIR__ . '/../config/razorpay_config.php',
    __DIR__ . '/../../config/razorpay_config.php',
    __DIR__ . '/config/razorpay_config.php',
];

$rz_cfg = [];
foreach ($rz_cfg_paths as $p) {
    if (file_exists($p)) { $rz_cfg = (array) @include $p; break; }
}

$key_id = getenv('RAZORPAY_KEY_ID') ?: ($rz_cfg['key_id'] ?? '');
$key_secret = getenv('RAZORPAY_KEY_SECRET') ?: ($rz_cfg['key_secret'] ?? '');

/* ---------- Read POST params ---------- */
$razorpay_payment_id = trim($_POST['razorpay_payment_id'] ?? '');
$razorpay_order_id   = trim($_POST['razorpay_order_id'] ?? '');
$razorpay_signature  = trim($_POST['razorpay_signature'] ?? '');
$studmaxid           = isset($_POST['studmaxid']) ? (int)$_POST['studmaxid'] : (isset($_GET['studmaxid']) ? (int)$_GET['studmaxid'] : null);

/* ---------- Basic validation ---------- */
if ($razorpay_payment_id === '' || $razorpay_order_id === '' || $razorpay_signature === '') {
    http_response_code(400);
    echo "Missing required Razorpay parameters.";
    exit;
}

/* ---------- Verify signature ---------- */
$verified = false;
$verify_error = '';
if (empty($key_secret)) {
    $verify_error = 'Server missing Razorpay key_secret. Configure config/razorpay_config.php or RAZORPAY_KEY_SECRET env.';
    log_server($verify_error);
} else {
    $payload = $razorpay_order_id . '|' . $razorpay_payment_id;
    $expected_signature = hash_hmac('sha256', $payload, $key_secret);
    if (hash_equals($expected_signature, $razorpay_signature)) {
        $verified = true;
    } else {
        $verify_error = 'Signature mismatch';
        log_server("Signature mismatch for order {$razorpay_order_id}");
    }
}

/* ---------- Record payment locally (best-effort) ---------- */
$payment_saved = false;
$insert_id = null;
if ($db instanceof mysqli) {
    try {
        $res = mysqli_query($db, "SHOW TABLES LIKE 'payments'");
        if ($res && mysqli_num_rows($res) > 0) {
            $order_e = mysqli_real_escape_string($db, $razorpay_order_id);
            $payment_e = mysqli_real_escape_string($db, $razorpay_payment_id);
            $status = $verified ? 'paid' : 'failed';
            $meta = json_encode(['student_id' => $studmaxid ?: null]);
            $meta_e = mysqli_real_escape_string($db, $meta);
            $now = date('Y-m-d H:i:s');

            $sql = "INSERT INTO payments (`order_id`,`payment_id`,`status`,`meta`,`created_at`) VALUES ('{$order_e}','{$payment_e}','{$status}','{$meta_e}','{$now}')";
            if (mysqli_query($db, $sql)) {
                $payment_saved = true;
                $insert_id = mysqli_insert_id($db);
            } else {
                log_server("payments INSERT failed: " . mysqli_error($db) . " SQL: {$sql}");
            }
        } else {
            log_server("payments table not found; skipping insert.");
        }
    } catch (Throwable $e) {
        log_server("Exception while inserting payments: " . $e->getMessage());
    }
} else {
    log_server("No mysqli connection available; skipping payments insert.");
}

/* ---------- Update student.status if studmaxid provided ---------- */
$student_updated = false;
if ($db instanceof mysqli && !empty($studmaxid)) {
    try {
        $status_val = $verified ? 'success' : 'failed';
        $now = date('Y-m-d H:i:s');

        $sql = "UPDATE student SET status = ?, modifiedon = ? WHERE stud_id = ?";
        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param($stmt, 'ssi', $status_val, $now, $studmaxid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $student_updated = true;
        } else {
            $sql2 = "UPDATE student SET status = '" . mysqli_real_escape_string($db, $status_val) . "' WHERE stud_id = " . intval($studmaxid);
            mysqli_query($db, $sql2);
            $student_updated = true;
        }
    } catch (Throwable $e) {
        log_server("Exception updating student: " . $e->getMessage());
    }
}

/* ---------- Render simple receipt ---------- */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Payment Receipt</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style> body{background:#f7fafc;padding:18px;font-family:system-ui} .card{max-width:860px;margin:18px auto;padding:20px} </style>
</head>
<body>
  <div class="card">
    <h4>Payment Receipt</h4>
    <table class="table table-sm">
      <tbody>
        <tr><th>Order ID</th><td><?php echo h($razorpay_order_id); ?></td></tr>
        <tr><th>Payment ID</th><td><?php echo h($razorpay_payment_id); ?></td></tr>
        <tr><th>Signature</th><td><?php echo h($razorpay_signature); ?></td></tr>
        <tr><th>Verification</th><td>
          <?php if ($verified): ?>
            <span class="badge bg-success">VERIFIED</span>
          <?php else: ?>
            <span class="badge bg-danger">FAILED</span>
          <?php endif; ?>
        </td></tr>
        <?php if (!$verified && $verify_error): ?>
          <tr><th>Verify Error</th><td><?php echo h($verify_error); ?></td></tr>
        <?php endif; ?>
        <?php if ($payment_saved): ?>
          <tr><th>Recorded</th><td>payments.id = <?php echo h($insert_id); ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($studmaxid)): ?>
          <tr><th>Student</th><td>ID <?php echo h($studmaxid); ?><?php if ($student_updated) echo ' — status updated'; ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="mt-3">
      <?php if ($verified): ?>
        <a class="btn btn-primary" href="index.php">Back to site</a>
      <?php else: ?>
        <a class="btn btn-warning" href="index.php">Back</a>
      <?php endif; ?>
    </div>

    <hr>
    <h6>Debug / Notes</h6>
    <ul>
      <li>Config loaded: <?php echo $config_loaded ? 'yes' : 'no'; ?></li>
      <li>Razorpay key_id: <?php echo h($key_id ? (strlen($key_id)>8 ? substr($key_id,0,4).'...'.substr($key_id,-4) : '***') : '(none)'); ?></li>
      <li>Server log: <code><?php echo h(__DIR__ . '/logs/paymentrecipt.log'); ?></code></li>
    </ul>
  </div>
</body>
</html>