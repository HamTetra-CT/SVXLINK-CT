#!/usr/bin/env bash
set -euo pipefail

paths=(
  "/etc/svxlink/svxlink.conf"
  "/etc/svxlink/svxlink.d/TetraLogic.conf"
  "/etc/svxlink/tetra_users.json"
  "/etc/svxlink/pei-init.json"
  "/var/log/svxlink"
)

echo "DMO system check"
echo

if command -v systemctl >/dev/null 2>&1; then
  printf "svxlink service: "
  systemctl is-active svxlink || true
fi

for path in "${paths[@]}"; do
  if [ -r "${path}" ]; then
    echo "OK   ${path}"
  else
    echo "MISS ${path}"
  fi
done

echo
if command -v svxlink >/dev/null 2>&1; then
  svxlink --version 2>&1 | head -1 || true
else
  echo "svxlink binary not found in PATH"
fi
