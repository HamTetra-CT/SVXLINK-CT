# Instalação SVXLINK-CT DMO

Este guia assume Debian 12/13, Ubuntu Server 24.04 ou Raspberry Pi OS 64-bit.

## Arquitecturas

- `amd64`/`x86_64`: NUC, mini-PC e computadores 64-bit.
- `arm64`/`aarch64`: Raspberry Pi 4/5 com sistema 64-bit.

O painel é PHP/Apache e não depende de binários próprios por arquitectura. O SvxLink/TetraLogic e controladores de áudio/USB continuam dependentes do sistema onde forem compilados/instalados.

## Instalação rápida

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/install-svxlink-ct.sh | sudo bash
```

O instalador:

- instala Apache/PHP;
- coloca o painel em `/var/www/html`;
- instala o menu `sudo svxlink-ct`;
- instala as vozes `pt_PT`;
- instala avisos IPMA por voz com Google Text-to-Speech e `sox`;
- instala o helper seguro que permite ao painel reiniciar SvxLink, reiniciar o equipamento e correr tarefas apt;
- configura `DEFAULT_LANG=pt_PT` quando encontra os ficheiros SvxLink;
- configura `SDS_PTY=/tmp/tetra_sds` e `PEI_PTY=/tmp/pei_pty` no `TetraLogic.conf` quando o ficheiro existe;
- instala a actualização diária de utilizadores às `04:00`.

Credenciais iniciais do painel:

- Utilizador: `admin`
- Palavra-passe: `hamtetra-ct`

Altera a palavra-passe em `Administração` no painel ou em:

```text
/var/www/html/include/config.local.php
```

## Actualizar apenas o painel

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/update-dashboard-only.sh | sudo bash
```

Este comando não mexe no SvxLink nem reinicia o serviço.

## Menu local

Depois da instalação:

```bash
sudo svxlink-ct
```

O menu permite:

- ver estado do sistema;
- ver o registo do SvxLink;
- actualizar o repositório;
- actualizar apenas o painel;
- actualizar `tetra_users.json` sem reiniciar;
- instalar/actualizar vozes `pt_PT`;
- editar `svxlink.conf`, `TetraLogic.conf` e `tetra_users.json`;
- reiniciar o SvxLink manualmente quando for mesmo necessário.
- correr `apt update` e `apt upgrade`;
- gerar aviso meteorológico IPMA manualmente.

## Administração pelo dashboard

O painel `Administração` permite configurar sem linha de comandos:

- indicativo do repetidor, por defeito `CQ0Exxx`;
- modo TETRA, GSSI local, TG prioritário e TGs monitorizados;
- DTMFs que disparam comandos SvxLink/TetraLogic, incluindo exemplos para `MetarInfo`, `Parrot ON` e `Parrot OFF`;
- palavra-passe do painel;
- comandos PEI e potência RF;
- avisos meteorológicos IPMA por distrito/ilha;
- reinício do SvxLink, reboot do equipamento, `apt update` e `apt upgrade`.

Os botões de manutenção usam `/usr/local/sbin/svxlink-ct-dashboard-action` via `/etc/sudoers.d/svxlink-ct-dashboard`. O Apache só consegue chamar esse helper com as acções previstas.

## Actualização diária de utilizadores

O cron instalado em `/etc/cron.d/svxlink-ct-users-update` corre todos os dias às `04:00`.

Só actualiza:

```text
/etc/svxlink/tetra_users.json
```

Não actualiza o painel, vozes, repositório ou binários SvxLink. Também não reinicia o SvxLink.

## SDS e PEI

Para envio SDS e comandos PEI sem reiniciar o serviço a cada alteração, o TetraLogic precisa destes canais:

```ini
SDS_PTY=/tmp/tetra_sds
PEI_PTY=/tmp/pei_pty
```

O instalador tenta configurar isto automaticamente. Depois de activar estes valores pela primeira vez, reinicia o SvxLink uma vez para o TetraLogic criar os canais:

```bash
sudo systemctl restart svxlink
```

Depois disso, o painel consegue enviar SDS e comandos PEI pelo PTY sem reinícios constantes.

## Vozes pt_PT

O script das vozes instala os ficheiros em:

```text
/usr/share/svxlink/sounds/pt_PT
```

Também tenta garantir:

```ini
DEFAULT_LANG=pt_PT
```

em `svxlink.conf` e `TetraLogic.conf`.

## Avisos meteorológicos IPMA

O instalador cria:

```text
/var/lib/svxlink-ct/meteo-alerts.json
/usr/local/sbin/svxlink-ct-meteo-alerts
/etc/cron.d/svxlink-ct-meteo-alerts
```

Depois copia a chave Google Text-to-Speech para:

```text
/home/pi/chave.json
```

Ou corre o update/instalador com:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/update-dashboard-only.sh | sudo CHAVE_JSON=/caminho/chave.json bash
```

No painel, entra em `Administração`, activa `Avisos meteorológicos`, escolhe o distrito/ilha IPMA e define o intervalo. O cron corre a cada 5 minutos mas respeita o intervalo escolhido.

Se o botão `Reiniciar SvxLink` indicar que a unidade não existe, confirma o nome com `systemctl list-units '*svx*'` e define:

```bash
sudo nano /etc/default/svxlink-ct-dashboard
```

com:

```text
SVXLINK_SERVICE="nome-da-unidade.service"
```

## Notas DMO MTM5400/MTM800E

- A leitura de RSSI por terminal depende do que o TetraLogic conseguir expor nos registos/PEI.
- A potência RF aparece com tabela dBm/W/mW, mas a aplicação directa fica bloqueada até confirmarmos o comando PEI Motorola correcto.
- Para produção, muda a palavra-passe inicial do painel.

## Compatibilidade Linux

O painel e instaladores são desenhados para `amd64` e `arm64` em Debian, Ubuntu Server e Raspberry Pi OS. A parte web é PHP/Apache e é independente da arquitectura. O SvxLink/TetraLogic, áudio ALSA, portas USB e permissões do rádio podem precisar de ajustes próprios de cada equipamento.
