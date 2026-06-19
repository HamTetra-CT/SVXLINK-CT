# SVXLINK-CT

DMO-focused SvxLink/TetraLogic tooling for MTM5400 experiments.

Current contents:

- `dashboard-dmo/`: lightweight PHP dashboard tuned for DMO and MTM5400.
- `sounds/pt_PT/`: Portuguese Portugal SvxLink voice prompts.
- `menu/svxlink-ct-menu.sh`: local maintenance menu.
- `install/install-svxlink-ct.sh`: all-in-one installer/update entrypoint.
- `install/install-dmo-dashboard.sh`: installs Apache/PHP and publishes the dashboard to `/var/www/html`.
- `install/update-dashboard-only.sh`: updates only the dashboard in `/var/www/html`.
- `install/update-tetra-users.sh`: atomically updates `/etc/svxlink/tetra_users.json` without restarting SvxLink.
- `install/check-dmo-system.sh`: checks expected SvxLink/TetraLogic files.
- `docs/INSTALL_DMO_STEP_BY_STEP.md`: recommended OS-first installation flow.

Recommended one-line workflow:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/install-svxlink-ct.sh | sudo bash
```

Manual workflow:

```bash
git clone git@github.com:HamTetra-CT/SVXLINK-CT.git
cd SVXLINK-CT
sudo install/install-dmo-dashboard.sh
sudo install/install-maintenance-tools.sh
sudo install/install-pt-voices.sh
```

Maintenance menu after install:

```bash
sudo svxlink-ct
```

Daily automatic job:

- Runs every day at `04:00`.
- Updates only `/etc/svxlink/tetra_users.json`.
- Does not restart SvxLink.
- Logs to `/var/log/svxlink-ct-users-update.log`.

Dashboard-only update:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/update-dashboard-only.sh | sudo bash
```

The ISO/IMG route should come after the installer is stable, because NUC and Raspberry Pi need separate image builds.
