#!/usr/bin/env bash
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/HamTetra-CT/SVXLINK-CT.git}"
BRANCH="${BRANCH:-main}"
TARGET_DIR="${TARGET_DIR:-/opt/svxlink-ct}"
WEB_ROOT="${WEB_ROOT:-/var/www/html}"
INSTALL_VOICES="${INSTALL_VOICES:-1}"
INSTALL_DASHBOARD="${INSTALL_DASHBOARD:-1}"
INSTALL_MENU="${INSTALL_MENU:-1}"
INSTALL_METEO="${INSTALL_METEO:-1}"
UPDATE_USERS_NOW="${UPDATE_USERS_NOW:-1}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Corre como root: sudo $0"
  exit 1
fi

ARCH="$(dpkg --print-architecture 2>/dev/null || uname -m)"
case "${ARCH}" in
  amd64|arm64|x86_64|aarch64) ;;
  *) echo "Aviso: arquitectura não testada: ${ARCH}" ;;
esac

if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update
  apt-get install -y git curl ca-certificates
fi

if [ -d "${TARGET_DIR}/.git" ]; then
  echo "A actualizar SVXLINK-CT em ${TARGET_DIR} (${ARCH})"
  git -C "${TARGET_DIR}" fetch --depth 1 origin "${BRANCH}"
  git -C "${TARGET_DIR}" checkout -B "${BRANCH}" "FETCH_HEAD"
else
  echo "A instalar SVXLINK-CT em ${TARGET_DIR} (${ARCH})"
  mkdir -p "$(dirname "${TARGET_DIR}")"
  git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${TARGET_DIR}"
fi

if [ "${INSTALL_DASHBOARD}" = "1" ]; then
  WEB_ROOT="${WEB_ROOT}" bash "${TARGET_DIR}/install/install-dmo-dashboard.sh"
fi

if [ "${INSTALL_MENU}" = "1" ]; then
  SVXLINK_CT_DIR="${TARGET_DIR}" bash "${TARGET_DIR}/install/install-maintenance-tools.sh"
fi

if [ "${INSTALL_VOICES}" = "1" ]; then
  bash "${TARGET_DIR}/install/install-pt-voices.sh"
fi

if [ "${INSTALL_METEO}" = "1" ]; then
  bash "${TARGET_DIR}/install/install-meteo-alerts.sh"
fi

if [ "${UPDATE_USERS_NOW}" = "1" ]; then
  /usr/local/sbin/svxlink-ct-update-users --quiet || true
fi

echo
echo "Instalação/actualização SVXLINK-CT concluída."
echo "Painel: http://$(hostname -I 2>/dev/null | awk '{print $1}')/"
echo "Menu: sudo svxlink-ct"
echo "Actualização diária de utilizadores: 04:00 via /etc/cron.d/svxlink-ct-users-update"
echo "Avisos meteorológicos: configurar em Administração depois de copiar /home/pi/chave.json"
