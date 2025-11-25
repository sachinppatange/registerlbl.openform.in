<?php
/**
 * web/data.php
 *
 * Payment page that reuses player logic and config from:
 *  - userpanel/player_repository.php (player_get_by_phone, player_save_or_update)
 *  - userpanel/auth.php (current_user)
 *  - config/app_config.php, config/wa_config.php (optional)
 *  - config/razorpay_config.php (required for orders)
 *
 * Flow:
 *  - If request POST with ajax=1 and start_payment=1: save profile, create Razorpay order and return JSON { success, order, key_id }
 *  - If request POST with ajax=1 and no start_payment: just save and return { success:true }
 *  - Non-AJAX: render a simple form prefilled from current player (if logged in) or ?mobile=...
 *
 * Notes:
 *  - Put your Razorpay keys in config/razorpay_config.php:
 *      return ['key_id'=>'rzp_test_xxx','key_secret'=>'yyy'];
 *  - Use test keys for local development.
 */

session_start();

// Prefer repository and auth from userpanel
require_once __DIR__ . '/../userpanel/player_repository.php';
require_once __DIR__ . '/../userpanel/auth.php';

// Load app/wa/razorpay configs if present
if (file_exists(__DIR__ . '/../config/app_config.php')) require_once __DIR__ . '/../config/app_config.php';
if (file_exists(__DIR__ . '/../config/wa_config.php')) require_once __DIR__ . '/../config/wa_config.php';

$rz_cfg = [];
if (file_exists(__DIR__ . '/../config/razorpay_config.php')) {
    $rz_cfg = (array) @include __DIR__ . '/../config/razorpay_config.php';
}

// Prefer environment variables over config file
$KEY_ID = getenv('RAZORPAY_KEY_ID') ?: ($rz_cfg['key_id'] ?? '');
$KEY_SECRET = getenv('RAZORPAY_KEY_SECRET') ?: ($rz_cfg['key_secret'] ?? '');

// Helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function debug_log($m){ @file_put_contents(__DIR__.'/logs/data_debug.log', date('c').' '.$m.PHP_EOL, FILE_APPEND|LOCK_EX); }

// Get current player (if logged-in) or by GET mobile
$player = null;
$phone = null;
if (function_exists('current_user')) {
    try { $phone = current_user(); } catch (Throwable $e) { $phone = null; }
}
if ($phone && function_exists('player_get_by_phone')) {
    $player = player_get_by_phone($phone) ?: null;
}
if (empty($player) && !empty($_GET['mobile']) && function_exists('player_get_by_phone')) {
    $phone = trim($_GET['mobile']);
    $player = player_get_by_phone($phone) ?: null;
}

// Default payment amount (INR)
$DEFAULT_PAYMENT_RUPEES = defined('DEFAULT_AMOUNT_RUPEES') ? DEFAULT_AMOUNT_RUPEES : 1.00;

// Create Razorpay order via cURL
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

    debug_log("CREATE_ORDER http={$httpCode} errno={$errno} err={$errstr} resp=" . substr((string)$resp,0,2000));

    if ($errno) return ['ok'=>false,'error'=>"Network error: {$errstr}", 'http'=>$httpCode];
    $json = json_decode($resp, true);
    if (!is_array($json) || ($httpCode < 200 || $httpCode >= 300)) {
        $desc = $json['error']['description'] ?? $json['error']['message'] ?? $resp;
        return ['ok'=>false,'error'=>"Razorpay API error ({$httpCode}): {$desc}", 'http'=>$httpCode, 'raw'=>$resp];
    }
    return ['ok'=>true,'order'=>$json];
}

// Save profile helper (uses player_repository functions)
function save_profile_ajax(&$player_out, &$msg_error) {
    if (!function_exists('player_save_or_update')) { $msg_error = 'player repository missing'; return false; }

    $phone = trim($_POST['mobile'] ?? '');
    if ($phone === '') { $msg_error = 'Mobile required'; return false; }

    $required = ['full_name','dob','village','court','play_time','blood_group','playing_years','mobile','aadhaar'];
    $missing = [];
    foreach ($required as $f) if (trim($_POST[$f] ?? '') === '') $missing[] = $f;
    if (!empty($missing)) { $msg_error = 'Missing: ' . implode(', ', $missing); return false; }

    // compute age_group
    $dob = trim($_POST['dob'] ?? '');
    $age_group = trim($_POST['age_group'] ?? '');
    if (empty($age_group) && $dob) {
        $b = strtotime($dob);
        if ($b) {
            $today = time();
            $age = (int)date('Y',$today) - (int)date('Y',$b);
            $m = (int)date('n',$today) - (int)date('n',$b);
            if ($m < 0 || ($m === 0 && (int)date('j',$today) < (int)date('j',$b))) $age--;
            if ($age>=30 && $age<=40) $age_group='30 to 40';
            elseif ($age>=41 && $age<=45) $age_group='41 to 45';
            elseif ($age>=46 && $age<=50) $age_group='46 to 50';
            elseif ($age>=51 && $age<=55) $age_group='51 to 55';
            elseif ($age>55) $age_group='Above 55';
        }
    }

    $data = [
        'full_name'=>trim($_POST['full_name']),
        'dob'=>$dob,
        'age_group'=>$age_group,
        'village'=>trim($_POST['village']),
        'court'=>trim($_POST['court']),
        'play_time'=>trim($_POST['play_time']),
        'blood_group'=>trim($_POST['blood_group']),
        'playing_years'=>trim($_POST['playing_years']),
        'mobile'=>$phone,
        'aadhaar'=>trim($_POST['aadhaar']),
        'terms'=>1
    ];

    if (!player_save_or_update($phone, $data)) { $msg_error = 'Failed to save profile'; return false; }

    $player_out = player_get_by_phone($phone) ?: ['mobile'=>$phone];

    // handle optional file uploads (store under userpanel storage)
    if (!empty($_FILES['aadhaar_card']['name']) && $_FILES['aadhaar_card']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['aadhaar_card']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, PLAYER_AADHAAR_ALLOWED_EXT)) {
            $pid = $player_out['id'] ?? null;
            $fn = $pid ? "aadhaar_{$pid}.{$ext}" : "aadhaar_".time().".{$ext}";
            $rel = "userpanel/storage/uploads/aadhaar/{$fn}";
            $abs = __DIR__ . '/' . $rel;
            if (!is_dir(dirname($abs))) @mkdir(dirname($abs),0755,true);
            @move_uploaded_file($_FILES['aadhaar_card']['tmp_name'], $abs);
            $player_out['aadhaar_card'] = $rel;
        }
    }
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, PLAYER_PHOTO_ALLOWED_EXT)) {
            $pid = $player_out['id'] ?? null;
            $fn = $pid ? "photo_{$pid}.{$ext}" : "photo_".time().".{$ext}";
            $rel = "userpanel/storage/uploads/photos/{$fn}";
            $abs = __DIR__ . '/' . $rel;
            if (!is_dir(dirname($abs))) @mkdir(dirname($abs),0755,true);
            @move_uploaded_file($_FILES['photo']['tmp_name'], $abs);
            $player_out['photo'] = $rel;
        }
    }

    // update file paths in DB if present
    $upd = [];
    if (!empty($player_out['aadhaar_card'])) $upd['aadhaar_card'] = $player_out['aadhaar_card'];
    if (!empty($player_out['photo'])) $upd['photo'] = $player_out['photo'];
    if (!empty($upd)) player_save_or_update($phone, array_merge($data,$upd));

    return true;
}

// AJAX endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax']) && $_POST['ajax'] === '1')) {
    $err = '';
    $savedPlayer = null;
    if (!save_profile_ajax($savedPlayer, $err)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'error'=>$err]);
        exit;
    }

    $start_payment = (isset($_POST['start_payment']) && trim($_POST['start_payment']) === '1');
    if ($start_payment) {
        if (empty($KEY_ID) || empty($KEY_SECRET)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'error'=>'Payment keys not configured on server']);
            exit;
        }

        $amount_rupees = floatval($_POST['payment_amount'] ?? $DEFAULT_PAYMENT_RUPEES);
        $amount_paise = max(100, (int)round($amount_rupees * 100));
        $receipt = 'player_' . ($savedPlayer['id'] ?? 'na') . '_' . time();

        $orderResp = create_razorpay_order($KEY_ID, $KEY_SECRET, $amount_paise, $receipt);
        if (!$orderResp['ok']) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'error'=>$orderResp['error'] ?? 'Order creation failed','http'=>$orderResp['http'] ?? null]);
            exit;
        }

        $created_order = $orderResp['order'];

        // optional local save of order
        if (file_exists(__DIR__ . '/../userpanel/payment_repository.php')) {
            try {
                require_once __DIR__ . '/../userpanel/payment_repository.php';
                if (function_exists('payment_create_local_order')) {
                    payment_create_local_order([
                        'order_id'=>$created_order['id'] ?? null,
                        'user_mobile'=>$savedPlayer['mobile'] ?? '',
                        'amount'=>$created_order['amount'] ?? $amount_paise,
                        'currency'=>$created_order['currency'] ?? 'INR',
                        'status'=>'created',
                        'meta'=>json_encode(['player_id'=>$savedPlayer['id'] ?? null]),
                    ]);
                }
            } catch (Throwable $e) { debug_log('payment_create_local_order failed: '.$e->getMessage()); }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>true,'order'=>$created_order,'key_id'=>$KEY_ID]);
        exit;
    }

    // save-only
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true]);
    exit;
}

// Non-AJAX: render page prefilled from $player
$prefill = $player ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Player Registration / Pay</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <style> body{background:#f8fafc;font-family:system-ui;padding:18px} .card{max-width:720px;margin:18px auto;padding:20px;background:#fff;border-radius:10px} </style>
</head>
<body>
  <div class="card">
    <h3>Player Registration / Pay</h3>
    <div id="messages"></div>

    <form id="profileForm" enctype="multipart/form-data" onsubmit="return false;">
      <input type="hidden" name="ajax" value="1">
      <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf'] ?? ''); ?>">
      <div class="row">
        <div class="col-md-6 mb-2"><label>Full name</label><input name="full_name" class="form-control" required value="<?php echo h($prefill['full_name'] ?? ''); ?>"></div>
        <div class="col-md-6 mb-2"><label>Mobile</label><input name="mobile" class="form-control" required value="<?php echo h($prefill['mobile'] ?? ''); ?>"></div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-2"><label>DOB</label><input name="dob" type="date" class="form-control" value="<?php echo h($prefill['dob'] ?? ''); ?>"></div>
        <div class="col-md-6 mb-2"><label>Age group</label><input name="age_group" class="form-control" readonly value="<?php echo h($prefill['age_group'] ?? ''); ?>"></div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-2"><label>Aadhaar</label><input name="aadhaar" class="form-control" maxlength="12" value="<?php echo h($prefill['aadhaar'] ?? ''); ?>"></div>
        <div class="col-md-6 mb-2"><label>Village/City</label><input name="village" class="form-control" value="<?php echo h($prefill['village'] ?? ''); ?>"></div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-2"><label>Court</label><input name="court" class="form-control" value="<?php echo h($prefill['court'] ?? ''); ?>"></div>
        <div class="col-md-6 mb-2"><label>Playing time</label><input name="play_time" class="form-control" value="<?php echo h($prefill['play_time'] ?? ''); ?>"></div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-2">
          <label>Blood group</label>
          <select name="blood_group" class="form-control">
            <option value="">Select</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): $sel = (($prefill['blood_group'] ?? '')===$bg)?'selected':''; ?>
              <option value="<?php echo h($bg); ?>" <?php echo $sel; ?>><?php echo h($bg); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 mb-2"><label>Playing years</label><select name="playing_years" class="form-control">
          <?php for($i=0;$i<=20;$i++){ $label = ($i===1?'1 Year':$i.' Years'); $sel = ((($prefill['playing_years'] ?? '')===$label)?' selected':''); ?>
            <option value="<?php echo h($label); ?>" <?php echo $sel; ?>><?php echo h($label); ?></option>
          <?php } ?>
          <option value="More than 20 Years" <?php echo ((($prefill['playing_years'] ?? '')==='More than 20 Years')?' selected':''); ?>>More than 20 Years</option>
        </select></div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-2"><label>Upload Aadhaar card</label><input type="file" name="aadhaar_card" class="form-control"></div>
        <div class="col-md-6 mb-2"><label>Upload Photo</label><input type="file" name="photo" class="form-control"></div>
      </div>

      <input type="hidden" id="payment_amount" name="payment_amount" value="<?php echo h((string)$DEFAULT_PAYMENT_RUPEES); ?>">
      <div class="text-center mt-3">
        <button id="savePayBtn" class="btn btn-success">Save &amp; Pay INR <?php echo h((string)$DEFAULT_PAYMENT_RUPEES); ?></button>
      </div>
    </form>
    <div id="status" class="mt-3"></div>
  </div>

<script>
(async function(){
  const form = document.getElementById('profileForm');
  const btn = document.getElementById('savePayBtn');
  const status = document.getElementById('status');

  function setStatus(m, err=false){ status.textContent = m; status.style.color = err ? '#7f1d1d' : '#2563eb'; }

  async function saveAndPay() {
    if (!form.reportValidity()) { setStatus('Please fill required fields', true); return; }
    btn.disabled = true; btn.textContent = 'Saving...'; setStatus('Saving profile...');

    const fd = new FormData(form);
    fd.append('ajax','1');
    fd.append('start_payment','1');

    try {
      const resp = await fetch(window.location.href, { method:'POST', body: fd, credentials:'same-origin' });
      const json = await resp.json().catch(()=>null);
      if (!json || !json.success) {
        setStatus('Server save/order creation failed: ' + (json && json.error ? json.error : 'Unknown'), true);
        btn.disabled=false; btn.textContent='Save & Pay INR <?php echo h((string)$DEFAULT_PAYMENT_RUPEES); ?>';
        return;
      }
      if (json.order && json.key_id) {
        setStatus('Opening payment...');
        openCheckout(json.key_id, json.order);
      } else {
        setStatus('Saved profile (no order returned)', false);
        btn.disabled=false; btn.textContent='Save & Pay INR <?php echo h((string)$DEFAULT_PAYMENT_RUPEES); ?>';
      }
    } catch (e) {
      console.error(e);
      setStatus('Network/server error. See console.', true);
      btn.disabled=false; btn.textContent='Save & Pay INR <?php echo h((string)$DEFAULT_PAYMENT_RUPEES); ?>';
    }
  }

  function openCheckout(keyId, order) {
    const options = {
      key: keyId,
      amount: order.amount,
      currency: order.currency || 'INR',
      name: form.querySelector('[name="full_name"]').value || 'Participant',
      description: 'Registration fee',
      order_id: order.id,
      prefill: { name: form.querySelector('[name="full_name"]').value || '', contact: form.querySelector('[name="mobile"]').value || '' },
      theme: { color: '#2563eb' },
      handler: function(res) {
        // POST to receipt for verification
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = 'paymentrecipt.php';
        const add = (n,v) => { const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; f.appendChild(i); };
        add('razorpay_payment_id', res.razorpay_payment_id || '');
        add('razorpay_order_id', res.razorpay_order_id || '');
        add('razorpay_signature', res.razorpay_signature || '');
        document.body.appendChild(f);
        f.submit();
      },
      modal: { ondismiss: function(){ setStatus('Checkout dismissed'); btn.disabled=false; btn.textContent='Save & Pay INR <?php echo h((string)$DEFAULT_PAYMENT_RUPEES); ?>'; } }
    };
    const rz = new Razorpay(options);
    rz.open();
  }

  btn.addEventListener('click', function(e){ e.preventDefault(); saveAndPay(); });
})();
</script>
</body>
</html>