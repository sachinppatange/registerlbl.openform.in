<?php
session_start();
require_once __DIR__ . '/admin_auth.php';               // must provide require_admin()
require_once __DIR__ . '/../userpanel/payment_repository.php';

// --- Authentication: Redirect if not logged in as admin ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=player_dashboard.php');
    exit;
}

// Helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// CSRF token for admin actions
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];

$msg_success = '';
$msg_error = '';

// Read identifiers from GET or POST
$order_id = trim($_GET['order'] ?? $_POST['order'] ?? '');
$payment_id = trim($_GET['payment'] ?? $_POST['payment'] ?? '');

// If only payment_id provided, try to resolve order_id
if (empty($order_id) && $payment_id !== '') {
    $row = payment_find_by_payment_id($payment_id);
    if ($row) $order_id = $row['order_id'] ?? '';
}

// Load payment record
$payment = null;
if ($order_id !== '') {
    $payment = payment_find_by_order_id($order_id);
}
if (!$payment && $payment_id !== '') {
    $payment = payment_find_by_payment_id($payment_id);
}

// Handle admin actions (update status / notes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {
    // Only allow if payment exists
    if (!$payment) {
        $msg_error = 'Payment record not found.';
    } else {
        $action = $_POST['action'] ?? '';
        // Allow updating status and notes via safe fields
        if ($action === 'update_status') {
            $new_status = trim($_POST['status'] ?? '');
            $notes = trim($_POST['admin_notes'] ?? '');
            $allowed = ['created','pending','paid','failed','refunded','authorized','cancelled','refund_failed'];
            if (!in_array($new_status, $allowed, true)) {
                $msg_error = 'Invalid status.';
            } else {
                $upd = payment_update_by_order_id($payment['order_id'], [
                    'status' => $new_status,
                    'notes' => $notes,
                ]);
                if ($upd) {
                    $msg_success = 'Payment updated.';
                    // refresh record
                    $payment = payment_find_by_order_id($payment['order_id']);
                } else {
                    $msg_error = 'Failed to update payment.';
                }
            }
        } elseif ($action === 'mark_paid') {
            // Admin may mark paid manually (requires payment_id). Accept optional payment_id/signature.
            $pmt_id = trim($_POST['manual_payment_id'] ?? '');
            $sig = trim($_POST['manual_signature'] ?? '');
            if ($pmt_id === '') {
                $msg_error = 'Payment ID required to mark paid.';
            } else {
                $ok = payment_mark_paid($payment['order_id'], $pmt_id, $sig ?: '', ['admin_marked_paid' => true, 'admin' => ($_SESSION['admin_user'] ?? 'admin')]);
                if ($ok) {
                    $msg_success = 'Marked as paid.';
                    $payment = payment_find_by_order_id($payment['order_id']);
                } else {
                    $msg_error = 'Failed to mark paid.';
                }
            }
        } elseif ($action === 'mark_failed') {
            $meta = ['admin_note' => trim($_POST['admin_notes'] ?? ''), 'admin' => ($_SESSION['admin_user'] ?? 'admin')];
            $ok = payment_mark_failed($payment['order_id'], $meta);
            if ($ok) {
                $msg_success = 'Marked as failed.';
                $payment = payment_find_by_order_id($payment['order_id']);
            } else {
                $msg_error = 'Failed to mark failed.';
            }
        } else {
            $msg_error = 'Unknown action.';
        }
    }
}

// Page title & fallback
$title = $payment ? ('Payment: ' . ($payment['order_id'] ?? '')) : 'Payment Detail';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo h($title); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{--bg:#f8fafc;--card:#fff;--accent:#2563eb;--muted:#64748b}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:#0f172a;margin:0;padding:18px;}
    .wrap{max-width:980px;margin:16px auto;}
    .card{background:var(--card);padding:18px;border-radius:10px;box-shadow:0 6px 18px rgba(37,99,235,0.04);}
    h1{margin:0 0 8px;color:var(--accent)}
    .muted{color:var(--muted)}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    td{padding:8px 6px;border-bottom:1px dashed #eef2ff;vertical-align:top}
    td.label{width:28%;color:var(--muted);font-weight:700}
    pre{background:#0b1220;color:#d1e7ff;padding:12px;border-radius:8px;overflow:auto}
    .kbd{font-family:monospace;background:#eef2ff;padding:6px 10px;border-radius:6px}
    .btn{display:inline-block;padding:8px 12px;background:var(--accent);color:#fff;border-radius:8px;text-decoration:none;margin-right:8px}
    .btn.warn{background:#b91c1c}
    .form-row{margin-top:10px}
    .flex{display:flex;gap:8px;align-items:center}
    .small{font-size:13px;color:var(--muted)}
  </style>
</head>
<body>
  <div class="wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h1><?php echo h($title); ?></h1>
      <div>
        <a class="btn" href="payments_list.php">Back to list</a>
      </div>
    </div>

    <div class="card">
      <?php if ($msg_success): ?><div style="background:#e6ffef;padding:10px;border-radius:8px;margin-bottom:10px;color:#064e3b"><?php echo h($msg_success); ?></div><?php endif;?>
      <?php if ($msg_error): ?><div style="background:#fff1f2;padding:10px;border-radius:8px;margin-bottom:10px;color:#7f1d1d"><?php echo h($msg_error); ?></div><?php endif;?>

      <?php if (!$payment): ?>
        <p class="muted">Payment record not found. Try searching by Payment ID or Order ID from the list.</p>
      <?php else: ?>
        <table>
          <tr><td class="label">Order ID</td><td><span class="kbd"><?php echo h($payment['order_id'] ?? ''); ?></span></td></tr>
          <tr><td class="label">Payment ID</td><td><span class="kbd"><?php echo h($payment['payment_id'] ?? '—'); ?></span></td></tr>
          <tr><td class="label">Status</td><td><?php echo h($payment['status'] ?? '—'); ?></td></tr>
          <tr><td class="label">Amount</td><td><?php echo h(number_format(((int)($payment['amount'] ?? 0))/100, 2) . ' ' . strtoupper($payment['currency'] ?? 'INR')); ?></td></tr>
          <tr><td class="label">User Mobile</td><td><?php echo h($payment['user_mobile'] ?? '—'); ?></td></tr>
          <tr><td class="label">Signature</td><td><pre><?php echo h($payment['signature'] ?? '—'); ?></pre></td></tr>
          <tr><td class="label">Created At</td><td><?php echo h($payment['created_at'] ?? '—'); ?></td></tr>
          <tr><td class="label">Updated At</td><td><?php echo h($payment['updated_at'] ?? '—'); ?></td></tr>
          <tr><td class="label">Notes</td><td class="small"><?php echo nl2br(h($payment['notes'] ?? '')); ?></td></tr>
        </table>

        <div style="margin-top:12px" class="flex">
          <?php if (!empty($payment['payment_id'])): ?>
            <a class="btn" href="https://dashboard.razorpay.com/app/payments/<?php echo urlencode($payment['payment_id']); ?>" target="_blank">Open in Razorpay</a>
          <?php endif; ?>
          <a class="btn" href="payments_list.php">Back to list</a>
        </div>

        <h3 style="margin-top:18px">Raw Metadata</h3>
        <div class="small">
          <?php
            $meta = $payment['meta'] ?? null;
            if (is_string($meta)) {
                $json = json_decode($meta, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo '<pre>' . h(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                } else {
                    echo '<pre>' . h($meta) . '</pre>';
                }
            } elseif (is_array($meta)) {
                echo '<pre>' . h(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            } else {
                echo '<pre>—</pre>';
            }
          ?>
        </div>

        <h3 style="margin-top:18px">Admin Actions</h3>
        <form method="post" style="margin-top:8px">
          <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
          <input type="hidden" name="order" value="<?php echo h($payment['order_id'] ?? ''); ?>">

          <div class="form-row">
            <label class="small">Update status and notes</label>
            <div class="flex" style="margin-top:6px">
              <select name="status" style="padding:8px;border-radius:6px;border:1px solid #e6eefc">
                <?php
                  $opts = ['created','pending','paid','failed','refunded','authorized','cancelled','refund_failed'];
                  foreach ($opts as $o) {
                      $sel = ($o === ($payment['status'] ?? '')) ? 'selected' : '';
                      echo '<option value="'.h($o).'" '.$sel.'>'.h(ucfirst($o)).'</option>';
                  }
                ?>
              </select>
              <input type="text" name="admin_notes" placeholder="Admin note (optional)" value="<?php echo h($payment['notes'] ?? ''); ?>" style="flex:1;padding:8px;border-radius:6px;border:1px solid #e6eefc">
              <button class="btn" name="action" value="update_status" type="submit">Update</button>
            </div>
          </div>

          <div class="form-row" style="margin-top:10px">
            <label class="small">Mark paid manually (enter payment id)</label>
            <div class="flex" style="margin-top:6px">
              <input type="text" name="manual_payment_id" placeholder="razorpay_payment_id" style="padding:8px;border-radius:6px;border:1px solid #e6eefc">
              <input type="text" name="manual_signature" placeholder="signature (optional)" style="padding:8px;border-radius:6px;border:1px solid #e6eefc">
              <button class="btn" name="action" value="mark_paid" type="submit">Mark Paid</button>
            </div>
            <div class="small muted" style="margin-top:6px">Use this if you manually verified payment or captured funds outside normal flow.</div>
          </div>

          <div class="form-row" style="margin-top:10px">
            <label class="small">Mark failed</label>
            <div class="flex" style="margin-top:6px">
              <input type="text" name="admin_notes" placeholder="Failure reason (optional)" style="flex:1;padding:8px;border-radius:6px;border:1px solid #e6eefc">
              <button class="btn warn" name="action" value="mark_failed" type="submit">Mark Failed</button>
            </div>
          </div>

        </form>

      <?php endif; ?>
    </div>
  </div>
</body>
</html>