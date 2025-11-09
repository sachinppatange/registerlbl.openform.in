<?php
// Admin Player Edit: Edit player info by admin (updated to include blood_group & playing_years)

session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/player_repository.php';

// --- Authentication: Redirect if not logged in as admin ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=player_edit.php');
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

// --- Handle form submit ---
$msg_success = '';
$msg_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect POST data
    $data = [
        'full_name'     => trim($_POST['full_name'] ?? ''),
        'dob'           => trim($_POST['dob'] ?? ''),
        'age_group'     => trim($_POST['age_group'] ?? ''),
        'village'       => trim($_POST['village'] ?? ''),
        'court'         => trim($_POST['court'] ?? ''),
        'play_time'     => trim($_POST['play_time'] ?? ''),
        'blood_group'   => trim($_POST['blood_group'] ?? ''),
        'playing_years' => trim($_POST['playing_years'] ?? ''),
        'mobile'        => trim($_POST['mobile'] ?? ''),
        'aadhaar'       => trim($_POST['aadhaar'] ?? ''),
        'status'        => trim($_POST['status'] ?? 'pending'),
    ];

    // File upload logic
    $aadhaar_card_path = $player['aadhaar_card'] ?? '';
    $photo_path = $player['photo'] ?? '';

    // Ensure upload dirs exist
    $aadhaarDir = __DIR__ . '/../userpanel/storage/uploads/aadhaar';
    $photoDir = __DIR__ . '/../userpanel/storage/uploads/photos';
    if (!is_dir($aadhaarDir)) @mkdir($aadhaarDir, 0775, true);
    if (!is_dir($photoDir)) @mkdir($photoDir, 0775, true);

    if (!empty($_FILES['aadhaar_card']['name']) && $_FILES['aadhaar_card']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['aadhaar_card']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','pdf'])) {
            $basename = 'aadhaar_' . $player_id . '.' . $ext;
            $relTarget = "../userpanel/storage/uploads/aadhaar/" . $basename;
            $absTarget = $aadhaarDir . '/' . $basename;
            // remove previous file if exists
            if (!empty($aadhaar_card_path)) {
                $prev = __DIR__ . '/../' . ltrim($aadhaar_card_path, '/');
                @unlink($prev);
            }
            if (move_uploaded_file($_FILES['aadhaar_card']['tmp_name'], $absTarget)) {
                $aadhaar_card_path = $relTarget;
            }
        }
    }
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            $basename = 'photo_' . $player_id . '.' . $ext;
            $relTarget = "../userpanel/storage/uploads/photos/" . $basename;
            $absTarget = $photoDir . '/' . $basename;
            if (!empty($photo_path)) {
                $prev = __DIR__ . '/../' . ltrim($photo_path, '/');
                @unlink($prev);
            }
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $absTarget)) {
                $photo_path = $relTarget;
            }
        }
    }
    $data['aadhaar_card'] = $aadhaar_card_path;
    $data['photo'] = $photo_path;

    // Update player
    if (update_player($player_id, $data)) {
        $msg_success = "Player details updated successfully!";
        $player = get_player_by_id($player_id);
    } else {
        $msg_error = "Failed to update player details.";
    }
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$age_groups = [
    '30 to 40'    => '30 to 40',
    '41 to 45'    => '41 to 45',
    '46 to 50'    => '46 to 50',
    '51 to 55'    => '51 to 55',
    'Above 55'    => 'Above 55',
];
$status_options = [
    'pending'  => 'Pending',
    'accepted' => 'Accepted',
    'rejected' => 'Rejected',
];

// Blood group options
$blood_groups = [
    '' => 'Select Blood Group',
    'A+'  => 'A+',
    'A-'  => 'A-',
    'B+'  => 'B+',
    'B-'  => 'B-',
    'AB+' => 'AB+',
    'AB-' => 'AB-',
    'O+'  => 'O+',
    'O-'  => 'O-',
];

// Playing years options: 0..20 and "More than 20 Years"
$playing_years_options = ['' => 'Select Playing Years'];
for ($i = 0; $i <= 20; $i++) {
    $label = ($i === 1) ? '1 Year' : $i . ' Years';
    $playing_years_options[$label] = $label;
}
$playing_years_options['More than 20 Years'] = 'More than 20 Years';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Player | Latur Badminton League Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --bg:#f8fafc; --card:#fff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0;}
        body { background:var(--bg); font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--text); margin:0;}
        .wrap { display:grid; place-items:center; min-height:100dvh; padding:16px;}
        .card { width:100%; max-width:520px; background:var(--card); border-radius:14px; box-shadow:0 6px 24px #2563eb14; padding:32px 22px; text-align:center;}
        .logo { width: 82px; height: 82px; margin:0 auto 14px auto; border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(37,99,235,0.10); overflow:hidden;}
        .logo img { width: 63px; height: 63px; }
        h1 { font-size:24px; text-align:center; margin-bottom:6px; color:var(--primary);}
        .sub { color:var(--muted); font-size:15px; margin-bottom:16px;}
        label { font-weight:500; display:block; margin:10px 0 5px; text-align:left;}
        input, select { width:100%; padding:9px; border-radius:10px; border:1px solid var(--border); font-size:15px; margin-bottom:4px;}
        input[type="file"] { padding:0;}
        .row { margin-bottom:12px;}
        .btn { width:100%; background:var(--primary); color:#fff; border-radius:10px; padding:13px 0; font-size:16px; font-weight:600; border:0; cursor:pointer; margin-top:14px;}
        .msg { margin-bottom:10px; padding:8px; border-radius:8px; font-size:14px;}
        .msg.success { background:#e0fce0; color:#166534;}
        .msg.error { background:#fee2e2; color:#7f1d1d;}
        .img-thumb { width:54px; height:54px; object-fit:cover; border-radius:9px; margin-bottom:6px; border:1px solid var(--border);}
        .back-link { text-decoration:none; color:#fff; background:#64748b; padding:8px 16px; border-radius:10px; font-size:15px; font-weight:600; margin-bottom:16px; display:inline-block;}
        @media (max-width:600px){.card{padding:14px 3px;} .logo{width:54px;height:54px;} .logo img{width:35px;height:35px;} h1{font-size:18px;}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        <a href="player_dashboard.php" class="back-link">&larr; Back to Dashboard</a>
        <h1>Edit Player</h1>
        <div class="sub">Update player details below</div>
        <?php if($msg_success): ?><div class="msg success"><?php echo h($msg_success);?></div><?php endif;?>
        <?php if($msg_error): ?><div class="msg error"><?php echo h($msg_error);?></div><?php endif;?>
        <form method="post" enctype="multipart/form-data" autocomplete="off" style="text-align:left;">
            <div class="row">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" value="<?php echo h($player['full_name']); ?>">
            </div>
            <div class="row">
                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" id="dob" value="<?php echo h($player['dob']); ?>">
            </div>
            <div class="row">
                <label for="age_group">Age Group</label>
                <select name="age_group" id="age_group">
                    <option value="">Select Age Group</option>
                    <?php foreach ($age_groups as $val): ?>
                        <option value="<?php echo h($val); ?>" <?php echo ($player['age_group'] === $val ? 'selected' : ''); ?>><?php echo h($val); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <label for="village">Village/City Name</label>
                <input type="text" name="village" id="village" value="<?php echo h($player['village']); ?>">
            </div>
            <div class="row">
                <label for="court">Court Name</label>
                <input type="text" name="court" id="court" value="<?php echo h($player['court']); ?>">
            </div>
            <div class="row">
                <label for="play_time">Playing Time</label>
                <input type="text" name="play_time" id="play_time" value="<?php echo h($player['play_time']); ?>">
            </div>

            <!-- NEW: Blood Group -->
            <div class="row">
                <label for="blood_group">Blood Group</label>
                <select name="blood_group" id="blood_group">
                    <?php foreach ($blood_groups as $key => $label): ?>
                        <option value="<?php echo h($key); ?>" <?php echo (isset($player['blood_group']) && $player['blood_group'] === $key) ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- NEW: Playing Years -->
            <div class="row">
                <label for="playing_years">Playing Years</label>
                <select name="playing_years" id="playing_years">
                    <?php foreach ($playing_years_options as $key => $label): ?>
                        <option value="<?php echo h($key); ?>" <?php echo (isset($player['playing_years']) && $player['playing_years'] === $key) ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <label for="mobile">Mobile Number</label>
                <input type="text" name="mobile" id="mobile" maxlength="12" value="<?php echo h($player['mobile']); ?>">
            </div>
            <div class="row">
                <label for="aadhaar">Aadhaar Number</label>
                <input type="text" name="aadhaar" id="aadhaar" maxlength="12" value="<?php echo h($player['aadhaar']); ?>">
            </div>
            <div class="row">
                <label for="aadhaar_card">Upload Aadhaar Card</label>
                <?php if (!empty($player['aadhaar_card'])): ?>
                    <a href="<?php echo h($player['aadhaar_card']); ?>" target="_blank">View Aadhaar Card</a>
                <?php endif; ?>
                <input type="file" name="aadhaar_card" id="aadhaar_card" accept=".jpg,.jpeg,.png,.pdf">
            </div>
            <div class="row">
                <label for="photo">Upload Photo</label>
                <?php if (!empty($player['photo'])): ?>
                    <br><img src="<?php echo h($player['photo']); ?>" class="img-thumb" alt="Photo">
                <?php endif; ?>
                <input type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png">
            </div>
            <div class="row">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <?php foreach ($status_options as $key => $label): ?>
                        <option value="<?php echo h($key); ?>" <?php echo ($player['status'] === $key ? 'selected' : ''); ?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit">Update Player</button>
        </form>
    </div>
</div>
</body>
</html>