<?php
session_start();
require_once __DIR__ . '/../userpanel/auth.php';
require_once __DIR__ . '/../userpanel/user_repository.php';

require_auth();
$phone = current_user();

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$msg_error = '';
$msg_info  = '';

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Ensure user exists in DB
try { user_create_if_not_exists($phone); } catch (Throwable $e) { $msg_error = 'Database error while ensuring user record.'; }

// Load user
$user = user_get($phone) ?? ['phone' => $phone];

// Save profile (all fields optional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $payload = [
        'full_name'      => trim($_POST['full_name'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'address_line_1' => trim($_POST['address_line_1'] ?? ''),
        'address_line_2' => trim($_POST['address_line_2'] ?? ''),
        'pincode'        => trim($_POST['pincode'] ?? ''),
        'city'           => trim($_POST['city'] ?? ''),
        'state'          => trim($_POST['state'] ?? ''),
    ];

    try {
        user_update_profile($phone, $payload);
        $msg_info = 'Profile saved.';
        $user = user_get($phone) ?? $user;
    } catch (Throwable $e) {
        $msg_error = 'Failed to save profile.';
    }
}

// India states
$states = [
    '' => 'Select State',
    'Andhra Pradesh' => 'Andhra Pradesh', 'Arunachal Pradesh' => 'Arunachal Pradesh', 'Assam' => 'Assam',
    'Bihar' => 'Bihar', 'Chhattisgarh' => 'Chhattisgarh', 'Goa' => 'Goa', 'Gujarat' => 'Gujarat',
    'Haryana' => 'Haryana', 'Himachal Pradesh' => 'Himachal Pradesh', 'Jharkhand' => 'Jharkhand',
    'Karnataka' => 'Karnataka', 'Kerala' => 'Kerala', 'Madhya Pradesh' => 'Madhya Pradesh',
    'Maharashtra' => 'Maharashtra', 'Manipur' => 'Manipur', 'Meghalaya' => 'Meghalaya',
    'Mizoram' => 'Mizoram', 'Nagaland' => 'Nagaland', 'Odisha' => 'Odisha', 'Punjab' => 'Punjab',
    'Rajasthan' => 'Rajasthan', 'Sikkim' => 'Sikkim', 'Tamil Nadu' => 'Tamil Nadu',
    'Telangana' => 'Telangana', 'Tripura' => 'Tripura', 'Uttar Pradesh' => 'Uttar Pradesh',
    'Uttarakhand' => 'Uttarakhand', 'West Bengal' => 'West Bengal',
    'Andaman and Nicobar Islands' => 'Andaman and Nicobar Islands', 'Chandigarh' => 'Chandigarh',
    'Dadra and Nagar Haveli and Daman and Diu' => 'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi' => 'Delhi', 'Jammu and Kashmir' => 'Jammu and Kashmir', 'Ladakh' => 'Ladakh',
    'Lakshadweep' => 'Lakshadweep', 'Puducherry' => 'Puducherry',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --primary:#2563eb; --secondary:#0ea5e9; --bg:#f8fafc; --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --ring:#93c5fd; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { min-height:100dvh; display:grid; place-items:center; padding:16px; }
        .card { width:100%; max-width:560px; background:var(--card); border-radius:14px; box-shadow:0 10px 30px rgba(2,8,23,.08); padding:20px; }
        @media (min-width:420px){ .card{ padding:24px; } }

        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:10px; }
        .link { text-decoration:none; color:#fff; background:#0f172a; padding:8px 12px; border-radius:10px; font-size:14px; font-weight:600; }
        .link.danger { background:#ef4444; }

        h1 { margin:6px 0 8px; font-size:22px; text-align:center; }
        .sub { text-align:center; color:var(--muted); margin-bottom:12px; }

        .msg { margin-top:10px; padding:12px; border-radius:12px; font-size:14px; }
        .msg.info { background:#eef2ff; color:#1e293b; }
        .msg.error { background:#fee2e2; color:#7f1d1d; }

        .panel { background:#f8fafc; border:1px dashed var(--border); border-radius:12px; padding:10px 12px; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
        .copy { background:#e2e8f0; border:0; padding:8px 10px; border-radius:10px; cursor:pointer; }

        label { display:block; margin:12px 0 6px; font-weight:600; font-size:14px; }
        .input, select { width:100%; padding:14px; border:1px solid var(--border); border-radius:12px; font-size:16px; background:#fff; transition: border-color .15s, box-shadow .15s; }
        .input:focus, select:focus { outline:none; border-color:var(--ring); box-shadow:0 0 0 4px rgba(59,130,246,.15); }

        .btn { display:block; width:100%; padding:14px 16px; border:0; border-radius:12px; background:var(--primary); color:#fff; font-size:16px; font-weight:600; cursor:pointer; box-shadow:0 6px 16px rgba(37,99,235,.25); margin-top:12px; }
        .hint { font-size:12px; color:var(--muted); margin-top:8px; text-align:center; }
        /* Toast */
        .toast{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);background:#0f172a;color:#fff;padding:10px 12px;border-radius:10px;opacity:0;transition:opacity .2s;z-index:9999;}
        .toast.show{opacity:1;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" role="region" aria-label="Profile">
        <div class="topbar">
            <a class="link" href="dashboard.php">Back to Dashboard</a>
            <a class="link danger" href="logout.php">Logout</a>
        </div>

        <h1>Your Profile</h1>
        <div class="sub">Mobile number acts as your User ID. All fields below are optional.</div>

        <?php if ($msg_info): ?><div class="msg info"><?php echo h($msg_info); ?></div><?php endif; ?>
        <?php if ($msg_error): ?><div class="msg error"><?php echo h($msg_error); ?></div><?php endif; ?>

        <div class="panel">
            <div>
                <strong>User ID (Phone)</strong><br>
                <span id="phoneDisp">+<?php echo h($user['phone']); ?></span>
            </div>
            <button class="copy" id="copyBtn" type="button" title="Copy phone">Copy</button>
        </div>

        <form method="post" autocomplete="off" novalidate>
            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">

            <label>Full Name*</label>
            <input class="input" type="text" name="full_name" placeholder="Enter full name" value="<?php echo h($user['full_name'] ?? ''); ?>">

            <label>Email</label>
            <input class="input" type="email" name="email" placeholder="Enter email address" value="<?php echo h($user['email'] ?? ''); ?>">

            <label>Address Line 1*</label>
            <input class="input" type="text" name="address_line_1" placeholder="Enter address line 1" value="<?php echo h($user['address_line_1'] ?? ''); ?>">

            <label>Address Line 2</label>
            <input class="input" type="text" name="address_line_2" placeholder="Enter address line 2" value="<?php echo h($user['address_line_2'] ?? ''); ?>">

            <label>Pincode*</label>
            <input class="input" type="text" name="pincode" placeholder="Enter pincode" inputmode="numeric" value="<?php echo h($user['pincode'] ?? ''); ?>">

            <label>City*</label>
            <input class="input" type="text" name="city" placeholder="Enter city" value="<?php echo h($user['city'] ?? ''); ?>">

            <label>State*</label>
            <select name="state">
                <?php foreach ($states as $val => $label): ?>
                    <option value="<?php echo h($val); ?>" <?php echo (($user['state'] ?? '') === $val) ? 'selected' : ''; ?>>
                        <?php echo h($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn" type="submit">Save Profile</button>
            <div class="hint">Note: Fields marked with * are optional here.</div>
        </form>
    </div>
</div>

<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script>
    // Toast helper
    const toastEl = document.getElementById('toast');
    function toast(msg){ if(!toastEl) return; toastEl.textContent=msg; toastEl.classList.add('show'); setTimeout(()=>toastEl.classList.remove('show'), 1800); }
    <?php if ($msg_info): ?>toast(<?php echo json_encode($msg_info); ?>);<?php endif; ?>

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

    // Pincode -> autofill City/State
    const pin = document.querySelector('input[name="pincode"]');
    const city = document.querySelector('input[name="city"]');
    const state = document.querySelector('select[name="state"]');
    if (pin && city && state) {
        let ctrl;
        pin.addEventListener('input', async () => {
            const v = pin.value.replace(/\D/g,'').slice(0,6);
            pin.value = v;
            if (v.length === 6) {
                ctrl?.abort(); ctrl = new AbortController();
                try {
                    const res = await fetch(`https://api.postalpincode.in/pincode/${v}`, { signal: ctrl.signal });
                    const data = await res.json();
                    const post = (data?.[0]?.PostOffice || [])[0];
                    if (post) {
                        if (!city.value) city.value  = post.District || '';
                        const opt = [...state.options].find(o => o.value === post.State);
                        if (opt) state.value = opt.value;
                    }
                } catch {}
            }
        });
    }
</script>
</body>
</html>