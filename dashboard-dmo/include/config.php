<?php
declare(strict_types=1);

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
define('DASH_LOCAL_CONFIG_PATH', $localConfigPath);
if (is_readable($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

function dash_config(string $key, string $default): string
{
    global $localConfig;

    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }

    if (array_key_exists($key, $localConfig) && $localConfig[$key] !== '') {
        return (string)$localConfig[$key];
    }

    return $default;
}

function dash_root_path(string $relative, string $default): string
{
    $root = dash_config('SVXDASH_ROOT', '');
    if ($root !== '') {
        return rtrim($root, '/') . $relative;
    }
    return $default;
}

define('DASH_TIMEZONE', dash_config('SVXDASH_TIMEZONE', 'Europe/Lisbon'));
define('DASH_VERSION', dash_config('SVXDASH_VERSION', 'V1.0'));
define('DASH_TITLE', dash_config('SVXDASH_TITLE', 'Painel SVXLINK'));
define('DASH_SUBTITLE', dash_config('SVXDASH_SUBTITLE', 'Motorola MTM5400'));
define('DASH_SITE', dash_config('SVXDASH_SITE', 'CQ0Exxx'));
define('DASH_REFRESH_SECONDS', (int)dash_config('SVXDASH_REFRESH_SECONDS', '5'));
define('DASH_LOG_LINES', (int)dash_config('SVXDASH_LOG_LINES', '900'));
define('DASH_HAMTETRA_URL', dash_config('SVXDASH_HAMTETRA_URL', 'https://github.com/HamTetra-CT/'));
define('DASH_TELEGRAM_URL', dash_config('SVXDASH_TELEGRAM_URL', 'https://t.me/+NPnwNiF8lLZlZmJk'));

define('SVXLINK_SERVICE', dash_config('SVXDASH_SVXLINK_SERVICE', 'svxlink'));
define('SVXLINK_CONFIG', dash_config(
    'SVXDASH_SVXLINK_CONFIG',
    dash_root_path('/etc/svxlink/svxlink.conf', '/etc/svxlink/svxlink.conf')
));
define('TETRALOGIC_CONFIG', dash_config(
    'SVXDASH_TETRALOGIC_CONFIG',
    dash_root_path('/etc/svxlink/svxlink.d/TetraLogic.conf', '/etc/svxlink/svxlink.d/TetraLogic.conf')
));
define('TETRA_USERS_FILE', dash_config(
    'SVXDASH_TETRA_USERS_FILE',
    dash_root_path('/etc/svxlink/tetra_users.json', '/etc/svxlink/tetra_users.json')
));
define('PEI_INIT_FILE', dash_config(
    'SVXDASH_PEI_INIT_FILE',
    dash_root_path('/etc/svxlink/pei-init.json', '/etc/svxlink/pei-init.json')
));
define('SVXLINK_LOG', dash_config(
    'SVXDASH_SVXLINK_LOG',
    dash_root_path('/var/log/svxlink', '/var/log/svxlink')
));

define('MTM_MODEL', dash_config('SVXDASH_MTM_MODEL', 'Motorola MTM5400'));
define('DMO_ROLE', dash_config('SVXDASH_DMO_ROLE', 'simulação de repetidor DMO'));
define('DASH_ADMIN_USER', dash_config('SVXDASH_ADMIN_USER', 'admin'));
define('DASH_DEFAULT_ADMIN_PASSWORD', dash_config('SVXDASH_DEFAULT_ADMIN_PASSWORD', 'hamtetra-ct'));
define('DASH_ADMIN_PASSWORD', dash_config('SVXDASH_ADMIN_PASSWORD', DASH_DEFAULT_ADMIN_PASSWORD));
define('DASH_SDS_PTY', dash_config('SVXDASH_SDS_PTY', dash_root_path('/tmp/tetra_sds', '/tmp/tetra_sds')));
define('DASH_SDS_PRESETS_FILE', dash_config(
    'SVXDASH_SDS_PRESETS_FILE',
    dash_root_path('/var/lib/svxlink-ct/sds-presets.json', '/var/lib/svxlink-ct/sds-presets.json')
));
define('DASH_SDS_LOG_FILE', dash_config(
    'SVXDASH_SDS_LOG_FILE',
    dash_root_path('/var/lib/svxlink-ct/sds-log.jsonl', '/var/lib/svxlink-ct/sds-log.jsonl')
));
define('DASH_PEI_PTY', dash_config('SVXDASH_PEI_PTY', dash_root_path('/tmp/pei_pty', '/tmp/pei_pty')));
define('DASH_PEI_LOG_FILE', dash_config(
    'SVXDASH_PEI_LOG_FILE',
    dash_root_path('/var/lib/svxlink-ct/pei-log.jsonl', '/var/lib/svxlink-ct/pei-log.jsonl')
));
define('DASH_POWER_COMMAND_TEMPLATE', dash_config('SVXDASH_POWER_COMMAND_TEMPLATE', ''));
define('DASH_METEO_CONFIG_FILE', dash_config(
    'SVXDASH_METEO_CONFIG_FILE',
    dash_root_path('/var/lib/svxlink-ct/meteo-alerts.json', '/var/lib/svxlink-ct/meteo-alerts.json')
));
define('DASH_METEO_STATE_FILE', dash_config(
    'SVXDASH_METEO_STATE_FILE',
    dash_root_path('/var/lib/svxlink-ct/meteo-alerts-state.json', '/var/lib/svxlink-ct/meteo-alerts-state.json')
));
define('DASH_METEO_RUNNER', dash_config('SVXDASH_METEO_RUNNER', '/usr/local/sbin/svxlink-ct-meteo-alerts'));
define('DASH_METEO_CREDENTIALS', dash_config('SVXDASH_METEO_CREDENTIALS', '/home/pi/chave.json'));
define('DASH_METEO_OUTPUT_WAV', dash_config('SVXDASH_METEO_OUTPUT_WAV', '/usr/share/svxlink/sounds/pt_PT/Core/aviso.wav'));
define('DASH_METEO_DTMF_PTY', dash_config('SVXDASH_METEO_DTMF_PTY', '/tmp/svxlink_dtmf'));
define('DASH_MAINT_HELPER', dash_config('SVXDASH_MAINT_HELPER', '/usr/local/sbin/svxlink-ct-dashboard-action'));
