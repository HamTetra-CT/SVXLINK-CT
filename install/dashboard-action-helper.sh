#!/usr/bin/env bash
set -euo pipefail

ACTION="${1:-}"
METEO_RUNNER="${METEO_RUNNER:-/usr/local/sbin/svxlink-ct-meteo-alerts}"
DEFAULTS_FILE="${DEFAULTS_FILE:-/etc/default/svxlink-ct-dashboard}"

if [ -r "${DEFAULTS_FILE}" ]; then
  # shellcheck disable=SC1090
  . "${DEFAULTS_FILE}"
fi

need_root() {
  if [ "$(id -u)" -ne 0 ]; then
    echo "Este helper tem de correr como root." >&2
    exit 1
  fi
}

systemd_unit_exists() {
  local unit="$1"
  local state
  state="$(systemctl show "${unit}" --property=LoadState --value 2>/dev/null || true)"
  [ -n "${state}" ] && [ "${state}" != "not-found" ]
}

detect_svxlink_service() {
  local candidate
  for candidate in "${SVXLINK_SERVICE:-}" svxlink.service svxlink svxlink-ct.service svxlink-ct; do
    [ -n "${candidate}" ] || continue
    if systemd_unit_exists "${candidate}"; then
      echo "${candidate}"
      return 0
    fi
  done

  systemctl list-unit-files --type=service --no-legend 'svxlink*.service' 2>/dev/null | awk 'NR == 1 {print $1; exit}'
}

restart_svxlink() {
  if command -v systemctl >/dev/null 2>&1; then
    local unit
    unit="$(detect_svxlink_service)"
    if [ -n "${unit}" ]; then
      systemctl restart "${unit}"
      echo "SvxLink reiniciado: ${unit}"
      return 0
    fi
  fi

  if command -v service >/dev/null 2>&1; then
    for candidate in "${SVXLINK_SERVICE:-}" svxlink svxlink-ct; do
      [ -n "${candidate}" ] || continue
      if service "${candidate}" status >/dev/null 2>&1; then
        service "${candidate}" restart
        echo "SvxLink reiniciado: ${candidate}"
        return 0
      fi
    done
  fi

  echo "Serviço SvxLink não encontrado. Define SVXLINK_SERVICE em ${DEFAULTS_FILE} com o nome correcto da unidade." >&2
  exit 1
}

need_root

case "${ACTION}" in
  restart-svxlink)
    restart_svxlink
    ;;
  restart-system)
    nohup /bin/sh -c 'sleep 2; systemctl reboot' >/dev/null 2>&1 &
    echo "Reinício do equipamento agendado."
    ;;
  apt-update)
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    echo "Lista de pacotes actualizada."
    ;;
  apt-upgrade)
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get -y upgrade
    echo "Pacotes actualizados."
    ;;
  meteo-now)
    if [ ! -x "${METEO_RUNNER}" ]; then
      echo "Runner de avisos meteorológicos não instalado: ${METEO_RUNNER}" >&2
      exit 1
    fi
    "${METEO_RUNNER}" --force
    echo "Aviso meteorológico gerado."
    ;;
  *)
    echo "Acção inválida." >&2
    exit 2
    ;;
esac
