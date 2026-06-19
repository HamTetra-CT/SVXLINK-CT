<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set(DASH_TIMEZONE);

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

    return [
        'paths' => [
            'svxlink_config' => SVXLINK_CONFIG,
            'tetralogic_config' => TETRALOGIC_CONFIG,
            'tetra_users' => TETRA_USERS_FILE,
            'pei_init' => PEI_INIT_FILE,
            'log' => resolve_log_path(SVXLINK_LOG),
        ],
        'global' => $global,
        'logic' => $tetra,
        'rx' => ['name' => $rxName] + $rx,
        'tx' => ['name' => $txName] + $tx,
        'reflector' => $reflector,
        'status_map' => $tetraFile[$statusSection] ?? [],
        'command_map' => $tetraFile[$commandSection] ?? [],
        'modules' => array_values(array_filter(array_map('trim', explode(',', $tetra['MODULES'] ?? '')))),
        'pei_init' => read_json_array(PEI_INIT_FILE),
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
