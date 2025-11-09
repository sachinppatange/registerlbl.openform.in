<?php
session_start();
require_once __DIR__ . '/../userpanel/auth.php';
require_once __DIR__ . '/player_repository.php';

require_auth();

$e164 = current_user() ?? '';

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Format phone as +CC AAA BBB CCCC (best-effort)
$displayPhone = '+' . $e164;
if ($e164) {
    $ccLen = 2; // adjust if needed
    $cc = substr($e164, 0, $ccLen);
    $local = substr($e164, $ccLen);
    if (strlen($local) === 10) {
        $displayPhone = "+$cc " . substr($local,0,3) . " " . substr($local,3,3) . " " . substr($local,6);
    }
}

// Load player profile from players table
$player = player_get_by_phone($e164) ?? [
    'mobile' => $e164,
    'full_name' => '',
    'dob' => '',
    'age_group' => '',
    'village' => '',
    'court' => '',
    'play_time' => '',
    'blood_group' => '',        // added
    'playing_years' => '',      // added
    'aadhaar' => '',
    'aadhaar_card' => '',
    'photo' => '',
    'status' => 'pending',
];

// Registration completeness calculation
$fields = ['full_name','dob','age_group','village','court','play_time','blood_group','playing_years','aadhaar','aadhaar_card','photo']; // added new fields
$filled = 0; foreach ($fields as $f) { if (!empty($player[$f])) $filled++; }
$percent = (int) round($filled / count($fields) * 100);

// Status label color
$status_labels = [
    'pending'  => ['Pending',   '#eab308'],
    'accepted' => ['Accepted',  '#22c55e'],
    'rejected' => ['Rejected',  '#ef4444'],
];
$status_val     = strtolower($player['status'] ?? 'pending');
$status_display = $status_labels[$status_val][0] ?? ucfirst($status_val);
$status_color   = $status_labels[$status_val][1] ?? '#64748b';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Player Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon as SVG logo -->
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --secondary:#0ea5e9; --bg:#f8fafc; --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { min-height:100dvh; display:grid; place-items:center; padding:16px; }
        .card { width:100%; max-width:580px; background:var(--card); border-radius:18px; box-shadow:0 10px 40px rgba(2,8,23,.09); padding:32px; }
        .logo { width: 110px; height: 110px; margin: 0 auto 18px auto; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(37,99,235,0.10); overflow: hidden; }
        .logo img { width: 82px; height: 82px; }
        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:10px; }
        .nav-left { display:flex; gap:10px; flex-wrap:wrap; }
        .link { text-decoration:none; color:#fff; background:#2563eb; padding:9px 18px; border-radius:12px; font-size:15px; font-weight:600; box-shadow:0 2px 12px #2563eb15; }
        .link.logout { background:#ef4444; }
        h1 { margin:0 0 8px; font-size:28px; text-align:center; color:var(--primary);}
        .sub { text-align:center; color:var(--muted); margin-bottom:18px; font-size:16px;}
        .panel { background:#f8fafc; border:1px dashed var(--border); border-radius:14px; padding:18px; margin-bottom:22px;}
        .row { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .k { color:#0f172a; font-weight:600; font-size:15px;}
        .v { color:#0f172a; white-space:pre-wrap; font-size:15px;}
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:14px 18px; border:0; border-radius:14px; background:var(--primary); color:#fff; font-size:15px; font-weight:600; cursor:pointer; box-shadow:0 6px 16px rgba(37,99,235,.18); text-decoration:none; }
        .btn.block { display:block; width:100%; margin-top:14px; }
        .copy { background:#e2e8f0; border:0; padding:8px 12px; border-radius:10px; cursor:pointer; font-size:15px;}
        .pill { display:inline-block; padding:4px 12px; background:#eef2ff; color:#1e293b; border-radius:999px; font-size:13px; margin-right:6px; }
        .muted { color:var(--muted); }
        .player-details { display:grid; gap:10px; margin-top:14px; }
        .profile-img { width:70px; height:70px; object-fit:cover; border-radius:12px; border:1px solid var(--border); margin-bottom:12px;}
        .status-label { display:inline-block; padding:5px 14px; border-radius:999px; font-weight:600; font-size:15px; color:#fff; background:<?php echo $status_color; ?>; margin-top:8px; margin-bottom:8px;}
        .actions-row { display:flex; gap:16px; justify-content:center; margin:28px 0 0 0;}
        .progress { height:12px; background:#e2e8f0; border-radius:999px; overflow:hidden; margin:8px 0;}
        .progress-bar { height:100%; background:#2563eb; }
        .percent-txt { font-size:13px; color:var(--primary); font-weight:600; float:right; }
        @media (max-width:600px) {
            .card { padding:18px; }
            .panel { padding:12px; }
            .logo { width: 64px; height: 64px;}
            .logo img { width: 46px; height: 46px;}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        <div class="topbar">
            <div class="nav-left"></div>
            <a class="link logout" href="logout.php">Logout</a>
        </div>

        <h1>Player Dashboard</h1>
        <div class="sub">Badminton Tournament Online Registration System</div>

        <div class="panel">
            <div class="row">
                <div>
                    <div class="k">User ID (Phone)</div>
                    <div class="v" id="phoneDisp"><?php echo h($displayPhone); ?></div>
                </div>
                <button class="copy" id="copyBtn" type="button" title="Copy phone">Copy</button>
            </div>
            <div class="percent-txt"><?php echo $percent; ?>% Registration Complete</div>
            <div class="progress"><div class="progress-bar" style="width:<?php echo $percent; ?>%;"></div></div>
            <div class="status-label"><?php echo h($status_display); ?></div>
        </div>

        <div class="player-details">
            <?php if (!empty($player['photo'])): ?>
                <div style="text-align:center;">
                    <img src="<?php echo h($player['photo']); ?>" class="profile-img" alt="Photo">
                </div>
            <?php endif; ?>
            <?php if (!empty($player['full_name'])): ?>
                <div><span class="pill">Full Name</span> <strong><?php echo h($player['full_name']); ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($player['dob'])): ?>
                <div><span class="pill">Date of Birth</span> <?php echo h($player['dob']); ?></div>
            <?php endif; ?>
            <?php if (!empty($player['age_group'])): ?>
                <div><span class="pill">Age Group</span> <?php echo h($player['age_group']); ?></div>
            <?php endif; ?>
            <?php if (!empty($player['village'])): ?>
                <div><span class="pill">Village/City</span> <?php echo h($player['village']); ?></div>
            <?php endif; ?>
            <?php if (!empty($player['court'])): ?>
                <div><span class="pill">Court Name</span> <?php echo h($player['court']); ?></div>
            <?php endif; ?>
            <?php if (!empty($player['play_time'])): ?>
                <div><span class="pill">Playing Time</span> <?php echo h($player['play_time']); ?></div>
            <?php endif; ?>

            <?php if (!empty($player['blood_group'])): ?>
                <div><span class="pill">Blood Group</span> <?php echo h($player['blood_group']); ?></div>
            <?php endif; ?>

            <?php if (!empty($player['playing_years'])): ?>
                <div><span class="pill">Playing Years</span> <?php echo h($player['playing_years']); ?></div>
            <?php endif; ?>

            <?php if (!empty($player['aadhaar'])): ?>
                <div><span class="pill">Aadhaar Number</span> <?php echo h($player['aadhaar']); ?></div>
            <?php endif; ?>
            <?php if (!empty($player['aadhaar_card'])): ?>
                <div><span class="pill">Aadhaar Card</span>
                    <a href="<?php echo h($player['aadhaar_card']); ?>" target="_blank">View Aadhaar Card</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="actions-row">
            <a class="btn block" style="background:var(--secondary);" href="player_profile.php">Player Registration and Edit / Update Profile</a>
        </div>
        <div class="muted" style="margin-top:14px;">
            Use the above button to register, edit, and update your player profile.
        </div>
    </div>
</div>

<script>
    // Copy phone
    const copyBtn = document.getElementById('copyBtn');
    const phoneDisp = document.getElementById('phoneDisp');
    if (copyBtn && phoneDisp) {
        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(phoneDisp.textContent.trim());
                copyBtn.textContent = 'Copied!';
                setTimeout(()=> copyBtn.textContent='Copy', 1400);
            } catch (_) {
                copyBtn.textContent = 'Copy failed';
                setTimeout(()=> copyBtn.textContent='Copy', 1400);
            }
        });
    }
</script>
</body>
</html>