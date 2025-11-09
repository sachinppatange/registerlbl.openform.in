<?php
// Latur Badminton League - Simple Responsive Landing Page (Logo Larger)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LBL - Latur Badminton League | Online Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Latur Badminton League - Badminton Tournament Online Registration System">
    <!-- Favicon as SVG logo -->
    <link rel="icon" type="image/svg+xml" href="./assets/lbllogo.svg">
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
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap {
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }
        .card {
            width: 100%;
            max-width: 400px;
            background: var(--card);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(2,8,23,.08);
            padding: 24px 16px;
            text-align: center;
        }
        .logo {
            width: 110px;
            height: 110px;
            margin-bottom: 16px;
            border-radius: 50%;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(37,99,235,0.10);
            overflow: hidden;
        }
        .logo img {
            width: 82px;
            height: 82px;
        }
        h1 {
            font-size: 1.5rem;
            margin: 0 0 8px 0;
            color: var(--primary);
            font-weight: 700;
        }
        .sub {
            color: var(--muted);
            font-size: 1rem;
            margin-bottom: 18px;
        }
        .btn {
            display: block;
            background: var(--primary);
            color: #fff;
            border-radius: 10px;
            padding: 12px 0;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            margin: 12px 0 0 0;
            text-decoration: none;
            box-shadow: 0 2px 8px #2563eb1a;
        }
        .btn.secondary {
            background: var(--secondary);
            margin-top: 8px;
        }
        .note {
            font-size: 13px;
            color: var(--muted);
            margin-top: 16px;
        }
        @media (max-width: 500px) {
            .card { max-width: 100%; padding: 12px 2px; }
            .logo { width: 64px; height: 64px;}
            .logo img { width: 46px; height: 46px;}
            h1 { font-size: 1.07rem; }
            .btn { padding: 10px 0; font-size: 14px;}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="./assets/lbllogo.svg" alt="LBL Logo">
        </div>
        <h1>LBL - Latur Badminton League</h1>
        <div class="sub">Badminton Tournament Online Registration System</div>
        <a class="btn" href="userpanel/login.php">Player Registration / Login</a>
        <a class="btn secondary" href="adminpanel/admin_login.php">Admin Login</a>
        <div class="note">
            Powered by <b>LBL</b>
        </div>
    </div>
</div>
</body>
</html>