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
];
```

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
