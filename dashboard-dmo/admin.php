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
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?php echo h($data['tetra']['callsign']); ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body data-page="admin">
  <header class="topbar compact">
    <div>
      <div class="eyebrow"><?php echo h($data['site']); ?></div>
      <h1>Admin</h1>
      <p>PEI control for <?php echo h($data['tetra']['model']); ?></p>
    </div>
    <nav class="nav">
      <a href="index.php">Dashboard</a>
      <a href="sds.php">SDS</a>
      <a class="active" href="admin.php">Admin</a>
      <a href="logs.php">Logs</a>
    </nav>
    <div class="service-pill <?php echo $pei['pei_pty_ready'] ? 'service-active' : 'service-inactive'; ?>">
      <span></span>
      <strong id="pei-pty-status"><?php echo $pei['pei_pty_ready'] ? 'PEI READY' : 'PEI OFF'; ?></strong>
    </div>
  </header>

  <main class="layout">
    <?php if (!$pei['admin_configured']): ?>
      <section class="notice warning">
        Define <strong>SVXDASH_ADMIN_PASSWORD</strong> em <code>/var/www/html/include/config.local.php</code> para ativar comandos PEI.
      </section>
    <?php endif; ?>

    <?php if (!$pei['pei_pty_ready']): ?>
      <section class="notice">
        O TetraLogic ainda nao criou o PEI_PTY em <code><?php echo h($pei['pei_pty']); ?></code>. Ativa <code>PEI_PTY=<?php echo h($pei['pei_pty']); ?></code> uma vez no <code>TetraLogic.conf</code> e reinicia o SvxLink uma vez.
      </section>
    <?php endif; ?>

    <section class="grid admin-grid">
      <article class="card">
        <div class="card-head">
          <div class="panel-title">PEI Commands</div>
          <span><?php echo $adminReady ? 'admin' : 'locked'; ?></span>
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
            Custom AT command
            <input name="command" id="pei-command" placeholder="AT+CSQ?" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Send command</button>
          </div>
          <p class="form-status" id="pei-command-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">RF Power</div>
          <span><?php echo $pei['power_template_configured'] ? 'template ready' : 'template missing'; ?></span>
        </div>
        <form class="form-stack" id="power-form">
          <label>
            Level
            <select id="power-dbm" <?php echo ($adminReady && $pei['power_template_configured']) ? '' : 'disabled'; ?>>
              <?php foreach ($pei['power_levels'] as $level): ?>
                <option value="<?php echo h((string)$level['dbm']); ?>"><?php echo h($level['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo ($adminReady && $pei['power_template_configured']) ? '' : 'disabled'; ?>>Apply power</button>
          </div>
          <p class="form-status" id="power-status">
            <?php echo $pei['power_template_configured'] ? '' : 'SVXDASH_POWER_COMMAND_TEMPLATE not configured'; ?>
          </p>
        </form>
        <div class="power-table-wrap">
          <table class="mini-table">
            <thead><tr><th>dBm</th><th>Power</th></tr></thead>
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
          <div class="panel-title">PEI Log</div>
          <span id="pei-log-count"><?php echo count($pei['log']); ?> entries</span>
        </div>
        <div class="table-wrap">
          <table class="activity-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Source</th>
                <th>Command</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="pei-log-body">
              <?php if (!$pei['log']): ?>
                <tr><td colspan="4" class="empty">No PEI commands sent from dashboard yet</td></tr>
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

    <footer class="footer-credit">
      SvxLink and TetraLogic credits remain with their original authors. This dashboard version: &lt;3 feita com amor pela HAMTETRA-CT.
    </footer>
  </main>

  <script>
    window.DMO_PEI = <?php echo json_encode($pei, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script src="assets/admin.js"></script>
</body>
</html>
