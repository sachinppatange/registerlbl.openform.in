<?php
// Admin Player View: View full details of a player
session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/player_repository.php';

// --- Authentication: Redirect if not logged in as admin ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=player_view.php');
    exit;
}

// --- Get player ID ---
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$player_id) {
    die('Invalid player ID.');
}

// --- Get player info ---
$player = get_player_by_id($player_id);
if (!$player) {
    die('Player not found.');
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Add new fields blood_group and playing_years to the view data
$fields = [
    'Full Name'       => $player['full_name'] ?? '',
    'Date of Birth'   => $player['dob'] ?? '',
    'Age Group'       => $player['age_group'] ?? '',
    'Village/City'    => $player['village'] ?? '',
    'Court Name'      => $player['court'] ?? '',
    'Playing Time'    => $player['play_time'] ?? '',
    'Blood Group'     => $player['blood_group'] ?? '',        // NEW
    'Playing Years'   => $player['playing_years'] ?? '',      // NEW
    'Mobile Number'   => $player['mobile'] ?? '',
    'Aadhaar Number'  => $player['aadhaar'] ?? '',
    'Status'          => ucfirst($player['status'] ?? 'pending'),
    'Created At'      => date('d M Y, h:i A', strtotime($player['created_at'] ?? ''))
];

// --- Fix photo and aadhaar_card links ---
function fix_path($url, $type = 'photo') {
    if (empty($url)) return '';
    // If already starts with http, return as is
    if (preg_match('/^https?:\/\//', $url)) return $url;
    // If starts with /userpanel, return as is
    if (strpos($url, '/userpanel/') === 0) return $url;
    // If starts with /adminpanel/storage/uploads, replace with /userpanel/storage/uploads
    if (strpos($url, '/adminpanel/storage/uploads/') === 0) {
        return str_replace('/adminpanel/storage/uploads/', '/userpanel/storage/uploads/', $url);
    }
    // If starts with adminpanel/storage/uploads, replace with userpanel/storage/uploads
    if (strpos($url, 'adminpanel/storage/uploads/') === 0) {
        return str_replace('adminpanel/storage/uploads/', 'userpanel/storage/uploads/', $url);
    }
    // If starts with ./ or ../, remove and prefix with /userpanel/storage/uploads
    if (strpos($url, './') === 0 || strpos($url, '../') === 0) {
        $basename = basename($url);
        if ($type === 'aadhaar') {
            return '/userpanel/storage/uploads/aadhaar/' . $basename;
        } else {
            return '/userpanel/storage/uploads/photos/' . $basename;
        }
    }
    // Otherwise, assume basename and prefix accordingly
    $basename = basename($url);
    if ($type === 'aadhaar') {
        return '/userpanel/storage/uploads/aadhaar/' . $basename;
    } else {
        return '/userpanel/storage/uploads/photos/' . $basename;
    }
}

$photo_url = !empty($player['photo']) ? fix_path($player['photo'], 'photo') : '/assets/default_user.png';
$aadhaar_card_url = !empty($player['aadhaar_card']) ? fix_path($player['aadhaar_card'], 'aadhaar') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Player Details | Latur Badminton League Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --bg:#f8fafc; --card:#fff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; }
        body { background:var(--bg); font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--text); margin:0;}
        .wrap { display:grid; place-items:center; min-height:100dvh; padding:16px;}
        .card { width:100%; max-width:520px; background:var(--card); border-radius:14px; box-shadow:0 6px 24px #2563eb14; padding:32px 22px; text-align:center;}
        .logo { width: 82px; height: 82px; margin:0 auto 14px auto; border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(37,99,235,0.10); overflow:hidden;}
        .logo img { width: 63px; height: 63px; }
        h1 { font-size:24px; text-align:center; margin-bottom:6px; color:var(--primary);}
        .sub { color:var(--muted); font-size:15px; margin-bottom:16px;}
        .view-table { width:100%; margin:0 auto 18px auto; border-radius:10px; background:#f1f5f9; padding:12px;}
        .view-table tr { border-bottom: 1px solid var(--border);}
        .view-table td { padding:11px 6px; font-size:15px; text-align:left;}
        .view-table td.label { color:var(--muted); font-weight:600; width:36%; }
        .view-table td.value { color:var(--text);}
        .img-thumb { width:74px; height:74px; object-fit:cover; border-radius:12px; border:2px solid var(--border); margin-bottom:8px;}
        .aadhaar-link { color:var(--primary); text-decoration:underline; font-size:13px;}
        .action-btn { display:inline-block; padding:10px 18px; border-radius:9px; font-size:16px; font-weight:600; text-decoration:none; margin-right:7px; margin-bottom:5px;}
        .edit-btn { background: #fbbf24; color:#111;}
        .back-btn { background: #64748b; color: #fff;}
        .accept-btn { background: #22c55e; color:#fff;}
        .reject-btn { background: #ef4444; color:#fff;}
        .accept-btn:hover { background:#166534;}
        .reject-btn:hover { background:#7f1d1d;}
        .edit-btn:hover { background:#b45309; color:#fff;}
        .back-btn:hover { background:#334155;}
        .note { font-size:13px; color:var(--muted); margin-top:18px;}
        @media (max-width:600px){.card{padding:14px 3px;} .logo{width:54px;height:54px;} .logo img{width:35px;height:35px;} h1{font-size:18px;} .img-thumb{width:44px;height:44px;} }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        <h1>Player Details</h1>
        <div class="sub">Full information for <b><?php echo h($player['full_name']); ?></b></div>
        <img src="<?php echo h($photo_url); ?>" class="img-thumb" alt="Player Photo"
                onerror="this.onerror=null;this.src='/assets/default_user.png';">
        <table class="view-table">
        <?php foreach ($fields as $label => $value): ?>
            <tr>
                <td class="label"><?php echo h($label); ?></td>
                <td class="value"><?php echo h($value); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!empty($aadhaar_card_url)): ?>
            <tr>
                <td class="label">Aadhaar Card</td>
                <td class="value"><a href="<?php echo h($aadhaar_card_url); ?>" target="_blank" class="aadhaar-link">View Aadhaar Card</a></td>
            </tr>
        <?php endif; ?>
        </table>
        <div>
            <a href="player_edit.php?id=<?php echo urlencode($player_id); ?>" class="action-btn edit-btn">Edit</a>
            <?php if (($player['status'] ?? 'pending') !== 'accepted'): ?>
                <a href="player_status.php?id=<?php echo urlencode($player_id); ?>&status=accepted" class="action-btn accept-btn">Accept</a>
            <?php endif; ?>
            <?php if (($player['status'] ?? 'pending') !== 'rejected'): ?>
                <a href="player_status.php?id=<?php echo urlencode($player_id); ?>&status=rejected" class="action-btn reject-btn" onclick="return confirm('Reject this player?');">Reject</a>
            <?php endif; ?>
            <a href="player_dashboard.php" class="action-btn back-btn">Back to Dashboard</a>
        </div>
        <div class="note">Powered by <b>LBL</b></div>
    </div>
</div>
</body>
</html>