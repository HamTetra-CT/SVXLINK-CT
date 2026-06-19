<?php
declare(strict_types=1);

require_once __DIR__ . '/include/functions.php';

if (dashboard_admin_configured() && !dashboard_admin_authenticated()) {
    require_dashboard_admin();
}

$data = dashboard_data();
$sds = sds_dashboard_state();
$adminReady = $sds['admin_configured'] && $sds['admin_authenticated'];
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SDS - <?php echo h($data['tetra']['callsign']); ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body data-page="sds">
  <header class="topbar compact">
    <div>
      <div class="eyebrow"><?php echo h($data['site']); ?></div>
      <h1>SDS</h1>
      <p>Envio e presets via TetraLogic SDS_PTY</p>
    </div>
    <nav class="nav">
      <a href="index.php">Dashboard</a>
      <a class="active" href="sds.php">SDS</a>
      <a href="admin.php">Admin</a>
      <a href="logs.php">Logs</a>
    </nav>
    <div class="service-pill <?php echo $sds['sds_pty_ready'] ? 'service-active' : 'service-inactive'; ?>">
      <span></span>
      <strong id="sds-pty-status"><?php echo $sds['sds_pty_ready'] ? 'PTY READY' : 'PTY OFF'; ?></strong>
    </div>
  </header>

  <main class="layout">
    <?php if (!$sds['admin_configured']): ?>
      <section class="notice warning">
        Define <strong>SVXDASH_ADMIN_PASSWORD</strong> em <code>/var/www/html/include/config.local.php</code> para ativar envio SDS e edicao de presets.
      </section>
    <?php endif; ?>

    <?php if (!$sds['sds_pty_ready']): ?>
      <section class="notice">
        O TetraLogic ainda nao criou o SDS_PTY em <code><?php echo h($sds['sds_pty']); ?></code>. Ativa <code>SDS_PTY=<?php echo h($sds['sds_pty']); ?></code> uma vez no <code>TetraLogic.conf</code> e reinicia o SvxLink uma vez.
      </section>
    <?php endif; ?>

    <section class="grid sds-grid">
      <article class="card">
        <div class="card-head">
          <div class="panel-title">Send SDS</div>
          <span><?php echo $adminReady ? 'admin' : 'locked'; ?></span>
        </div>
        <form class="form-stack" id="sds-send-form">
          <label>
            Destination ISSI/TSI
            <input name="destination" id="sds-destination" inputmode="numeric" pattern="[0-9]*" list="sds-users" placeholder="ex: 23451 ou 0901163830023451" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <datalist id="sds-users">
            <?php foreach ($sds['users'] as $user): ?>
              <option value="<?php echo h($user['tsi']); ?>"><?php echo h($user['label']); ?></option>
            <?php endforeach; ?>
          </datalist>
          <label>
            Type
            <select name="type" id="sds-type" <?php echo $adminReady ? '' : 'disabled'; ?>>
              <option value="T">Text SDS</option>
              <option value="R">Raw HEX</option>
            </select>
          </label>
          <label>
            Message
            <textarea name="message" id="sds-message" rows="4" maxlength="220" placeholder="Max 120 chars para texto" <?php echo $adminReady ? '' : 'disabled'; ?>></textarea>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Send</button>
            <button type="button" class="button secondary" id="sds-clear" <?php echo $adminReady ? '' : 'disabled'; ?>>Clear</button>
          </div>
          <p class="form-status" id="sds-send-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">TetraLogic Path</div>
          <span><?php echo h($sds['sds_pty']); ?></span>
        </div>
        <dl class="kv compact-kv">
          <div><dt>SDS PTY</dt><dd><?php echo $sds['sds_pty_ready'] ? 'READY' : 'NOT FOUND'; ?></dd></div>
          <div><dt>Presets</dt><dd id="sds-preset-count"><?php echo count($sds['presets']); ?></dd></div>
          <div><dt>Users</dt><dd><?php echo count($sds['users']); ?></dd></div>
          <div><dt>Mode</dt><dd><?php echo h($data['tetra']['mode']); ?></dd></div>
        </dl>
      </article>
    </section>

    <section class="grid sds-split">
      <article class="card">
        <div class="card-head">
          <div class="panel-title">Presets</div>
          <span>live JSON, sem restart</span>
        </div>
        <div class="preset-list" id="sds-presets">
          <?php foreach ($sds['presets'] as $preset): ?>
            <button class="preset-item" type="button"
              data-id="<?php echo h($preset['id']); ?>"
              data-destination="<?php echo h($preset['destination']); ?>"
              data-type="<?php echo h($preset['type']); ?>"
              data-message="<?php echo h($preset['message']); ?>">
              <strong><?php echo h($preset['label']); ?></strong>
              <span><?php echo h(($preset['type'] === 'R' ? 'RAW' : 'TEXT') . ' ' . ($preset['destination'] ?: 'sem destino')); ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <form class="form-stack preset-form" id="sds-preset-form">
          <input type="hidden" name="id" id="preset-id">
          <label>
            Label
            <input name="label" id="preset-label" placeholder="Nome do preset" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <label>
            Default destination
            <input name="destination" id="preset-destination" inputmode="numeric" pattern="[0-9]*" placeholder="opcional" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <label>
            Type
            <select name="type" id="preset-type" <?php echo $adminReady ? '' : 'disabled'; ?>>
              <option value="T">Text SDS</option>
              <option value="R">Raw HEX</option>
            </select>
          </label>
          <label>
            Message
            <textarea name="message" id="preset-message" rows="3" maxlength="220" <?php echo $adminReady ? '' : 'disabled'; ?>></textarea>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Save preset</button>
            <button type="button" class="button secondary" id="preset-delete" <?php echo $adminReady ? '' : 'disabled'; ?>>Delete</button>
          </div>
          <p class="form-status" id="sds-preset-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">SDS Log</div>
          <span id="sds-log-count"><?php echo count($sds['log']); ?> entries</span>
        </div>
        <div class="table-wrap">
          <table class="activity-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Dir</th>
                <th>Dest</th>
                <th>Type</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody id="sds-log-body">
              <?php if (!$sds['log']): ?>
                <tr><td colspan="5" class="empty">No SDS sent from dashboard yet</td></tr>
              <?php endif; ?>
              <?php foreach ($sds['log'] as $entry): ?>
                <tr>
                  <td><?php echo h(date('H:i:s', strtotime((string)($entry['time'] ?? 'now')) ?: time())); ?></td>
                  <td><span class="tag tag-sds"><?php echo h(strtoupper((string)($entry['direction'] ?? 'tx'))); ?></span></td>
                  <td><?php echo h((string)($entry['destination'] ?? '')); ?></td>
                  <td><?php echo h((string)($entry['type'] ?? 'T')); ?></td>
                  <td><?php echo h((string)($entry['message'] ?? '')); ?></td>
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
    window.DMO_SDS = <?php echo json_encode($sds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script src="assets/sds.js"></script>
</body>
</html>
