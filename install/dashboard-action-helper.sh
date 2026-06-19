#!/usr/bin/env bash
set -euo pipefail

ACTION="${1:-}"
METEO_RUNNER="${METEO_RUNNER:-/usr/local/sbin/svxlink-ct-meteo-alerts}"

need_root() {
  if [ "$(id -u)" -ne 0 ]; then
    echo "Este helper tem de correr como root." >&2
    exit 1
  fi
}

need_root

case "${ACTION}" in
  restart-svxlink)
    systemctl restart svxlink
    echo "SvxLink reiniciado."
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
