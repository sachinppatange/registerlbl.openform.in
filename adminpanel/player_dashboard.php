<?php
// Admin Player Dashboard: Shows list of registered players, actions (view, edit, accept/reject, export)
// Only accessible to logged-in admin
// UPDATED: include blood_group and playing_years (read from DB and displayed)
// Path normalization: ensure photo and aadhaar links point to /userpanel/storage/... when rendered from adminpanel
// so links like "/userpanel/storage/uploads/photos/photo_7.jpeg" or "userpanel/storage/..." or "../userpanel/..." all resolve correctly.

session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/wa_config.php';
require_once __DIR__ . '/player_repository.php';

// --- Authentication: Redirect if not logged in as admin ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=player_dashboard.php');
    exit;
}

// --- Sorting ---
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_dir = (isset($_GET['sort_dir']) && $_GET['sort_dir'] === 'asc') ? 'ASC' : 'DESC';

// Valid sort columns (added blood_group and playing_years)
$valid_sort = [
    'full_name'      => 'Name',
    'dob'            => 'DOB',
    'age_group'      => 'Age Group',
    'village'        => 'Village',
    'court'          => 'Court',
    'play_time'      => 'Play Time',
    'blood_group'    => 'Blood Group',
    'playing_years'  => 'Playing Years',
    'created_at'     => 'Registration Date',
    'status'         => 'Status',
];
if (!isset($valid_sort[$sort_by])) $sort_by = 'created_at';

// --- Connect DB and get player list ---
// Note: ensure players table has blood_group and playing_years columns
function get_players_sorted($by, $dir) {
    $pdo = db();
    // Safeguard: allow only specific columns to be used in ORDER BY
    $allowed = ['id','full_name','dob','age_group','village','court','play_time','blood_group','playing_years','mobile','aadhaar','status','created_at'];
    if (!in_array($by, $allowed, true)) $by = 'created_at';
    $dir = ($dir === 'ASC') ? 'ASC' : 'DESC';
    $sql = "SELECT id, full_name, dob, age_group, village, court, play_time, blood_group, playing_years, mobile, aadhaar, photo, aadhaar_card, status, created_at
            FROM players
            ORDER BY `$by` $dir";
    $stmt = $pdo->query($sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

$players = [];
$db_error = '';
try {
    $players = get_players_sorted($sort_by, $sort_dir);
    if (!is_array($players)) {
        $players = [];
        $db_error = 'Database error';
    }
} catch (Throwable $e) {
    $players = [];
    $db_error = $e->getMessage();
}

// --- Admin info ---
$admin_phone = $_SESSION['admin_auth_user'];

// --- Player count ---
$total_players = count($players);

// --- Age group counts ---
$age_groups = ['30 to 40', '41 to 45', '46 to 50', '51 to 55', 'Above 55'];
$age_group_counts = array_fill_keys($age_groups, 0);
$accepted_count = $rejected_count = $pending_count = 0;
foreach ($players as $p) {
    if (isset($age_group_counts[$p['age_group']])) $age_group_counts[$p['age_group']]++;
    if (($p['status'] ?? '') === 'accepted') $accepted_count++;
    elseif (($p['status'] ?? '') === 'rejected') $rejected_count++;
    else $pending_count++;
}

// -- Helper to normalize admin-side asset links (so admin pages point to userpanel storage paths) --
function admin_normalize_path(?string $path, string $type = 'photo'): string {
    // $type: 'photo' or 'aadhaar'
    // Desired admin-side form: ../userpanel/storage/uploads/photos/xxx or ../userpanel/storage/uploads/aadhaar/xxx
    $default_photo = '../assets/default_user.png';
    if (empty($path)) {
        return ($type === 'photo') ? $default_photo : '';
    }
    $p = trim($path);

    // If absolute URL (http/https), return as is
    if (preg_match('#^https?://#i', $p)) return $p;

    // If already starts with ../userpanel/ => good
    if (strpos($p, '../userpanel/') === 0) return $p;

    // If starts with '/userpanel/' => convert to ../userpanel/...
    if (strpos($p, '/userpanel/') === 0) {
        return '..' . $p;
    }

    // If starts with 'userpanel/' => prefix ../
    if (strpos($p, 'userpanel/') === 0) {
        return '../' . $p;
    }

    // If starts with '/adminpanel/storage/uploads/' => replace with ../userpanel/storage/uploads/
    if (strpos($p, '/adminpanel/storage/uploads/') === 0) {
        return '..' . str_replace('/adminpanel/storage/uploads/', '/userpanel/storage/uploads/', $p);
    }

    // If contains 'adminpanel/storage/uploads/' (no leading slash)
    if (strpos($p, 'adminpanel/storage/uploads/') === 0) {
        return '../' . str_replace('adminpanel/storage/uploads/', 'userpanel/storage/uploads/', $p);
    }

    // If path starts with '../' or './' but not pointing to userpanel - attempt to extract basename and map to userpanel storage
    if (strpos($p, '../') === 0 || strpos($p, './') === 0) {
        $base = basename($p);
        if ($type === 'aadhaar') {
            return '../userpanel/storage/uploads/aadhaar/' . $base;
        } else {
            return '../userpanel/storage/uploads/photos/' . $base;
        }
    }

    // If path is a bare filename (e.g., photo_7.jpeg), prefix userpanel storage
    if (preg_match('/^[^\\/]+\\.[a-zA-Z0-9]{2,6}$/', $p)) {
        if ($type === 'aadhaar') {
            return '../userpanel/storage/uploads/aadhaar/' . $p;
        } else {
            return '../userpanel/storage/uploads/photos/' . $p;
        }
    }

    // Otherwise, fallback: try to use basename and assume userpanel storage
    $base = basename($p);
    if ($type === 'aadhaar') {
        return '../userpanel/storage/uploads/aadhaar/' . $base;
    } else {
        return '../userpanel/storage/uploads/photos/' . $base;
    }
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sort_link($col) {
    global $sort_by, $sort_dir, $valid_sort;
    $dir = ($sort_by === $col && $sort_dir === 'ASC') ? 'desc' : 'asc';
    return "<a href=\"?sort_by=$col&sort_dir=$dir\" class=\"sort-btn\">" . h($valid_sort[$col]) . (($sort_by === $col) ? ($sort_dir === 'ASC' ? " ▲" : " ▼") : "") . "</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Player Dashboard | Latur Badminton League</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            --table-head: #eff6ff;
            --table-row: #fff;
            --table-alt: #f1f5f9;
            --action-view: #0ea5e9;
            --action-edit: #fbbf24;
            --action-accept: #22c55e;
            --action-reject: #ef4444;
        }
        body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background:var(--bg); color:var(--text);}
        .wrap { min-height:100dvh; display:grid; place-items:center; padding:16px;}
        .card { width:100%; max-width:1250px; background:var(--card); border-radius:18px; box-shadow:0 10px 34px rgba(2,8,23,.09); padding:34px;}
        .logo { width: 110px; height: 110px; margin: 0 auto 18px auto; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(37,99,235,0.10); overflow: hidden;}
        .logo img { width: 82px; height: 82px; }
        h1 { margin:0 0 10px; font-size:29px; color:var(--primary); }
        .topbar { display:flex;justify-content:space-between;align-items:center;margin-bottom:18px; flex-wrap:wrap;}
        .admin-info { color:var(--muted); font-size:15px; }
        .count-box { background: #e0f2fe; color: #2563eb; font-size: 1.1rem; font-weight: 700; border-radius: 14px; padding: 10px 18px; margin-bottom: 16px; display: inline-block;}
        .btn { padding:8px 18px; background:var(--primary); color:#fff; border-radius:10px; border:0; font-weight:600; cursor:pointer; text-decoration:none; font-size:16px; transition: background 0.18s;}
        .btn:hover { background: var(--secondary);}
        .logout-btn { background:#e11d48;}
        .export-btn { background: #2563eb; color:#fff; border-radius:8px; margin-right:8px;}
        .back-btn { background: #64748b; color: #fff; border-radius:8px; margin-right:8px; font-size:15px; padding:8px 16px;}
        .sort-btn { background: #fff; color: #2563eb; font-weight: 600; padding: 4px 10px; border-radius: 7px; border:1px solid #dbeafe; font-size:15px; text-decoration:none; margin-right:4px;}
        .sort-btn:hover { background: #e0f2fe;}
        .stat-bar { display:flex; gap:18px; margin-bottom:18px; flex-wrap:wrap;}
        .stat-box { background: #eff6ff; border-radius: 14px; padding: 13px 36px; color: #2563eb; font-size: 1.12rem; font-weight: 700; display: flex; align-items: center; box-shadow: 0 2px 12px #2563eb0c;}
        .stat-title { font-weight:500; color:var(--muted); font-size:15px; margin-right:10px;}
        .stat-count { font-size: 1.4rem; font-weight:700; }
        .stat-accept { color: var(--action-accept);}
        .stat-reject { color: var(--action-reject);}
        .stat-pending { color: var(--muted);}
        .export-bar { margin-bottom:18px; display:flex; justify-content:space-between;align-items:center; flex-wrap:wrap;}
        table { width:100%; border-collapse:separate; border-spacing:0; margin-top:16px; background:var(--card); box-shadow:0 2px 16px #2563eb0a;}
        th, td { padding:14px 9px; text-align:left; border-bottom:1px solid var(--border);}
        th { background:var(--table-head); color:var(--primary); font-size:16px;}
        tr { background:var(--table-row);}
        tr:nth-child(even) { background:var(--table-alt);}
        tr:last-child td { border-bottom:none;}
        .empty { color:var(--muted); text-align:center; padding:30px;}
        @media (max-width: 1200px) { .card{padding:10px;} .stat-bar{gap:8px;} th, td{font-size:13px;} .stat-box{padding:10px 10px;} }
        .scroll-table { overflow-x:auto; }
        .status-accepted { color: var(--action-accept); font-weight: 600; }
        .status-rejected { color: var(--action-reject); font-weight: 600; }
        .status-pending  { color: var(--muted); font-weight: 600; }
        .action-btn { display:inline-block; padding:7px 15px; border-radius:8px; font-size:15px; font-weight:600; text-decoration:none; margin-right:5px; margin-bottom:5px; transition: background 0.18s;}
        .view-btn { background:var(--action-view); color:#fff;}
        .view-btn:hover { background:#0369a1;}
        .edit-btn { background:var(--action-edit); color:#111;}
        .edit-btn:hover { background:#b45309; color:#fff;}
        .accept-btn { background:var(--action-accept); color:#fff;}
        .accept-btn:hover { background:#166534;}
        .reject-btn { background:var(--action-reject); color:#fff;}
        .reject-btn:hover { background:#7f1d1d;}
        .photo-thumb { width:54px; height:54px; object-fit:cover; border-radius:12px; border:2px solid var(--border); background:#f1f5f9;}
        .aadhaar-link { color:var(--primary); text-decoration:underline; font-size:13px;}
        .note { font-size:13px; color:var(--muted); margin-top:18px;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo-bar">
            <div class="logo">
                <img src="../assets/lbllogo.svg" alt="LBL Logo">
            </div>
            <h1>Latur Badminton League - Admin Player Dashboard</h1>
            <div class="admin-info">
                Logged in as: <b><?php echo h($admin_phone); ?></b>
            </div>
            <span class="count-box">Total Player Registrations: <?php echo $total_players; ?></span>
        </div>

        <div class="stat-bar">
            <?php foreach ($age_groups as $ag): ?>
                <div class="stat-box">
                    <span class="stat-title"><?php echo h($ag); ?>:</span>
                    <span class="stat-count"><?php echo h($age_group_counts[$ag]); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="stat-box stat-accept">
                <span class="stat-title">Accepted:</span>
                <span class="stat-count"><?php echo $accepted_count; ?></span>
            </div>
            <div class="stat-box stat-reject">
                <span class="stat-title">Rejected:</span>
                <span class="stat-count"><?php echo $rejected_count; ?></span>
            </div>
            <div class="stat-box stat-pending">
                <span class="stat-title">Pending:</span>
                <span class="stat-count"><?php echo $pending_count; ?></span>
            </div>
        </div>

        <div class="export-bar">
            <div>
                <a href="player_export_excel.php" class="btn export-btn">Export Excel</a>
                <a href="player_export_pdf.php" class="btn export-btn">Export PDF</a>
                <a href="admin_dashboard.php" class="btn back-btn">Back to Admin Dashboard</a>
            </div>
            <a href="admin_logout.php" class="btn logout-btn" title="Logout">Logout</a>
        </div>

        <div style="margin-bottom:10px;">
            <?php foreach ($valid_sort as $col => $label): ?>
                <?php echo sort_link($col); ?>
            <?php endforeach; ?>
        </div>

        <h2 style="font-size:21px;margin-top:14px;text-align:left;">Registered Players</h2>

        <?php if ($db_error): ?>
            <div class="empty">Database Error: <?php echo h($db_error); ?></div>
        <?php elseif (empty($players)): ?>
            <div class="empty">No players found.</div>
        <?php else: ?>
            <div class="scroll-table">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Full Name</th>
                        <th>DOB</th>
                        <th>Age Group</th>
                        <th>Village</th>
                        <th>Court</th>
                        <th>Play Time</th>
                        <th>Blood Group</th>
                        <th>Playing Years</th>
                        <th>Mobile</th>
                        <th>Aadhaar</th>
                        <th>Aadhaar Card</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($players as $p): ?>
                    <tr>
                        <td>
                            <?php
                            // Normalize photo path so admin page links correctly to userpanel storage
                            $photoPath = admin_normalize_path($p['photo'] ?? '', 'photo');
                            ?>
                            <img src="<?php echo h($photoPath); ?>" class="photo-thumb" alt="Photo"
                                onerror="this.onerror=null;this.src='../assets/default_user.png';">
                        </td>
                        <td><?php echo h($p['full_name']); ?></td>
                        <td><?php echo h($p['dob']); ?></td>
                        <td><?php echo h($p['age_group']); ?></td>
                        <td><?php echo h($p['village']); ?></td>
                        <td><?php echo h($p['court']); ?></td>
                        <td><?php echo h($p['play_time']); ?></td>
                        <td><?php echo h($p['blood_group'] ?? ''); ?></td>
                        <td><?php echo h($p['playing_years'] ?? ''); ?></td>
                        <td><?php echo h($p['mobile']); ?></td>
                        <td><?php echo h($p['aadhaar']); ?></td>
                        <td>
                            <?php if (!empty($p['aadhaar_card'])): ?>
                                <?php $aadPath = admin_normalize_path($p['aadhaar_card'], 'aadhaar'); ?>
                                <a class="aadhaar-link" href="<?php echo h($aadPath); ?>" target="_blank">View</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-<?php echo h($p['status'] ?? 'pending'); ?>">
                                <?php echo ucfirst($p['status'] ?? 'pending'); ?>
                            </span>
                        </td>
                        <td>
                            <a href="player_view.php?id=<?php echo urlencode($p['id']); ?>" class="action-btn view-btn">View</a>
                            <a href="player_edit.php?id=<?php echo urlencode($p['id']); ?>" class="action-btn edit-btn">Edit</a>
                            <?php if (($p['status'] ?? 'pending') !== 'accepted'): ?>
                                <a href="player_status.php?id=<?php echo urlencode($p['id']); ?>&status=accepted" class="action-btn accept-btn">Accept</a>
                            <?php endif; ?>
                            <?php if (($p['status'] ?? 'pending') !== 'rejected'): ?>
                                <a href="player_status.php?id=<?php echo urlencode($p['id']); ?>&status=rejected" class="action-btn reject-btn" onclick="return confirm('Reject this player?');">Reject</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <div class="note">Powered by <b>LBL</b></div>
    </div>
</div>
</body>
</html>