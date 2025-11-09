<?php
// Admin Dashboard: Entry page for admin panel
// Shows recent player records and registration count.
// Only accessible to logged-in admin

session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../config/app_config.php';

// --- Authentication: Redirect if not logged in as admin ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=admin_dashboard.php');
    exit;
}

// --- Admin info ---
$admin_phone = $_SESSION['admin_auth_user'];

// --- Database connection for players ---
require_once __DIR__ . '/../userpanel/player_repository.php';
$pdo = db();

// Total registrations count
$countStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM players");
$total_players = $countStmt ? (int)$countStmt->fetchColumn() : 0;

// Recent registrations (latest 5)
$recentStmt = $pdo->query("SELECT full_name, mobile, age_group, village, created_at, status FROM players ORDER BY created_at DESC LIMIT 5");
$recent_players = $recentStmt ? $recentStmt->fetchAll(PDO::FETCH_ASSOC) : [];

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$status_labels = [
    'pending'  => ['Pending',   '#eab308'],
    'accepted' => ['Accepted',  '#22c55e'],
    'rejected' => ['Rejected',  '#ef4444'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Latur Badminton League</title>
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
            max-width: 560px;
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 10px 32px rgba(2,8,23,.09);
            padding: 32px 20px;
            text-align: center;
        }
        .logo {
            width: 110px;
            height: 110px;
            margin: 0 auto 14px auto;
            border-radius: 50%;
            background: #fff;
            display: flex;
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
            margin: 0 0 12px 0;
            color: var(--primary);
            font-weight: 700;
            letter-spacing: 1px;
        }
        .sub {
            color: var(--muted);
            font-size: 1rem;
            margin-bottom: 18px;
        }
        .admin-info {
            color: var(--muted);
            font-size: 15px;
            margin-bottom: 18px;
        }
        .count-box {
            display: flex; justify-content: center; align-items: center; gap: 8px;
            background: #e0f2fe;
            color: #2563eb; font-size: 1.2rem; font-weight: 700;
            border-radius: 14px;
            padding: 14px 0; margin-bottom: 16px;
        }
        .recent-title {
            font-size: 1.1rem; color: var(--primary); margin-bottom: 10px; font-weight: 600; text-align:left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th, td {
            padding: 8px 6px;
            text-align: left;
            font-size: 15px;
        }
        th {
            background: #f1f5f9;
            color: var(--primary);
            font-weight: 600;
        }
        tr {
            background: #fff;
            border-bottom: 1px solid var(--border);
        }
        .pill-status {
            display: inline-block;
            padding: 4px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 999px;
            color: #fff;
        }
        .pill-pending { background: #eab308; }
        .pill-accepted { background: #22c55e; }
        .pill-rejected { background: #ef4444; }
        .note {
            font-size: 13px;
            color: var(--muted);
            margin-top: 16px;
        }
        .logout-btn {
            display: block;
            width: 100%;
            background: #e11d48;
            color: #fff;
            border-radius: 10px;
            padding: 14px 0;
            font-size: 16px;
            font-weight: 600;
            border: 0;
            cursor: pointer;
            text-decoration: none;
            margin-top: 22px;
        }
        .allplayers-btn {
            display: block;
            width: 100%;
            background: var(--secondary);
            color: #fff;
            border-radius: 10px;
            padding: 14px 0;
            font-size: 16px;
            font-weight: 600;
            border: 0;
            cursor: pointer;
            text-decoration: none;
            margin: 12px 0 24px 0;
        }
        @media (max-width: 600px) {
            .card { max-width: 100%; padding: 16px 2px; }
            .logo { width: 64px; height: 64px;}
            .logo img { width: 46px; height: 46px;}
            th, td { font-size: 13px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        <h1>LBL - Latur Badminton League</h1>
        <div class="sub">Admin Dashboard</div>
        <div class="admin-info">
            Logged in as: <b><?php echo h($admin_phone); ?></b>
        </div>
        <div class="count-box">
            <span>Total Player Registrations:</span> <span><?php echo $total_players; ?></span>
        </div>
        <a href="../adminpanel/player_dashboard.php" class="allplayers-btn">View / Update All Players</a>
        <div class="recent-title">Recent Player Registrations</div>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Age Group</th>
                    <th>Village</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recent_players): foreach ($recent_players as $row): 
                $status = strtolower($row['status']);
                $status_class = "pill-".($status_labels[$status] ? $status : "pending");
            ?>
                <tr>
                    <td><?php echo h($row['full_name']); ?></td>
                    <td><?php echo h($row['mobile']); ?></td>
                    <td><?php echo h($row['age_group']); ?></td>
                    <td><?php echo h($row['village']); ?></td>
                    <td><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>
                    <td><span class="pill-status <?php echo $status_class; ?>"><?php echo h($status_labels[$status][0] ?? ucfirst($status)); ?></span></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center;color:#64748b;">No registrations found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <a href="admin_logout.php" class="logout-btn">Logout</a>
        <div class="note">Powered by <b>LBL</b> | Showing last 5 registered players.</div>
    </div>
</div>
</body>
</html>