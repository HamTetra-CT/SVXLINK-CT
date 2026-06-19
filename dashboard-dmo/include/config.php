<?php
declare(strict_types=1);

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
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
define('DASH_TITLE', dash_config('SVXDASH_TITLE', 'SVXLINK DMO Dashboard'));
define('DASH_SUBTITLE', dash_config('SVXDASH_SUBTITLE', 'MTM5400 DMO Gateway'));
define('DASH_SITE', dash_config('SVXDASH_SITE', 'CT DMO'));
define('DASH_REFRESH_SECONDS', (int)dash_config('SVXDASH_REFRESH_SECONDS', '2'));
define('DASH_LOG_LINES', (int)dash_config('SVXDASH_LOG_LINES', '900'));

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
define('DMO_ROLE', dash_config('SVXDASH_DMO_ROLE', 'DMO repeater simulation'));
