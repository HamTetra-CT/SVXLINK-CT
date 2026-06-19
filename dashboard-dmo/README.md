# SVXLINK DMO Dashboard

Dashboard PHP leve para uma instalacao SvxLink com TetraLogic e Motorola MTM5400 em DMO.

## Objetivo

- Mostrar estado DMO: idle, RX local e TX para GSSI.
- Ler `svxlink.conf`, `TetraLogic.conf`, `tetra_users.json`, `pei-init.json` e `/var/log/svxlink`.
- Remover partes que nao interessam neste uso: EchoLink, TGs analogicos genericos, QRZ externo e parsing pesado de logs antigos.
- Evitar comandos shell para ler logs. O dashboard usa leitura parcial do ficheiro para manter o Raspberry responsivo.

## Instalar

Copiar a pasta `dashboard-dmo` para o web root do Raspberry, por exemplo:

```bash
sudo cp -a dashboard-dmo /var/www/html/dmo
sudo chown -R www-data:www-data /var/www/html/dmo
```

O utilizador do web server precisa de leitura em:

```text
/etc/svxlink/svxlink.conf
/etc/svxlink/svxlink.d/TetraLogic.conf
/etc/svxlink/tetra_users.json
/etc/svxlink/pei-init.json
/var/log/svxlink
```

## Configuracao

Por omissao usa os caminhos normais do sistema. Para ajustar sem editar codigo, criar `include/config.local.php`:

```php
<?php
return [
    'SVXDASH_TIMEZONE' => 'Europe/Lisbon',
    'SVXDASH_SITE' => 'CT DMO',
    'SVXDASH_TITLE' => 'SVXLINK DMO Dashboard',
    'SVXDASH_SUBTITLE' => 'MTM5400 DMO Gateway',
    'SVXDASH_MTM_MODEL' => 'Motorola MTM5400',
];
```

Tambem pode ser testado com variaveis de ambiente:

```bash
SVXDASH_ROOT=/path/to/extracted-root php -S 127.0.0.1:8088 -t dashboard-dmo
```

## Endpoints

- `index.php`: dashboard principal.
- `logs.php`: eventos filtrados.
- `api.php?action=dashboard`: estado completo em JSON.
- `api.php?action=events`: eventos e estado runtime.

## Dados DMO usados

O parser foi afinado para as mensagens reais do TetraLogic:

- `Groupcall from ... to ...`
- `Init groupcall to GSSI: ...`
- `Rx1: The squelch is OPEN/CLOSED`
- `Tx1: Turning the transmitter ON/OFF`
- `PEI init finished`
- `+++ State Sds received: ...`
- `ReflectorLogic: Selecting TG #...`
- `Rx1: Distortion detected`

Isto evita depender de modulos que ja nao se usam neste cenario.
