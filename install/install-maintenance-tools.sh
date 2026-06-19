#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
MENU_BIN="${MENU_BIN:-/usr/local/sbin/svxlink-ct}"
USERS_BIN="${USERS_BIN:-/usr/local/sbin/svxlink-ct-update-users}"
CRON_FILE="${CRON_FILE:-/etc/cron.d/svxlink-ct-users-update}"
CRON_SCHEDULE="${CRON_SCHEDULE:-0 4 * * *}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root: sudo $0"
  exit 1
fi

if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update
  apt-get install -y whiptail curl ca-certificates git jq nano less
fi

install -m 0755 "${REPO_ROOT}/install/update-tetra-users.sh" "${USERS_BIN}"
install -m 0755 "${REPO_ROOT}/menu/svxlink-ct-menu.sh" "${MENU_BIN}"

cat > "${CRON_FILE}" <<CRON
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
${CRON_SCHEDULE} root ${USERS_BIN} --quiet >> /var/log/svxlink-ct-users-update.log 2>&1
CRON
chmod 0644 "${CRON_FILE}"

echo "Installed:"
echo "  ${MENU_BIN}"
echo "  ${USERS_BIN}"
echo "  ${CRON_FILE}"
