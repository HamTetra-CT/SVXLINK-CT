#!/usr/bin/env bash
set -euo pipefail

URL="${TETRA_USERS_URL:-https://raw.githubusercontent.com/HamTetra-CT/users_update/main/tetra_users.json}"
TARGET_FILE="${TETRA_USERS_TARGET:-/etc/svxlink/tetra_users.json}"
BACKUP_DIR="${TETRA_USERS_BACKUP_DIR:-/var/backups/svxlink-ct}"
QUIET=0
FORCE=0

while [ "$#" -gt 0 ]; do
  case "$1" in
    --quiet) QUIET=1 ;;
    --force) FORCE=1 ;;
    *) echo "Unknown argument: $1" >&2; exit 2 ;;
  esac
  shift
done

log() {
  if [ "${QUIET}" -eq 0 ]; then
    echo "$@"
  fi
}

if [ "$(id -u)" -ne 0 ]; then
  echo "Corre como root: sudo $0" >&2
  exit 1
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

log "Downloading tetra_users.json"
if command -v curl >/dev/null 2>&1; then
  curl -fsSL "${URL}" -o "${tmp}"
elif command -v wget >/dev/null 2>&1; then
  wget -q "${URL}" -O "${tmp}"
else
  echo "curl or wget is required" >&2
  exit 1
fi

if command -v jq >/dev/null 2>&1; then
  jq . "${tmp}" >/dev/null
elif command -v python3 >/dev/null 2>&1; then
  python3 -m json.tool "${tmp}" >/dev/null
else
  echo "jq or python3 is required to validate JSON" >&2
  exit 1
fi

mkdir -p "$(dirname "${TARGET_FILE}")" "${BACKUP_DIR}"

if [ "${FORCE}" -eq 0 ] && [ -f "${TARGET_FILE}" ] && cmp -s "${tmp}" "${TARGET_FILE}"; then
  log "tetra_users.json already up to date"
  exit 0
fi

if [ -f "${TARGET_FILE}" ]; then
  backup="${BACKUP_DIR}/tetra_users.$(date +%Y%m%d-%H%M%S).json"
  cp -a "${TARGET_FILE}" "${backup}"
  log "Backup: ${backup}"
fi

install -m 0644 "${tmp}" "${TARGET_FILE}"
log "Instalado ${TARGET_FILE}"
log "O SvxLink não foi reiniciado."
