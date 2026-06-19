#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="${SVXLINK_CT_DIR:-/opt/svxlink-ct}"
USERS_UPDATE="${USERS_UPDATE:-/usr/local/sbin/svxlink-ct-update-users}"

need_whiptail() {
  if ! command -v whiptail >/dev/null 2>&1; then
    echo "whiptail not found. Install with: sudo apt install whiptail"
    exit 1
  fi
}

msg() {
  whiptail --title "SVXLINK-CT" --msgbox "$1" 10 72
}

confirm() {
  whiptail --title "SVXLINK-CT" --yesno "$1" 10 72
}

run_in_shell() {
  clear
  echo "== $1 =="
  shift
  "$@"
  echo
  read -r -p "Enter para voltar ao menu..." _
}

update_repo() {
  if [ ! -d "${REPO_DIR}/.git" ]; then
    msg "Repositorio nao encontrado em ${REPO_DIR}. Corre primeiro o instalador SVXLINK-CT."
    return
  fi
  run_in_shell "Atualizar repositorio SVXLINK-CT" git -C "${REPO_DIR}" pull --ff-only
}

update_dashboard() {
  run_in_shell "Atualizar apenas dashboard" sudo bash "${REPO_DIR}/install/update-dashboard-only.sh"
}

update_users() {
  run_in_shell "Atualizar tetra_users.json sem restart" "${USERS_UPDATE}"
}

install_voices() {
  run_in_shell "Instalar vozes pt_PT" sudo bash "${REPO_DIR}/install/install-pt-voices.sh"
}

open_file() {
  local file="$1"
  if [ ! -e "${file}" ]; then
    msg "Ficheiro nao encontrado: ${file}"
    return
  fi
  sudo "${EDITOR:-nano}" "${file}"
}

svx_log_path() {
  if [ -f /var/log/svxlink ]; then
    echo /var/log/svxlink
    return
  fi
  if [ -f /var/log/svxlink/svxlink.log ]; then
    echo /var/log/svxlink/svxlink.log
    return
  fi
  if [ -d /var/log/svxlink ]; then
    find /var/log/svxlink -type f -print 2>/dev/null | sort | tail -1
  fi
}

view_svxlink_log() {
  local log_file
  log_file="$(svx_log_path)"
  if [ -z "${log_file}" ]; then
    msg "Log do SvxLink nao encontrado em /var/log/svxlink."
    return
  fi
  clear
  sudo less +F "${log_file}" || true
}

need_whiptail

while true; do
  choice=$(whiptail --title "SVXLINK-CT Maintenance" --menu "Escolhe uma opcao:" 24 86 13 \
    1 "Ver estado do sistema" \
    2 "Ver logs SvxLink" \
    3 "Atualizar SVXLINK-CT repo" \
    4 "Atualizar apenas dashboard" \
    5 "Atualizar tetra_users.json sem restart" \
    6 "Instalar/atualizar vozes pt_PT" \
    7 "Editar svxlink.conf" \
    8 "Editar TetraLogic.conf" \
    9 "Editar tetra_users.json" \
    10 "Abrir alsamixer" \
    11 "Reiniciar SvxLink manualmente" \
    12 "Reboot do sistema" \
    13 "Sair" 3>&1 1>&2 2>&3) || break

  case "${choice}" in
    1) run_in_shell "Estado do sistema" bash "${REPO_DIR}/install/check-dmo-system.sh" ;;
    2) view_svxlink_log ;;
    3) update_repo ;;
    4) update_dashboard ;;
    5) update_users ;;
    6) install_voices ;;
    7) open_file /etc/svxlink/svxlink.conf ;;
    8) open_file /etc/svxlink/svxlink.d/TetraLogic.conf ;;
    9) open_file /etc/svxlink/tetra_users.json ;;
    10) sudo alsamixer ;;
    11)
      if confirm "Reiniciar SvxLink agora? Isto cria uma pequena quebra de servico."; then
        sudo systemctl restart svxlink
        msg "SvxLink reiniciado."
      fi
      ;;
    12)
      if confirm "Reiniciar o sistema agora?"; then
        sudo reboot
      fi
      ;;
    13) break ;;
  esac
done

clear
