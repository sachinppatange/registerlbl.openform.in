<?php
session_start();
require_once __DIR__ . '/../userpanel/auth.php';
require_once __DIR__ . '/player_repository.php';
require_once __DIR__ . '/../config/player_config.php';

// Optional configs
if (file_exists(__DIR__ . '/../config/app_config.php')) require_once __DIR__ . '/../config/app_config.php';
if (file_exists(__DIR__ . '/../config/wa_config.php')) require_once __DIR__ . '/../config/wa_config.php';

// Load Razorpay keys (config file or env)
$rz_cfg = [];
if (file_exists(__DIR__ . '/../config/razorpay_config.php')) $rz_cfg = (array) @include __DIR__ . '/../config/razorpay_config.php';
$KEY_ID = getenv('RAZORPAY_KEY_ID') ?: ($rz_cfg['key_id'] ?? '');
$KEY_SECRET = getenv('RAZORPAY_KEY_SECRET') ?: ($rz_cfg['key_secret'] ?? '');

// Authentication
require_auth();
$phone = current_user();

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// Ensure upload dirs
@mkdir(__DIR__ . '/storage/uploads/aadhaar', 0755, true);
@mkdir(__DIR__ . '/storage/uploads/photos', 0755, true);
@mkdir(__DIR__ . '/storage/logs', 0755, true);

// Helpers
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function log_debug($m){ @file_put_contents(__DIR__ . '/storage/logs/player_profile.log', date('c').' '.$m.PHP_EOL, FILE_APPEND|LOCK_EX); }

// Compute age group (server)
function compute_age_group_from_dob(?string $dob): string {
    if (empty($dob)) return '';
    $birth = strtotime($dob);
    if (!$birth) return '';
    $today = time();
    $age = (int)date('Y', $today) - (int)date('Y', $birth);
    $m = (int)date('n', $today) - (int)date('n', $birth);
    if ($m < 0 || ($m === 0 && (int)date('j', $today) < (int)date('j', $birth))) $age--;
    if ($age >= 30 && $age <= 40) return '30 to 40';
    if ($age >= 41 && $age <= 45) return '41 to 45';
    if ($age >= 46 && $age <= 50) return '46 to 50';
    if ($age >= 51 && $age <= 55) return '51 to 55';
    if ($age > 55) return 'Above 55';
    return '';
}

// Render payment status badge (keeps previous UI)
function render_payment_status_badge(?array $payment): string {
    if (empty($payment)) return '';
    $status = $payment['status'] ?? '';
    $amount = isset($payment['amount']) ? number_format(((int)$payment['amount'])/100, 2) : '';
    $order = $payment['order_id'] ?? '';
    $txn = $payment['payment_id'] ?? '';
    $when = $payment['updated_at'] ?? $payment['created_at'] ?? '';
    $badgeColor = 'background:#fef3c7;color:#92400e;';
    $label = 'Pending';
    if (strtolower($status) === 'paid' || strtolower($status) === 'success') { $badgeColor = 'background:#dcfce7;color:#014d2f;'; $label='Paid'; }
    if (strtolower($status) === 'failed') { $badgeColor = 'background:#fee2e2;color:#7f1d1d;'; $label='Failed'; }
    $html = '<div style="margin:12px 0;padding:10px;border-radius:8px;text-align:left;">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;">';
    $html .= '<div><strong>Payment Status:</strong> <span style="display:inline-block;padding:6px 10px;border-radius:999px;'.$badgeColor.'margin-left:8px;">'.h($label).'</span></div>';
    $html .= '<div style="color:#6b7280;font-size:13px;">'.h($when).'</div>';
    $html .= '</div>';
    $html .= '<div style="margin-top:8px;color:#374151;font-size:14px;">';
    if ($amount !== '') $html .= 'Amount: ₹'.h($amount).' &nbsp; ';
    if ($order) $html .= 'Order: '.h($order).' &nbsp; ';
    if ($txn) $html .= 'Txn: '.h($txn).' &nbsp; ';
    $html .= '</div>';
    if (!empty($txn)) $html .= '<div style="margin-top:8px;"><a href="payment/receipt.php?payment_id='.urlencode($txn).'">View receipt</a></div>';
    elseif (!empty($order)) $html .= '<div style="margin-top:8px;"><a href="payment/receipt.php?order_id='.urlencode($order).'">View receipt</a></div>';
    $html .= '</div>';
    return $html;
}

// Create Razorpay order (cURL)
function create_razorpay_order($keyId, $keySecret, $amount_paise, $receipt) {
    $payload = json_encode(['amount' => (int)$amount_paise, 'currency' => 'INR', 'receipt' => $receipt, 'payment_capture' => 1]);
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
    log_debug("CREATE_ORDER http={$httpCode} errno={$errno} err={$errstr} resp=" . substr((string)$resp,0,2000));
    if ($errno) return ['ok'=>false,'http'=>$httpCode,'error'=>"Network error: {$errstr}"];
    $json = json_decode($resp, true);
    if (!is_array($json) || ($httpCode < 200 || $httpCode >= 300)) {
        $desc = $json['error']['description'] ?? $json['error']['message'] ?? $resp;
        return ['ok'=>false,'http'=>$httpCode,'error'=>"Razorpay API error ({$httpCode}): {$desc}", 'raw'=>$resp];
    }
    return ['ok'=>true,'order'=>$json];
}

// Save profile and handle uploads (reuses repository functions)
function save_profile_request($phone, &$player, &$msg_error) {
    $required_fields = ['full_name','dob','village','court','play_time','blood_group','playing_years','mobile','aadhaar'];
    $missing = [];
    foreach ($required_fields as $rf) if (trim($_POST[$rf] ?? '') === '') $missing[] = $rf;
    if (!empty($missing)) {
        $labels = [
            'full_name'=>'Full name','dob'=>'Date of birth','age_group'=>'Age group','village'=>'Village/City',
            'court'=>'Court','play_time'=>'Playing time','blood_group'=>'Blood group','playing_years'=>'Playing years',
            'mobile'=>'Mobile','aadhaar'=>'Aadhaar number','aadhaar_card'=>'Aadhaar card','photo'=>'Photo'
        ];
        $miss_lbl = array_map(function($k) use ($labels){ return $labels[$k] ?? $k; }, $missing);
        $msg_error = 'Please fill/attach required fields: ' . implode(', ', $miss_lbl) . '.';
        return false;
    }

    $dob = trim($_POST['dob'] ?? '');
    $computed_age_group = compute_age_group_from_dob($dob);
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'dob' => $dob,
        'age_group' => $computed_age_group ?: trim($_POST['age_group'] ?? ''),
        'village' => trim($_POST['village'] ?? ''),
        'court' => trim($_POST['court'] ?? ''),
        'play_time' => trim($_POST['play_time'] ?? ''),
        'blood_group' => trim($_POST['blood_group'] ?? ''),
        'playing_years' => trim($_POST['playing_years'] ?? ''),
        'mobile' => trim($_POST['mobile'] ?? ''),
        'aadhaar' => trim($_POST['aadhaar'] ?? ''),
        'terms' => 1
    ];

    if (!player_save_or_update($phone, $data)) { $msg_error = 'Failed to save player profile.'; return false; }

    $player = player_get_by_phone($phone) ?: ['mobile'=>$phone];
    $player_id = $player['id'] ?? null;

    // Aadhaar upload
    if (!empty($_FILES['aadhaar_card']['name']) && $_FILES['aadhaar_card']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['aadhaar_card']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, PLAYER_AADHAAR_ALLOWED_EXT)) {
            $filename = $player_id ? "aadhaar_{$player_id}.{$ext}" : "aadhaar_".time().".{$ext}";
            $rel = "storage/uploads/aadhaar/{$filename}";
            $abs = __DIR__ . '/' . $rel;
            if (!empty($player['aadhaar_card']) && file_exists(__DIR__ . '/' . $player['aadhaar_card'])) @unlink(__DIR__ . '/' . $player['aadhaar_card']);
            if (@move_uploaded_file($_FILES['aadhaar_card']['tmp_name'], $abs)) $player['aadhaar_card'] = $rel;
            else $msg_error = 'Failed to upload Aadhaar card file.';
        } else $msg_error = 'Invalid Aadhaar card file type.';
    }

    // Photo upload
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, PLAYER_PHOTO_ALLOWED_EXT)) {
            $filename = $player_id ? "photo_{$player_id}.{$ext}" : "photo_".time().".{$ext}";
            $rel = "storage/uploads/photos/{$filename}";
            $abs = __DIR__ . '/' . $rel;
            if (!empty($player['photo']) && file_exists(__DIR__ . '/' . $player['photo'])) @unlink(__DIR__ . '/' . $player['photo']);
            if (@move_uploaded_file($_FILES['photo']['tmp_name'], $abs)) $player['photo'] = $rel;
            else $msg_error = 'Failed to upload Photo file.';
        } else $msg_error = 'Invalid Photo file type.';
    }

    // Update DB with file paths if changed
    $data_files = [];
    if (!empty($player['aadhaar_card'])) $data_files['aadhaar_card'] = $player['aadhaar_card'];
    if (!empty($player['photo'])) $data_files['photo'] = $player['photo'];
    if (!empty($data_files)) player_save_or_update($phone, array_merge($data, $data_files));

    return true;
}

// Load current player and latest payment (best-effort)
$player = player_get_by_phone($phone) ?? ['mobile'=>$phone];
$latestPayment = null;
try {
    if (!empty($player['id']) && function_exists('db')) {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE player_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$player['id']]);
        $latestPayment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) { log_debug('[player_profile] payments lookup error: '.$e->getMessage()); }

// AJAX endpoint: save profile and optionally create order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax']) && $_POST['ajax'] === '1')) {
    // CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'error'=>'CSRF mismatch']);
        exit;
    }

    $msg = '';
    if (!save_profile_request($phone, $player, $msg)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'error'=>$msg ?: 'Save failed']);
        exit;
    }

    $start_payment = isset($_POST['start_payment']) && trim($_POST['start_payment']) === '1';
    if ($start_payment) {
        if (empty($KEY_ID) || empty($KEY_SECRET)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'error'=>'Payment keys not configured on server']);
            exit;
        }
        $amount_rupees = floatval($_POST['payment_amount'] ?? (defined('DEFAULT_AMOUNT_RUPEES') ? DEFAULT_AMOUNT_RUPEES : 1.00));
        $amount_paise = max(100, (int)round($amount_rupees * 100));
        $receipt = 'player_' . ($player['id'] ?? 'na') . '_' . time();

        $ord = create_razorpay_order($KEY_ID, $KEY_SECRET, $amount_paise, $receipt);
        if (!$ord['ok']) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'error'=>$ord['error'] ?? 'Order creation failed','http'=>$ord['http'] ?? null]);
            exit;
        }

        $created_order = $ord['order'];
        // optional local save
        if (file_exists(__DIR__ . '/payment_repository.php')) {
            try {
                require_once __DIR__ . '/payment_repository.php';
                if (function_exists('payment_create_local_order')) {
                    payment_create_local_order([
                        'order_id'=>$created_order['id'] ?? null,
                        'user_mobile'=>$player['mobile'] ?? '',
                        'amount'=>$created_order['amount'] ?? $amount_paise,
                        'currency'=>$created_order['currency'] ?? 'INR',
                        'status'=>'created',
                        'meta'=>json_encode(['player_id'=>$player['id'] ?? null]),
                    ]);
                }
            } catch (Throwable $e) { log_debug('payment_create_local_order exception: '.$e->getMessage()); }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>true,'order'=>$created_order,'key_id'=>$KEY_ID]);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true]);
    exit;
}

// Non-AJAX POST (backwards compatibility) - keep original save flow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $msg_error = '';
    $msg_success = '';
    if (!save_profile_request($phone, $player, $msg_error)) {
        // $msg_error already set
    } else {
        $msg_success = 'Player profile saved successfully!';
    }
    // refresh latest payment
    try {
        if (!empty($player['id']) && function_exists('db')) {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE player_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$player['id']]);
            $latestPayment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) { log_debug('[player_profile] payments lookup error: '.$e->getMessage()); }
}

// Options (same as original)
$age_groups = ['30 to 40','41 to 45','46 to 50','51 to 55','Above 55'];
$blood_groups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$playing_years_options = []; for ($i=0;$i<=20;$i++){ $lbl = ($i===1?'1 Year':$i.' Years'); $playing_years_options[$lbl]=$lbl; } $playing_years_options['More than 20 Years']='More than 20 Years';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Player Registration / Profile</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --muted:#64748b; --bg:#f8fafc; --card:#fff; --border:#e2e8f0; }
        body{background:var(--bg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;color:#0f172a}
        .wrap{display:grid;place-items:center;min-height:100vh;padding:12px}
        .card{width:100%;max-width:430px;background:var(--card);border-radius:13px;padding:28px 18px;box-shadow:0 4px 16px rgba(37,99,235,0.08);text-align:center}
        .logo{width:82px;height:82px;margin:0 auto 10px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(37,99,235,0.10);overflow:hidden}
        .logo img{width:63px;height:63px}
        .topbar{display:flex;justify-content:flex-start;align-items:center;margin-bottom:10px}
        .link{ text-decoration:none;color:#fff;background:#0f172a;padding:8px 16px;border-radius:10px;font-size:15px;font-weight:600;margin-right:12px;}
        h1{font-size:22px;color:var(--primary);margin:0 0 8px}
        .sub{color:var(--muted);font-size:15px;margin-bottom:16px}
        label{font-weight:500;display:block;margin:12px 0 6px;text-align:left}
        input,select{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);font-size:15px}
        input[type=file]{padding:0}
        .row{margin-bottom:14px}
        .btn{width:100%;background:var(--primary);color:#fff;border-radius:10px;padding:13px 0;font-size:16px;font-weight:600;border:0;cursor:pointer;margin-top:14px}
        .btn.savepay{background:#16a34a}
        .msg{margin-bottom:10px;padding:8px;border-radius:8px;font-size:14px}
        .msg.success{background:#e0fce0;color:#166534}
        .msg.error{background:#fee2e2;color:#7f1d1d}
        .img-thumb{width:54px;height:54px;object-fit:cover;border-radius:9px;margin-bottom:6px;border:1px solid var(--border)}
        .lock-select{background:#e2e8f0;pointer-events:none}
        @media (max-width:500px){ .card{padding:12px 8px} .logo{width:54px;height:54px} .logo img{width:35px;height:35px} h1{font-size:18px} .btn{padding:10px 0;font-size:14px} }
    </style>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="logo"><img src="../assets/lbllogo.svg" alt="LBL Logo"></div>
    <div class="topbar"><a class="link" href="dashboard.php">&larr; Back to Dashboard</a></div>
    <h1>Player Registration / Profile</h1>
    <div class="sub">Fill your details for the Latur Badminton League registration</div>

    <?php echo render_payment_status_badge($latestPayment); ?>
    <?php if(!empty($msg_success)): ?><div class="msg success"><?php echo h($msg_success);?></div><?php endif;?>
    <?php if(!empty($msg_error)): ?><div class="msg error"><?php echo h($msg_error);?></div><?php endif;?>

    <form id="profileForm" onsubmit="return false;" method="post" enctype="multipart/form-data" autocomplete="off" style="text-align:left">
      <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
      <div class="row"><label for="full_name">Full Name</label><input required type="text" name="full_name" id="full_name" value="<?php echo h($player['full_name'] ?? ''); ?>"></div>
      <div class="row"><label for="mobile">Mobile Number</label><input required type="text" name="mobile" id="mobile" value="<?php echo h($player['mobile'] ?? $phone); ?>" maxlength="12" pattern="\d{10,12}" readonly></div>
      <div class="row"><label for="dob">Date of Birth</label><input required type="date" name="dob" id="dob" onchange="updateAgeGroup()" min="1970-01-01" max="<?php echo date('Y-m-d'); ?>" value="<?php echo h($player['dob'] ?? ''); ?>"></div>
      <div class="row"><label for="age_group">Age Group</label><select required name="age_group" id="age_group" readonly class="lock-select"><option value="">Select Age Group</option><?php foreach ($age_groups as $val): ?><option value="<?php echo h($val); ?>" <?php echo (($player['age_group'] ?? '') === $val) ? 'selected' : '';?>><?php echo h($val); ?></option><?php endforeach; ?></select></div>

      <div class="row"><label for="aadhaar">Aadhaar Number</label><input required type="text" name="aadhaar" id="aadhaar" maxlength="12" pattern="\d{12}" value="<?php echo h($player['aadhaar'] ?? ''); ?>"></div>
      <div class="row"><label for="village">Village/City Name</label><input required type="text" name="village" id="village" value="<?php echo h($player['village'] ?? ''); ?>"></div>
      <div class="row"><label for="court">Court Name</label><input required type="text" name="court" id="court" value="<?php echo h($player['court'] ?? ''); ?>"></div>
      <div class="row"><label for="play_time">Playing Time</label><input required type="text" name="play_time" id="play_time" value="<?php echo h($player['play_time'] ?? ''); ?>"></div>

      <div class="row"><label for="blood_group">Blood Group</label><select required name="blood_group" id="blood_group"><option value="">Select Blood Group</option><?php foreach ($blood_groups as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (($player['blood_group'] ?? '') === $key) ? 'selected' : '';?>><?php echo h($label); ?></option><?php endforeach; ?></select></div>

      <div class="row"><label for="playing_years">From how many years have you been playing badminton?</label><select required name="playing_years" id="playing_years"><option value="">Select</option><?php foreach ($playing_years_options as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (($player['playing_years'] ?? '') === $key) ? 'selected' : '';?>><?php echo h($label); ?></option><?php endforeach; ?></select></div>

      <div class="row"><label for="aadhaar_card">Upload Aadhaar Card</label><?php if (!empty($player['aadhaar_card'])): ?><a href="<?php echo h($player['aadhaar_card']); ?>" target="_blank">View Aadhaar Card</a><?php endif; ?><input <?php echo empty($player['aadhaar_card']) ? 'required' : ''; ?> type="file" name="aadhaar_card" id="aadhaar_card" accept=".jpg,.jpeg,.png,.pdf"></div>

      <div class="row"><label for="photo">Upload Photo</label><?php if (!empty($player['photo'])): ?><br><img src="<?php echo h($player['photo']); ?>" class="img-thumb" alt="Photo"><?php endif; ?><input <?php echo empty($player['photo']) ? 'required' : ''; ?> type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png"></div>

      <div class="terms"><input type="checkbox" name="terms" id="terms" checked disabled> <label for="terms">I confirm all information is correct and accept all terms and conditions.</label></div>

      <button id="savePayBtn" class="btn savepay" type="button">Save &amp; Pay</button>
    </form>
  </div>
</div>

<script>
// Ensure age group initial value is computed
(function(){
  const dobEl = document.getElementById('dob');
  if (dobEl && dobEl.value) updateAgeGroup();
})();

function updateAgeGroup() {
    const dobEl = document.getElementById('dob');
    const dob = dobEl.value;
    if (!dob) return;
    const birth = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    let group = '';
    if (age >= 30 && age <= 40) group = '30 to 40';
    else if (age >= 41 && age <= 45) group = '41 to 45';
    else if (age >= 46 && age <= 50) group = '46 to 50';
    else if (age >= 51 && age <= 55) group = '51 to 55';
    else if (age > 55) group = 'Above 55';
    document.getElementById('age_group').value = group;
}

// Centralized doSaveAndPay used by UI — performs AJAX create-order and opens checkout
async function doSaveAndPay(opts = {}) {
    const form = document.getElementById('profileForm');
    if (!form) throw new Error('Form not found');
    if (!form.reportValidity()) throw new Error('Please fill required fields');

    const fd = new FormData(form);
    fd.set('ajax','1');
    fd.set('csrf','<?php echo h($csrf); ?>');
    fd.set('start_payment','1');
    fd.set('payment_amount', opts.amountRupees || '<?php echo h((string)(defined("DEFAULT_AMOUNT_RUPEES") ? DEFAULT_AMOUNT_RUPEES : 1.00)); ?>');

    const res = await fetch(window.location.href, { method:'POST', body: fd, credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' } });
    const json = await res.json().catch(()=>null);
    if (!json) throw new Error('Unexpected server response');
    if (!json.success) throw new Error(json.error || 'Order creation failed');
    if (!json.order || !json.key_id) throw new Error('Invalid order data returned');
    // open Razorpay Checkout
    const order = json.order;
    const options = {
        key: json.key_id,
        amount: order.amount,
        currency: order.currency || 'INR',
        name: opts.prefillName || document.getElementById('full_name').value || 'LBL Participant',
        description: 'Registration fee',
        order_id: order.id,
        prefill: { name: opts.prefillName || document.getElementById('full_name').value || '', contact: document.getElementById('mobile').value || '' },
        theme: { color: '#2563eb' },
        handler: function (res) {
            // Post verification to receipt
            const f = document.createElement('form'); f.method='POST'; f.action='../web/paymentrecipt.php';
            [['razorpay_payment_id',res.razorpay_payment_id],['razorpay_order_id',res.razorpay_order_id],['razorpay_signature',res.razorpay_signature]].forEach(function(pair){
                const i=document.createElement('input'); i.type='hidden'; i.name=pair[0]; i.value=pair[1]||''; f.appendChild(i);
            });
            document.body.appendChild(f); f.submit();
        },
        modal: {
            ondismiss: function(){ alert('Payment cancelled'); }
        }
    };
    const rzp = new Razorpay(options);
    rzp.open();
}

// Wire Save & Pay button to save via AJAX then call doSaveAndPay
(function(){
    const savePayBtn = document.getElementById('savePayBtn');
    const profileForm = document.getElementById('profileForm');

    async function saveProfileAjax() {
        if (!profileForm.reportValidity()) return { success:false, error:'Please fill required fields' };
        const fd = new FormData(profileForm);
        fd.set('ajax','1');
        fd.set('csrf','<?php echo h($csrf); ?>');
        const res = await fetch(window.location.href, { method:'POST', body: fd, credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' } });
        const json = await res.json().catch(()=>null);
        if (json) return json;
        const text = await res.text().catch(()=>'');
        if (text.indexOf('Player profile saved successfully!') !== -1) return { success:true };
        return { success:false, error:'Unexpected server response' };
    }

    savePayBtn.addEventListener('click', async function(){
        savePayBtn.disabled = true;
        savePayBtn.textContent = 'Saving...';
        try {
            const saveRes = await saveProfileAjax();
            if (!saveRes || !saveRes.success) {
                alert('Save failed: ' + (saveRes && saveRes.error ? saveRes.error : 'Unknown error'));
                savePayBtn.disabled = false;
                savePayBtn.textContent = 'Save & Pay';
                return;
            }

            savePayBtn.textContent = 'Starting payment...';
            try {
                await doSaveAndPay({ amountRupees: '<?php echo h((string)(defined("DEFAULT_AMOUNT_RUPEES") ? DEFAULT_AMOUNT_RUPEES : 1.00)); ?>', prefillName: document.getElementById('full_name').value });
                // doSaveAndPay will redirect on success; if returns, reset UI
                savePayBtn.disabled = false;
                savePayBtn.textContent = 'Save & Pay';
            } catch (err) {
                console.error(err);
                alert('Payment initialization failed: ' + (err.message || err));
                savePayBtn.disabled = false;
                savePayBtn.textContent = 'Save & Pay';
            }
        } catch (err) {
            console.error(err);
            alert('Save failed: ' + (err.message || err));
            savePayBtn.disabled = false;
            savePayBtn.textContent = 'Save & Pay';
        }
    });
})();
</script>
</body>
</html>