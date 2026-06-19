# SVXLINK-CT

DMO-focused SvxLink/TetraLogic tooling for MTM5400 experiments.

Current contents:

- `dashboard-dmo/`: lightweight PHP dashboard tuned for DMO and MTM5400.
- `install/install-dmo-dashboard.sh`: installs Apache/PHP and publishes the dashboard to `/var/www/html`.
- `install/check-dmo-system.sh`: checks expected SvxLink/TetraLogic files.
- `docs/INSTALL_DMO_STEP_BY_STEP.md`: recommended OS-first installation flow.

Recommended first workflow:

```bash
git clone git@github.com:HamTetra-CT/SVXLINK-CT.git
cd SVXLINK-CT
sudo install/install-dmo-dashboard.sh
```

The ISO/IMG route should come after the installer is stable, because NUC and Raspberry Pi need separate image builds.
