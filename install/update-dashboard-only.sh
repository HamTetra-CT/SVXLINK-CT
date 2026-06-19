#!/usr/bin/env bash
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/HamTetra-CT/SVXLINK-CT.git}"
BRANCH="${BRANCH:-main}"
TARGET_DIR="${TARGET_DIR:-/opt/svxlink-ct}"
WEB_ROOT="${WEB_ROOT:-/var/www/html}"
STATE_DIR="${STATE_DIR:-/var/lib/svxlink-ct}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Corre como root: sudo $0"
  exit 1
fi

if ! command -v git >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update
  apt-get install -y git ca-certificates
fi

if [ -d "${TARGET_DIR}/.git" ]; then
  echo "[1/4] A actualizar repositório em ${TARGET_DIR}"
  git -C "${TARGET_DIR}" fetch --depth 1 origin "${BRANCH}"
  git -C "${TARGET_DIR}" checkout -B "${BRANCH}" "FETCH_HEAD"
else
  echo "[1/4] A clonar repositório para ${TARGET_DIR}"
  mkdir -p "$(dirname "${TARGET_DIR}")"
  git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${TARGET_DIR}"
fi

DASH_SRC="${TARGET_DIR}/dashboard-dmo"
if [ ! -d "${DASH_SRC}" ]; then
  echo "Fonte do painel não encontrada: ${DASH_SRC}"
  exit 1
fi

echo "[2/4] A preparar estado do painel"
mkdir -p "${WEB_ROOT}/include" "${STATE_DIR}"
TMP_CONFIG="$(mktemp)"
HAD_CONFIG=0
if [ -f "${WEB_ROOT}/include/config.local.php" ]; then
  cp "${WEB_ROOT}/include/config.local.php" "${TMP_CONFIG}"
  HAD_CONFIG=1
fi

echo "[3/4] A copiar ficheiros do painel"
rm -f "${WEB_ROOT}/index.html" "${WEB_ROOT}/index.htm"
cp -a "${DASH_SRC}/." "${WEB_ROOT}/"
if [ "${HAD_CONFIG}" -eq 1 ]; then
  cp "${TMP_CONFIG}" "${WEB_ROOT}/include/config.local.php"
else
  cat > "${WEB_ROOT}/include/config.local.php" <<'PHP'
<?php
return [
    'SVXDASH_TIMEZONE' => 'Europe/Lisbon',
    'SVXDASH_VERSION' => 'V1.0',
    'SVXDASH_SITE' => 'CT DMO',
    'SVXDASH_TITLE' => 'Painel SVXLINK DMO',
    'SVXDASH_SUBTITLE' => 'Gateway DMO MTM5400',
    'SVXDASH_REFRESH_SECONDS' => '5',
    'SVXDASH_MTM_MODEL' => 'Motorola MTM5400',
    'SVXDASH_ADMIN_USER' => 'admin',
    'SVXDASH_DEFAULT_ADMIN_PASSWORD' => 'hamtetra-ct',
    'SVXDASH_ADMIN_PASSWORD' => 'hamtetra-ct',
    'SVXDASH_HAMTETRA_URL' => 'https://github.com/HamTetra-CT/',
    'SVXDASH_TELEGRAM_URL' => 'https://t.me/+NPnwNiF8lLZlZmJk',
    'SVXDASH_SDS_PTY' => '/tmp/tetra_sds',
    'SVXDASH_PEI_PTY' => '/tmp/pei_pty',
    'SVXDASH_SDS_PRESETS_FILE' => '/var/lib/svxlink-ct/sds-presets.json',
    'SVXDASH_SDS_LOG_FILE' => '/var/lib/svxlink-ct/sds-log.jsonl',
    'SVXDASH_PEI_LOG_FILE' => '/var/lib/svxlink-ct/pei-log.jsonl',
];
PHP
fi
rm -f "${TMP_CONFIG}"

echo "[4/4] A ajustar permissões"
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${WEB_ROOT}" "${STATE_DIR}"
fi
find "${WEB_ROOT}" -type d -exec chmod 755 {} +
find "${WEB_ROOT}" -type f -exec chmod 644 {} +
chmod 775 "${STATE_DIR}"

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload apache2 2>/dev/null || systemctl restart apache2 2>/dev/null || true
fi

IP_ADDR="$(hostname -I 2>/dev/null | awk '{print $1}')"
if [ -z "${IP_ADDR}" ]; then
  IP_ADDR="localhost"
fi

echo
echo "Painel actualizado."
echo "Abrir: http://${IP_ADDR}/"
