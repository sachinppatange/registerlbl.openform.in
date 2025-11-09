<?php
session_start();
require_once __DIR__ . '/../userpanel/whatsapp.php';

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// Next param (return after auth). Only allow internal safe pages.
$next = $_GET['next'] ?? $_POST['next'] ?? 'dashboard.php';
$allowedNext = ['dashboard.php', 'player_profile.php'];
if (!in_array(basename($next), $allowedNext, true)) $next = 'dashboard.php';

$msg_error = '';
$msg_info  = '';

// OTP session ctx
if (!isset($_SESSION['otp_ctx'])) {
    $_SESSION['otp_ctx'] = [
        'hash' => null,
        'mobile_e164' => null,
        'expires_at' => 0,
        'attempts' => 0,
        'last_sent_at' => 0
    ];
}
$ctx = &$_SESSION['otp_ctx'];

function reset_ctx(): void {
    $_SESSION['otp_ctx'] = [
        'hash' => null,
        'mobile_e164' => null,
        'expires_at' => 0,
        'attempts' => 0,
        'last_sent_at' => 0
    ];
}

function has_active_otp(array $ctx): bool {
    return !empty($ctx['mobile_e164']) && !empty($ctx['hash']) && time() < ($ctx['expires_at'] ?? 0);
}

$debug_block = '';
function set_debug($data): void {
    global $debug_block;
    if (APP_DEBUG) {
        $debug_block = '<pre style="white-space:pre-wrap;background:#111;color:#eee;padding:10px;border-radius:8px;overflow:auto;">' .
            htmlspecialchars(is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) .
            '</pre>';
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp') {
        $mobile_input = trim($_POST['mobile'] ?? '');
        if (!preg_match('/^\d{10}$/', $mobile_input)) {
            $msg_error = 'Please enter a valid 10-digit phone number.';
        } else {
            $to_e164 = to_e164($mobile_input);
            if (!$to_e164) {
                $msg_error = 'Please enter a valid phone number.';
            } else {
                $now = time();
                if ($ctx['last_sent_at'] && ($now - $ctx['last_sent_at']) < OTP_RESEND_COOLDOWN) {
                    $wait = OTP_RESEND_COOLDOWN - ($now - $ctx['last_sent_at']);
                    $msg_error = "Please try again in $wait seconds.";
                } else {
                    // Generate OTP
                    $min = (int) pow(10, OTP_LENGTH - 1);
                    $max = (int) pow(10, OTP_LENGTH) - 1;
                    $otp = (string) random_int($min, $max);

                    $hash = password_hash($otp, PASSWORD_DEFAULT);
                    $expires = time() + OTP_EXPIRY_SECONDS;

                    $res = send_whatsapp_otp($to_e164, $otp);
                    if ($res['ok']) {
                        $ctx['hash'] = $hash;
                        $ctx['mobile_e164'] = $to_e164;
                        $ctx['expires_at'] = $expires;
                        $ctx['attempts'] = 0;
                        $ctx['last_sent_at'] = time();
                        $msg_info = 'Your OTP has been sent via WhatsApp.';
                    } else {
                        $msg_error = 'Failed to send OTP.';
                        set_debug([
                            'http_code' => $res['http_code'],
                            'error' => $res['error'],
                            'response' => $res['response'],
                            'hint' => 'Check Token/Phone ID/Template/Lang. Ensure E.164 number. Verify template body & button parameters.'
                        ]);
                    }
                }
            }
        }
    }

    if ($action === 'resend_otp') {
        if (empty($ctx['mobile_e164'])) {
            $msg_error = 'Please enter your phone number first.';
        } else {
            $now = time();
            if ($ctx['last_sent_at'] && ($now - $ctx['last_sent_at']) < OTP_RESEND_COOLDOWN) {
                $wait = OTP_RESEND_COOLDOWN - ($now - $ctx['last_sent_at']);
                $msg_error = "Please try again in $wait seconds.";
            } else {
                $min = (int) pow(10, OTP_LENGTH - 1);
                $max = (int) pow(10, OTP_LENGTH) - 1;
                $otp = (string) random_int($min, $max);

                $hash = password_hash($otp, PASSWORD_DEFAULT);
                $expires = time() + OTP_EXPIRY_SECONDS;

                $res = send_whatsapp_otp($ctx['mobile_e164'], $otp);
                if ($res['ok']) {
                    $ctx['hash'] = $hash;
                    $ctx['expires_at'] = $expires;
                    $ctx['attempts'] = 0;
                    $ctx['last_sent_at'] = time();
                    $msg_info = 'Your OTP has been sent via WhatsApp.';
                } else {
                    $msg_error = 'Failed to resend OTP.';
                    set_debug([
                        'http_code' => $res['http_code'],
                        'error' => $res['error'],
                        'response' => $res['response']
                    ]);
                }
            }
        }
    }

    if ($action === 'change_phone') {
        reset_ctx();
        $msg_info = 'You can change your phone number now.';
    }

    if ($action === 'login') {
        // Accept single-field OTP or 4 boxes (backward compatible)
        if (isset($_POST['otp'])) {
            $otp_input = preg_replace('/\D+/', '', $_POST['otp'] ?? '');
        } else {
            $d1 = $_POST['d1'] ?? '';
            $d2 = $_POST['d2'] ?? '';
            $d3 = $_POST['d3'] ?? '';
            $d4 = $_POST['d4'] ?? '';
            $otp_input = $d1 . $d2 . $d3 . $d4;
        }

        if (!preg_match('/^\d{' . OTP_LENGTH . '}$/', $otp_input)) {
            $msg_error = 'Please enter the ' . OTP_LENGTH . '-digit OTP.';
        } elseif (empty($ctx['hash']) || empty($ctx['mobile_e164'])) {
            $msg_error = 'Please request an OTP first.';
        } elseif (time() > $ctx['expires_at']) {
            $msg_error = 'OTP expired. Tap Resend.';
        } else {
            $ctx['attempts']++;
            if ($ctx['attempts'] > 5) {
                reset_ctx();
                $msg_error = 'Too many attempts. Please request a new OTP.';
            } else {
                if (password_verify($otp_input, $ctx['hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['auth_user'] = $ctx['mobile_e164'];
                    reset_ctx();
                    $dest = $_POST['next'] ?? 'dashboard.php';
                    $dest = in_array(basename($dest), $allowedNext, true) ? basename($dest) : 'dashboard.php';
                    header('Location: ' . $dest);
                    exit;
                } else {
                    $msg_error = 'Login failed. Incorrect OTP.';
                }
            }
        }
    }
}

$otp_active = has_active_otp($ctx);

// Prefill local 10-digit for UI
$mobile_prefill = '';
if (!empty($ctx['mobile_e164']) && substr($ctx['mobile_e164'], 0, strlen(WA_COUNTRY_CODE)) === WA_COUNTRY_CODE) {
    $maybe_local = substr($ctx['mobile_e164'], strlen(WA_COUNTRY_CODE));
    if (strlen($maybe_local) === 10) $mobile_prefill = $maybe_local;
}

// Resend cooldown remaining
$cooldownRemaining = 0;
if ($otp_active && !empty($ctx['last_sent_at'])) {
    $cooldownRemaining = max(0, OTP_RESEND_COOLDOWN - (time() - $ctx['last_sent_at']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Player Registration / Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon as SVG logo -->
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --secondary:#0ea5e9; --bg:#f8fafc; --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --ring:#93c5fd; }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased; }
        .wrap { min-height: 100dvh; display: grid; place-items: center; padding: 16px; }
        .card { width: 100%; max-width: 400px; background: var(--card); border-radius: 14px; box-shadow: 0 10px 30px rgba(2,8,23,.08); padding: 24px 16px; text-align: center; }
        @media (min-width: 400px) { .card { padding: 32px 20px; } }
        .logo { width: 210px; height: 210px; margin: 0 auto 16px auto; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(37,99,235,0.10); overflow: hidden; }
        .logo img { width: 182px; height: 182px; }
        h1 { margin: 0 0 8px; font-size: 1.4rem; text-align:center; color:var(--primary); font-weight:700;}
        .sub { text-align:center; color:var(--muted); font-size:1rem; margin-bottom:8px; }
        label { display:block; margin:18px 0 8px; font-weight:600; font-size:14px; text-align:left; }
        .input { width:100%; padding:14px; border:1px solid var(--border); border-radius:12px; font-size:16px; background:#fff; transition:border-color .15s, box-shadow .15s; }
        .input:focus { outline:none; border-color:var(--ring); box-shadow:0 0 0 4px rgba(59,130,246,.15); }
        .phone-group { position:relative; }
        .phone-prefix { position:absolute; left:10px; top:50%; transform:translateY(-50%); background:#f1f5f9; border:1px solid var(--border); color:#111827; padding:8px 10px; border-radius:10px; font-weight:600; font-size:14px; user-select:none; }
        .phone-input { padding-left:88px; }
        .btn { display:block; width:100%; padding:14px 0; border:0; border-radius:12px; background:var(--primary); color:#fff; font-size:16px; font-weight:600; cursor:pointer; box-shadow:0 6px 16px rgba(37,99,235,.25); margin-top:12px;}
        .btn.secondary { background:var(--secondary); box-shadow:0 6px 16px rgba(14,165,233,.25); }
        .btn[disabled] { opacity:.6; cursor:not-allowed; box-shadow:none; }
        .btn.loading { position:relative; color:transparent; }
        .btn.loading::after { content:""; position:absolute; inset:0; margin:auto; width:18px; height:18px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:spin .8s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .top-gap { margin-top:14px; }
        .msg { margin:10px 0 0; padding:12px; border-radius:12px; font-size:14px; }
        .msg.info { background:#eef2ff; color:#1e293b; }
        .msg.error { background:#fee2e2; color:#7f1d1d; }
        .note { font-size:13px; color:var(--muted); margin-top:16px; text-align:center; line-height:1.4; }
        .phone-display { display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border:1px dashed var(--border); border-radius:12px; padding:10px 12px; margin-top:4px; }
        .assist { font-size:12px; color:var(--muted); margin-top:8px; text-align:center; }
        /* Toast */
        .toast{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);background:#0f172a;color:#fff;padding:10px 12px;border-radius:10px;opacity:0;transition:opacity .2s;z-index:9999;}
        .toast.show{opacity:1;}
        @media (max-width: 500px) {
            .card { max-width: 100%; padding: 12px 2px; }
            .logo { width: 164px; height: 164px;}
            .logo img { width: 146px; height: 146px;}
            h1 { font-size: 1.07rem; }
            .btn { padding: 10px 0; font-size: 14px;}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" role="region" aria-label="Player Registration / Login">
        <div class="logo">
            <img src="../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        <h1>Player Registration / Login</h1>
        <div class="sub">Badminton Tournament Online Registration System</div>

        <!-- Global messages -->
        <div aria-live="polite" aria-atomic="true">
            <?php if ($msg_info): ?><div class="msg info"><?php echo htmlspecialchars($msg_info); ?></div><?php endif; ?>
            <?php if ($msg_error): ?><div class="msg error"><?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
        </div>

        <!-- Phone Section -->
        <?php if (!$otp_active): ?>
            <form method="post" id="phoneForm" autocomplete="off" novalidate class="top-gap">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="send_otp">
                <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
                <label for="phone">Phone*</label>
                <div class="phone-group">
                    <span class="phone-prefix">+<?php echo htmlspecialchars(WA_COUNTRY_CODE); ?></span>
                    <input id="phone" class="input phone-input" type="tel" name="mobile" maxlength="10" inputmode="numeric" pattern="\d{10}" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($mobile_prefill); ?>" required>
                </div>
                <button class="btn top-gap" id="sendOtpBtn" type="submit" disabled>Send OTP</button>
                <div class="note">By clicking Get Started, you agree to our Terms and have read and acknowledge our Privacy Policy.</div>
            </form>
        <?php else: ?>

            <!-- OTP Section -->
            <div class="top-gap">
                <label>Phone*</label>
                <div class="phone-display">
                    <small>+<?php echo htmlspecialchars(WA_COUNTRY_CODE . ' ' . substr($ctx['mobile_e164'], strlen(WA_COUNTRY_CODE))); ?></small>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="change_phone">
                        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
                        <button class="link" type="submit">Change</button>
                    </form>
                </div>

                <form method="post" id="otpLoginForm" autocomplete="off" novalidate class="top-gap">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">

                    <label for="otp">Enter OTP</label>
                    <input id="otp" name="otp" class="input" inputmode="numeric" pattern="\d{<?php echo (int) OTP_LENGTH; ?>}" maxlength="<?php echo (int) OTP_LENGTH; ?>" autocomplete="one-time-code" placeholder="<?php echo str_repeat('_', (int) OTP_LENGTH); ?>" required>

                    <div class="assist">
                        We sent an OTP on WhatsApp.
                        <a href="whatsapp://send" class="link">Open WhatsApp</a>
                        <br>
                        Didn't receive the OTP?
                        <button type="button" id="resendBtn" class="link" <?php echo $cooldownRemaining > 0 ? 'disabled aria-disabled="true"' : ''; ?> aria-live="polite">
                            <?php echo $cooldownRemaining > 0 ? 'Resend in ' . sprintf('00:%02d', $cooldownRemaining) : 'Resend OTP'; ?>
                        </button>
                    </div>

                    <button class="btn secondary top-gap" type="submit" id="loginBtn">Login</button>
                </form>

                <!-- Hidden Resend form -->
                <form method="post" id="resendFormHidden" style="display:none;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="resend_otp">
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
                </form>
            </div>
        <?php endif; ?>

        <?php if ($debug_block && APP_DEBUG): ?>
            <div style="margin-top:12px;"><?php echo $debug_block; ?></div>
        <?php endif; ?>
    </div>
</div>

<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script>
    // Toast helper
    const toastEl = document.getElementById('toast');
    function toast(msg){ if(!toastEl) return; toastEl.textContent=msg; toastEl.classList.add('show'); setTimeout(()=>toastEl.classList.remove('show'), 1800); }
    <?php if ($msg_info): ?>toast(<?php echo json_encode($msg_info); ?>);<?php endif; ?>

    // Phone digits only + enable Send OTP when 10 digits
    const phoneInput = document.getElementById('phone');
    const sendBtn = document.getElementById('sendOtpBtn');
    if (phoneInput && sendBtn) {
        const toggle = () => { const d = phoneInput.value.replace(/\D+/g,'').length === 10; sendBtn.disabled = !d; };
        phoneInput.addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D+/g,'').slice(0,10); toggle(); });
        toggle();
        phoneInput.form?.addEventListener('submit', () => sendBtn.classList.add('loading'));
    }

    // OTP single input: auto-limit and auto-submit on complete
    const otp = document.getElementById('otp');
    const verifyBtn = document.getElementById('loginBtn');
    if (otp && verifyBtn) {
        otp.addEventListener('input', () => {
            const len = <?php echo (int) OTP_LENGTH; ?>;
            otp.value = otp.value.replace(/\D/g,'').slice(0,len);
            if (otp.value.length === len) {
                verifyBtn.disabled = true;
                otp.form.submit();
            }
        });
        setTimeout(()=>{ try{ otp.focus(); }catch(_){} }, 100);
    }

    // Resend OTP countdown + submit
    const resendBtn = document.getElementById('resendBtn');
    if (resendBtn) {
        let remaining = <?php echo (int) $cooldownRemaining; ?>;
        const tick = () => {
            if (remaining > 0) {
                remaining--;
                resendBtn.textContent = 'Resend in ' + ('00:' + String(remaining).padStart(2,'0'));
                resendBtn.setAttribute('disabled','true');
                resendBtn.setAttribute('aria-disabled','true');
            } else {
                resendBtn.textContent = 'Resend OTP';
                resendBtn.removeAttribute('disabled');
                resendBtn.removeAttribute('aria-disabled');
                clearInterval(timer);
            }
        };
        if (remaining > 0) { var timer = setInterval(tick, 1000); }
        resendBtn.addEventListener('click', () => {
            if (resendBtn.hasAttribute('disabled')) return;
            document.getElementById('resendFormHidden').submit();
        });
    }
</script>
</body>
</html>