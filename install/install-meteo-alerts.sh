#!/usr/bin/env bash
set -euo pipefail

STATE_DIR="${STATE_DIR:-/var/lib/svxlink-ct}"
CONFIG_FILE="${CONFIG_FILE:-${STATE_DIR}/meteo-alerts.json}"
RUNNER_BIN="${RUNNER_BIN:-/usr/local/sbin/svxlink-ct-meteo-alerts}"
CRON_FILE="${CRON_FILE:-/etc/cron.d/svxlink-ct-meteo-alerts}"
CRON_SCHEDULE="${CRON_SCHEDULE:-*/5 * * * *}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ "$(id -u)" -ne 0 ]; then
  echo "Corre como root: sudo $0"
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "Este instalador suporta Debian, Ubuntu e Raspberry Pi OS com apt-get."
  exit 1
fi

ARCH="$(dpkg --print-architecture 2>/dev/null || uname -m)"
case "${ARCH}" in
  amd64|arm64|x86_64|aarch64) ;;
  *) echo "Aviso: arquitectura não testada: ${ARCH}" ;;
esac

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y python3 python3-pip ca-certificates sox

if ! pip3 install "google-cloud-texttospeech<2.0.0"; then
  pip3 install --break-system-packages "google-cloud-texttospeech<2.0.0"
fi

mkdir -p "${STATE_DIR}"
install -m 0755 "${REPO_ROOT}/meteo/meteo_alerts.py" "${RUNNER_BIN}"

if [ ! -f "${CONFIG_FILE}" ]; then
  cat > "${CONFIG_FILE}" <<JSON
{
  "enabled": false,
  "interval_minutes": 60,
  "location_id": "LSB",
  "location_label": "Lisboa",
  "area": "LSB",
  "area_label": "Lisboa",
  "credentials": "/home/pi/chave.json",
  "output_wav": "/usr/share/svxlink/sounds/pt_PT/Core/aviso.wav",
  "dtmf_pty": "/tmp/svxlink_dtmf",
  "dtmf_command": "99#",
  "trigger_dtmf": true,
  "api_url": "https://api.ipma.pt/open-data/forecast/warnings/warnings_www.json"
}
JSON
fi

cat > "${CRON_FILE}" <<CRON
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
${CRON_SCHEDULE} root ${RUNNER_BIN} --due >> /var/log/svxlink-ct-meteo-alerts.log 2>&1
CRON
chmod 0644 "${CRON_FILE}"

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${STATE_DIR}" 2>/dev/null || true
fi
chmod 775 "${STATE_DIR}"

echo "Avisos meteorológicos instalados."
echo "Configuração: ${CONFIG_FILE}"
echo "Runner: ${RUNNER_BIN}"
echo "Cron: ${CRON_FILE}"
echo "Copia a chave Google para /home/pi/chave.json e activa os avisos no painel de Administração."
