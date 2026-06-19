# Painel SVXLINK DMO

Painel PHP leve para uma instalação SvxLink com TetraLogic e Motorola MTM5400 em DMO.

## Objetivo

- Mostrar estado DMO: espera, RX local e TX para GSSI.
- Ler `svxlink.conf`, `TetraLogic.conf`, `tetra_users.json`, `pei-init.json` e `/var/log/svxlink`.
- Remover partes que não interessam neste uso: pontes legadas, TGs analógicos genéricos, QRZ externo e leitura pesada de registos antigos.
- Evitar comandos shell para ler registos. O painel usa leitura parcial do ficheiro para manter o Raspberry responsivo.

## Instalar

Copiar a pasta `dashboard-dmo` para o web root do Raspberry, por exemplo:

```bash
sudo cp -a dashboard-dmo /var/www/html/dmo
sudo chown -R www-data:www-data /var/www/html/dmo
```

O utilizador do servidor web precisa de leitura em:

```text
/etc/svxlink/svxlink.conf
/etc/svxlink/svxlink.d/TetraLogic.conf
/etc/svxlink/tetra_users.json
/etc/svxlink/pei-init.json
/var/log/svxlink
```

## Configuração

Por omissão usa os caminhos normais do sistema. Para ajustar sem editar código, cria `include/config.local.php`:

```php
<?php
return [
    'SVXDASH_TIMEZONE' => 'Europe/Lisbon',
    'SVXDASH_VERSION' => 'V1.0',
    'SVXDASH_SITE' => 'CT DMO',
    'SVXDASH_TITLE' => 'Painel SVXLINK DMO',
    'SVXDASH_SUBTITLE' => 'Gateway DMO MTM5400',
    'SVXDASH_MTM_MODEL' => 'Motorola MTM5400',
    'SVXDASH_ADMIN_USER' => 'admin',
    'SVXDASH_ADMIN_PASSWORD' => 'hamtetra-ct',
];
```

A palavra-passe inicial é `hamtetra-ct`. Altera no painel em `Administração` ou directamente em `/var/www/html/include/config.local.php`.

Também pode ser testado com variáveis de ambiente:

```bash
SVXDASH_ROOT=/path/to/extracted-root php -S 127.0.0.1:8088 -t dashboard-dmo
```

## Endpoints

- `index.php`: painel principal.
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

Isto evita depender de módulos que já não se usam neste cenário.
