<?php
declare(strict_types=1);

require_once __DIR__ . '/include/functions.php';

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? 'dashboard';
$data = dashboard_data();

if ($action === 'events') {
    echo json_encode([
        'events' => $data['events'],
        'runtime' => $data['runtime'],
        'generated_at' => $data['generated_at'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
