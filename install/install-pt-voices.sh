#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
SOURCE_DIR="${SOURCE_DIR:-${REPO_ROOT}/sounds/pt_PT}"
SOUNDS_ROOT="${SOUNDS_ROOT:-/usr/share/svxlink/sounds}"
TARGET_DIR="${TARGET_DIR:-${SOUNDS_ROOT}/pt_PT}"
SET_DEFAULT_LANG="${SET_DEFAULT_LANG:-1}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Corre como root: sudo $0"
  exit 1
fi

if [ ! -d "${SOURCE_DIR}" ]; then
  echo "Fonte das vozes não encontrada: ${SOURCE_DIR}"
  exit 1
fi

mkdir -p "${SOUNDS_ROOT}"
if [ -d "${TARGET_DIR}" ]; then
  backup="${TARGET_DIR}.backup.$(date +%Y%m%d-%H%M%S)"
  cp -a "${TARGET_DIR}" "${backup}"
  echo "Vozes pt_PT existentes guardadas em ${backup}"
fi

rm -rf "${TARGET_DIR}"
cp -a "${SOURCE_DIR}" "${TARGET_DIR}"
find "${TARGET_DIR}" -type d -exec chmod 755 {} +
find "${TARGET_DIR}" -type f -exec chmod 644 {} +

set_default_lang() {
  local file="$1"
  local section="${2:-}"
  [ -f "${file}" ] || return 0
  if grep -q '^DEFAULT_LANG=' "${file}"; then
    sed -i 's/^DEFAULT_LANG=.*/DEFAULT_LANG=pt_PT/' "${file}"
  elif grep -q '^#DEFAULT_LANG=' "${file}"; then
    sed -i '0,/^#DEFAULT_LANG=.*/s//DEFAULT_LANG=pt_PT/' "${file}"
  elif [ -n "${section}" ] && grep -q "^\[${section}\]" "${file}"; then
    tmp="$(mktemp)"
    awk -v section="[${section}]" '
      { print }
      $0 == section && done == 0 {
        print "DEFAULT_LANG=pt_PT"
        done = 1
      }
    ' "${file}" > "${tmp}"
    install -m 0644 "${tmp}" "${file}"
    rm -f "${tmp}"
  else
    printf '\nDEFAULT_LANG=pt_PT\n' >> "${file}"
  fi
}

if [ "${SET_DEFAULT_LANG}" = "1" ]; then
  set_default_lang /etc/svxlink/svxlink.conf GLOBAL
  set_default_lang /etc/svxlink/svxlink.d/TetraLogic.conf TetraLogic
  echo "DEFAULT_LANG=pt_PT aplicado nos ficheiros de configuração encontrados."
  echo "Reinicia o SvxLink manualmente quando quiseres aplicar a mudança de idioma das vozes."
fi

echo "Vozes pt_PT instaladas em ${TARGET_DIR}"
