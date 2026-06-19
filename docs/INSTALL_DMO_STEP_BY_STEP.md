# SVXLINK CT DMO install plan

This is the recommended first version: install the OS first, then run one installer from this repo.

Building a ready ISO/IMG is possible later, but it is not the best first step because:

- Intel NUC uses normal Debian amd64 ISO boot.
- Raspberry Pi uses ARM images and a different boot layout.
- Every hardware target needs separate image testing.
- A shell installer is faster to iterate while we are still tuning MTM5400, PEI, TetraLogic and dashboard behavior.

## 1. Install the OS

NUC:

- Debian 12 minimal, amd64.
- Enable SSH during install.
- Use a wired network while testing.

Raspberry Pi:

- Raspberry Pi OS Lite 64-bit when possible.
- Enable SSH in Raspberry Pi Imager.
- Use a stable power supply.

## 2. Update base system

```bash
sudo apt update
sudo apt -y full-upgrade
sudo reboot
```

## 3. Clone the repository

```bash
sudo apt install -y git
git clone git@github.com:HamTetra-CT/SVXLINK-CT.git
cd SVXLINK-CT
```

If SSH is not configured on the target yet, use HTTPS for the first install:

```bash
git clone https://github.com/HamTetra-CT/SVXLINK-CT.git
```

## 4. Install dashboard

```bash
sudo install/install-dmo-dashboard.sh
```

The dashboard is installed directly into:

```text
/var/www/html
```

Open:

```text
http://radio-ip/
```

Recommended all-in-one install/update:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/install-svxlink-ct.sh | sudo bash
```

This installs the dashboard, the maintenance menu, daily users update, and the `pt_PT` SvxLink voices.

## 5. Check SvxLink/TetraLogic files

```bash
sudo install/check-dmo-system.sh
```

Expected files:

```text
/etc/svxlink/svxlink.conf
/etc/svxlink/svxlink.d/TetraLogic.conf
/etc/svxlink/tetra_users.json
/etc/svxlink/pei-init.json
/var/log/svxlink
```

## 6. Dashboard config overrides

Edit:

```text
/var/www/html/include/config.local.php
```

Useful values:

```php
<?php
return [
    'SVXDASH_TIMEZONE' => 'Europe/Lisbon',
    'SVXDASH_SITE' => 'CT DMO',
    'SVXDASH_SUBTITLE' => 'MTM5400 DMO Gateway',
    'SVXDASH_ADMIN_USER' => 'admin',
    'SVXDASH_ADMIN_PASSWORD' => 'change-me',
    'SVXDASH_SDS_PTY' => '/tmp/tetra_sds',
    'SVXDASH_PEI_PTY' => '/tmp/pei_pty',
];
```

## 7. Update only the dashboard

Use this when the radio/SvxLink side is already installed and you only want the latest dashboard:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/update-dashboard-only.sh | sudo bash
```

This preserves `/var/www/html/include/config.local.php`.

## Daily contacts update

The all-in-one installer creates:

```text
/etc/cron.d/svxlink-ct-users-update
```

Default schedule:

```cron
0 4 * * * root /usr/local/sbin/svxlink-ct-update-users --quiet >> /var/log/svxlink-ct-users-update.log 2>&1
```

Only `/etc/svxlink/tetra_users.json` is updated. The cron does not update the dashboard, voices, repository or SvxLink binaries.

The file replacement is atomic and does not restart SvxLink. The dashboard reads the updated users file on each request. The current TetraLogic code appears to load user data at service start, so internal TetraLogic user labels may still require a manual restart unless we patch TetraLogic to reload the JSON at runtime.

## 8. SDS and PEI admin

For live SDS sending without restarting SvxLink, enable this once in `/etc/svxlink/svxlink.d/TetraLogic.conf`:

```ini
SDS_PTY=/tmp/tetra_sds
```

For live PEI commands from the admin dashboard, enable this once:

```ini
PEI_PTY=/tmp/pei_pty
```

After adding those two lines, restart SvxLink once:

```bash
sudo systemctl restart svxlink
```

After that, sending SDS and PEI commands from the dashboard does not require restarting SvxLink.

## 9. Portuguese voices

The all-in-one installer copies the voices to:

```text
/usr/share/svxlink/sounds/pt_PT
```

It also sets:

```ini
DEFAULT_LANG=pt_PT
```

in `/etc/svxlink/svxlink.conf` and `/etc/svxlink/svxlink.d/TetraLogic.conf` when those files exist.

If you install the voices later:

```bash
sudo /opt/svxlink-ct/install/install-pt-voices.sh
```

Restart SvxLink manually when you want the voice language change to take effect.

The admin dashboard includes a dBm to W/mW table. Applying power is intentionally disabled until the exact Motorola PEI command is confirmed. When confirmed, set a template in `config.local.php`, for example:

```php
'SVXDASH_POWER_COMMAND_TEMPLATE' => 'AT+CONFIRMED_COMMAND={dbm}',
```

Supported placeholders are `{dbm}`, `{mw}` and `{w}`.

## RSSI and registered users

Current TetraLogic can count configured users and users seen in recent DMO activity.

Per-user RSSI is not guaranteed with the current code. The existing `AT+CSQ?` path measures the gateway radio RSSI and publishes `Rssi:info` for the gateway, not for each remote ISSI. The dashboard therefore shows:

- mobiles heard in the recent log window
- configured user count
- gateway RSSI if present
- per-mobile RSSI only when a RSSI line can be correlated close to an RX event

For real per-user RSSI we probably need a TetraLogic change that logs or publishes a signal value together with the active ISSI during `handleCallBegin` or PEI RX events, if the MTM5400 exposes that information in DMO.

## Later: prebuilt image

After the installer is stable, make two image targets:

- Debian amd64 image for NUC
- Raspberry Pi OS arm64 image for Raspberry Pi

Use the same installer inside both image builds so there is only one source of truth.
