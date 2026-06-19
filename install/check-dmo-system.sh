#!/usr/bin/env bash
set -euo pipefail

paths=(
  "/etc/svxlink/svxlink.conf"
  "/etc/svxlink/svxlink.d/TetraLogic.conf"
  "/etc/svxlink/tetra_users.json"
  "/etc/svxlink/pei-init.json"
  "/var/log/svxlink"
)

echo "Verificação do sistema DMO"
echo

if command -v systemctl >/dev/null 2>&1; then
  printf "serviço svxlink: "
  systemctl is-active svxlink || true
fi

for path in "${paths[@]}"; do
  if [ -r "${path}" ]; then
    echo "OK   ${path}"
  else
    echo "FALTA ${path}"
  fi
done

echo
for key in SDS_PTY PEI_PTY; do
  value="$(awk -F= -v key="${key}" '$1 == key {print $2}' /etc/svxlink/svxlink.d/TetraLogic.conf 2>/dev/null | tail -1)"
  if [ -n "${value}" ] && [ -e "${value}" ]; then
    echo "OK   ${key}=${value}"
  elif [ -n "${value}" ]; then
    echo "FALTA ${key}=${value} configurado, mas o dispositivo não existe"
  else
    echo "FALTA ${key} não configurado"
  fi
done

echo
if command -v svxlink >/dev/null 2>&1; then
  svxlink --version 2>&1 | head -1 || true
else
  echo "binário svxlink não encontrado no PATH"
fi
