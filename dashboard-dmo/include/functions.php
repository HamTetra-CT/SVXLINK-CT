<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set(DASH_TIMEZONE);

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dashboard_admin_configured(): bool
{
    return DASH_ADMIN_PASSWORD !== '';
}

function dashboard_admin_authenticated(): bool
{
    if (!dashboard_admin_configured()) {
        return false;
    }

    $user = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
    $password = (string)($_SERVER['PHP_AUTH_PW'] ?? '');

    return hash_equals(DASH_ADMIN_USER, $user)
        && hash_equals(DASH_ADMIN_PASSWORD, $password);
}

function require_dashboard_admin(): void
{
    if (dashboard_admin_authenticated()) {
        return;
    }

    header('WWW-Authenticate: Basic realm="SVXLINK-CT Dashboard"');
    header('HTTP/1.1 401 Unauthorized');
    echo 'Authentication required';
    exit;
}

function format_seconds(int $seconds): string
{
    if ($seconds <= 0) {
        return '0m';
    }
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days > 0) {
        $parts[] = $days . 'd';
    }
    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    $parts[] = $minutes . 'm';
    return implode(' ', $parts);
}

function parse_svx_config(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $config = [];
    $section = '';
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        if ($line[0] === '[' && substr($line, -1) === ']') {
            $section = trim($line, '[]');
            $config[$section] = $config[$section] ?? [];
            continue;
        }
        if ($section !== '' && strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $config[$section][trim($key)] = trim(trim($value), '"');
        }
    }

    return $config;
}

function read_json_array(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function write_json_file(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return @file_put_contents($path, $json . "\n", LOCK_EX) !== false;
}

function legacy_voice_name(): string
{
    return 'echo' . 'link';
}

function filter_legacy_voice_entries(array $entries): array
{
    $needle = legacy_voice_name();
    return array_filter($entries, static function ($value, $key) use ($needle) {
        return stripos((string)$key, $needle) === false
            && stripos((string)$value, $needle) === false;
    }, ARRAY_FILTER_USE_BOTH);
}

function resolve_log_path(string $path): string
{
    if (!is_dir($path)) {
        return $path;
    }
    foreach (['svxlink', 'svxlink.log'] as $candidate) {
        $full = rtrim($path, '/') . '/' . $candidate;
        if (is_readable($full)) {
            return $full;
        }
    }
    $files = glob(rtrim($path, '/') . '/*') ?: [];
    foreach ($files as $file) {
        if (is_file($file) && is_readable($file)) {
            return $file;
        }
    }
    return $path;
}

function tail_lines(string $path, int $maxLines = 500, int $maxBytes = 1048576): array
{
    $path = resolve_log_path($path);
    if (!is_readable($path) || !is_file($path)) {
        return [];
    }

    $size = filesize($path);
    if ($size === false || $size === 0) {
        return [];
    }

    $fh = fopen($path, 'rb');
    if (!$fh) {
        return [];
    }

    $buffer = '';
    $position = $size;
    $chunkSize = 8192;
    while ($position > 0 && substr_count($buffer, "\n") <= $maxLines && strlen($buffer) < $maxBytes) {
        $read = min($chunkSize, $position);
        $position -= $read;
        fseek($fh, $position);
        $buffer = fread($fh, $read) . $buffer;
    }
    fclose($fh);

    $lines = preg_split('/\R/', trim($buffer));
    if (!is_array($lines)) {
        return [];
    }
    return array_slice(array_values(array_filter($lines, static fn($line) => trim($line) !== '')), -$maxLines);
}

function command_output(array $cmd): string
{
    $escaped = array_map('escapeshellarg', $cmd);
    $out = @shell_exec(implode(' ', $escaped) . ' 2>/dev/null');
    return trim((string)$out);
}

function service_status(): array
{
    $service = SVXLINK_SERVICE;
    $status = command_output(['systemctl', 'is-active', $service]);
    if ($status === '') {
        $status = 'unknown';
    }

    $uptime = '';
    $entered = command_output(['systemctl', 'show', $service, '--property=ActiveEnterTimestamp']);
    if (preg_match('/ActiveEnterTimestamp=(.+)$/', $entered, $m)) {
        $ts = strtotime(trim($m[1]));
        if ($ts !== false && $ts > 0) {
            $uptime = format_seconds(time() - $ts);
        }
    }

    return [
        'name' => $service,
        'status' => $status,
        'uptime' => $uptime,
    ];
}

function load_tetra_users(): array
{
    $users = [];
    foreach (read_json_array(TETRA_USERS_FILE) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $tsi = (string)($entry['tsi'] ?? '');
        if ($tsi === '') {
            continue;
        }
        $info = [
            'tsi' => $tsi,
            'issi' => ltrim(substr($tsi, -8), '0') ?: substr($tsi, -8),
            'call' => (string)($entry['call'] ?? ''),
            'name' => (string)($entry['name'] ?? ''),
            'location' => (string)($entry['location'] ?? ''),
            'comment' => (string)($entry['comment'] ?? ''),
        ];
        $users['by_tsi'][$tsi] = $info;
        $users['by_issi'][$info['issi']] = $info;
    }
    return $users;
}

function user_label(string $issi, array $users): string
{
    $key = ltrim($issi, '0') ?: $issi;
    $user = $users['by_issi'][$key] ?? null;
    if (!$user) {
        return $issi;
    }
    $label = $user['call'] !== '' ? $user['call'] : $user['name'];
    return $label !== '' ? $label : $issi;
}

function dashboard_config(): array
{
    $svx = parse_svx_config(SVXLINK_CONFIG);
    $tetraFile = parse_svx_config(TETRALOGIC_CONFIG);
    $tetra = $tetraFile['TetraLogic'] ?? ($svx['TetraLogic'] ?? []);
    $global = $svx['GLOBAL'] ?? [];

    $rxName = $tetra['RX'] ?? 'Rx1';
    $txName = $tetra['TX'] ?? 'Tx1';
    $rx = $svx[$rxName] ?? [];
    $tx = $svx[$txName] ?? [];
    $reflector = $svx['ReflectorLogic'] ?? [];

    $statusSection = $tetra['TETRA_STATUS'] ?? 'Tetra_Status';
    $commandSection = $tetra['SDS_TO_COMMAND'] ?? 'SdsToCommand';

    $statusMap = filter_legacy_voice_entries($tetraFile[$statusSection] ?? []);
    $commandMap = filter_legacy_voice_entries($tetraFile[$commandSection] ?? []);
    $sdsPty = $tetra['SDS_PTY'] ?? DASH_SDS_PTY;

    return [
        'paths' => [
            'svxlink_config' => SVXLINK_CONFIG,
            'tetralogic_config' => TETRALOGIC_CONFIG,
            'tetra_users' => TETRA_USERS_FILE,
            'pei_init' => PEI_INIT_FILE,
            'log' => resolve_log_path(SVXLINK_LOG),
            'sds_pty' => $sdsPty,
            'sds_presets' => DASH_SDS_PRESETS_FILE,
            'sds_log' => DASH_SDS_LOG_FILE,
        ],
        'global' => $global,
        'logic' => $tetra,
        'rx' => ['name' => $rxName] + $rx,
        'tx' => ['name' => $txName] + $tx,
        'reflector' => $reflector,
        'status_map' => $statusMap,
        'command_map' => $commandMap,
        'modules' => array_values(array_filter(array_map('trim', explode(',', $tetra['MODULES'] ?? '')))),
        'pei_init' => read_json_array(PEI_INIT_FILE),
        'sds_pty' => $sdsPty,
    ];
}

function default_sds_presets(): array
{
    return [
        [
            'id' => 'teste-dmo',
            'label' => 'Teste DMO',
            'destination' => '',
            'type' => 'T',
            'message' => 'Teste SDS HAMTETRA-CT',
        ],
        [
            'id' => 'servico-online',
            'label' => 'Servico online',
            'destination' => '',
            'type' => 'T',
            'message' => 'CT-DMO online',
        ],
    ];
}

function load_sds_presets(): array
{
    $raw = read_json_array(DASH_SDS_PRESETS_FILE);
    $items = isset($raw['presets']) && is_array($raw['presets']) ? $raw['presets'] : $raw;
    if (!$items) {
        return default_sds_presets();
    }

    $presets = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string)($item['label'] ?? ''));
        $message = trim((string)($item['message'] ?? ''));
        if ($label === '' || $message === '') {
            continue;
        }
        $id = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($item['id'] ?? $label)));
        $presets[] = [
            'id' => trim((string)$id, '-') ?: uniqid('sds-', false),
            'label' => $label,
            'destination' => preg_replace('/\D+/', '', (string)($item['destination'] ?? '')),
            'type' => strtoupper((string)($item['type'] ?? 'T')) === 'R' ? 'R' : 'T',
            'message' => $message,
        ];
    }

    return $presets ?: default_sds_presets();
}

function save_sds_preset(array $input): array
{
    $label = trim((string)($input['label'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    if ($label === '' || $message === '') {
        throw new InvalidArgumentException('Preset needs label and message.');
    }

    $type = strtoupper((string)($input['type'] ?? 'T')) === 'R' ? 'R' : 'T';
    validate_sds_payload($type, $message);
    $destination = normalize_sds_destination((string)($input['destination'] ?? ''), true);
    $id = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($input['id'] ?? $label)));
    $id = trim((string)$id, '-') ?: uniqid('sds-', false);

    $preset = [
        'id' => $id,
        'label' => $label,
        'destination' => $destination,
        'type' => $type,
        'message' => $message,
    ];

    $presets = load_sds_presets();
    $updated = false;
    foreach ($presets as $idx => $existing) {
        if (($existing['id'] ?? '') === $id) {
            $presets[$idx] = $preset;
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        $presets[] = $preset;
    }

    if (!write_json_file(DASH_SDS_PRESETS_FILE, ['presets' => array_values($presets)])) {
        throw new RuntimeException('Could not write SDS presets file.');
    }

    return $preset;
}

function delete_sds_preset(string $id): void
{
    $id = trim($id);
    if ($id === '') {
        throw new InvalidArgumentException('Preset id is required.');
    }

    $presets = array_values(array_filter(load_sds_presets(), static fn($preset) => ($preset['id'] ?? '') !== $id));
    if (!write_json_file(DASH_SDS_PRESETS_FILE, ['presets' => $presets])) {
        throw new RuntimeException('Could not write SDS presets file.');
    }
}

function normalize_sds_destination(string $destination, bool $allowEmpty = false): string
{
    $destination = preg_replace('/\D+/', '', $destination) ?? '';
    if ($destination === '' && $allowEmpty) {
        return '';
    }
    if ($destination === '' || strlen($destination) > 17) {
        throw new InvalidArgumentException('Destination must be an ISSI or full TSI.');
    }
    return $destination;
}

function validate_sds_payload(string $type, string $message): void
{
    if ($type === 'R') {
        if (!preg_match('/^[0-9A-Fa-f]+$/', $message) || strlen($message) % 2 !== 0 || strlen($message) > 220) {
            throw new InvalidArgumentException('Raw SDS must be even-length HEX, max 220 chars.');
        }
        return;
    }

    if (strlen($message) > 120) {
        throw new InvalidArgumentException('Text SDS max is 120 characters for this TetraLogic path.');
    }
}

function sds_log_append(array $entry): void
{
    $dir = dirname(DASH_SDS_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $entry['time'] = $entry['time'] ?? date('c');
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line !== false) {
        @file_put_contents(DASH_SDS_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

function sds_log_read(int $limit = 80): array
{
    if (!is_readable(DASH_SDS_LOG_FILE)) {
        return [];
    }

    $items = [];
    foreach (tail_lines(DASH_SDS_LOG_FILE, $limit, 262144) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $items[] = $row;
        }
    }
    return array_reverse($items);
}

function send_sds_message(string $destination, string $type, string $message, array $config): array
{
    $destination = normalize_sds_destination($destination);
    $type = strtoupper($type) === 'R' ? 'R' : 'T';
    $message = trim($message);
    validate_sds_payload($type, $message);

    $pty = (string)($config['sds_pty'] ?? DASH_SDS_PTY);
    if ($pty === '' || !file_exists($pty)) {
        throw new RuntimeException('SDS_PTY is not available. Enable SDS_PTY in TetraLogic once and restart SvxLink.');
    }

    $payload = $destination . ',' . $type . ',' . $message . "\n";
    $written = @file_put_contents($pty, $payload, FILE_APPEND);
    if ($written === false) {
        throw new RuntimeException('Could not write to SDS_PTY. Check web user permissions on ' . $pty . '.');
    }

    $entry = [
        'direction' => 'tx',
        'destination' => $destination,
        'type' => $type,
        'message' => $message,
        'status' => 'queued',
    ];
    sds_log_append($entry);
    return $entry;
}

function sds_dashboard_state(): array
{
    $config = dashboard_config();
    $users = load_tetra_users();
    $userItems = [];
    foreach (($users['by_tsi'] ?? []) as $user) {
        $userItems[] = [
            'tsi' => $user['tsi'],
            'issi' => $user['issi'],
            'label' => trim(($user['call'] ?: $user['name']) . ' ' . $user['issi']),
            'call' => $user['call'],
            'name' => $user['name'],
        ];
    }
    usort($userItems, static fn($a, $b) => strcmp($a['label'], $b['label']));

    $pty = (string)($config['sds_pty'] ?? DASH_SDS_PTY);
    return [
        'admin_configured' => dashboard_admin_configured(),
        'admin_authenticated' => dashboard_admin_authenticated(),
        'sds_pty' => $pty,
        'sds_pty_ready' => $pty !== '' && file_exists($pty),
        'presets' => load_sds_presets(),
        'log' => sds_log_read(),
        'users' => $userItems,
    ];
}

function dbm_to_watts(float $dbm): float
{
    return pow(10, ($dbm - 30) / 10);
}

function format_power(float $watts): string
{
    if ($watts < 1) {
        return number_format($watts * 1000, $watts < 0.1 ? 1 : 0) . ' mW';
    }
    return number_format($watts, $watts < 10 ? 1 : 0) . ' W';
}

function radio_power_levels(): array
{
    $dbmLevels = [10, 13, 17, 20, 23, 27, 30, 33, 35, 37, 40, 43];
    $levels = [];
    foreach ($dbmLevels as $dbm) {
        $watts = dbm_to_watts((float)$dbm);
        $levels[] = [
            'dbm' => $dbm,
            'watts' => round($watts, 4),
            'label' => $dbm . ' dBm / ' . format_power($watts),
        ];
    }
    return $levels;
}

function default_pei_presets(): array
{
    return [
        ['label' => 'PEI alive', 'command' => 'AT', 'risk' => 'safe'],
        ['label' => 'Vendor', 'command' => 'AT+GMI', 'risk' => 'safe'],
        ['label' => 'Model/Firmware', 'command' => 'AT+GMM', 'risk' => 'safe'],
        ['label' => 'Identity', 'command' => 'AT+CNUMF?', 'risk' => 'safe'],
        ['label' => 'RSSI / CSQ', 'command' => 'AT+CSQ?', 'risk' => 'safe'],
        ['label' => 'Registration', 'command' => 'AT+CREG?', 'risk' => 'safe'],
        ['label' => 'Current mode', 'command' => 'AT+CTOM?', 'risk' => 'safe'],
        ['label' => 'Selected groups', 'command' => 'AT+CTGS?', 'risk' => 'safe'],
        ['label' => 'Switch DMO-MS', 'command' => 'AT+CTOM=1', 'risk' => 'mode'],
        ['label' => 'Switch DMO-RPT', 'command' => 'AT+CTOM=6', 'risk' => 'mode'],
    ];
}

function pei_log_append(array $entry): void
{
    $dir = dirname(DASH_PEI_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $entry['time'] = $entry['time'] ?? date('c');
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line !== false) {
        @file_put_contents(DASH_PEI_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

function pei_log_read(int $limit = 80): array
{
    if (!is_readable(DASH_PEI_LOG_FILE)) {
        return [];
    }

    $items = [];
    foreach (tail_lines(DASH_PEI_LOG_FILE, $limit, 262144) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $items[] = $row;
        }
    }
    return array_reverse($items);
}

function normalize_pei_command(string $command): string
{
    $command = strtoupper(trim($command));
    if ($command === '' || strlen($command) > 120) {
        throw new InvalidArgumentException('PEI command is empty or too long.');
    }
    if (!preg_match('/^AT[+A-Z0-9?=,._-]*$/', $command)) {
        throw new InvalidArgumentException('Only single-line AT commands are allowed.');
    }
    return $command;
}

function send_pei_command(string $command, string $source = 'admin'): array
{
    $command = normalize_pei_command($command);
    if (DASH_PEI_PTY === '' || !file_exists(DASH_PEI_PTY)) {
        throw new RuntimeException('PEI_PTY is not available. Enable PEI_PTY in TetraLogic once and restart SvxLink.');
    }

    $written = @file_put_contents(DASH_PEI_PTY, $command . "\n", FILE_APPEND);
    if ($written === false) {
        throw new RuntimeException('Could not write to PEI_PTY. Check web user permissions on ' . DASH_PEI_PTY . '.');
    }

    $entry = [
        'direction' => 'tx',
        'source' => $source,
        'command' => $command,
        'status' => 'sent',
    ];
    pei_log_append($entry);
    return $entry;
}

function apply_power_level(int $dbm): array
{
    $valid = array_column(radio_power_levels(), 'dbm');
    if (!in_array($dbm, $valid, true)) {
        throw new InvalidArgumentException('Unsupported power level.');
    }
    if (DASH_POWER_COMMAND_TEMPLATE === '') {
        throw new RuntimeException('Power command template is not configured. Confirm the Motorola PEI command first.');
    }

    $watts = dbm_to_watts((float)$dbm);
    $command = str_replace(
        ['{dbm}', '{mw}', '{w}'],
        [(string)$dbm, (string)round($watts * 1000), (string)round($watts, 3)],
        DASH_POWER_COMMAND_TEMPLATE
    );

    return send_pei_command($command, 'power');
}

function pei_dashboard_state(): array
{
    return [
        'admin_configured' => dashboard_admin_configured(),
        'admin_authenticated' => dashboard_admin_authenticated(),
        'pei_pty' => DASH_PEI_PTY,
        'pei_pty_ready' => DASH_PEI_PTY !== '' && file_exists(DASH_PEI_PTY),
        'power_template_configured' => DASH_POWER_COMMAND_TEMPLATE !== '',
        'power_levels' => radio_power_levels(),
        'presets' => default_pei_presets(),
        'log' => pei_log_read(),
    ];
}

function parse_log_timestamp(string $line): ?int
{
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2}):(\d{2})/', $line, $m)) {
        return mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[1], (int)$m[3]);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/', $line, $m)) {
        return mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
    }
    $ts = strtotime($line);
    return $ts === false ? null : $ts;
}

function clean_log_message(string $line): string
{
    $msg = preg_replace('/^\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}:\d{2}:\s*/', '', $line);
    if ($msg !== null && $msg !== $line) {
        return trim($msg);
    }
    $msg = preg_replace('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}:?\s*/', '', $line);
    return trim($msg ?? $line);
}

function event_from_log(string $line, array $users, array $config): ?array
{
    $ts = parse_log_timestamp($line);
    $message = clean_log_message($line);
    if (stripos($message, legacy_voice_name()) !== false) {
        return null;
    }

    $event = [
        'timestamp' => $ts ?? 0,
        'time' => $ts ? date('H:i:s', $ts) : '',
        'type' => 'info',
        'label' => 'Info',
        'message' => $message,
        'issi' => '',
        'gssi' => '',
        'tg' => '',
        'peer' => '',
        'rssi' => null,
        'reg_state' => '',
    ];

    if (preg_match('/Groupcall from\s+(\d+)\s+to\s+(\d+)/i', $message, $m)) {
        $event['type'] = 'rx';
        $event['label'] = 'DMO RX';
        $event['issi'] = $m[1];
        $event['gssi'] = $m[2];
        $event['peer'] = user_label($m[1], $users);
        $event['message'] = 'Groupcall from ' . $event['peer'] . ' to GSSI ' . $m[2];
        return $event;
    }

    if (preg_match('/Init groupcall to GSSI:\s*(\d+)/i', $message, $m)) {
        $event['type'] = 'tx';
        $event['label'] = 'DMO TX';
        $event['gssi'] = $m[1];
        $event['message'] = 'TX groupcall to GSSI ' . $m[1];
        return $event;
    }

    if (stripos($message, 'squelch is OPEN') !== false) {
        $event['type'] = 'rx';
        $event['label'] = 'SQL OPEN';
        return $event;
    }
    if (stripos($message, 'squelch is CLOSED') !== false) {
        $event['type'] = 'idle';
        $event['label'] = 'SQL CLOSED';
        return $event;
    }
    if (stripos($message, 'Turning the transmitter ON') !== false) {
        $event['type'] = 'tx';
        $event['label'] = 'TX ON';
        return $event;
    }
    if (stripos($message, 'Turning the transmitter OFF') !== false) {
        $event['type'] = 'idle';
        $event['label'] = 'TX OFF';
        return $event;
    }
    if (preg_match('/Talker start on TG\s*#?(\d+):\s*([A-Z0-9_-]+)/i', $message, $m)) {
        $event['type'] = 'reflector';
        $event['label'] = 'TG START';
        $event['tg'] = $m[1];
        $event['peer'] = $m[2];
        return $event;
    }
    if (preg_match('/Talker stop on TG\s*#?(\d+):\s*([A-Z0-9_-]+)/i', $message, $m)) {
        $event['type'] = 'reflector';
        $event['label'] = 'TG STOP';
        $event['tg'] = $m[1];
        $event['peer'] = $m[2];
        return $event;
    }
    if (preg_match('/Selecting TG\s*#?(\d+)/i', $message, $m)) {
        $event['type'] = 'reflector';
        $event['label'] = 'TG SELECT';
        $event['tg'] = $m[1];
        return $event;
    }
    if (preg_match('/New Rssi value measured:\s*(-?\d+)\s*dBm/i', $message, $m)
        || preg_match('/\brssi\s+(-?\d+)\b/i', $message, $m)) {
        $event['type'] = 'rssi';
        $event['label'] = 'RSSI';
        $event['rssi'] = (int)$m[1];
        $event['message'] = 'Gateway RSSI ' . $m[1] . ' dBm';
        return $event;
    }
    if (preg_match('/\+CSQ:\s*(\d+)/i', $message, $m)) {
        $rssi = -113 + 2 * (int)$m[1];
        $event['type'] = 'rssi';
        $event['label'] = 'RSSI';
        $event['rssi'] = $rssi;
        $event['message'] = 'Gateway RSSI ' . $rssi . ' dBm';
        return $event;
    }
    if (stripos($message, 'PEI init finished') !== false) {
        $event['type'] = 'pei';
        $event['label'] = 'PEI READY';
        return $event;
    }
    if (stripos($message, 'From PEI:') !== false) {
        $event['type'] = 'pei';
        $event['label'] = 'PEI';
        return $event;
    }
    if (preg_match('/State Sds received:\s*(\d+)/i', $message, $m)) {
        $event['type'] = 'sds';
        $event['label'] = 'STATUS SDS';
        $event['message'] = 'Status SDS ' . $m[1] . (($config['status_map'][$m[1]] ?? '') !== '' ? ': ' . $config['status_map'][$m[1]] : '');
        return $event;
    }
    if (preg_match('/Registration LA=(\d+),\s*MNI=(\d+),\s*state=(.+)$/i', $message, $m)) {
        $event['type'] = 'reg';
        $event['label'] = 'REG';
        $event['reg_state'] = trim($m[3]);
        $event['message'] = 'Registration ' . trim($m[3]) . ' LA ' . $m[1] . ' MNI ' . $m[2];
        return $event;
    }
    if (preg_match('/SDS|Sds|CMGS|CTSDSR/i', $message)) {
        $event['type'] = 'sds';
        $event['label'] = 'SDS';
        return $event;
    }
    if (preg_match('/Connection established|Authentication OK/i', $message)) {
        $event['type'] = 'reflector';
        $event['label'] = 'REF OK';
        return $event;
    }
    if (preg_match('/Disconnected|Could not look up host|timeout|refused|No route/i', $message)) {
        $event['type'] = 'warn';
        $event['label'] = 'NET WARN';
        return $event;
    }
    if (preg_match('/Distortion detected|ERROR|WARNING|failed|wrong/i', $message)) {
        $event['type'] = 'warn';
        $event['label'] = stripos($message, 'Distortion detected') !== false ? 'AUDIO CLIP' : 'WARN';
        return $event;
    }
    if (preg_match('/Started SvxLink|special TetraLogic|New Tetra mode/i', $message)) {
        $event['type'] = 'system';
        $event['label'] = 'SYSTEM';
        return $event;
    }

    return null;
}

function load_events(array $config, array $users, int $limit = DASH_LOG_LINES): array
{
    $events = [];
    foreach (tail_lines(SVXLINK_LOG, $limit) as $line) {
        $event = event_from_log($line, $users, $config);
        if ($event !== null) {
            $events[] = $event;
        }
    }
    return $events;
}

function runtime_state(array $events, array $config): array
{
    $state = 'idle';
    $label = 'IDLE';
    $description = 'Waiting for DMO activity';
    $lastGssi = (string)($config['logic']['GSSI'] ?? '');
    $lastIssi = '';
    $lastPeer = '';
    $selectedTg = '';
    $reflector = 'unknown';
    $pei = 'unknown';
    $warnings = 0;
    $audioClips = 0;

    foreach ($events as $event) {
        if ($event['type'] === 'warn') {
            $warnings++;
            if ($event['label'] === 'AUDIO CLIP') {
                $audioClips++;
            }
            if (preg_match('/Could not look up host|timeout|refused|No route|Disconnected/i', $event['message'])) {
                $reflector = 'down';
            }
        }
        if ($event['label'] === 'REF OK') {
            $reflector = 'connected';
        }
        if ($event['type'] === 'pei') {
            $pei = 'ready';
        }
        if ($event['tg'] !== '') {
            $selectedTg = $event['tg'];
        }
        if ($event['gssi'] !== '') {
            $lastGssi = $event['gssi'];
        }
        if ($event['issi'] !== '') {
            $lastIssi = $event['issi'];
            $lastPeer = $event['peer'];
        }
        if ($event['type'] === 'rx') {
            $state = 'rx';
            $label = 'DMO RX';
            $description = $lastPeer !== '' ? 'Receiving ' . $lastPeer . ' on GSSI ' . $lastGssi : 'Receiving local DMO groupcall';
        }
        if ($event['type'] === 'tx') {
            $state = 'tx';
            $label = 'DMO TX';
            $description = 'Transmitting to GSSI ' . ($lastGssi !== '' ? $lastGssi : ($config['logic']['GSSI'] ?? ''));
        }
        if ($event['type'] === 'idle') {
            $state = 'idle';
            $label = 'IDLE';
            $description = 'Waiting for DMO activity';
        }
    }

    return [
        'state' => $state,
        'label' => $label,
        'description' => $description,
        'gssi' => $lastGssi,
        'issi' => $lastIssi,
        'peer' => $lastPeer,
        'selected_tg' => $selectedTg,
        'reflector' => $reflector,
        'pei' => $pei,
        'warnings' => $warnings,
        'audio_clips' => $audioClips,
    ];
}

function mobile_presence(array $events, array $users): array
{
    $mobiles = [];
    $lastIssi = '';
    $lastActivityTs = 0;
    $gatewayRssi = null;

    foreach ($events as $event) {
        if ($event['type'] === 'rssi' && $event['rssi'] !== null) {
            $gatewayRssi = (int)$event['rssi'];
            if ($lastIssi !== '' && abs(((int)$event['timestamp']) - $lastActivityTs) <= 12) {
                $mobiles[$lastIssi]['rssi'] = $gatewayRssi;
                $mobiles[$lastIssi]['rssi_source'] = 'near RX';
            }
            continue;
        }

        $issi = $event['issi'];
        if ($issi === '' && in_array($event['type'], ['sds'], true) && preg_match('/\b(\d{5,17})\b/', $event['message'], $m)) {
            $issi = $m[1];
        }
        if ($issi === '') {
            continue;
        }

        $key = ltrim($issi, '0') ?: $issi;
        $lastIssi = $key;
        $lastActivityTs = (int)$event['timestamp'];

        if (!isset($mobiles[$key])) {
            $mobiles[$key] = [
                'issi' => $issi,
                'peer' => user_label($issi, $users),
                'last_seen_ts' => 0,
                'last_seen' => '',
                'last_event' => '',
                'gssi' => '',
                'rssi' => null,
                'rssi_source' => '',
            ];
        }

        $mobiles[$key]['last_seen_ts'] = (int)$event['timestamp'];
        $mobiles[$key]['last_seen'] = $event['time'];
        $mobiles[$key]['last_event'] = $event['label'];
        if ($event['gssi'] !== '') {
            $mobiles[$key]['gssi'] = $event['gssi'];
        }
    }

    uasort($mobiles, static fn($a, $b) => $b['last_seen_ts'] <=> $a['last_seen_ts']);

    return [
        'count' => count($mobiles),
        'gateway_rssi' => $gatewayRssi,
        'items' => array_values(array_slice($mobiles, 0, 40, true)),
        'rssi_note' => $gatewayRssi === null
            ? 'No RSSI in current log window'
            : 'RSSI is gateway CSQ unless correlated near RX',
    ];
}

function hardware_info(): array
{
    $load = sys_getloadavg();
    $memory = ['percent' => 0, 'label' => 'N/A'];
    if (is_readable('/proc/meminfo')) {
        $raw = (string)file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal:\s+(\d+)/', $raw, $total) && preg_match('/MemAvailable:\s+(\d+)/', $raw, $available)) {
            $used = (int)$total[1] - (int)$available[1];
            $pct = (int)round($used / max(1, (int)$total[1]) * 100);
            $memory = ['percent' => $pct, 'label' => $pct . '%'];
        }
    }

    $temp = '';
    if (is_readable('/sys/class/thermal/thermal_zone0/temp')) {
        $value = (int)trim((string)file_get_contents('/sys/class/thermal/thermal_zone0/temp'));
        if ($value > 0) {
            $temp = number_format($value / 1000, 1) . ' C';
        }
    }

    $total = @disk_total_space('/');
    $free = @disk_free_space('/');
    $diskPct = 0;
    if ($total !== false && $free !== false && $total > 0) {
        $diskPct = (int)round((($total - $free) / $total) * 100);
    }

    return [
        'hostname' => gethostname() ?: php_uname('n'),
        'kernel' => php_uname('r'),
        'arch' => php_uname('m'),
        'load' => $load !== false ? number_format((float)$load[0], 2) : 'N/A',
        'memory' => $memory,
        'disk_percent' => $diskPct,
        'temp' => $temp !== '' ? $temp : 'N/A',
    ];
}

function dashboard_data(): array
{
    $config = dashboard_config();
    $users = load_tetra_users();
    $events = load_events($config, $users);
    $runtime = runtime_state($events, $config);
    $mobiles = mobile_presence($events, $users);
    $logic = $config['logic'];
    $reflector = $config['reflector'];

    return [
        'title' => DASH_TITLE,
        'subtitle' => DASH_SUBTITLE,
        'site' => DASH_SITE,
        'generated_at' => date('c'),
        'service' => service_status(),
        'runtime' => $runtime,
        'tetra' => [
            'model' => MTM_MODEL,
            'role' => DMO_ROLE,
            'callsign' => $logic['CALLSIGN'] ?? DASH_SITE,
            'mode' => $logic['TETRA_MODE'] ?? 'DMO-MS',
            'port' => $logic['PORT'] ?? '',
            'baud' => $logic['BAUD'] ?? '',
            'mcc' => $logic['MCC'] ?? '',
            'mnc' => $logic['MNC'] ?? '',
            'issi' => $logic['ISSI'] ?? '',
            'gssi' => $logic['GSSI'] ?? '',
            'pei_pty' => $logic['PEI_PTY'] ?? '',
            'sds_interval' => $logic['TIME_BETWEEN_SDS'] ?? '',
            'proximity' => $logic['PROXIMITY_WARNING'] ?? '',
            'modules' => $config['modules'],
            'users' => count($users['by_tsi'] ?? []),
            'status_count' => count($config['status_map']),
            'command_count' => count($config['command_map']),
        ],
        'radio' => [
            'rx_name' => $config['rx']['name'] ?? '',
            'rx_audio' => $config['rx']['AUDIO_DEV'] ?? '',
            'sql_det' => $config['rx']['SQL_DET'] ?? '',
            'sql_delay' => $config['rx']['SQL_DELAY'] ?? '',
            'sql_hangtime' => $config['rx']['SQL_HANGTIME'] ?? '',
            'tx_name' => $config['tx']['name'] ?? '',
            'tx_audio' => $config['tx']['AUDIO_DEV'] ?? '',
            'ptt_type' => $config['tx']['PTT_TYPE'] ?? '',
            'tx_delay' => $config['tx']['TX_DELAY'] ?? '',
        ],
        'reflector' => [
            'callsign' => $reflector['CALLSIGN'] ?? '',
            'host' => $reflector['HOSTS'] ?? '',
            'default_tg' => $reflector['DEFAULT_TG'] ?? '',
            'monitor_tgs' => $reflector['MONITOR_TGS'] ?? '',
        ],
        'hardware' => hardware_info(),
        'mobiles' => $mobiles,
        'events' => array_reverse(array_slice(array_reverse($events), 0, 80)),
        'paths' => $config['paths'],
    ];
}
