<?php
declare(strict_types=1);

require_once __DIR__ . '/include/functions.php';

if (dashboard_admin_configured() && !dashboard_admin_authenticated()) {
    require_dashboard_admin();
}

$data = dashboard_data();
$pei = pei_dashboard_state();
$adminSettings = dashboard_admin_settings_state();
$settings = $adminSettings['settings'];
$meteo = meteo_dashboard_state();
$meteoConfig = $meteo['config'];
$adminReady = $pei['admin_configured'] && $pei['admin_authenticated'];
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administração - <?php echo h($data['tetra']['callsign']); ?></title>
  <link rel="icon" type="image/png" href="assets/favicon.png">
  <link rel="apple-touch-icon" href="assets/favicon.png">
  <link rel="stylesheet" href="assets/app.css">
</head>
<body data-page="admin">
  <header class="topbar compact">
    <div class="brand-lockup">
      <img src="assets/hamtetra-ct-logo.jpg" alt="HAMTETRA-CT Portugal">
      <div>
        <div class="brand-title">SVXLINK-CT <span>by HamTetra-CT</span></div>
        <h1>Administração</h1>
        <p>Controlo PEI para <?php echo h($data['tetra']['model']); ?></p>
      </div>
    </div>
    <nav class="nav">
      <a href="index.php">Painel</a>
      <a href="sds.php">SDS</a>
      <a class="active" href="admin.php">Administração</a>
      <a href="logs.php">Registos</a>
      <a href="index.php#hardware">Equipamento</a>
      <select class="language-select" id="language-select" aria-label="Idioma">
        <option value="pt">PT</option>
        <option value="en">EN</option>
        <option value="fr">FR</option>
        <option value="es">ES</option>
      </select>
      <button class="theme-toggle" type="button" id="theme-toggle" aria-label="Modo dia" title="Modo dia"><span class="theme-icon" aria-hidden="true">☀</span></button>
    </nav>
    <div class="top-actions">
      <div class="local-time">Hora local: <strong data-local-time><?php echo h(date('H:i:s')); ?></strong></div>
      <div class="service-pill <?php echo $pei['pei_pty_ready'] ? 'service-active' : 'service-inactive'; ?>">
        <span></span>
        <strong id="pei-pty-status"><?php echo $pei['pei_pty_ready'] ? 'PEI PRONTO' : 'PEI INDISPONÍVEL'; ?></strong>
      </div>
    </div>
  </header>

  <main class="layout">
    <?php if (!$pei['admin_configured']): ?>
      <section class="notice warning">
        Define a palavra-passe de administração em <code>/var/www/html/include/config.local.php</code>.
      </section>
    <?php endif; ?>

    <?php if (!$pei['pei_pty_ready']): ?>
      <section class="notice">
        O canal PEI ainda não está disponível em <code><?php echo h($pei['pei_pty']); ?></code>. O instalador configura <code>PEI_PTY=<?php echo h($pei['pei_pty']); ?></code>; se acabaste de instalar, reinicia o SvxLink uma vez para o TetraLogic criar o canal.
      </section>
    <?php endif; ?>

    <section class="grid admin-grid">
      <article class="card admin-wide">
        <div class="card-head">
          <div class="panel-title">Configuração do repetidor</div>
          <span><?php echo h($adminSettings['paths']['tetralogic_config']); ?></span>
        </div>
        <form class="form-stack config-form" id="server-config-form">
          <div class="form-grid">
            <label>
              Indicativo do repetidor
              <input name="callsign" id="config-callsign" value="<?php echo h($settings['callsign']); ?>" placeholder="CQ0Exxx" <?php echo $adminReady ? '' : 'disabled'; ?>>
            </label>
            <label>
              Modo TETRA
              <select name="tetra_mode" id="config-tetra-mode" <?php echo $adminReady ? '' : 'disabled'; ?>>
                <?php foreach (['DMO-MS', 'DMO-RPT', 'TMO'] as $mode): ?>
                  <option value="<?php echo h($mode); ?>" <?php echo $settings['tetra_mode'] === $mode ? 'selected' : ''; ?>><?php echo h($mode); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              GSSI local
              <input name="gssi" id="config-gssi" value="<?php echo h($settings['gssi']); ?>" inputmode="numeric" <?php echo $adminReady ? '' : 'disabled'; ?>>
            </label>
            <label>
              TG prioritário
              <input name="default_tg" id="config-default-tg" value="<?php echo h($settings['default_tg']); ?>" inputmode="numeric" <?php echo $adminReady ? '' : 'disabled'; ?>>
            </label>
            <label>
              TG monitorizados
              <input name="monitor_tgs" id="config-monitor-tgs" value="<?php echo h($settings['monitor_tgs']); ?>" placeholder="268, 915, 9990" <?php echo $adminReady ? '' : 'disabled'; ?>>
            </label>
            <label>
              Módulos activos
              <input name="modules" id="config-modules" value="<?php echo h($settings['modules']); ?>" placeholder="ModuleMetarInfo,ModuleParrot" <?php echo $adminReady ? '' : 'disabled'; ?>>
            </label>
          </div>
          <label>
            DTMFs para comandos SvxLink/TetraLogic
            <textarea name="dtmf_commands" id="config-dtmf-commands" spellcheck="false" placeholder="99=ModuleMetarInfo:play" <?php echo $adminReady ? '' : 'disabled'; ?>><?php echo h($settings['dtmf_commands']); ?></textarea>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Guardar configuração</button>
          </div>
          <p class="form-status" id="server-config-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">Acesso ao painel</div>
          <span>utilizador: <?php echo h(DASH_ADMIN_USER); ?></span>
        </div>
        <dl class="kv compact-kv">
          <div><dt>Inicial</dt><dd><?php echo h(DASH_DEFAULT_ADMIN_PASSWORD); ?></dd></div>
          <div><dt>Ficheiro</dt><dd>/var/www/html/include/config.local.php</dd></div>
        </dl>
        <form class="form-stack password-form" id="admin-password-form">
          <label>
            Nova palavra-passe
            <input type="password" id="admin-password" autocomplete="new-password" minlength="8" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <label>
            Confirmar palavra-passe
            <input type="password" id="admin-password-confirm" autocomplete="new-password" minlength="8" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Guardar palavra-passe</button>
          </div>
          <p class="form-status" id="admin-password-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">Consola PEI</div>
          <span><?php echo $adminReady ? 'administração activa' : 'bloqueado'; ?></span>
        </div>
        <div class="command-grid" id="pei-presets">
          <?php foreach ($pei['presets'] as $preset): ?>
            <button class="command-preset risk-<?php echo h($preset['risk']); ?>" type="button"
              data-command="<?php echo h($preset['command']); ?>"
              <?php echo $adminReady ? '' : 'disabled'; ?>>
              <strong><?php echo h($preset['label']); ?></strong>
              <span><?php echo h($preset['command']); ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <form class="form-stack command-form" id="pei-command-form">
          <label>
            Comando AT manual
            <input name="command" id="pei-command" placeholder="AT+CSQ?" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Enviar comando</button>
          </div>
          <p class="form-status" id="pei-command-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">Potência RF</div>
          <span><?php echo $pei['power_template_configured'] ? 'modelo pronto' : 'modelo em falta'; ?></span>
        </div>
        <form class="form-stack" id="power-form">
          <label>
            Nível
            <select id="power-dbm" <?php echo ($adminReady && $pei['power_template_configured']) ? '' : 'disabled'; ?>>
              <?php foreach ($pei['power_levels'] as $level): ?>
                <option value="<?php echo h((string)$level['dbm']); ?>"><?php echo h($level['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo ($adminReady && $pei['power_template_configured']) ? '' : 'disabled'; ?>>Aplicar potência</button>
          </div>
          <p class="form-status" id="power-status">
            <?php echo $pei['power_template_configured'] ? '' : 'Modelo de comando de potência ainda não configurado'; ?>
          </p>
        </form>
        <div class="power-table-wrap">
          <table class="mini-table">
            <thead><tr><th>dBm</th><th>Potência</th></tr></thead>
            <tbody>
              <?php foreach ($pei['power_levels'] as $level): ?>
                <tr><td><?php echo h((string)$level['dbm']); ?></td><td><?php echo h($level['label']); ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>

      <article class="card meteo-card">
        <div class="card-head">
          <div class="panel-title">Avisos meteorológicos</div>
          <span><?php echo $meteo['runner_ready'] ? 'serviço pronto' : 'instalador pendente'; ?></span>
        </div>
        <form class="form-stack" id="meteo-form">
          <label class="check-row">
            <input type="checkbox" id="meteo-enabled" <?php echo !empty($meteoConfig['enabled']) ? 'checked' : ''; ?> <?php echo $adminReady ? '' : 'disabled'; ?>>
            <span>Activar avisos por voz</span>
          </label>
          <label>
            Intervalo
            <select id="meteo-interval" <?php echo $adminReady ? '' : 'disabled'; ?>>
              <?php foreach ($meteo['intervals'] as $interval): ?>
                <option value="<?php echo h((string)$interval['value']); ?>" <?php echo (int)$meteoConfig['interval_minutes'] === (int)$interval['value'] ? 'selected' : ''; ?>>
                  <?php echo h($interval['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Procurar distrito/ilha
            <input type="search" id="meteo-location-search" placeholder="Lisboa, Porto, Madeira..." <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <label>
            Distrito/ilha IPMA
            <select id="meteo-location" class="scroll-select" size="8" <?php echo $adminReady ? '' : 'disabled'; ?>>
              <?php foreach ($meteo['locations'] as $location): ?>
                <option value="<?php echo h($location['id']); ?>" <?php echo $meteoConfig['location_id'] === $location['id'] ? 'selected' : ''; ?>>
                  <?php echo h($location['label'] . ' (' . $location['area'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <dl class="kv compact-kv admin-info">
            <div><dt>Chave</dt><dd class="<?php echo $meteo['credentials_ready'] ? 'ok-text' : 'warn-text'; ?>"><?php echo h($meteoConfig['credentials']); ?></dd></div>
            <div><dt>Áudio</dt><dd><?php echo h($meteoConfig['output_wav']); ?></dd></div>
            <div><dt>DTMF</dt><dd><?php echo h($meteoConfig['dtmf_command'] . ' em ' . $meteoConfig['dtmf_pty']); ?></dd></div>
          </dl>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Guardar avisos</button>
            <button type="button" class="button secondary maintenance-action" data-action="meteo-now" <?php echo $adminReady ? '' : 'disabled'; ?>>Gerar agora</button>
          </div>
          <p class="form-status" id="meteo-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">Manutenção</div>
          <span><?php echo $adminSettings['helper_ready'] ? 'helper activo' : 'helper em falta'; ?></span>
        </div>
        <div class="maintenance-grid">
          <?php foreach ($adminSettings['actions'] as $action): ?>
            <?php if ($action['id'] === 'meteo-now') { continue; } ?>
            <button class="maintenance-action risk-<?php echo h($action['risk']); ?>" type="button" data-action="<?php echo h($action['id']); ?>" <?php echo ($adminReady && $adminSettings['helper_ready']) ? '' : 'disabled'; ?>>
              <?php echo h($action['label']); ?>
            </button>
          <?php endforeach; ?>
        </div>
        <p class="form-status" id="maintenance-status"></p>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">Registo PEI</div>
          <span id="pei-log-count"><?php echo count($pei['log']); ?> entradas</span>
        </div>
        <div class="table-wrap">
          <table class="activity-table">
            <thead>
              <tr>
                <th>Hora</th>
                <th>Origem</th>
                <th>Comando</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody id="pei-log-body">
              <?php if (!$pei['log']): ?>
                <tr><td colspan="4" class="empty">Ainda não foram enviados comandos PEI pelo painel</td></tr>
              <?php endif; ?>
              <?php foreach ($pei['log'] as $entry): ?>
                <tr>
                  <td><?php echo h(date('H:i:s', strtotime((string)($entry['time'] ?? 'now')) ?: time())); ?></td>
                  <td><?php echo h((string)($entry['source'] ?? 'admin')); ?></td>
                  <td><?php echo h((string)($entry['command'] ?? '')); ?></td>
                  <td><span class="tag tag-pei"><?php echo h(strtoupper((string)($entry['status'] ?? 'sent'))); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>
    </section>

    <?php echo dashboard_footer(); ?>
  </main>

  <script>
    window.DMO_PEI = <?php echo json_encode($pei, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.DMO_ADMIN_SETTINGS = <?php echo json_encode($adminSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.DMO_METEO = <?php echo json_encode($meteo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script src="assets/theme.js"></script>
  <script src="assets/i18n.js"></script>
  <script src="assets/admin.js"></script>
</body>
</html>
