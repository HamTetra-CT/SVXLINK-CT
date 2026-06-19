# SVXLINK-CT

Ferramentas SvxLink/TetraLogic focadas em DMO para testes com Motorola MTM5400/MTM800E.

Alvos suportados:

- `amd64`/`x86_64`: NUC, mini-PC e computadores 64-bit.
- `arm64`/`aarch64`: Raspberry Pi OS/Debian 64-bit.
- Distribuições alvo: Debian 12/13, Ubuntu Server 24.04 e Raspberry Pi OS 64-bit.

Conteúdo:

- `dashboard-dmo/`: painel PHP leve, afinado para DMO e MTM5400.
- `sounds/pt_PT/`: vozes SvxLink em português de Portugal.
- `menu/svxlink-ct-menu.sh`: menu local de manutenção.
- `install/install-svxlink-ct.sh`: instalador/actualizador completo.
- `install/install-dmo-dashboard.sh`: instala Apache/PHP e publica o painel em `/var/www/html`.
- `install/update-dashboard-only.sh`: actualiza apenas o painel em `/var/www/html`.
- `install/install-meteo-alerts.sh`: instala avisos IPMA por voz com Google Text-to-Speech e `sox`.
- `install/update-tetra-users.sh`: actualiza `/etc/svxlink/tetra_users.json` de forma atómica, sem reiniciar o SvxLink.
- `install/check-dmo-system.sh`: verifica os ficheiros esperados de SvxLink/TetraLogic.
- `docs/INSTALL_DMO_STEP_BY_STEP.md`: guia de instalação passo a passo.

Instalação recomendada:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/install-svxlink-ct.sh | sudo bash
```

Instalação manual:

```bash
git clone git@github.com:HamTetra-CT/SVXLINK-CT.git
cd SVXLINK-CT
sudo install/install-dmo-dashboard.sh
sudo install/install-maintenance-tools.sh
sudo install/install-pt-voices.sh
```

Menu de manutenção depois da instalação:

```bash
sudo svxlink-ct
```

Credenciais iniciais do painel de administração:

- Utilizador: `admin`
- Palavra-passe: `hamtetra-ct`
- Altera no painel em `Administração` ou directamente em `/var/www/html/include/config.local.php`.

Administração pelo painel:

- Indicativo do repetidor, por defeito `CQ0Exxx`.
- GSSI local, TG prioritário e TGs monitorizados.
- DTMFs para comandos SvxLink/TetraLogic, com exemplos para `MetarInfo`, `Parrot ON` e `Parrot OFF`.
- Botões para reiniciar SvxLink, reiniciar o equipamento, correr `apt update`, correr `apt upgrade` e gerar aviso meteorológico.
- Potência RF com níveis `27.5 dBm/560 mW` até `40.0 dBm/10.0 W`; a aplicação depende do comando PEI Motorola correcto em `SVXDASH_POWER_COMMAND_TEMPLATE`.

Tarefa automática diária:

- Corre todos os dias às `04:00`.
- Actualiza apenas `/etc/svxlink/tetra_users.json`.
- Não reinicia o SvxLink.
- Regista em `/var/log/svxlink-ct-users-update.log`.

Actualizar apenas o painel:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/update-dashboard-only.sh | sudo bash
```

Se a unidade systemd do SvxLink tiver outro nome:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/update-dashboard-only.sh | sudo SVXLINK_SERVICE=nome-da-unidade bash
```

Avisos meteorológicos IPMA:

- Instala dependências com o instalador completo ou `sudo install/install-meteo-alerts.sh`.
- Copia a chave Google Text-to-Speech automaticamente com `CHAVE_JSON=/caminho/chave.json` ou deixa-a manualmente em `/home/pi/chave.json`.
- Activa os avisos em `Administração` e escolhe o distrito/ilha IPMA.
- O cron verifica a cada 5 minutos, mas só gera aviso quando passa o intervalo definido no painel.

ISO/IMG deve ficar para uma fase posterior. Para já, o instalador é o caminho mais simples porque NUC e Raspberry Pi precisam de imagens diferentes.
