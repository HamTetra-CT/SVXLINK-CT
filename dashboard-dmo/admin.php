<?php
declare(strict_types=1);

require_once __DIR__ . '/include/functions.php';

if (dashboard_admin_configured() && !dashboard_admin_authenticated()) {
    require_dashboard_admin();
}

$data = dashboard_data();
$pei = pei_dashboard_state();
$adminReady = $pei['admin_configured'] && $pei['admin_authenticated'];
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administração - <?php echo h($data['tetra']['callsign']); ?></title>
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
      <a href="index.php#hardware">Hardware</a>
      <select class="language-select" id="language-select" aria-label="Idioma">
        <option value="pt">PT</option>
        <option value="en">EN</option>
        <option value="fr">FR</option>
        <option value="es">ES</option>
      </select>
      <button class="theme-toggle" type="button" id="theme-toggle">Modo dia</button>
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
  </script>
  <script src="assets/theme.js"></script>
  <script src="assets/i18n.js"></script>
  <script src="assets/admin.js"></script>
</body>
</html>
