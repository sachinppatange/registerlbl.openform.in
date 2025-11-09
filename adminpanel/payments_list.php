<?php
session_start();
require_once __DIR__ . '/admin_auth.php';           // should provide require_admin()
require_once __DIR__ . '/../userpanel/payment_repository.php';

// --- Authentication: Redirect if not logged in as admin ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=player_dashboard.php');
    exit;
}

// Helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Read filters from GET
$status = trim($_GET['status'] ?? '');
$user_mobile = trim($_GET['user_mobile'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build filters array for repository
$filters = [];
if ($status !== '') $filters['status'] = $status;
if ($user_mobile !== '') $filters['user_mobile'] = $user_mobile;
if ($date_from !== '') $filters['date_from'] = $date_from;
if ($date_to !== '') $filters['date_to'] = $date_to;

// Fetch payments and counts
$payments = payment_list($filters, $per_page, $offset);
$counts = payment_count_by_status();
$shown_count = count($payments);

// Build query string helper (preserve filters)
function build_q(array $overrides = []) {
    $base = $_GET;
    foreach ($overrides as $k => $v) {
        $base[$k] = $v;
    }
    return http_build_query($base);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin — Payments</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{--bg:#f8fafc;--card:#fff;--accent:#2563eb;--muted:#64748b}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); margin:0; color:#0f172a;}
    .wrap{max-width:1100px;margin:22px auto;padding:18px;}
    .card{background:var(--card);border-radius:10px;padding:18px;box-shadow:0 6px 18px rgba(37,99,235,0.04);}
    h1{margin:0 0 8px;color:var(--accent)}
    .filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center}
    .filters input, .filters select{padding:8px;border:1px solid #e6eefc;border-radius:8px}
    .btn{background:var(--accent);color:#fff;padding:9px 12px;border-radius:8px;text-decoration:none;border:0;cursor:pointer}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th, td{padding:10px 8px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:14px}
    th{background:#fbfdff;color:#0b1724}
    .kbd{font-family:monospace;background:#eef2ff;padding:4px 8px;border-radius:6px}
    .status-paid{color: #0b8454; font-weight:700}
    .status-failed{color: #b91c1c; font-weight:700}
    .status-created{color:#92400e; font-weight:700}
    .meta{font-size:12px;color:var(--muted)}
    .pager{margin-top:12px;display:flex;gap:8px;align-items:center}
    .summary{margin-bottom:8px;color:var(--muted);font-size:14px}
    .small{font-size:12px;color:var(--muted)}
    a.link { color: var(--accent); text-decoration:none; font-weight:600; }
  </style>
</head>
<body>
  <div class="wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h1>Payments</h1>
      <div>
        <a class="btn" href="admin_dashboard.php">Back to Dashboard</a>
      </div>
    </div>

    <div class="card">
      <div class="summary">
        <?php
          $statuses = ['created','paid','failed','pending','refunded','authorized','cancelled'];
          $parts = [];
          foreach ($statuses as $s) {
              $cnt = isset($counts[$s]) ? (int)$counts[$s] : 0;
              $parts[] = h($s) . ': ' . h($cnt);
          }
          echo implode(' • ', $parts);
        ?>
      </div>

      <form method="get" class="filters" style="align-items:center">
        <label class="small">Status:
          <select name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?php echo h($s); ?>" <?php if($status===$s) echo 'selected';?>><?php echo h(ucfirst($s)); ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="small">Mobile:
          <input type="text" name="user_mobile" placeholder="Mobile number" value="<?php echo h($user_mobile); ?>">
        </label>

        <label class="small">From:
          <input type="date" name="date_from" value="<?php echo h($date_from); ?>">
        </label>

        <label class="small">To:
          <input type="date" name="date_to" value="<?php echo h($date_to); ?>">
        </label>

        <button class="btn" type="submit">Filter</button>

        <div style="margin-left:auto" class="small">
          Showing <?php echo h($shown_count); ?> records (page <?php echo h($page); ?>)
        </div>
      </form>

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Order ID</th>
            <th>Payment ID</th>
            <th>Mobile</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Created At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted)">No payments found.</td></tr>
          <?php else: ?>
            <?php foreach ($payments as $i => $p): ?>
              <tr>
                <td><?php echo h($offset + $i + 1); ?></td>
                <td><span class="kbd"><?php echo h($p['order_id'] ?? ''); ?></span></td>
                <td><?php echo h($p['payment_id'] ?? '—'); ?></td>
                <td><?php echo h($p['user_mobile'] ?? '—'); ?></td>
                <td><?php
                    $amt = isset($p['amount']) ? ((int)$p['amount']/100) : 0;
                    echo h(number_format($amt,2) . ' ' . strtoupper($p['currency'] ?? 'INR'));
                ?></td>
                <td>
                  <?php
                    $st = $p['status'] ?? '';
                    $cls = 'status-created';
                    if ($st === 'paid') $cls = 'status-paid';
                    if ($st === 'failed') $cls = 'status-failed';
                    if ($st === 'pending') $cls = 'status-created';
                    echo '<span class="'.$cls.'">'.h($st).'</span>';
                  ?>
                </td>
                <td><?php echo h($p['created_at'] ?? ''); ?></td>
                <td>
                  <a class="link" href="payment_view.php?order=<?php echo urlencode($p['order_id'] ?? ''); ?>">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="pager">
        <?php if ($page > 1): ?>
          <a class="btn" href="?<?php echo build_q(['page' => $page-1]); ?>">Prev</a>
        <?php endif; ?>
        <div class="small">Page <?php echo h($page); ?></div>
        <?php if ($shown_count === $per_page): ?>
          <a class="btn" href="?<?php echo build_q(['page' => $page+1]); ?>">Next</a>
        <?php endif; ?>

        <div style="margin-left:auto" class="small">
          <form method="get" style="display:inline">
            <?php
              // preserve current filters as hidden inputs for export
              $keep = $_GET;
              unset($keep['page']);
              foreach ($keep as $k=>$v) {
                if ($v === '') continue;
                echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
              }
            ?>
            <button class="btn" formaction="payments_export_csv.php" formmethod="get" name="export" value="csv">Export CSV</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</body>
</html>