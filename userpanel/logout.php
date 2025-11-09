<?php
session_start();
// सेशन क्लिअर करून user ला लॉगआउट करा
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logged out | Latur Badminton League</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon as SVG logo -->
    <link rel="icon" type="image/svg+xml" href="../assets/lbllogo.svg">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #0ea5e9;
            --bg: #f8fafc;
            --card: #fff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
        }
        *{box-sizing:border-box;}
        body{margin:0; font-family:system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:var(--bg); color:var(--text);}
        .wrap{min-height:100dvh; display:grid; place-items:center; padding:16px;}
        .card{width:100%; max-width:400px; background:var(--card); border-radius:14px; box-shadow:0 8px 24px rgba(2,8,23,.08); padding:28px 18px; text-align:center;}
        .logo { width: 82px; height: 82px; margin:0 auto 14px auto; border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(37,99,235,0.10); overflow:hidden; }
        .logo img { width: 63px; height: 63px; }
        h1{margin:0 0 8px; font-size:22px; color:var(--primary);}
        .sub{color:var(--muted); margin-bottom:18px; font-size:15px;}
        .btn{display:block; width:100%; padding:14px 0; border:0; border-radius:12px; background:var(--primary); color:#fff; font-size:16px; font-weight:600; cursor:pointer; margin-top:10px; text-decoration:none;}
        .note{font-size:13px; color:var(--muted); margin-top:18px;}
        @media (max-width:500px){.card{max-width:100%;padding:12px 2px;}.logo{width:54px;height:54px;}.logo img{width:35px;height:35px;}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        <h1>You have been logged out</h1>
        <div class="sub">Thank you for visiting.<br>You can log in again below.</div>
        <a class="btn" href="login.php">Login</a>
        <div class="note">Powered by <b>LBL - Latur Badminton League</b></div>
    </div>
</div>
</body>
</html>