#!/usr/bin/env bash
set -euo pipefail

WEB_ROOT="${WEB_ROOT:-/var/www/html}"
SITE_NAME="${SITE_NAME:-CT DMO}"
TIMEZONE="${TIMEZONE:-Europe/Lisbon}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
SDS_PTY="${SDS_PTY:-/tmp/tetra_sds}"
PEI_PTY="${PEI_PTY:-/tmp/pei_pty}"
POWER_COMMAND_TEMPLATE="${POWER_COMMAND_TEMPLATE:-}"
STATE_DIR="${STATE_DIR:-/var/lib/svxlink-ct}"
FORCE_CONFIG="${FORCE_CONFIG:-0}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DASH_SRC="${DASH_SRC:-${REPO_ROOT}/dashboard-dmo}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root: sudo $0"
  exit 1
fi

if [ ! -d "${DASH_SRC}" ]; then
  echo "Dashboard source not found: ${DASH_SRC}"
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "This installer currently supports Debian/Raspberry Pi OS with apt-get."
  exit 1
fi

echo "[1/6] Installing web packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y apache2 php php-cli libapache2-mod-php

echo "[2/6] Preparing web root: ${WEB_ROOT}"
mkdir -p "${WEB_ROOT}"
mkdir -p "${STATE_DIR}"
if [ -e "${WEB_ROOT}/index.php" ] || [ -e "${WEB_ROOT}/index.html" ]; then
  BACKUP="${WEB_ROOT}.backup.$(date +%Y%m%d-%H%M%S)"
  mkdir -p "${BACKUP}"
  cp -a "${WEB_ROOT}/." "${BACKUP}/"
  echo "Existing web root backed up to ${BACKUP}"
fi
rm -f "${WEB_ROOT}/index.html" "${WEB_ROOT}/index.htm"

echo "[3/6] Installing dashboard files"
cp -a "${DASH_SRC}/." "${WEB_ROOT}/"

echo "[4/6] Writing local dashboard config"
php_string() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\'/\\\'}"
  printf "%s" "${value}"
}

TIMEZONE_PHP="$(php_string "${TIMEZONE}")"
SITE_NAME_PHP="$(php_string "${SITE_NAME}")"
ADMIN_USER_PHP="$(php_string "${ADMIN_USER}")"
ADMIN_PASSWORD_PHP="$(php_string "${ADMIN_PASSWORD}")"
SDS_PTY_PHP="$(php_string "${SDS_PTY}")"
PEI_PTY_PHP="$(php_string "${PEI_PTY}")"
POWER_COMMAND_TEMPLATE_PHP="$(php_string "${POWER_COMMAND_TEMPLATE}")"
STATE_DIR_PHP="$(php_string "${STATE_DIR}")"

if [ -f "${WEB_ROOT}/include/config.local.php" ] && [ "${FORCE_CONFIG}" != "1" ]; then
  echo "Keeping existing ${WEB_ROOT}/include/config.local.php"
else
  cat > "${WEB_ROOT}/include/config.local.php" <<PHP
<?php
return [
    'SVXDASH_TIMEZONE' => '${TIMEZONE_PHP}',
    'SVXDASH_SITE' => '${SITE_NAME_PHP}',
    'SVXDASH_TITLE' => 'SVXLINK DMO Dashboard',
    'SVXDASH_SUBTITLE' => 'MTM5400 DMO Gateway',
    'SVXDASH_MTM_MODEL' => 'Motorola MTM5400',
    'SVXDASH_ADMIN_USER' => '${ADMIN_USER_PHP}',
    'SVXDASH_ADMIN_PASSWORD' => '${ADMIN_PASSWORD_PHP}',
    'SVXDASH_SDS_PTY' => '${SDS_PTY_PHP}',
    'SVXDASH_PEI_PTY' => '${PEI_PTY_PHP}',
    'SVXDASH_POWER_COMMAND_TEMPLATE' => '${POWER_COMMAND_TEMPLATE_PHP}',
    'SVXDASH_SDS_PRESETS_FILE' => '${STATE_DIR_PHP}/sds-presets.json',
    'SVXDASH_SDS_LOG_FILE' => '${STATE_DIR_PHP}/sds-log.jsonl',
    'SVXDASH_PEI_LOG_FILE' => '${STATE_DIR_PHP}/pei-log.jsonl',
];
PHP
fi

echo "[5/6] Setting permissions"
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${WEB_ROOT}"
  chown -R www-data:www-data "${STATE_DIR}"
fi
find "${WEB_ROOT}" -type d -exec chmod 755 {} +
find "${WEB_ROOT}" -type f -exec chmod 644 {} +
chmod 775 "${STATE_DIR}"

if [ -e /var/log/svxlink ]; then
  chmod a+r /var/log/svxlink 2>/dev/null || true
fi
if [ -d /etc/svxlink ]; then
  chmod -R a+rX /etc/svxlink 2>/dev/null || true
fi

echo "[6/6] Enabling Apache"
systemctl enable --now apache2

IP_ADDR="$(hostname -I 2>/dev/null | awk '{print $1}')"
if [ -z "${IP_ADDR}" ]; then
  IP_ADDR="localhost"
fi

echo
echo "Dashboard installed."
echo "Open: http://${IP_ADDR}/"
echo
echo "If SvxLink paths differ, edit:"
echo "  ${WEB_ROOT}/include/config.local.php"
echo
echo "To enable SDS send/edit, set SVXDASH_ADMIN_PASSWORD in:"
echo "  ${WEB_ROOT}/include/config.local.php"
echo "TetraLogic also needs SDS_PTY=${SDS_PTY} and PEI_PTY=${PEI_PTY} enabled once."
