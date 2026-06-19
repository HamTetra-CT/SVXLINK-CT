<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set(DASH_TIMEZONE);

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dashboard_footer(): string
{
    return '<footer class="footer-credit">'
        . '<span>Versão do painel ' . h(DASH_VERSION) . '.</span>'
        . '<span>&lt;3 feita com amor pela <a href="' . h(DASH_HAMTETRA_URL) . '" target="_blank" rel="noopener">HAMTETRA-CT</a>.</span>'
        . '<a class="telegram-link" href="' . h(DASH_TELEGRAM_URL) . '" target="_blank" rel="noopener" aria-label="Grupo Telegram HAMTETRA-CT">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.7 4.3 18.5 19.6c-.2 1-.8 1.2-1.6.8l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.5 13.4 1.8 12c-1-.3-1-1 .2-1.5L20.3 3.4c.9-.3 1.7.2 1.4.9Z"/></svg>'
        . '<span>Telegram</span></a>'
        . '<span>Créditos SvxLink e TetraLogic mantidos pelos autores originais.</span>'
        . '</footer>';
}

function service_status_label(string $status): string
{
    return [
        'active' => 'ATIVO',
        'inactive' => 'INATIVO',
        'failed' => 'FALHA',
        'connected' => 'LIGADO',
        'down' => 'EM BAIXO',
        'ready' => 'PRONTO',
        'unknown' => 'DESCONHECIDO',
    ][$status] ?? strtoupper($status);
}

function log_filter_label(string $type): string
{
    return [
        'all' => 'TODOS',
        'rx' => 'RX',
        'tx' => 'TX',
        'idle' => 'ESPERA',
        'sds' => 'SDS',
        'pei' => 'PEI',
        'rssi' => 'RSSI',
        'reg' => 'REGISTO',
        'reflector' => 'REFLETOR',
        'warn' => 'ALERTAS',
        'system' => 'SISTEMA',
    ][$type] ?? strtoupper($type);
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

    header('WWW-Authenticate: Basic realm="SVXLINK-CT Painel"');
    http_response_code(401);
    echo 'Autenticação necessária';
    exit;
}

function write_local_dashboard_config(array $changes): void
{
    $current = [];
    if (is_readable(DASH_LOCAL_CONFIG_PATH)) {
        $loaded = require DASH_LOCAL_CONFIG_PATH;
        if (is_array($loaded)) {
            $current = $loaded;
        }
    }

    foreach ($changes as $key => $value) {
        $current[$key] = (string)$value;
    }

    ksort($current);
    $body = "<?php\nreturn " . var_export($current, true) . ";\n";
    $dir = dirname(DASH_LOCAL_CONFIG_PATH);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (@file_put_contents(DASH_LOCAL_CONFIG_PATH, $body, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível gravar o ficheiro de configuração local.');
    }
}

function update_admin_password(string $password): void
{
    $password = trim($password);
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('A palavra-passe tem de ter pelo menos 8 caracteres.');
    }
    write_local_dashboard_config([
        'SVXDASH_ADMIN_USER' => DASH_ADMIN_USER,
        'SVXDASH_ADMIN_PASSWORD' => $password,
    ]);
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
            'label' => 'Serviço online',
            'destination' => '',
            'type' => 'T',
            'message' => DASH_SITE . ' online',
        ],
        [
            'id' => 'metarinfo',
            'label' => 'MetarInfo',
            'destination' => '',
            'type' => 'T',
            'message' => '99',
        ],
        [
            'id' => 'parrot-on',
            'label' => 'Parrot ON',
            'destination' => '',
            'type' => 'T',
            'message' => '91',
        ],
        [
            'id' => 'parrot-off',
            'label' => 'Parrot OFF',
            'destination' => '',
            'type' => 'T',
            'message' => '90',
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
        throw new InvalidArgumentException('O modelo precisa de nome e mensagem.');
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
        throw new RuntimeException('Não foi possível gravar o ficheiro de modelos SDS.');
    }

    return $preset;
}

function delete_sds_preset(string $id): void
{
    $id = trim($id);
    if ($id === '') {
        throw new InvalidArgumentException('É necessário escolher um modelo.');
    }

    $presets = array_values(array_filter(load_sds_presets(), static fn($preset) => ($preset['id'] ?? '') !== $id));
    if (!write_json_file(DASH_SDS_PRESETS_FILE, ['presets' => $presets])) {
        throw new RuntimeException('Não foi possível gravar o ficheiro de modelos SDS.');
    }
}

function normalize_sds_destination(string $destination, bool $allowEmpty = false): string
{
    $destination = preg_replace('/\D+/', '', $destination) ?? '';
    if ($destination === '' && $allowEmpty) {
        return '';
    }
    if ($destination === '' || strlen($destination) > 17) {
        throw new InvalidArgumentException('O destino tem de ser um ISSI ou TSI completo.');
    }
    return $destination;
}

function validate_sds_payload(string $type, string $message): void
{
    if ($type === 'R') {
        if (!preg_match('/^[0-9A-Fa-f]+$/', $message) || strlen($message) % 2 !== 0 || strlen($message) > 220) {
            throw new InvalidArgumentException('O SDS HEX tem de ter tamanho par e no máximo 220 caracteres.');
        }
        return;
    }

    if (strlen($message) > 120) {
        throw new InvalidArgumentException('O SDS de texto está limitado a 120 caracteres neste caminho TetraLogic.');
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
        throw new RuntimeException('O SDS_PTY não está disponível. Confirma o SDS_PTY no TetraLogic e reinicia o SvxLink uma vez.');
    }

    $payload = $destination . ',' . $type . ',' . $message . "\n";
    $written = @file_put_contents($pty, $payload, FILE_APPEND);
    if ($written === false) {
        throw new RuntimeException('Não foi possível escrever no SDS_PTY. Confirma as permissões do utilizador web em ' . $pty . '.');
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

function format_bytes_value(float $bytes): string
{
    if ($bytes <= 0) {
        return 'Indisponível';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = $bytes;
    $idx = 0;
    while ($value >= 1024 && $idx < count($units) - 1) {
        $value /= 1024;
        $idx++;
    }

    $precision = $value >= 10 || $idx === 0 ? 0 : 1;
    return number_format($value, $precision, ',', ' ') . ' ' . $units[$idx];
}

function radio_power_levels(): array
{
    return [
        ['dbm' => 27.5, 'watts' => 0.56, 'label' => '27.5 dBm / 560 mW'],
        ['dbm' => 30.0, 'watts' => 1.0, 'label' => '30.0 dBm / 1.0 W'],
        ['dbm' => 32.5, 'watts' => 1.8, 'label' => '32.5 dBm / 1.8 W'],
        ['dbm' => 35.0, 'watts' => 3.0, 'label' => '35.0 dBm / 3.0 W'],
        ['dbm' => 37.5, 'watts' => 5.6, 'label' => '37.5 dBm / 5.6 W'],
        ['dbm' => 40.0, 'watts' => 10.0, 'label' => '40.0 dBm / 10.0 W'],
    ];
}

function meteo_intervals(): array
{
    $minutes = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 60, 120, 180, 240, 300, 360, 420, 480, 540, 600, 660, 720, 1440];
    $items = [];
    foreach ($minutes as $value) {
        if ($value === 1440) {
            $label = '1x ao dia';
        } elseif ($value >= 60) {
            $hours = intdiv($value, 60);
            $label = $hours === 1 ? '1 hora' : $hours . ' horas';
        } else {
            $label = $value . ' minutos';
        }
        $items[] = ['value' => $value, 'label' => $label];
    }
    return $items;
}

function meteo_locations(): array
{
    return [
        ['id' => 'AVE', 'label' => 'Aveiro', 'area' => 'AVE', 'area_label' => 'Aveiro'],
        ['id' => 'BEJ', 'label' => 'Beja', 'area' => 'BEJ', 'area_label' => 'Beja'],
        ['id' => 'BRA', 'label' => 'Braga', 'area' => 'BRA', 'area_label' => 'Braga'],
        ['id' => 'BGC', 'label' => 'Bragança', 'area' => 'BGC', 'area_label' => 'Bragança'],
        ['id' => 'CBR', 'label' => 'Castelo Branco', 'area' => 'CBR', 'area_label' => 'Castelo Branco'],
        ['id' => 'COI', 'label' => 'Coimbra', 'area' => 'COI', 'area_label' => 'Coimbra'],
        ['id' => 'EVO', 'label' => 'Évora', 'area' => 'EVO', 'area_label' => 'Évora'],
        ['id' => 'FAR', 'label' => 'Faro', 'area' => 'FAR', 'area_label' => 'Faro'],
        ['id' => 'GUA', 'label' => 'Guarda', 'area' => 'GUA', 'area_label' => 'Guarda'],
        ['id' => 'LEI', 'label' => 'Leiria', 'area' => 'LEI', 'area_label' => 'Leiria'],
        ['id' => 'LSB', 'label' => 'Lisboa', 'area' => 'LSB', 'area_label' => 'Lisboa'],
        ['id' => 'PTG', 'label' => 'Portalegre', 'area' => 'PTG', 'area_label' => 'Portalegre'],
        ['id' => 'POR', 'label' => 'Porto', 'area' => 'POR', 'area_label' => 'Porto'],
        ['id' => 'STR', 'label' => 'Santarém', 'area' => 'STR', 'area_label' => 'Santarém'],
        ['id' => 'SET', 'label' => 'Setúbal', 'area' => 'SET', 'area_label' => 'Setúbal'],
        ['id' => 'VCT', 'label' => 'Viana do Castelo', 'area' => 'VCT', 'area_label' => 'Viana do Castelo'],
        ['id' => 'VRL', 'label' => 'Vila Real', 'area' => 'VRL', 'area_label' => 'Vila Real'],
        ['id' => 'VIS', 'label' => 'Viseu', 'area' => 'VIS', 'area_label' => 'Viseu'],
        ['id' => 'MAD', 'label' => 'Madeira', 'area' => 'MAD', 'area_label' => 'Madeira'],
        ['id' => 'PXO', 'label' => 'Porto Santo', 'area' => 'PXO', 'area_label' => 'Porto Santo'],
        ['id' => 'SMA', 'label' => 'Santa Maria', 'area' => 'SMA', 'area_label' => 'Santa Maria'],
        ['id' => 'TER', 'label' => 'Terceira', 'area' => 'TER', 'area_label' => 'Terceira'],
        ['id' => 'GRA', 'label' => 'Graciosa', 'area' => 'GRA', 'area_label' => 'Graciosa'],
        ['id' => 'SJG', 'label' => 'São Jorge', 'area' => 'SJG', 'area_label' => 'São Jorge'],
        ['id' => 'PIC', 'label' => 'Pico', 'area' => 'PIC', 'area_label' => 'Pico'],
        ['id' => 'FAI', 'label' => 'Faial', 'area' => 'FAI', 'area_label' => 'Faial'],
        ['id' => 'FLO', 'label' => 'Flores', 'area' => 'FLO', 'area_label' => 'Flores'],
        ['id' => 'COR', 'label' => 'Corvo', 'area' => 'COR', 'area_label' => 'Corvo'],
    ];
}

function default_meteo_config(): array
{
    return [
        'enabled' => false,
        'interval_minutes' => 60,
        'location_id' => 'LSB',
        'location_label' => 'Lisboa',
        'area' => 'LSB',
        'area_label' => 'Lisboa',
        'credentials' => DASH_METEO_CREDENTIALS,
        'output_wav' => DASH_METEO_OUTPUT_WAV,
        'dtmf_pty' => DASH_METEO_DTMF_PTY,
        'dtmf_command' => '99#',
        'trigger_dtmf' => true,
        'api_url' => 'https://api.ipma.pt/open-data/forecast/warnings/warnings_www.json',
    ];
}

function find_meteo_location(string $id): ?array
{
    foreach (meteo_locations() as $location) {
        if ($location['id'] === $id) {
            return $location;
        }
    }
    return null;
}

function load_meteo_config(): array
{
    $config = default_meteo_config();
    $stored = read_json_array(DASH_METEO_CONFIG_FILE);
    if ($stored) {
        $config = array_replace($config, $stored);
    }
    $location = find_meteo_location((string)($config['location_id'] ?? ''));
    if (!$location) {
        $location = find_meteo_location('LSB');
        $config['location_id'] = 'LSB';
    }
    if ($location) {
        $config['location_label'] = $location['label'];
        $config['area'] = $location['area'];
        $config['area_label'] = $location['area_label'];
    }
    $config['interval_minutes'] = (int)($config['interval_minutes'] ?? 60);
    $config['enabled'] = (bool)($config['enabled'] ?? false);
    $config['trigger_dtmf'] = (bool)($config['trigger_dtmf'] ?? true);
    return $config;
}

function save_meteo_config(array $input): array
{
    $interval = (int)($input['interval_minutes'] ?? 60);
    $allowed = array_column(meteo_intervals(), 'value');
    if (!in_array($interval, $allowed, true)) {
        throw new InvalidArgumentException('Intervalo de avisos inválido.');
    }

    $location = find_meteo_location((string)($input['location_id'] ?? ''));
    if (!$location) {
        throw new InvalidArgumentException('Distrito dos avisos inválido.');
    }

    $current = load_meteo_config();
    $config = array_replace($current, [
        'enabled' => !empty($input['enabled']),
        'interval_minutes' => $interval,
        'location_id' => $location['id'],
        'location_label' => $location['label'],
        'area' => $location['area'],
        'area_label' => $location['area_label'],
        'credentials' => DASH_METEO_CREDENTIALS,
        'output_wav' => DASH_METEO_OUTPUT_WAV,
        'dtmf_pty' => DASH_METEO_DTMF_PTY,
        'dtmf_command' => '99#',
        'trigger_dtmf' => true,
    ]);

    if (!write_json_file(DASH_METEO_CONFIG_FILE, $config)) {
        throw new RuntimeException('Não foi possível gravar a configuração dos avisos.');
    }

    return $config;
}

function meteo_dashboard_state(): array
{
    $config = load_meteo_config();
    $state = read_json_array(DASH_METEO_STATE_FILE);
    $outputDir = dirname((string)$config['output_wav']);

    return [
        'admin_configured' => dashboard_admin_configured(),
        'admin_authenticated' => dashboard_admin_authenticated(),
        'config' => $config,
        'intervals' => meteo_intervals(),
        'locations' => meteo_locations(),
        'runner' => DASH_METEO_RUNNER,
        'runner_ready' => is_executable(DASH_METEO_RUNNER),
        'credentials_ready' => is_readable((string)$config['credentials']),
        'output_dir_ready' => is_dir($outputDir) && is_writable($outputDir),
        'state_file' => DASH_METEO_STATE_FILE,
        'last_state' => $state,
    ];
}

function set_config_section_keys(string $path, string $section, array $changes): void
{
    if (!is_readable($path)) {
        throw new RuntimeException('Não foi possível ler ' . $path . '.');
    }
    if (!is_writable($path)) {
        throw new RuntimeException('Sem permissões de escrita em ' . $path . '.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Não foi possível abrir ' . $path . '.');
    }

    $foundSection = false;
    $inside = false;
    $seen = [];
    $out = [];

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim !== '' && $trim[0] === '[' && substr($trim, -1) === ']') {
            if ($inside) {
                foreach ($changes as $key => $value) {
                    if (!isset($seen[$key])) {
                        $out[] = $key . '=' . $value;
                    }
                }
            }
            $inside = trim($trim, '[]') === $section;
            $foundSection = $foundSection || $inside;
            $out[] = $line;
            continue;
        }

        if ($inside && preg_match('/^\s*([A-Za-z0-9_]+)\s*=/', $line, $m)) {
            $key = $m[1];
            if (array_key_exists($key, $changes)) {
                $out[] = $key . '=' . $changes[$key];
                $seen[$key] = true;
                continue;
            }
        }
        $out[] = $line;
    }

    if ($inside) {
        foreach ($changes as $key => $value) {
            if (!isset($seen[$key])) {
                $out[] = $key . '=' . $value;
            }
        }
    }

    if (!$foundSection) {
        $out[] = '';
        $out[] = '[' . $section . ']';
        foreach ($changes as $key => $value) {
            $out[] = $key . '=' . $value;
        }
    }

    $backup = $path . '.backup.' . date('Ymd-His');
    @copy($path, $backup);
    if (@file_put_contents($path, implode("\n", $out) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível gravar ' . $path . '.');
    }
}

function parse_sds_command_text(string $text): array
{
    $items = [];
    foreach (preg_split('/\R/', $text) ?: [] as $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        if (strpos($line, '=') === false) {
            throw new InvalidArgumentException('Cada DTMF tem de usar o formato CODIGO=COMANDO.');
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || $value === '') {
            throw new InvalidArgumentException('Cada DTMF precisa de código e comando.');
        }
        if (!preg_match('/^[0-9A-Za-z_.:-]+$/', $key)) {
            throw new InvalidArgumentException('Código DTMF inválido: ' . $key);
        }
        $items[$key] = $value;
    }
    return $items;
}

function sds_command_text(array $commandMap): string
{
    if (!$commandMap) {
        $commandMap = [
            '99' => 'ModuleMetarInfo:play',
            '91' => 'ModuleParrot:activate',
            '90' => 'ModuleParrot:deactivate',
        ];
    }
    $lines = [];
    foreach ($commandMap as $key => $value) {
        $lines[] = $key . '=' . $value;
    }
    return implode("\n", $lines);
}

function save_repeater_config(array $input): array
{
    $callsign = trim((string)($input['callsign'] ?? ''));
    $gssi = preg_replace('/\D+/', '', (string)($input['gssi'] ?? '')) ?? '';
    $defaultTg = preg_replace('/\D+/', '', (string)($input['default_tg'] ?? '')) ?? '';
    $monitorTgs = preg_replace('/[^0-9,]+/', '', (string)($input['monitor_tgs'] ?? '')) ?? '';
    $mode = trim((string)($input['tetra_mode'] ?? 'DMO-MS'));
    $modules = trim((string)($input['modules'] ?? ''));
    $dtmfCommands = parse_sds_command_text((string)($input['dtmf_commands'] ?? ($input['sds_commands'] ?? '')));

    if (!preg_match('/^[A-Za-z0-9_-]{3,18}$/', $callsign)) {
        throw new InvalidArgumentException('Indicativo inválido.');
    }
    if ($gssi === '' || strlen($gssi) > 8) {
        throw new InvalidArgumentException('GSSI inválido.');
    }
    if ($defaultTg !== '' && strlen($defaultTg) > 8) {
        throw new InvalidArgumentException('TG prioritário inválido.');
    }
    if (!in_array($mode, ['DMO-MS', 'DMO-RPT', 'TMO'], true)) {
        throw new InvalidArgumentException('Modo TETRA inválido.');
    }

    $tetraChanges = [
        'CALLSIGN' => $callsign,
        'TETRA_MODE' => $mode,
        'GSSI' => $gssi,
    ];
    if ($modules !== '') {
        $tetraChanges['MODULES'] = $modules;
    }

    set_config_section_keys(TETRALOGIC_CONFIG, 'TetraLogic', $tetraChanges);
    set_config_section_keys(TETRALOGIC_CONFIG, 'SdsToCommand', $dtmfCommands);

    if (is_readable(SVXLINK_CONFIG) && is_writable(SVXLINK_CONFIG)) {
        $reflectorChanges = ['CALLSIGN' => $callsign];
        if ($defaultTg !== '') {
            $reflectorChanges['DEFAULT_TG'] = $defaultTg;
        }
        if ($monitorTgs !== '') {
            $reflectorChanges['MONITOR_TGS'] = $monitorTgs;
        }
        set_config_section_keys(SVXLINK_CONFIG, 'ReflectorLogic', $reflectorChanges);
    }

    write_local_dashboard_config(['SVXDASH_SITE' => $callsign]);
    return dashboard_admin_settings_state();
}

function dashboard_admin_settings_state(): array
{
    $config = dashboard_config();
    $logic = $config['logic'];
    $reflector = $config['reflector'];
    $sdsCommands = sds_command_text($config['command_map']);

    return [
        'paths' => [
            'svxlink_config' => SVXLINK_CONFIG,
            'tetralogic_config' => TETRALOGIC_CONFIG,
            'helper' => DASH_MAINT_HELPER,
        ],
        'settings' => [
            'callsign' => DASH_SITE,
            'tetra_mode' => (string)($logic['TETRA_MODE'] ?? 'DMO-MS'),
            'gssi' => (string)($logic['GSSI'] ?? '1'),
            'default_tg' => (string)($reflector['DEFAULT_TG'] ?? ''),
            'monitor_tgs' => (string)($reflector['MONITOR_TGS'] ?? ''),
            'modules' => implode(',', $config['modules']),
            'dtmf_commands' => $sdsCommands,
        ],
        'helper_ready' => is_executable(DASH_MAINT_HELPER),
        'actions' => [
            ['id' => 'restart-svxlink', 'label' => 'Reiniciar SvxLink', 'risk' => 'medium'],
            ['id' => 'apt-update', 'label' => 'Actualizar lista apt', 'risk' => 'low'],
            ['id' => 'apt-upgrade', 'label' => 'Actualizar pacotes', 'risk' => 'medium'],
            ['id' => 'meteo-now', 'label' => 'Gerar aviso meteo agora', 'risk' => 'low'],
            ['id' => 'restart-system', 'label' => 'Reiniciar equipamento', 'risk' => 'high'],
        ],
    ];
}

function run_dashboard_maintenance_action(string $action): array
{
    $allowed = array_column(dashboard_admin_settings_state()['actions'], 'id');
    if (!in_array($action, $allowed, true)) {
        throw new InvalidArgumentException('Acção de manutenção inválida.');
    }
    if (!is_executable(DASH_MAINT_HELPER)) {
        throw new RuntimeException('Helper de manutenção não instalado: ' . DASH_MAINT_HELPER);
    }

    $cmd = implode(' ', array_map('escapeshellarg', ['sudo', '-n', DASH_MAINT_HELPER, $action])) . ' 2>&1';
    $output = [];
    $code = 1;
    exec($cmd, $output, $code);
    $text = trim(implode("\n", array_slice($output, -40)));
    if ($code !== 0) {
        throw new RuntimeException($text !== '' ? $text : 'A acção falhou.');
    }

    return [
        'action' => $action,
        'status' => 'ok',
        'output' => $text,
    ];
}

function default_pei_presets(): array
{
    return [
        ['label' => 'PEI activo', 'command' => 'AT', 'risk' => 'safe'],
        ['label' => 'Fabricante', 'command' => 'AT+GMI', 'risk' => 'safe'],
        ['label' => 'Modelo/Firmware', 'command' => 'AT+GMM', 'risk' => 'safe'],
        ['label' => 'Identidade', 'command' => 'AT+CNUMF?', 'risk' => 'safe'],
        ['label' => 'RSSI / CSQ', 'command' => 'AT+CSQ?', 'risk' => 'safe'],
        ['label' => 'Registo', 'command' => 'AT+CREG?', 'risk' => 'safe'],
        ['label' => 'Modo actual', 'command' => 'AT+CTOM?', 'risk' => 'safe'],
        ['label' => 'Grupos seleccionados', 'command' => 'AT+CTGS?', 'risk' => 'safe'],
        ['label' => 'Mudar para DMO-MS', 'command' => 'AT+CTOM=1', 'risk' => 'mode'],
        ['label' => 'Mudar para DMO-RPT', 'command' => 'AT+CTOM=6', 'risk' => 'mode'],
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
        throw new InvalidArgumentException('O comando PEI está vazio ou é demasiado longo.');
    }
    if (!preg_match('/^AT[+A-Z0-9?=,._-]*$/', $command)) {
        throw new InvalidArgumentException('Só são permitidos comandos AT de uma linha.');
    }
    return $command;
}

function send_pei_command(string $command, string $source = 'admin'): array
{
    $command = normalize_pei_command($command);
    if (DASH_PEI_PTY === '' || !file_exists(DASH_PEI_PTY)) {
        throw new RuntimeException('O PEI_PTY não está disponível. Confirma o PEI_PTY no TetraLogic e reinicia o SvxLink uma vez.');
    }

    $written = @file_put_contents(DASH_PEI_PTY, $command . "\n", FILE_APPEND);
    if ($written === false) {
        throw new RuntimeException('Não foi possível escrever no PEI_PTY. Confirma as permissões do utilizador web em ' . DASH_PEI_PTY . '.');
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

function apply_power_level(float $dbm): array
{
    $selected = null;
    foreach (radio_power_levels() as $level) {
        if (abs((float)$level['dbm'] - $dbm) < 0.01) {
            $selected = $level;
            break;
        }
    }
    if ($selected === null) {
        throw new InvalidArgumentException('Nível de potência não suportado.');
    }
    if (DASH_POWER_COMMAND_TEMPLATE === '') {
        throw new RuntimeException('O modelo de comando de potência ainda não está configurado. Confirma primeiro o comando PEI Motorola.');
    }

    $watts = (float)$selected['watts'];
    $command = str_replace(
        ['{dbm}', '{mw}', '{w}'],
        [number_format((float)$selected['dbm'], 1, '.', ''), (string)round($watts * 1000), (string)round($watts, 3)],
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
        $event['message'] = 'Chamada de grupo de ' . $event['peer'] . ' para GSSI ' . $m[2];
        return $event;
    }

    if (preg_match('/Init groupcall to GSSI:\s*(\d+)/i', $message, $m)) {
        $event['type'] = 'tx';
        $event['label'] = 'DMO TX';
        $event['gssi'] = $m[1];
        $event['message'] = 'TX chamada de grupo para GSSI ' . $m[1];
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
        $event['message'] = 'RSSI do gateway ' . $m[1] . ' dBm';
        return $event;
    }
    if (preg_match('/\+CSQ:\s*(\d+)/i', $message, $m)) {
        $rssi = -113 + 2 * (int)$m[1];
        $event['type'] = 'rssi';
        $event['label'] = 'RSSI';
        $event['rssi'] = $rssi;
        $event['message'] = 'RSSI do gateway ' . $rssi . ' dBm';
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
        $event['label'] = 'ESTADO SDS';
        $event['message'] = 'Estado SDS ' . $m[1] . (($config['status_map'][$m[1]] ?? '') !== '' ? ': ' . $config['status_map'][$m[1]] : '');
        return $event;
    }
    if (preg_match('/Registration LA=(\d+),\s*MNI=(\d+),\s*state=(.+)$/i', $message, $m)) {
        $event['type'] = 'reg';
        $event['label'] = 'REG';
        $event['reg_state'] = trim($m[3]);
        $event['message'] = 'Registo ' . trim($m[3]) . ' LA ' . $m[1] . ' MNI ' . $m[2];
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
        $event['label'] = 'ALERTA REDE';
        return $event;
    }
    if (preg_match('/Distortion detected|ERROR|WARNING|failed|wrong/i', $message)) {
        $event['type'] = 'warn';
        $event['label'] = stripos($message, 'Distortion detected') !== false ? 'SAT. ÁUDIO' : 'ALERTA';
        return $event;
    }
    if (preg_match('/Started SvxLink|special TetraLogic|New Tetra mode/i', $message)) {
        $event['type'] = 'system';
        $event['label'] = 'SISTEMA';
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
    $label = 'EM ESPERA';
    $description = 'A aguardar actividade DMO';
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
            if ($event['label'] === 'SAT. ÁUDIO') {
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
            $description = $lastPeer !== '' ? 'A receber ' . $lastPeer . ' no GSSI ' . $lastGssi : 'A receber chamada de grupo DMO local';
        }
        if ($event['type'] === 'tx') {
            $state = 'tx';
            $label = 'DMO TX';
            $description = 'A transmitir para o GSSI ' . ($lastGssi !== '' ? $lastGssi : ($config['logic']['GSSI'] ?? ''));
        }
        if ($event['type'] === 'idle') {
            $state = 'idle';
            $label = 'EM ESPERA';
            $description = 'A aguardar actividade DMO';
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
                $mobiles[$lastIssi]['rssi_source'] = 'perto do RX';
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
            ? 'Sem RSSI nos registos recentes'
            : 'RSSI do gateway; por terminal só quando correlacionado com RX',
    ];
}

function hardware_info(): array
{
    $load = sys_getloadavg();
    $memory = [
        'percent' => 0,
        'free_percent' => 0,
        'available_percent' => 0,
        'label' => 'Indisponível',
        'total' => 'Indisponível',
        'used' => 'Indisponível',
        'free' => 'Indisponível',
        'available' => 'Indisponível',
        'used_of_total' => 'Indisponível',
    ];
    if (is_readable('/proc/meminfo')) {
        $raw = (string)file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal:\s+(\d+)/', $raw, $total) && preg_match('/MemAvailable:\s+(\d+)/', $raw, $available)) {
            $totalKb = (int)$total[1];
            $availableKb = (int)$available[1];
            $freeKb = preg_match('/MemFree:\s+(\d+)/', $raw, $freeMatch) ? (int)$freeMatch[1] : $availableKb;
            $usedKb = max(0, $totalKb - $availableKb);
            $pct = (int)round($usedKb / max(1, $totalKb) * 100);
            $freePct = (int)round($freeKb / max(1, $totalKb) * 100);
            $availablePct = (int)round($availableKb / max(1, $totalKb) * 100);
            $totalLabel = format_bytes_value($totalKb * 1024);
            $usedLabel = format_bytes_value($usedKb * 1024);
            $memory = [
                'percent' => $pct,
                'free_percent' => $freePct,
                'available_percent' => $availablePct,
                'label' => $pct . '%',
                'total' => $totalLabel,
                'used' => $usedLabel,
                'free' => format_bytes_value($freeKb * 1024),
                'available' => format_bytes_value($availableKb * 1024),
                'used_of_total' => $usedLabel . ' / ' . $totalLabel,
            ];
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
    $disk = [
        'percent' => 0,
        'total' => 'Indisponível',
        'used' => 'Indisponível',
        'free' => 'Indisponível',
        'used_of_total' => 'Indisponível',
    ];
    if ($total !== false && $free !== false && $total > 0) {
        $used = $total - $free;
        $diskPct = (int)round(($used / $total) * 100);
        $totalLabel = format_bytes_value((float)$total);
        $usedLabel = format_bytes_value((float)$used);
        $disk = [
            'percent' => $diskPct,
            'total' => $totalLabel,
            'used' => $usedLabel,
            'free' => format_bytes_value((float)$free),
            'used_of_total' => $usedLabel . ' / ' . $totalLabel,
        ];
    }

    return [
        'hostname' => gethostname() ?: php_uname('n'),
        'kernel' => php_uname('r'),
        'arch' => php_uname('m'),
        'cpu_cores' => trim(command_output(['nproc'])) ?: 'Indisponível',
        'load' => $load !== false ? number_format((float)$load[0], 2) : 'Indisponível',
        'memory' => $memory,
        'disk' => $disk,
        'disk_percent' => $diskPct,
        'temp' => $temp !== '' ? $temp : 'Indisponível',
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
            'callsign' => DASH_SITE,
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
