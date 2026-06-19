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

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_body(): array
{
    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($action === 'sds_state') {
    json_out(sds_dashboard_state());
}

if ($action === 'pei_state') {
    json_out(pei_dashboard_state());
}

if (in_array($action, ['sds_send', 'sds_save_preset', 'sds_delete_preset', 'pei_send', 'pei_power'], true)) {
    if (!dashboard_admin_configured()) {
        json_out(['ok' => false, 'error' => 'Admin password is not configured.'], 403);
    }
    if (!dashboard_admin_authenticated()) {
        header('WWW-Authenticate: Basic realm="SVXLINK-CT Dashboard"');
        json_out(['ok' => false, 'error' => 'Authentication required.'], 401);
    }

    try {
        $body = json_body();
        if ($action === 'sds_send') {
            $config = dashboard_config();
            $entry = send_sds_message(
                (string)($body['destination'] ?? ''),
                (string)($body['type'] ?? 'T'),
                (string)($body['message'] ?? ''),
                $config
            );
            json_out(['ok' => true, 'entry' => $entry, 'state' => sds_dashboard_state()]);
        }

        if ($action === 'sds_save_preset') {
            $preset = save_sds_preset($body);
            json_out(['ok' => true, 'preset' => $preset, 'state' => sds_dashboard_state()]);
        }

        if ($action === 'pei_send') {
            $entry = send_pei_command((string)($body['command'] ?? ''), 'admin');
            json_out(['ok' => true, 'entry' => $entry, 'state' => pei_dashboard_state()]);
        }

        if ($action === 'pei_power') {
            $entry = apply_power_level((int)($body['dbm'] ?? 0));
            json_out(['ok' => true, 'entry' => $entry, 'state' => pei_dashboard_state()]);
        }

        delete_sds_preset((string)($body['id'] ?? ''));
        json_out(['ok' => true, 'state' => sds_dashboard_state()]);
    } catch (Throwable $err) {
        json_out(['ok' => false, 'error' => $err->getMessage()], 400);
    }
}

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
