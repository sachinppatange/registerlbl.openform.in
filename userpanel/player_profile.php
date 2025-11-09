<?php
session_start();
require_once __DIR__ . '/../userpanel/auth.php';
require_once __DIR__ . '/player_repository.php';

// Load configs
$razorpay_cfg = [];
$config_path = __DIR__ . '/../config/razorpay_config.php';
if (file_exists($config_path)) {
    $razorpay_cfg = require $config_path;
}

// Authentication
require_auth();
$phone = current_user();

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// Messages / state
$msg_error = '';
$msg_success = '';
$created_order = null;
$should_start_payment = false;
$start_payment_amount_paise = 0;

// Load existing player
$player = player_get_by_phone($phone) ?? ['mobile' => $phone];

// Ensure upload folders & logs
@mkdir(__DIR__ . '/storage/uploads/aadhaar', 0755, true);
@mkdir(__DIR__ . '/storage/uploads/photos', 0755, true);
@mkdir(__DIR__ . '/storage/logs', 0755, true);

// Helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rp_log($m){ @file_put_contents(__DIR__.'/storage/logs/razorpay_debug.log', date('c').' '.$m.PHP_EOL, FILE_APPEND|LOCK_EX); }

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

// DOB constraints
$max_dob = '1995-11-01';
$min_dob = '1945-01-01';

// Detect payment request
$start_payment_requested = (isset($_POST['start_payment']) && trim($_POST['start_payment']) === '1');

// Handle form submit: save profile and optionally create Razorpay order (₹1 demo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {
    // Required fields
    $required_fields = ['full_name','dob','village','court','play_time','blood_group','playing_years','mobile','aadhaar'];
    $missing = [];
    foreach ($required_fields as $rf) {
        if (trim($_POST[$rf] ?? '') === '') $missing[] = $rf;
    }

    // DOB validation
    $dob_input = trim($_POST['dob'] ?? '');
    if ($dob_input) {
        $dob_ts = strtotime($dob_input);
        if ($dob_ts === false || $dob_ts < strtotime($min_dob) || $dob_ts > strtotime($max_dob)) {
            $missing[] = 'dob';
            $msg_error = "Please select a valid Date of Birth between {$min_dob} and {$max_dob}.";
        }
    }

    // Age group compute
    $computed_age_group = compute_age_group_from_dob($dob_input);
    if ($computed_age_group === '' && trim($_POST['age_group'] ?? '') === '') $missing[] = 'age_group';

    // Files if not already present
    if (empty($player['aadhaar_card']) && (empty($_FILES['aadhaar_card']['name']) || $_FILES['aadhaar_card']['error'] !== 0)) $missing[] = 'aadhaar_card';
    if (empty($player['photo']) && (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== 0)) $missing[] = 'photo';

    if (!empty($missing) && empty($msg_error)) {
        $labels = [
            'full_name'=>'Full name','dob'=>'Date of birth','age_group'=>'Age group','village'=>'Village/City',
            'court'=>'Court','play_time'=>'Playing time','blood_group'=>'Blood group','playing_years'=>'Playing years',
            'mobile'=>'Mobile','aadhaar'=>'Aadhaar number','aadhaar_card'=>'Aadhaar card','photo'=>'Photo'
        ];
        $missing_labels = array_map(fn($k)=>$labels[$k] ?? $k, $missing);
        $msg_error = 'Please fill/attach required fields: ' . implode(', ', $missing_labels) . '.';
    } else {
        // Prepare data
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'dob' => $dob_input,
            'age_group' => $computed_age_group ?: trim($_POST['age_group'] ?? ''),
            'village' => trim($_POST['village'] ?? ''),
            'court' => trim($_POST['court'] ?? ''),
            'play_time' => trim($_POST['play_time'] ?? ''),
            'blood_group' => trim($_POST['blood_group'] ?? ''),
            'playing_years' => trim($_POST['playing_years'] ?? ''),
            'mobile' => trim($_POST['mobile'] ?? ''),
            'aadhaar' => trim($_POST['aadhaar'] ?? ''),
            'terms' => 1,
        ];

        // Save basic data
        if (!player_save_or_update($phone, $data)) {
            $msg_error = "Failed to save player profile.";
        } else {
            // Refresh
            $player = player_get_by_phone($phone) ?? $player;
            $player_id = $player['id'] ?? null;

            // Handle uploads
            $aadhaar_card_path = $player['aadhaar_card'] ?? '';
            $photo_path = $player['photo'] ?? '';

            if (!empty($_FILES['aadhaar_card']['name']) && $_FILES['aadhaar_card']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['aadhaar_card']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, PLAYER_AADHAAR_ALLOWED_EXT)) {
                    $filename = $player_id ? "aadhaar_{$player_id}.{$ext}" : "aadhaar_".time().".{$ext}";
                    $absTarget = __DIR__ . "/storage/uploads/aadhaar/{$filename}";
                    if (@move_uploaded_file($_FILES['aadhaar_card']['tmp_name'], $absTarget)) {
                        $aadhaar_card_path = 'userpanel/storage/uploads/aadhaar/' . $filename;
                    } else $msg_error = "Failed to upload Aadhaar card file.";
                } else $msg_error = "Invalid Aadhaar card file type.";
            }

            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, PLAYER_PHOTO_ALLOWED_EXT)) {
                    $filename = $player_id ? "photo_{$player_id}.{$ext}" : "photo_".time().".{$ext}";
                    $absTarget = __DIR__ . "/storage/uploads/photos/{$filename}";
                    if (@move_uploaded_file($_FILES['photo']['tmp_name'], $absTarget)) {
                        $photo_path = 'userpanel/storage/uploads/photos/' . $filename;
                    } else $msg_error = "Failed to upload Photo file.";
                } else $msg_error = "Invalid Photo file type.";
            }

            // Update file paths in DB if changed
            $file_updates = [];
            if ($aadhaar_card_path !== ($player['aadhaar_card'] ?? '')) $file_updates['aadhaar_card'] = $aadhaar_card_path;
            if ($photo_path !== ($player['photo'] ?? '')) $file_updates['photo'] = $photo_path;
            if (!empty($file_updates)) {
                if (!player_save_or_update($phone, array_merge($data, $file_updates))) {
                    rp_log("Failed to update file paths for {$phone}");
                } else {
                    $player = player_get_by_phone($phone) ?? $player;
                }
            }

            if (empty($msg_error)) $msg_success = "Player profile saved successfully!";
        }
    }

    // If start payment requested and save successful, create Razorpay order server-side (₹1)
    if ($start_payment_requested && empty($msg_error) && !empty($msg_success)) {
        $amt_rupees = 1.0; // force ₹1 demo
        $amount_paise = max(100, (int) round($amt_rupees * 100));

        $keyId = trim($razorpay_cfg['key_id'] ?? '');
        $keySecret = trim($razorpay_cfg['key_secret'] ?? '');
        if (empty($keyId) || empty($keySecret)) {
            $msg_error = 'Payment configuration missing. Contact admin.';
            rp_log('Missing Razorpay keys in config.');
        } else {
            $receipt = 'player_' . ($player['id'] ?? 'na') . '_' . time();
            $payload = json_encode(['amount' => $amount_paise, 'currency' => 'INR', 'receipt' => $receipt, 'payment_capture' => 1]);

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

            rp_log("CREATE_ORDER http={$httpCode} errno={$errno} errstr={$errstr} resp=" . substr((string)$resp,0,2000));
            $json = json_decode($resp, true);

            if ($errno || !is_array($json) || ($httpCode < 200 || $httpCode >= 300)) {
                $msg_error = 'Failed to initiate payment. Try again later.';
                rp_log('Razorpay order create failed: ' . substr((string)$resp,0,2000));
            } else {
                $created_order = $json;
                $should_start_payment = true;
                $start_payment_amount_paise = (int)($json['amount'] ?? $amount_paise);

                // Optional: create local payment record if function exists
                if (function_exists('payment_create_local_order')) {
                    payment_create_local_order([
                        'order_id' => $created_order['id'] ?? null,
                        'user_mobile' => $phone,
                        'amount' => $start_payment_amount_paise,
                        'currency' => 'INR',
                        'status' => 'created',
                        'meta' => json_encode(['player_id' => $player['id'] ?? null]),
                    ]);
                }
            }
        }
    }
}

// Select options
$age_groups = ['30 to 40','41 to 45','46 to 50','51 to 55','Above 55'];
$blood_groups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$playing_years_options = [];
for ($i=0;$i<=20;$i++) $playing_years_options[($i===1?'1 Year':$i.' Years')] = ($i===1?'1 Year':$i.' Years');
$playing_years_options['More than 20 Years'] = 'More than 20 Years';

// Default amount shown to user (rupees)
$default_payment_amount = 1;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Player Registration / Profile</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
  <style>
    :root{--primary:#2563eb;--muted:#64748b}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#f8fafc;color:#0f172a;margin:0;padding:18px}
    .wrap{max-width:520px;margin:28px auto}
    .card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 10px 30px rgba(37,99,235,0.06)}
    label{display:block;margin-top:12px;font-weight:600}
    input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc;margin-top:6px}
    .btn{width:100%;padding:12px;border:0;border-radius:10px;background:var(--primary);color:#fff;font-weight:700;margin-top:16px;cursor:pointer}
    .muted{color:var(--muted);font-size:13px;margin-top:8px}
    .msg{padding:10px;border-radius:8px;margin-bottom:12px}
    .msg.success{background:#ecfdf5;color:#065f46}
    .msg.error{background:#fff1f2;color:#7f1d1d}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2>Player Registration / Profile</h2>
      <p class="muted">Fill details and pay registration fee (demo ₹1)</p>

      <?php if ($msg_success): ?><div class="msg success"><?php echo h($msg_success); ?></div><?php endif; ?>
      <?php if ($msg_error): ?><div class="msg error"><?php echo h($msg_error); ?></div><?php endif; ?>

      <form id="profileForm" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <input type="hidden" name="start_payment" id="start_payment" value="0">
        <input type="hidden" name="payment_amount" id="payment_amount" value="<?php echo h($default_payment_amount); ?>">

        <label for="full_name">Full Name</label>
        <input type="text" name="full_name" id="full_name" required value="<?php echo h($player['full_name'] ?? ''); ?>">

        <label for="mobile">Mobile</label>
        <input type="text" name="mobile" id="mobile" required readonly value="<?php echo h($player['mobile'] ?? $phone); ?>">

        <label for="dob">Date of Birth</label>
        <input type="date" name="dob" id="dob" required min="<?php echo h($min_dob); ?>" max="<?php echo h($max_dob); ?>" value="<?php echo h($player['dob'] ?? ''); ?>">

        <label for="age_group">Age Group</label>
        <select name="age_group" id="age_group" required>
          <option value="">Select</option>
          <?php foreach ($age_groups as $g): ?>
            <option value="<?php echo h($g); ?>" <?php echo (($player['age_group'] ?? '') === $g) ? 'selected' : ''; ?>><?php echo h($g); ?></option>
          <?php endforeach; ?>
        </select>

        <label for="aadhaar">Aadhaar</label>
        <input type="text" name="aadhaar" id="aadhaar" required maxlength="12" value="<?php echo h($player['aadhaar'] ?? ''); ?>">

        <label for="village">Village / City</label>
        <input type="text" name="village" id="village" required value="<?php echo h($player['village'] ?? ''); ?>">

        <label for="court">Court</label>
        <input type="text" name="court" id="court" required value="<?php echo h($player['court'] ?? ''); ?>">

        <label for="play_time">Playing Time</label>
        <input type="text" name="play_time" id="play_time" required value="<?php echo h($player['play_time'] ?? ''); ?>">

        <label for="blood_group">Blood Group</label>
        <select name="blood_group" id="blood_group" required>
          <option value="">Select</option>
          <?php foreach ($blood_groups as $b): ?>
            <option value="<?php echo h($b); ?>" <?php echo (($player['blood_group'] ?? '') === $b) ? 'selected' : ''; ?>><?php echo h($b); ?></option>
          <?php endforeach; ?>
        </select>

        <label for="playing_years">From how many years have you been playing?</label>
        <select name="playing_years" id="playing_years" required>
          <option value="">Select</option>
          <?php foreach ($playing_years_options as $k=>$v): ?>
            <option value="<?php echo h($k); ?>" <?php echo (($player['playing_years'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo h($v); ?></option>
          <?php endforeach; ?>
        </select>

        <label for="aadhaar_card">Upload Aadhaar Card</label>
        <?php if (!empty($player['aadhaar_card'])): ?><div><a href="<?php echo h($player['aadhaar_card']); ?>" target="_blank">View current Aadhaar</a></div><?php endif; ?>
        <input type="file" name="aadhaar_card" id="aadhaar_card" <?php echo empty($player['aadhaar_card']) ? 'required' : ''; ?> accept=".jpg,.jpeg,.png,.pdf">

        <label for="photo">Upload Photo</label>
        <?php if (!empty($player['photo'])): ?><div><img src="<?php echo h($player['photo']); ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px" alt="photo"></div><?php endif; ?>
        <input type="file" name="photo" id="photo" <?php echo empty($player['photo']) ? 'required' : ''; ?> accept=".jpg,.jpeg,.png">

        <div style="margin-top:12px;font-size:14px">
          <input type="checkbox" id="terms" checked disabled> <label for="terms" style="display:inline;font-weight:400">I confirm details are correct</label>
        </div>

        <!-- Save & Pay Fees (₹1) -->
        <button class="btn" type="button" id="savePayBtn" onclick="onSaveAndPayClick(this)">Save &amp; Pay Fees (₹<?php echo h($default_payment_amount); ?>)</button>
      </form>
    </div>
  </div>

  <!-- Include local Razorpay helper (if you maintain one) and fallback to official checkout.js -->
  <script src="../assets/js/razorpay_checkout.js"></script>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

  <script>
    function onSaveAndPayClick(btn) {
      btn.disabled = true;
      btn.innerText = 'Saving...';
      document.getElementById('start_payment').value = '1';
      var pa = document.getElementById('payment_amount');
      if (!pa || !pa.value) document.getElementById('payment_amount').value = '<?php echo h($default_payment_amount); ?>';
      document.getElementById('profileForm').submit();
    }
  </script>

  <?php if ($should_start_payment && !empty($created_order)): ?>
  <script>
  (function(){
    const createdOrder = <?php echo json_encode($created_order); ?>;
    const publicKey = '<?php echo h($razorpay_cfg['key_id'] ?? ''); ?>';
    const callbackUrl = '/userpanel/razorpay_callback.php';
    function openDirectCheckout(order) {
      if (!order || !publicKey) {
        console.error('Missing order or public key', order, publicKey);
        return;
      }
      const options = {
        key: publicKey,
        amount: order.amount,
        currency: order.currency || 'INR',
        name: 'Latur Badminton League',
        description: 'Player registration fee',
        order_id: order.id,
        prefill: { contact: '<?php echo h($player['mobile'] ?? $phone); ?>' },
        theme: { color: '#2563eb' },
        handler: function(res) {
          const fd = new FormData();
          fd.append('razorpay_payment_id', res.razorpay_payment_id || '');
          fd.append('razorpay_order_id', res.razorpay_order_id || '');
          fd.append('razorpay_signature', res.razorpay_signature || '');
          fd.append('csrf', '<?php echo h($csrf); ?>');
          fetch(callbackUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(r => r.json().catch(()=>({})))
            .then(data => {
              if (data && data.success) {
                window.location.href = data.redirect || '/userpanel/payment_success.php';
              } else {
                alert('Payment verification failed. Please contact support.');
                console.error('verification failed', data);
              }
            }).catch(err => {
              console.error('Error verifying payment', err);
              alert('Payment made but server verification failed.');
            });
        },
        modal: { ondismiss: function(){ console.info('Checkout dismissed'); } }
      };
      const rzp = new Razorpay(options);
      rzp.open();
    }

    // If a local helper object exists, try helper.createAndPay, otherwise use direct Checkout.js
    if (typeof RazorpayCheckout !== 'undefined' && typeof RazorpayCheckout.createAndPay === 'function') {
      try {
        RazorpayCheckout.init({
          keyId: publicKey,
          callbackUrl: callbackUrl,
          prefill: { contact: '<?php echo h($player['mobile'] ?? $phone); ?>' },
          theme: { color: '#2563eb' }
        });
        RazorpayCheckout.createAndPay({ order_id: '<?php echo h($created_order['id'] ?? ''); ?>', amount_paise: <?php echo (int)$start_payment_amount_paise; ?>, receipt_note: 'Player registration fee' });
      } catch (e) {
        console.error('Helper failed, falling back to direct checkout.js', e);
        openDirectCheckout(createdOrder);
      }
    } else {
      openDirectCheckout(createdOrder);
    }
  })();
  </script>
  <?php endif; ?>

</body>
</html>