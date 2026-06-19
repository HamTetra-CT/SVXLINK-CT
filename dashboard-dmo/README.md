# Painel SVXLINK DMO

Painel PHP leve para uma instalação SvxLink com TetraLogic e Motorola MTM5400 em DMO.

## Objetivo

- Mostrar estado DMO: espera, RX local e TX para GSSI.
- Ler `svxlink.conf`, `TetraLogic.conf`, `tetra_users.json`, `pei-init.json` e `/var/log/svxlink`.
- Gerir pelo painel o indicativo, GSSI, TG prioritário, TGs monitorizados e DTMFs para comandos.
- Activar avisos meteorológicos IPMA por distrito/ilha com voz `pt_PT`.
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
    'SVXDASH_SITE' => 'CQ0Exxx',
    'SVXDASH_TITLE' => 'Painel SVXLINK',
    'SVXDASH_SUBTITLE' => 'Motorola MTM5400',
    'SVXDASH_MTM_MODEL' => 'Motorola MTM5400',
    'SVXDASH_ADMIN_USER' => 'admin',
    'SVXDASH_ADMIN_PASSWORD' => 'hamtetra-ct',
    'SVXDASH_METEO_CONFIG_FILE' => '/var/lib/svxlink-ct/meteo-alerts.json',
    'SVXDASH_MAINT_HELPER' => '/usr/local/sbin/svxlink-ct-dashboard-action',
];
```

A palavra-passe inicial é `hamtetra-ct`. Altera no painel em `Administração` ou directamente em `/var/www/html/include/config.local.php`.

O indicativo mostrado no topo vem de `SVXDASH_SITE`; por defeito é `CQ0Exxx`. O update do painel força este valor por defeito, a menos que corras com `FORCE_SITE=0`.

O botão `Reiniciar SvxLink` usa `/usr/local/sbin/svxlink-ct-dashboard-action`. Se a unidade systemd não se chamar `svxlink`, define o nome em `/etc/default/svxlink-ct-dashboard`:

```text
SVXLINK_SERVICE="nome-da-unidade.service"
```

Também pode ser testado com variáveis de ambiente:

```bash
SVXDASH_ROOT=/path/to/extracted-root php -S 127.0.0.1:8088 -t dashboard-dmo
```

## Endpoints

- `index.php`: painel principal.
- `logs.php`: eventos filtrados.
- `api.php?action=dashboard`: estado completo em JSON.
- `api.php?action=events`: eventos e estado runtime.
- `api.php?action=meteo_state`: estado dos avisos IPMA.
- `api.php?action=server_config`: grava configuração principal do repetidor, com autenticação.
- `api.php?action=maintenance_action`: executa acções de manutenção permitidas, com autenticação.

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

## Avisos IPMA

Os avisos oficiais são por área/distrito. O painel mostra apenas distritos e ilhas IPMA, por exemplo `Lisboa (LSB)`, `Porto (POR)` e `Madeira (MAD)`.

Para gerar voz é necessário:

```bash
sudo install/install-meteo-alerts.sh
sudo cp chave.json /home/pi/chave.json
```

A chave é local da máquina e não pertence ao repositório.
