<?php
session_start();
require_once __DIR__ . '/../userpanel/auth.php';
require_once __DIR__ . '/player_repository.php';
require_once __DIR__ . '/../config/player_config.php';
$razorpay_cfg = require __DIR__ . '/../config/razorpay_config.php';

// Optional: try to load Razorpay PHP SDK if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../razorpay-php/Razorpay.php')) {
    require_once __DIR__ . '/../razorpay-php/Razorpay.php';
}

use Razorpay\Api\Api;

// Authentication: Only logged-in users can fill/edit profile
require_auth();
$phone = current_user();

// CSRF Protection
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$msg_error = '';
$msg_success = '';
$should_start_payment = false;
$razorpay_order = null;

// Load existing player profile if available
$player = player_get_by_phone($phone) ?? ['mobile' => $phone];

// Directory paths
$aadhaarDir = __DIR__ . '/storage/uploads/aadhaar';
$photoDir   = __DIR__ . '/storage/uploads/photos';

// Ensure upload folders exist
if (!is_dir($aadhaarDir)) mkdir($aadhaarDir, 0777, true);
if (!is_dir($photoDir)) mkdir($photoDir, 0777, true);

// Helper: compute age group from dob (server-side)
function compute_age_group_from_dob(?string $dob): string {
    if (empty($dob)) return '';
    $birth = strtotime($dob);
    if (!$birth) return '';
    $today = time();
    $age = (int)date('Y', $today) - (int)date('Y', $birth);
    $m = (int)date('n', $today) - (int)date('n', $birth);
    if ($m < 0 || ($m === 0 && (int)date('j', $today) < (int)date('j', $birth))) {
        $age--;
    }
    if ($age >= 30 && $age <= 40) return '30 to 40';
    if ($age >= 41 && $age <= 45) return '41 to 45';
    if ($age >= 46 && $age <= 50) return '46 to 50';
    if ($age >= 51 && $age <= 55) return '51 to 55';
    if ($age > 55) return 'Above 55';
    return '';
}

// Constraint: disallow DOB after 1995-11-01
$max_dob = '1995-11-01'; // 1 Nov 1995
$min_dob = '1945-01-01'; // optional lower bound

// Check if the form requested a payment start after save (POST)
$start_payment_requested = (isset($_POST['start_payment']) && trim($_POST['start_payment']) === '1');

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {

    // Basic required fields to validate (server-side)
    $required_fields = [
        'full_name',
        'dob',
        'village',
        'court',
        'play_time',
        'blood_group',
        'playing_years',
        'mobile',
        'aadhaar',
    ];

    $missing = [];
    foreach ($required_fields as $rf) {
        if (trim($_POST[$rf] ?? '') === '') {
            $missing[] = $rf;
        }
    }

    // Server-side DOB check: must be on or before $max_dob
    $dob_input = trim($_POST['dob'] ?? '');
    if ($dob_input) {
        $dob_ts = strtotime($dob_input);
        $min_ts = strtotime($min_dob);
        $max_ts = strtotime($max_dob);
        if ($dob_ts === false || $dob_ts < $min_ts || $dob_ts > $max_ts) {
            $missing[] = 'dob';
            $msg_error = "Please select a valid Date of Birth between {$min_dob} and {$max_dob}. Dates after {$max_dob} are not allowed.";
        }
    }

    // Age group must be derived from dob (ensure server computes it)
    $computed_age_group = compute_age_group_from_dob(trim($_POST['dob'] ?? ''));
    if ($computed_age_group === '') {
        // If no valid age group, consider age_group missing
        if (trim($_POST['age_group'] ?? '') === '') {
            $missing[] = 'age_group';
        }
    }

    // For files: if user already has uploaded file (in DB) it's acceptable; otherwise require upload
    if (empty($player['aadhaar_card']) && (empty($_FILES['aadhaar_card']['name']) || $_FILES['aadhaar_card']['error'] !== 0)) {
        $missing[] = 'aadhaar_card';
    }
    if (empty($player['photo']) && (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== 0)) {
        $missing[] = 'photo';
    }

    if (!empty($missing) && empty($msg_error)) {
        // Friendly labels mapping
        $labels = [
            'full_name' => 'Full name',
            'dob' => 'Date of birth',
            'age_group' => 'Age group (auto-calculated from DOB)',
            'village' => 'Village/City',
            'court' => 'Court',
            'play_time' => 'Playing time',
            'blood_group' => 'Blood group',
            'playing_years' => 'Playing years',
            'mobile' => 'Mobile',
            'aadhaar' => 'Aadhaar number',
            'aadhaar_card' => 'Aadhaar card upload',
            'photo' => 'Photo upload',
        ];
        $missing_labels = array_map(function($k) use ($labels) { return $labels[$k] ?? $k; }, $missing);
        $msg_error = 'Please fill/attach required fields: ' . implode(', ', $missing_labels) . '.';
    } elseif (empty($missing) && empty($msg_error)) {
        // All required present — prepare data (exclude file paths for initial save)
        $data = [
            'full_name'      => trim($_POST['full_name'] ?? ''),
            'dob'            => trim($_POST['dob'] ?? ''),
            'age_group'      => $computed_age_group ?: trim($_POST['age_group'] ?? ''),
            'village'        => trim($_POST['village'] ?? ''),
            'court'          => trim($_POST['court'] ?? ''),
            'play_time'      => trim($_POST['play_time'] ?? ''),
            'blood_group'    => trim($_POST['blood_group'] ?? ''),
            'playing_years'  => trim($_POST['playing_years'] ?? ''),
            'mobile'         => trim($_POST['mobile'] ?? ''),
            'aadhaar'        => trim($_POST['aadhaar'] ?? ''),
            'terms'          => 1, // Always set to 1, auto-selected
        ];

        // STEP 1: Save/update basic data first (without file paths) to ensure we have an ID
        $saved = player_save_or_update($phone, $data);
        if (!$saved) {
            $msg_error = "Failed to save player profile.";
        } else {
            // Refresh player to get ID
            $player = player_get_by_phone($phone) ?? $player;
            $player_id = $player['id'] ?? null;

            // Prepare file paths using player ID (if available)
            $aadhaar_card_path = $player['aadhaar_card'] ?? '';
            $photo_path = $player['photo'] ?? '';

            // File upload logic: use player ID in filename (overwrite if exists)
            if (!empty($_FILES['aadhaar_card']['name']) && $_FILES['aadhaar_card']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['aadhaar_card']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, PLAYER_AADHAAR_ALLOWED_EXT)) {
                    // Build filename using ID (keep extension)
                    if ($player_id) {
                        $filename = "aadhaar_" . $player_id . "." . $ext;
                    } else {
                        // Fallback if no id (shouldn't happen) — timestamp
                        $filename = "aadhaar_" . time() . "." . $ext;
                    }
                    $target = "storage/uploads/aadhaar/" . $filename;
                    $absTarget = __DIR__ . '/' . $target;
                    // If a previous file exists, try unlink (ignore failure)
                    if (!empty($aadhaar_card_path) && file_exists(__DIR__ . '/' . $aadhaar_card_path)) {
                        @unlink(__DIR__ . '/' . $aadhaar_card_path);
                    }
                    if (move_uploaded_file($_FILES['aadhaar_card']['tmp_name'], $absTarget)) {
                        $aadhaar_card_path = $target;
                    } else {
                        $msg_error = "Failed to upload Aadhaar card file.";
                    }
                } else {
                    $msg_error = "Invalid Aadhaar card file type.";
                }
            }

            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, PLAYER_PHOTO_ALLOWED_EXT)) {
                    if ($player_id) {
                        $filename = "photo_" . $player_id . "." . $ext;
                    } else {
                        $filename = "photo_" . time() . "." . $ext;
                    }
                    $target = "storage/uploads/photos/" . $filename;
                    $absTarget = __DIR__ . '/' . $target;
                    if (!empty($photo_path) && file_exists(__DIR__ . '/' . $photo_path)) {
                        @unlink(__DIR__ . '/' . $photo_path);
                    }
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $absTarget)) {
                        $photo_path = $target;
                    } else {
                        $msg_error = "Failed to upload Photo file.";
                    }
                } else {
                    $msg_error = "Invalid Photo file type.";
                }
            }

            // STEP 2: Update DB with file paths (if any changes)
            $data_files = [];
            if ($aadhaar_card_path !== ($player['aadhaar_card'] ?? '')) $data_files['aadhaar_card'] = $aadhaar_card_path;
            if ($photo_path !== ($player['photo'] ?? '')) $data_files['photo'] = $photo_path;

            if (!empty($data_files)) {
                // Merge into $data for update
                $data_update = array_merge($data, $data_files);
                if (!player_save_or_update($phone, $data_update)) {
                    $msg_error = $msg_error ? $msg_error . ' Also failed to update file paths in DB.' : 'Failed to update file paths in DB.';
                } else {
                    // Success
                    if (empty($msg_error)) {
                        $msg_success = "Player profile saved successfully!";
                        $player = player_get_by_phone($phone) ?? $player;
                    }
                }
            } else {
                // No file changes; already saved basic data
                if (empty($msg_error)) {
                    $msg_success = "Player profile saved successfully!";
                    $player = player_get_by_phone($phone) ?? $player;
                }
            }
        }
    }

    // If user requested to start payment after successful save, create a Razorpay order server-side and open Checkout
    if ($start_payment_requested && empty($msg_error) && !empty($msg_success)) {
        // derive amount from posted field (in rupees) or use demo default (1)
        $amt_rupees = (float)str_replace(',', '.', ($_POST['payment_amount'] ?? '1'));
        // Enforce minimum 100 paise (₹1) to avoid 1 paise issues in demo
        $amount_paise = max(100, (int) round($amt_rupees * 100));

        // Create Razorpay order using API keys from config
        $keyId = $razorpay_cfg['key_id'] ?? '';
        $keySecret = $razorpay_cfg['key_secret'] ?? '';

        if (empty($keyId) || empty($keySecret)) {
            $msg_error = 'Payment configuration missing. Please contact admin.';
        } else {
            try {
                // Use SDK if available, otherwise fallback to cURL
                if (class_exists('\Razorpay\Api\Api')) {
                    $api = new Api($keyId, $keySecret);
                    $receipt = 'player_' . ($player['id'] ?? 'na') . '_' . time();
                    $order = $api->order->create([
                        'amount' => $amount_paise,
                        'currency' => 'INR',
                        'receipt' => $receipt,
                        'payment_capture' => 1,
                    ]);
                    $razorpay_order = $order;
                } else {
                    // cURL fallback
                    $receipt = 'player_' . ($player['id'] ?? 'na') . '_' . time();
                    $payload = json_encode([
                        'amount' => $amount_paise,
                        'currency' => 'INR',
                        'receipt' => $receipt,
                        'payment_capture' => 1,
                    ]);
                    $ch = curl_init('https://api.razorpay.com/v1/orders');
                    curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    $resp = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $razorpay_order = json_decode($resp, true);
                    } else {
                        error_log('Razorpay order create failed: ' . $resp);
                        $msg_error = 'Failed to initiate payment. Try again later.';
                    }
                }

                if ($razorpay_order) {
                    // Optionally record order in local payments table (if you have a payments repo)
                    if (function_exists('payment_create_local_order')) {
                        // payment_create_local_order should be implemented in your payment_repository.php
                        @payment_create_local_order([
                            'order_id' => $razorpay_order['id'] ?? $razorpay_order->id ?? null,
                            'user_mobile' => $phone,
                            'amount' => $amount_paise,
                            'currency' => 'INR',
                            'status' => 'created',
                            'meta' => json_encode(['player_id' => $player['id'] ?? null]),
                        ]);
                    }

                    // Prepare to open checkout on page render
                    $should_start_payment = true;
                    $start_payment_amount_paise = $amount_paise;
                    // store success message as flash for GET render
                    $_SESSION['flash_msg_success'] = $msg_success;
                } else {
                    if (empty($msg_error)) $msg_error = 'Unable to create payment order.';
                }
            } catch (Throwable $e) {
                error_log('Razorpay create order exception: ' . $e->getMessage());
                $msg_error = 'Failed to initiate payment. Please try again.';
            }
        }
    }
}

// Age Group options
$age_groups = [
    '30 to 40'    => '30 to 40',
    '41 to 45'    => '41 to 45',
    '46 to 50'    => '46 to 50',
    '51 to 55'    => '51 to 55',
    'Above 55'    => 'Above 55',
];

// Blood group options
$blood_groups = [
    'A+'  => 'A+',
    'A-'  => 'A-',
    'B+'  => 'B+',
    'B-'  => 'B-',
    'AB+' => 'AB+',
    'AB-' => 'AB-',
    'O+'  => 'O+',
    'O-'  => 'O-',
];

// Playing years options: individual entries from 0 to 20, plus "More than 20 Years"
$playing_years_options = [];
for ($i = 0; $i <= 20; $i++) {
    $label = ($i === 1) ? '1 Year' : $i . ' Years';
    $playing_years_options[$label] = $label;
}
$playing_years_options['More than 20 Years'] = 'More than 20 Years';

// Helper for escaping output
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Default payment amount (rupees) — demo uses 1 INR
$default_payment_amount = 1; // rupees (demo)

// If we created an order server-side, determine its id to pass to client
$created_order_id = null;
if (!empty($razorpay_order)) {
    $created_order_id = is_array($razorpay_order) ? ($razorpay_order['id'] ?? null) : ($razorpay_order->id ?? null);
    // ensure start_payment amount is set
    if (empty($start_payment_amount_paise) && !empty($razorpay_order['amount'])) {
        $start_payment_amount_paise = (int)($razorpay_order['amount']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Player Registration / Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon as SVG logo -->
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --secondary:#0ea5e9; --bg:#f8fafc; --card:#fff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0;}
        body { background:var(--bg); font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--text); margin:0;}
        .wrap { display:grid; place-items:center; min-height:100dvh; padding:12px;}
        .card { width:100%; max-width:430px; background:var(--card); border-radius:13px; box-shadow:0 4px 16px #2563eb14; padding:28px 18px; text-align:center;}
        .logo { width: 82px; height: 82px; margin:0 auto 10px auto; border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(37,99,235,0.10); overflow:hidden; }
        .logo img { width: 63px; height: 63px; }
        .topbar { display:flex; justify-content:flex-start; align-items:center; margin-bottom:10px; }
        .link { text-decoration:none; color:#fff; background:#0f172a; padding:8px 16px; border-radius:10px; font-size:15px; font-weight:600; margin-right:12px; }
        h1 { font-size:22px; text-align:center; margin-bottom:8px; color:var(--primary);}
        .sub { color:var(--muted); font-size:15px; margin-bottom:16px;}
        label { font-weight:500; display:block; margin:12px 0 6px; text-align:left;}
        input, select { width:100%; padding:10px; border-radius:10px; border:1px solid var(--border); font-size:15px; }
        input[type="file"] { padding:0;}
        .row { margin-bottom:14px;}
        .btn { width:100%; background:var(--primary); color:#fff; border-radius:10px; padding:13px 0; font-size:16px; font-weight:600; border:0; cursor:pointer; margin-top:14px;}
        .btn.secondary { background:#0ea5e9; margin-top:8px; }
        .msg { margin-bottom:10px; padding:8px; border-radius:8px; font-size:14px;}
        .msg.success { background:#e0fce0; color:#166534;}
        .msg.error { background:#fee2e2; color:#7f1d1d;}
        .terms { margin-top:12px; font-size:14px;}
        .lock-select { background:#e2e8f0; pointer-events:none;}
        .img-thumb { width:54px; height:54px; object-fit:cover; border-radius:9px; margin-bottom:6px; border:1px solid var(--border);}
        @media (max-width:500px){
            .card { max-width:100%; padding:12px 2px;}
            .logo { width: 54px; height: 54px;}
            .logo img { width: 35px; height: 35px;}
            h1 { font-size:18px;}
            .btn { padding:10px 0; font-size:14px;}
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
            <a class="link" href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>Player Registration / Profile</h1>
        <div class="sub">Fill your details for the Latur Badminton League registration</div>

        <?php if(!empty($_SESSION['flash_msg_success'])): ?>
            <div class="msg success"><?php echo h($_SESSION['flash_msg_success']); unset($_SESSION['flash_msg_success']); ?></div>
        <?php endif; ?>
        <?php if($msg_error): ?><div class="msg error"><?php echo h($msg_error);?></div><?php endif;?>
        <?php if($msg_success && empty($_SESSION['flash_msg_success'])): ?><div class="msg success"><?php echo h($msg_success);?></div><?php endif;?>

        <form id="profileForm" method="post" enctype="multipart/form-data" autocomplete="off" style="text-align:left;">
            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
            <!-- Hidden controls for payment flow -->
            <input type="hidden" name="start_payment" id="start_payment" value="1"> <!-- always start payment after Save & Pay click (single action) -->
            <input type="hidden" name="payment_amount" id="payment_amount" value="<?php echo h($default_payment_amount); ?>">

            <div class="row">
                <label for="full_name">Full Name</label>
                <input required type="text" name="full_name" id="full_name" value="<?php echo h($player['full_name'] ?? ''); ?>">
            </div>

            <div class="row">
                <label for="mobile">Mobile Number</label>
                <input required type="text" name="mobile" id="mobile" value="<?php echo h($player['mobile'] ?? $phone); ?>" maxlength="12" pattern="\d{10,12}" readonly>
            </div>

            <div class="row">
                <label for="dob">Date of Birth</label>
                <input required type="date" name="dob" id="dob" onchange="(function(){const e=document.getElementById('dob');const d=new Date(e.value);if(!isNaN(d))document.getElementById('age_group').value=(function(age){if(age>=30&&age<=40)return'30 to 40';if(age>=41&&age<=45)return'41 to 45';if(age>=46&&age<=50)return'46 to 50';if(age>=51&&age<=55)return'51 to 55';if(age>55)return'Above 55';return'';})(new Date().getFullYear()-d.getFullYear()-((new Date().getMonth()<d.getMonth()|| (new Date().getMonth()===d.getMonth() && new Date().getDate()<d.getDate()))?1:0));})()" min="<?php echo h($min_dob); ?>" max="<?php echo h($max_dob); ?>" value="<?php echo h($player['dob'] ?? ''); ?>">
            </div>
            <div class="row">
                <label for="age_group">Age Group</label>
                <select required name="age_group" id="age_group" readonly class="lock-select">
                    <option value="">Select Age Group</option>
                    <?php foreach ($age_groups as $val): ?>
                        <option value="<?php echo h($val); ?>" <?php echo (($player['age_group'] ?? '') === $val) ? 'selected' : '';?>><?php echo h($val); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <label for="aadhaar">Aadhaar Number</label>
                <input required type="text" name="aadhaar" id="aadhaar" maxlength="12" pattern="\d{12}" value="<?php echo h($player['aadhaar'] ?? ''); ?>">
            </div>

            <div class="row">
                <label for="village">Village/City Name</label>
                <input required type="text" name="village" id="village" value="<?php echo h($player['village'] ?? ''); ?>">
            </div>
            <div class="row">
                <label for="court">Court Name</label>
                <input required type="text" name="court" id="court" value="<?php echo h($player['court'] ?? ''); ?>">
            </div>
            <div class="row">
                <label for="play_time">Playing Time</label>
                <input required type="text" name="play_time" id="play_time" value="<?php echo h($player['play_time'] ?? ''); ?>">
            </div>

            <!-- Blood Group -->
            <div class="row">
                <label for="blood_group">Blood Group</label>
                <select required name="blood_group" id="blood_group">
                    <option value="">Select Blood Group</option>
                    <?php foreach ($blood_groups as $key => $label): ?>
                        <option value="<?php echo h($key); ?>" <?php echo (($player['blood_group'] ?? '') === $key) ? 'selected' : '';?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Playing Years: 0 to 20 as separate options -->
            <div class="row">
                <label for="playing_years">From how many years have you been playing badminton?</label>
                <select required name="playing_years" id="playing_years">
                    <option value="">Select</option>
                    <?php foreach ($playing_years_options as $key => $label): ?>
                        <option value="<?php echo h($key); ?>" <?php echo (($player['playing_years'] ?? '') === $key) ? 'selected' : '';?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <label for="aadhaar_card">Upload Aadhaar Card</label>
                <?php if (!empty($player['aadhaar_card'])): ?>
                    <a href="<?php echo h($player['aadhaar_card']); ?>" target="_blank">View Aadhaar Card</a>
                <?php endif; ?>
                <input <?php echo empty($player['aadhaar_card']) ? 'required' : ''; ?> type="file" name="aadhaar_card" id="aadhaar_card" accept=".jpg,.jpeg,.png,.pdf">
            </div>
            <div class="row">
                <label for="photo">Upload Photo</label>
                <?php if (!empty($player['photo'])): ?>
                    <br><img src="<?php echo h($player['photo']); ?>" class="img-thumb" alt="Photo">
                <?php endif; ?>
                <input <?php echo empty($player['photo']) ? 'required' : ''; ?> type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png">
            </div>
            <div class="terms">
                <input type="checkbox" name="terms" id="terms" checked disabled>
                <label for="terms">I confirm all information is correct and accept all terms and conditions.</label>
            </div>

            <!-- Single action button that saves profile and triggers payment server-side -->
            <button class="btn secondary" type="submit" id="savePayBtn">Save &amp; Pay (₹<?php echo h($default_payment_amount); ?> Demo)</button>
        </form>
    </div>
</div>

<!-- Include Razorpay Checkout.js (client) -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
/*
 Client behavior:
 - If server created an order during POST, PHP sets JS vars below and we immediately open Checkout.
 - The handler sends the razorpay response to /userpanel/razorpay_callback.php via POST (JSON or form) so server verifies signature.
*/
(function(){
    const createdOrderId = <?php echo json_encode($created_order_id); ?>;
    const startAmountPaise = <?php echo json_encode((int)$start_payment_amount_paise); ?>;
    const publicKey = <?php echo json_encode($razorpay_cfg['key_id'] ?? ''); ?>;
    const prefillMobile = <?php echo json_encode($player['mobile'] ?? $phone); ?>;
    const callbackEndpoint = '/userpanel/razorpay_callback.php';

    function openRazorpay(orderId, amountPaise) {
        if (!publicKey) {
            console.error('Razorpay public key not configured.');
            alert('Payment cannot start: missing configuration.');
            return;
        }
        const options = {
            key: publicKey,
            amount: amountPaise,
            currency: 'INR',
            name: 'Latur Badminton League',
            description: 'Player registration fee',
            order_id: orderId,
            prefill: { contact: prefillMobile },
            theme: { color: '#2563eb' },
            handler: function (response) {
                // Send payment details to server for verification & finalization
                const payload = new FormData();
                payload.append('razorpay_payment_id', response.razorpay_payment_id || '');
                payload.append('razorpay_order_id', response.razorpay_order_id || '');
                payload.append('razorpay_signature', response.razorpay_signature || '');
                // include CSRF for server (if required)
                payload.append('csrf', '<?php echo h($csrf); ?>');

                fetch(callbackEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: payload,
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(function(res){ return res.json(); })
                .then(function(data){
                    if (data && data.success) {
                        // redirect to success page if provided else reload profile
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            alert('Payment successful.');
                            window.location.reload();
                        }
                    } else {
                        console.error('Payment verification failed', data);
                        alert('Payment verification failed. Please contact support.');
                        // optional redirect if provided
                        if (data && data.redirect) window.location.href = data.redirect;
                    }
                }).catch(function(err){
                    console.error('Error while verifying payment:', err);
                    alert('Payment was made but server verification failed. Contact support.');
                });
            },
            modal: {
                ondismiss: function() {
                    // user closed the checkout
                    console.info('Razorpay checkout dismissed by user.');
                    // reload or show message
                }
            }
        };
        const rzp = new Razorpay(options);
        rzp.open();
    }

    // If an order was created server-side, open checkout automatically
    if (createdOrderId && startAmountPaise > 0) {
        // open after small timeout to ensure UI updated
        setTimeout(function(){
            openRazorpay(createdOrderId, startAmountPaise);
        }, 300);
    }
})();
</script>
</body>
</html>