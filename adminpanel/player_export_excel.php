<?php
// Export all players as Excel (CSV) including new fields blood_group and playing_years
// Accessible to admin only

session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/player_repository.php';

// --- Authentication: Only admin allowed ---
if (empty($_SESSION['admin_auth_user'])) {
    header('Location: admin_login.php?next=player_export_excel.php');
    exit;
}

// --- Get all players (ensure admin/player_repository.php SELECT includes blood_group and playing_years) ---
$players = get_all_players();

// --- Excel/CSV export headers ---
$filename = 'players_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Output UTF-8 BOM so Excel opens file with correct encoding
echo "\xEF\xBB\xBF";

// --- Output CSV ---
$fields = [
    'ID',
    'Full Name',
    'DOB',
    'Age Group',
    'Village',
    'Court',
    'Play Time',
    'Blood Group',       // NEW
    'Playing Years',     // NEW
    'Mobile',
    'Aadhaar',
    'Photo',
    'Aadhaar Card',
    'Status',
    'Created At'
];

$output = fopen('php://output', 'w');
if ($output === false) {
    http_response_code(500);
    echo "Failed to open output stream.";
    exit;
}

// Write header row
fputcsv($output, $fields);

// Write rows
foreach ($players as $p) {
    // Normalize values and guard against missing keys
    $row = [
        $p['id'] ?? '',
        $p['full_name'] ?? '',
        $p['dob'] ?? '',
        $p['age_group'] ?? '',
        $p['village'] ?? '',
        $p['court'] ?? '',
        $p['play_time'] ?? '',
        $p['blood_group'] ?? '',       // NEW
        $p['playing_years'] ?? '',     // NEW
        $p['mobile'] ?? '',
        $p['aadhaar'] ?? '',
        $p['photo'] ?? '',
        $p['aadhaar_card'] ?? '',
        $p['status'] ?? '',
        $p['created_at'] ?? '',
    ];

    fputcsv($output, $row);
}

fclose($output);
exit;
?>