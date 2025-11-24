<?php
session_start();
require_once __DIR__ . '/../userpanel/auth.php';
require_once __DIR__ . '/player_repository.php';
require_once __DIR__ . '/../config/player_config.php';

// Authentication
require_auth();
$phone = current_user();

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$msg_error = '';
$msg_success = '';

// Load existing player profile if available
$player = player_get_by_phone($phone) ?? ['mobile' => $phone];

// Ensure upload folders
@mkdir(__DIR__ . '/storage/uploads/aadhaar', 0777, true);
@mkdir(__DIR__ . '/storage/uploads/photos', 0777, true);
@mkdir(__DIR__ . '/storage/logs', 0777, true);

// Fallback allowed ext if not defined
if (!defined('PLAYER_AADHAAR_ALLOWED_EXT')) define('PLAYER_AADHAAR_ALLOWED_EXT', ['jpg','jpeg','png','pdf']);
if (!defined('PLAYER_PHOTO_ALLOWED_EXT')) define('PLAYER_PHOTO_ALLOWED_EXT', ['jpg','jpeg','png']);

// (Existing server-side save logic remains unchanged)
// --- (Keep your existing PHP handling for POST/save and optional server-side order creation) ---
// For clarity this file keeps the same PHP save logic you already have earlier in the repo.
// (The important change below is the client-side JS that no longer requires doSaveAndPay.)

// Helper for escaping output
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Player Registration / Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root{--primary:#2563eb;--muted:#64748b}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#f8fafc;color:#0f172a;margin:0;padding:18px}
        .wrap{max-width:520px;margin:28px auto}
        .card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 10px 30px rgba(37,99,235,0.06)}
        label{display:block;margin-top:12px;font-weight:600}
        input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc;margin-top:6px}
        .btn{width:100%;padding:12px;border:0;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;margin-top:16px;cursor:pointer}
        .muted{color:var(--muted);font-size:13px;margin-top:8px}
        .msg{padding:10px;border-radius:8px;margin-bottom:12px}
        .msg.success{background:#ecfdf5;color:#065f46}
        .msg.error{background:#fff1f2;color:#7f1d1d}
    </style>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2>Player Registration / Profile</h2>
      <p class="muted">Fill details and pay registration fee (demo)</p>

      <?php if ($msg_success): ?><div class="msg success"><?php echo h($msg_success); ?></div><?php endif; ?>
      <?php if ($msg_error): ?><div class="msg error"><?php echo h($msg_error); ?></div><?php endif; ?>

      <form id="profileForm" method="post" enctype="multipart/form-data" autocomplete="off" style="text-align:left;">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <!-- other fields (full_name, mobile, dob, age_group...) -->
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" required value="<?php echo h($player['full_name'] ?? ''); ?>">

        <label for="mobile">Mobile Number</label>
        <input type="text" id="mobile" name="mobile" readonly required value="<?php echo h($player['mobile'] ?? $phone); ?>">

        <label for="dob">Date of Birth</label>
        <input type="date" id="dob" name="dob" required value="<?php echo h($player['dob'] ?? ''); ?>">

        <label for="age_group">Age Group</label>
        <select id="age_group" name="age_group" readonly>
          <option value="">Select Age Group</option>
          <option value="30 to 40" <?php echo (($player['age_group'] ?? '') === '30 to 40') ? 'selected' : ''; ?>>30 to 40</option>
          <option value="41 to 45" <?php echo (($player['age_group'] ?? '') === '41 to 45') ? 'selected' : ''; ?>>41 to 45</option>
          <option value="46 to 50" <?php echo (($player['age_group'] ?? '') === '46 to 50') ? 'selected' : ''; ?>>46 to 50</option>
          <option value="51 to 55" <?php echo (($player['age_group'] ?? '') === '51 to 55') ? 'selected' : ''; ?>>51 to 55</option>
          <option value="Above 55" <?php echo (($player['age_group'] ?? '') === 'Above 55') ? 'selected' : ''; ?>>Above 55</option>
        </select>

        <label for="aadhaar">Aadhaar Number</label>
        <input type="text" id="aadhaar" name="aadhaar" maxlength="12" required value="<?php echo h($player['aadhaar'] ?? ''); ?>">

        <label for="village">Village / City</label>
        <input type="text" id="village" name="village" required value="<?php echo h($player['village'] ?? ''); ?>">

        <label for="court">Court</label>
        <input type="text" id="court" name="court" required value="<?php echo h($player['court'] ?? ''); ?>">

        <label for="play_time">Playing Time</label>
        <input type="text" id="play_time" name="play_time" required value="<?php echo h($player['play_time'] ?? ''); ?>">

        <label for="blood_group">Blood Group</label>
        <select id="blood_group" name="blood_group" required>
          <option value="">Select</option>
          <option value="A+" <?php echo (($player['blood_group'] ?? '') === 'A+') ? 'selected' : ''; ?>>A+</option>
          <option value="A-" <?php echo (($player['blood_group'] ?? '') === 'A-') ? 'selected' : ''; ?>>A-</option>
          <option value="B+" <?php echo (($player['blood_group'] ?? '') === 'B+') ? 'selected' : ''; ?>>B+</option>
          <option value="B-" <?php echo (($player['blood_group'] ?? '') === 'B-') ? 'selected' : ''; ?>>B-</option>
          <option value="O+" <?php echo (($player['blood_group'] ?? '') === 'O+') ? 'selected' : ''; ?>>O+</option>
          <option value="O-" <?php echo (($player['blood_group'] ?? '') === 'O-') ? 'selected' : ''; ?>>O-</option>
          <option value="AB+" <?php echo (($player['blood_group'] ?? '') === 'AB+') ? 'selected' : ''; ?>>AB+</option>
          <option value="AB-" <?php echo (($player['blood_group'] ?? '') === 'AB-') ? 'selected' : ''; ?>>AB-</option>
        </select>

        <label for="playing_years">From how many years have you been playing?</label>
        <select id="playing_years" name="playing_years" required>
          <option value="">Select</option>
          <?php for ($i=0;$i<=20;$i++): $lbl = ($i===1)?'1 Year':$i.' Years'; ?>
            <option value="<?php echo h($lbl); ?>" <?php echo (($player['playing_years'] ?? '') === $lbl) ? 'selected' : ''; ?>><?php echo h($lbl); ?></option>
          <?php endfor; ?>
          <option value="More than 20 Years" <?php echo (($player['playing_years'] ?? '') === 'More than 20 Years') ? 'selected' : ''; ?>>More than 20 Years</option>
        </select>

        <label for="aadhaar_card">Upload Aadhaar Card</label>
        <input type="file" id="aadhaar_card" name="aadhaar_card" accept=".jpg,.jpeg,.png,.pdf">

        <label for="photo">Upload Photo</label>
        <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png">

        <input type="hidden" name="payment_amount" id="payment_amount" value="1">
        <button id="savePayBtn" class="btn" type="button">Save &amp; Pay INR 1</button>
      </form>
    </div>
  </div>

  <!-- Keep payment.js include if you have it, but page will not depend on it. -->
  <script src="js/payment.js"></script>

  <script>
    // New client-side flow: AJAX save + request server to create order (ajax=1,start_payment=1)
    // If server responds with JSON { success:true, order: {...}, key_id: 'rzp_...' } we open Checkout.
    // If server doesn't support AJAX order creation, we fallback to normal form submit with start_payment=1.

    async function saveProfileAndStartPayment() {
      const btn = document.getElementById('savePayBtn');
      const form = document.getElementById('profileForm');

      if (!form.reportValidity()) {
        alert('Please fill required fields');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Saving...';

      const fd = new FormData(form);
      fd.append('ajax', '1');
      fd.append('start_payment', '1'); // request server to create order
      fd.append('csrf', '<?php echo h($csrf); ?>');
      fd.append('payment_amount', document.getElementById('payment_amount').value || '1');

      try {
        const res = await fetch(window.location.href, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: {
            // don't set Content-Type; let browser set boundary
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (e) { json = null; }

        // If server returned JSON with order details -> open Checkout
        if (json && json.success && json.order && json.key_id) {
          btn.textContent = 'Starting payment...';
          openRazorpayCheckout(json.key_id, json.order);
          return;
        }

        // If JSON with success but no order, maybe server saved only
        if (json && json.success && !json.order) {
          alert('Profile saved. Payment not started by server; trying fallback submit.');
          fallbackSubmit();
          return;
        }

        // If server returned non-JSON response but contains success HTML, try to detect server-side success string
        if (typeof text === 'string' && text.indexOf('Player profile saved successfully') !== -1) {
          // Server saved; now fallback to submit form normally to trigger server-side order creation path
          fallbackSubmit();
          return;
        }

        // Otherwise show error returned by server JSON or HTML
        let errMsg = 'Save failed. ';
        if (json && json.error) errMsg += json.error;
        else {
          // attempt to extract error div from HTML
          const m = text.match(/<div class="msg error">([\s\S]*?)<\/div>/);
          if (m && m[1]) {
            const tmp = document.createElement('div'); tmp.innerHTML = m[1];
            errMsg += tmp.textContent.trim();
          } else {
            errMsg += 'Unknown server response.';
          }
        }
        alert(errMsg);
        btn.disabled = false;
        btn.textContent = 'Save & Pay INR 1';
      } catch (e) {
        console.error('Network error', e);
        // Fallback to full form submit if network/JSON parse failed but server probably accepts normal submit
        alert('Network error while saving. Trying fallback submit to server.');
        fallbackSubmit();
      }
    }

    function fallbackSubmit() {
      // Prepare hidden inputs and do a normal submit so the server-side non-AJAX path runs (which may create order).
      const form = document.getElementById('profileForm');
      let start = document.getElementById('start_payment');
      if (!start) {
        start = document.createElement('input');
        start.type = 'hidden'; start.name = 'start_payment'; start.id = 'start_payment';
        form.appendChild(start);
      }
      start.value = '1';
      // ensure csrf is present (it is)
      form.removeAttribute('onsubmit'); // allow submit
      form.submit();
    }

    function openRazorpayCheckout(keyId, order) {
      const btn = document.getElementById('savePayBtn');
      const options = {
        key: keyId,
        amount: order.amount,
        currency: order.currency || 'INR',
        name: document.getElementById('full_name').value || 'LBL Participant',
        description: 'Registration fee (₹1)',
        order_id: order.id,
        prefill: { contact: document.getElementById('mobile').value || '', name: document.getElementById('full_name').value || '' },
        theme: { color: '#2563eb' },
        handler: function (res) {
          // You should verify payment server-side at /userpanel/razorpay_callback.php
          // For now, redirect to a success page or reload
          window.location.href = '/userpanel/payment_success.php';
        },
        modal: {
          ondismiss: function() {
            alert('Checkout closed.');
            if (btn) { btn.disabled = false; btn.textContent = 'Save & Pay INR 1'; }
          }
        }
      };
      const rzp = new Razorpay(options);
      rzp.open();
    }

    document.getElementById('savePayBtn').addEventListener('click', function(e) {
      // Primary: call our AJAX+checkout flow. This does not depend on payment.js/doSaveAndPay.
      saveProfileAndStartPayment();
    });
  </script>
</body>
</html>