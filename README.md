# SVXLINK-CT

Ferramentas SvxLink/TetraLogic focadas em DMO para testes com Motorola MTM5400/MTM800E.

Alvos suportados:

- `amd64`/`x86_64`: NUC, mini-PC e computadores 64-bit.
- `arm64`/`aarch64`: Raspberry Pi OS/Debian 64-bit.

Conteúdo:

- `dashboard-dmo/`: painel PHP leve, afinado para DMO e MTM5400.
- `sounds/pt_PT/`: vozes SvxLink em português de Portugal.
- `menu/svxlink-ct-menu.sh`: menu local de manutenção.
- `install/install-svxlink-ct.sh`: instalador/actualizador completo.
- `install/install-dmo-dashboard.sh`: instala Apache/PHP e publica o painel em `/var/www/html`.
- `install/update-dashboard-only.sh`: actualiza apenas o painel em `/var/www/html`.
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

Tarefa automática diária:

- Corre todos os dias às `04:00`.
- Actualiza apenas `/etc/svxlink/tetra_users.json`.
- Não reinicia o SvxLink.
- Regista em `/var/log/svxlink-ct-users-update.log`.

Actualizar apenas o painel:

```bash
curl -fsSL https://raw.githubusercontent.com/HamTetra-CT/SVXLINK-CT/main/install/update-dashboard-only.sh | sudo bash
```

ISO/IMG deve ficar para uma fase posterior. Para já, o instalador é o caminho mais simples porque NUC e Raspberry Pi precisam de imagens diferentes.
