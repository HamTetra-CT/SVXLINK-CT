#!/usr/bin/env bash
set -euo pipefail

WEB_ROOT="${WEB_ROOT:-/var/www/html}"
SITE_NAME="${SITE_NAME:-CQ0Exxx}"
TIMEZONE="${TIMEZONE:-Europe/Lisbon}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-hamtetra-ct}"
SDS_PTY="${SDS_PTY:-/tmp/tetra_sds}"
PEI_PTY="${PEI_PTY:-/tmp/pei_pty}"
POWER_COMMAND_TEMPLATE="${POWER_COMMAND_TEMPLATE:-}"
STATE_DIR="${STATE_DIR:-/var/lib/svxlink-ct}"
METEO_CONFIG_FILE="${METEO_CONFIG_FILE:-${STATE_DIR}/meteo-alerts.json}"
METEO_STATE_FILE="${METEO_STATE_FILE:-${STATE_DIR}/meteo-alerts-state.json}"
METEO_RUNNER="${METEO_RUNNER:-/usr/local/sbin/svxlink-ct-meteo-alerts}"
METEO_CREDENTIALS="${METEO_CREDENTIALS:-/home/pi/chave.json}"
METEO_OUTPUT_WAV="${METEO_OUTPUT_WAV:-/usr/share/svxlink/sounds/pt_PT/Core/aviso.wav}"
METEO_DTMF_PTY="${METEO_DTMF_PTY:-/tmp/svxlink_dtmf}"
MAINT_HELPER="${MAINT_HELPER:-/usr/local/sbin/svxlink-ct-dashboard-action}"
TETRALOGIC_CONF="${TETRALOGIC_CONF:-/etc/svxlink/svxlink.d/TetraLogic.conf}"
SVXLINK_CONF="${SVXLINK_CONF:-/etc/svxlink/svxlink.conf}"
FORCE_CONFIG="${FORCE_CONFIG:-0}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DASH_SRC="${DASH_SRC:-${REPO_ROOT}/dashboard-dmo}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Corre como root: sudo $0"
  exit 1
fi

if [ ! -d "${DASH_SRC}" ]; then
  echo "Fonte do painel não encontrada: ${DASH_SRC}"
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "Este instalador suporta Debian/Raspberry Pi OS com apt-get."
  exit 1
fi

ARCH="$(dpkg --print-architecture 2>/dev/null || uname -m)"
case "${ARCH}" in
  amd64|arm64|x86_64|aarch64) ;;
  *) echo "Aviso: arquitectura não testada: ${ARCH}" ;;
esac

set_conf_key() {
  local file="$1"
  local key="$2"
  local value="$3"
  if grep -Eq "^[#;[:space:]]*${key}=" "${file}"; then
    sed -i "0,/^[#;[:space:]]*${key}=.*/s|^[#;[:space:]]*${key}=.*|${key}=${value}|" "${file}"
  else
    printf '\n%s=%s\n' "${key}" "${value}" >> "${file}"
  fi
}

echo "[1/7] A instalar pacotes web (${ARCH})"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y apache2 php php-cli libapache2-mod-php

echo "[2/7] A preparar a raiz web: ${WEB_ROOT}"
mkdir -p "${WEB_ROOT}"
mkdir -p "${STATE_DIR}"
if [ -e "${WEB_ROOT}/index.php" ] || [ -e "${WEB_ROOT}/index.html" ]; then
  BACKUP="${WEB_ROOT}.backup.$(date +%Y%m%d-%H%M%S)"
  mkdir -p "${BACKUP}"
  cp -a "${WEB_ROOT}/." "${BACKUP}/"
  echo "Raiz web existente guardada em ${BACKUP}"
fi
rm -f "${WEB_ROOT}/index.html" "${WEB_ROOT}/index.htm"

echo "[3/7] A instalar ficheiros do painel"
cp -a "${DASH_SRC}/." "${WEB_ROOT}/"

echo "[4/7] A escrever configuração local do painel"
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
METEO_CONFIG_FILE_PHP="$(php_string "${METEO_CONFIG_FILE}")"
METEO_STATE_FILE_PHP="$(php_string "${METEO_STATE_FILE}")"
METEO_RUNNER_PHP="$(php_string "${METEO_RUNNER}")"
METEO_CREDENTIALS_PHP="$(php_string "${METEO_CREDENTIALS}")"
METEO_OUTPUT_WAV_PHP="$(php_string "${METEO_OUTPUT_WAV}")"
METEO_DTMF_PTY_PHP="$(php_string "${METEO_DTMF_PTY}")"
MAINT_HELPER_PHP="$(php_string "${MAINT_HELPER}")"

if [ -f "${WEB_ROOT}/include/config.local.php" ] && [ "${FORCE_CONFIG}" != "1" ]; then
  echo "A manter ${WEB_ROOT}/include/config.local.php existente"
else
  cat > "${WEB_ROOT}/include/config.local.php" <<PHP
<?php
return [
    'SVXDASH_TIMEZONE' => '${TIMEZONE_PHP}',
    'SVXDASH_VERSION' => 'V1.0',
    'SVXDASH_SITE' => '${SITE_NAME_PHP}',
    'SVXDASH_TITLE' => 'Painel SVXLINK DMO',
    'SVXDASH_SUBTITLE' => 'Ponte DMO MTM5400',
    'SVXDASH_REFRESH_SECONDS' => '5',
    'SVXDASH_MTM_MODEL' => 'Motorola MTM5400',
    'SVXDASH_ADMIN_USER' => '${ADMIN_USER_PHP}',
    'SVXDASH_DEFAULT_ADMIN_PASSWORD' => 'hamtetra-ct',
    'SVXDASH_ADMIN_PASSWORD' => '${ADMIN_PASSWORD_PHP}',
    'SVXDASH_HAMTETRA_URL' => 'https://github.com/HamTetra-CT/',
    'SVXDASH_TELEGRAM_URL' => 'https://t.me/+NPnwNiF8lLZlZmJk',
    'SVXDASH_SDS_PTY' => '${SDS_PTY_PHP}',
    'SVXDASH_PEI_PTY' => '${PEI_PTY_PHP}',
    'SVXDASH_POWER_COMMAND_TEMPLATE' => '${POWER_COMMAND_TEMPLATE_PHP}',
    'SVXDASH_SDS_PRESETS_FILE' => '${STATE_DIR_PHP}/sds-presets.json',
    'SVXDASH_SDS_LOG_FILE' => '${STATE_DIR_PHP}/sds-log.jsonl',
    'SVXDASH_PEI_LOG_FILE' => '${STATE_DIR_PHP}/pei-log.jsonl',
    'SVXDASH_METEO_CONFIG_FILE' => '${METEO_CONFIG_FILE_PHP}',
    'SVXDASH_METEO_STATE_FILE' => '${METEO_STATE_FILE_PHP}',
    'SVXDASH_METEO_RUNNER' => '${METEO_RUNNER_PHP}',
    'SVXDASH_METEO_CREDENTIALS' => '${METEO_CREDENTIALS_PHP}',
    'SVXDASH_METEO_OUTPUT_WAV' => '${METEO_OUTPUT_WAV_PHP}',
    'SVXDASH_METEO_DTMF_PTY' => '${METEO_DTMF_PTY_PHP}',
    'SVXDASH_MAINT_HELPER' => '${MAINT_HELPER_PHP}',
];
PHP
fi

echo "[5/7] A configurar SDS_PTY e PEI_PTY no TetraLogic"
if [ -f "${TETRALOGIC_CONF}" ]; then
  cp -a "${TETRALOGIC_CONF}" "${TETRALOGIC_CONF}.backup.$(date +%Y%m%d-%H%M%S)"
  set_conf_key "${TETRALOGIC_CONF}" "SDS_PTY" "${SDS_PTY}"
  set_conf_key "${TETRALOGIC_CONF}" "PEI_PTY" "${PEI_PTY}"
  echo "Configuração actualizada em ${TETRALOGIC_CONF}"
else
  echo "TetraLogic.conf não encontrado em ${TETRALOGIC_CONF}; mantém a configuração manual se estiver noutro caminho."
fi

echo "[6/7] A ajustar permissões"
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
if id www-data >/dev/null 2>&1; then
  for conf_file in "${SVXLINK_CONF}" "${TETRALOGIC_CONF}"; do
    if [ -f "${conf_file}" ]; then
      chgrp www-data "${conf_file}" 2>/dev/null || true
      chmod 664 "${conf_file}" 2>/dev/null || true
    fi
  done
fi

echo "[7/7] A activar Apache"
systemctl enable --now apache2

IP_ADDR="$(hostname -I 2>/dev/null | awk '{print $1}')"
if [ -z "${IP_ADDR}" ]; then
  IP_ADDR="localhost"
fi

echo
echo "Painel instalado."
echo "Abrir: http://${IP_ADDR}/"
echo
echo "Credenciais iniciais da administração:"
echo "  utilizador: ${ADMIN_USER}"
echo "  palavra-passe: ${ADMIN_PASSWORD}"
echo
echo "Se os caminhos do SvxLink forem diferentes, edita:"
echo "  ${WEB_ROOT}/include/config.local.php"
echo
echo "SDS_PTY=${SDS_PTY} e PEI_PTY=${PEI_PTY} ficam configurados quando ${TETRALOGIC_CONF} existe."
echo "Depois de configurar estes PTY, reinicia o SvxLink uma vez para o TetraLogic criar os canais."
