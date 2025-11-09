<?php
// Admin Player Status: Accept or Reject a player (by id/status param)
// UPDATED: after changing status, fetch and display player details (including blood_group & playing_years)

session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/player_repository.php';

// --- Authentication: Redirect if not logged in as admin ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=player_status.php');
    exit;
}

// --- Get player ID and status ---
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status    = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';

$status_options = ['accepted', 'rejected', 'pending'];

if (!$player_id || !in_array($status, $status_options, true)) {
    die('Invalid player ID or status.');
}

// --- Update status ---
$success = set_player_status($player_id, $status);

// --- Fetch updated player (ensure repository SELECT includes blood_group & playing_years) ---
$player = get_player_by_id($player_id);
if (!$player) {
    $msg = "Player updated but failed to fetch player record.";
} else {
    $msg = $success ? "Player status updated to " . ucfirst($status) . " successfully!" : "Failed to update player status.";
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Helper: fix stored paths (same logic as player_view.php)
function fix_path($url, $type = 'photo') {
    if (empty($url)) return '';
    if (preg_match('/^https?:\/\//', $url)) return $url;
    if (strpos($url, '/userpanel/') === 0) return $url;
    if (strpos($url, '/adminpanel/storage/uploads/') === 0) {
        return str_replace('/adminpanel/storage/uploads/', '/userpanel/storage/uploads/', $url);
    }
    if (strpos($url, 'adminpanel/storage/uploads/') === 0) {
        return str_replace('adminpanel/storage/uploads/', 'userpanel/storage/uploads/', $url);
    }
    if (strpos($url, './') === 0 || strpos($url, '../') === 0) {
        $basename = basename($url);
        if ($type === 'aadhaar') {
            return '/userpanel/storage/uploads/aadhaar/' . $basename;
        } else {
            return '/userpanel/storage/uploads/photos/' . $basename;
        }
    }
    $basename = basename($url);
    if ($type === 'aadhaar') {
        return '/userpanel/storage/uploads/aadhaar/' . $basename;
    } else {
        return '/userpanel/storage/uploads/photos/' . $basename;
    }
}

$photo_url = '/assets/default_user.png';
$aadhaar_url = '';
if (!empty($player['photo'])) $photo_url = fix_path($player['photo'], 'photo');
if (!empty($player['aadhaar_card'])) $aadhaar_url = fix_path($player['aadhaar_card'], 'aadhaar');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Player Status Update | LBL Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --bg:#f8fafc; --card:#fff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; }
        body { background:var(--bg); font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--text); margin:0; }
        .wrap { display:grid; place-items:center; min-height:100dvh; padding:16px; }
        .card { width:100%; max-width:720px; background:var(--card); border-radius:12px; box-shadow:0 8px 30px rgba(2,8,23,.06); padding:24px; }
        h1 { margin:0 0 6px; color:var(--primary); font-size:20px; }
        .sub { color:var(--muted); margin-bottom:12px; }
        .msg { padding:10px 12px; background:#e6f4ff; border-radius:10px; margin-bottom:12px; color:var(--text); }
        .grid { display:grid; grid-template-columns:120px 1fr; gap:16px; align-items:start; }
        .img-thumb { width:120px; height:120px; object-fit:cover; border-radius:12px; border:1px solid var(--border); }
        table { width:100%; border-collapse:collapse; margin-top:8px; }
        td.label { width:38%; color:var(--muted); padding:8px 6px; vertical-align:top; font-weight:600; }
        td.value { padding:8px 6px; vertical-align:top; color:var(--text); }
        .actions { margin-top:14px; display:flex; gap:8px; flex-wrap:wrap; }
        .btn { padding:10px 14px; border-radius:10px; text-decoration:none; color:#fff; font-weight:700; }
        .btn.back { background:#64748b; }
        .btn.view { background:#2563eb; }
        .note { margin-top:14px; color:var(--muted); font-size:13px; }
        @media (max-width:720px){ .grid{grid-template-columns:1fr; } .img-thumb{width:84px;height:84px;} td.label{width:40%;} }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" role="region" aria-label="Player status update result">
        <h1>Player Status Update</h1>
        <div class="sub"><?php echo h($msg); ?></div>

        <?php if ($player): ?>
            <div class="grid">
                <div style="text-align:center;">
                    <img src="<?php echo h($photo_url); ?>" alt="Player photo" class="img-thumb"
                         onerror="this.onerror=null;this.src='/assets/default_user.png'">
                </div>
                <div>
                    <table>
                        <tr><td class="label">Full Name</td><td class="value"><?php echo h($player['full_name'] ?? ''); ?></td></tr>
                        <tr><td class="label">Mobile</td><td class="value"><?php echo h($player['mobile'] ?? ''); ?></td></tr>
                        <tr><td class="label">DOB</td><td class="value"><?php echo h($player['dob'] ?? ''); ?></td></tr>
                        <tr><td class="label">Age Group</td><td class="value"><?php echo h($player['age_group'] ?? ''); ?></td></tr>
                        <tr><td class="label">Village/City</td><td class="value"><?php echo h($player['village'] ?? ''); ?></td></tr>
                        <tr><td class="label">Court</td><td class="value"><?php echo h($player['court'] ?? ''); ?></td></tr>
                        <tr><td class="label">Playing Time</td><td class="value"><?php echo h($player['play_time'] ?? ''); ?></td></tr>

                        <!-- NEW: blood_group & playing_years -->
                        <tr><td class="label">Blood Group</td><td class="value"><?php echo h($player['blood_group'] ?? ''); ?></td></tr>
                        <tr><td class="label">Playing Years</td><td class="value"><?php echo h($player['playing_years'] ?? ''); ?></td></tr>

                        <tr><td class="label">Aadhaar</td><td class="value"><?php echo h($player['aadhaar'] ?? ''); ?></td></tr>
                        <?php if (!empty($aadhaar_url)): ?>
                            <tr><td class="label">Aadhaar Card</td>
                                <td class="value"><a href="<?php echo h($aadhaar_url); ?>" target="_blank">View Aadhaar Card</a></td></tr>
                        <?php endif; ?>
                        <tr><td class="label">Status</td><td class="value"><?php echo h(ucfirst($player['status'] ?? 'pending')); ?></td></tr>
                        <tr><td class="label">Created At</td><td class="value"><?php echo h(date('d M Y, h:i A', strtotime($player['created_at'] ?? ''))); ?></td></tr>
                    </table>

                    <div class="actions">
                        <a class="btn view" href="player_view.php?id=<?php echo urlencode($player_id); ?>">View Full</a>
                        <a class="btn back" href="player_dashboard.php">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="msg">Player record not available.</div>
            <div class="actions">
                <a class="btn back" href="player_dashboard.php">Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <div class="note">Note: blood_group and playing_years values are read from the players table.</div>
    </div>
</div>
</body>
</html>