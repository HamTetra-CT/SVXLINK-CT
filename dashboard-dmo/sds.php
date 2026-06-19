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
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SDS - <?php echo h($data['title']); ?> - <?php echo h($data['tetra']['callsign']); ?></title>
  <link rel="icon" type="image/png" href="<?php echo asset_url('assets/favicon.png'); ?>">
  <link rel="apple-touch-icon" href="<?php echo asset_url('assets/favicon.png'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/app.css'); ?>">
</head>
<body data-page="sds">
  <header class="topbar compact">
    <div class="brand-lockup">
      <img src="assets/hamtetra-ct-logo.jpg" alt="HAMTETRA-CT Portugal">
      <div>
        <div class="brand-title">SvxLink Dashboard TETRA <span>by HamTetra-CT</span></div>
        <h1>SDS</h1>
        <p>Envio e modelos via TetraLogic SDS_PTY</p>
      </div>
    </div>
    <nav class="nav">
      <a href="index.php">Painel</a>
      <a class="active" href="sds.php">SDS</a>
      <a href="admin.php">Administração</a>
      <a href="logs.php">Registos</a>
      <a href="index.php#hardware">Equipamento</a>
      <select class="language-select" id="language-select" aria-label="Idioma">
        <option value="pt">PT</option>
        <option value="en">EN</option>
        <option value="fr">FR</option>
        <option value="es">ES</option>
      </select>
      <button class="theme-toggle" type="button" id="theme-toggle" aria-label="Alterar tema"><span class="theme-icon" aria-hidden="true">☀</span></button>
    </nav>
    <div class="top-actions">
      <div class="local-time">Hora local: <strong data-local-time><?php echo h(date('H:i:s')); ?></strong></div>
      <div class="service-pill <?php echo $sds['sds_pty_ready'] ? 'service-active' : 'service-inactive'; ?>">
        <span></span>
        <strong id="sds-pty-status"><?php echo $sds['sds_pty_ready'] ? 'PTY PRONTO' : 'PTY INDISPONÍVEL'; ?></strong>
      </div>
    </div>
  </header>

  <main class="layout">
    <?php if (!$sds['admin_configured']): ?>
      <section class="notice warning">
        Define a palavra-passe de administração em <code>/var/www/html/include/config.local.php</code> para activar o envio SDS e a edição de modelos.
      </section>
    <?php endif; ?>

    <?php if (!$sds['sds_pty_ready']): ?>
      <section class="notice">
        O canal SDS ainda não está disponível em <code><?php echo h($sds['sds_pty']); ?></code>. O instalador configura <code>SDS_PTY=<?php echo h($sds['sds_pty']); ?></code>; se acabaste de instalar, reinicia o SvxLink uma vez para o TetraLogic criar o canal.
      </section>
    <?php endif; ?>

    <section class="grid sds-grid">
      <article class="card">
        <div class="card-head">
          <div class="panel-title">Enviar SDS</div>
          <span><?php echo $adminReady ? 'administração activa' : 'bloqueado'; ?></span>
        </div>
        <form class="form-stack" id="sds-send-form">
          <label>
            Destino ISSI/TSI
            <input name="destination" id="sds-destination" inputmode="numeric" pattern="[0-9]*" list="sds-users" placeholder="ex: 23451 ou 0901163830023451" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <datalist id="sds-users">
            <?php foreach ($sds['users'] as $user): ?>
              <option value="<?php echo h($user['tsi']); ?>"><?php echo h($user['label']); ?></option>
            <?php endforeach; ?>
          </datalist>
          <label>
            Tipo
            <select name="type" id="sds-type" <?php echo $adminReady ? '' : 'disabled'; ?>>
              <option value="T">Texto SDS</option>
              <option value="R">HEX bruto</option>
            </select>
          </label>
          <label>
            Mensagem
            <textarea name="message" id="sds-message" rows="4" maxlength="220" placeholder="Máximo 120 caracteres para texto" <?php echo $adminReady ? '' : 'disabled'; ?>></textarea>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Enviar</button>
            <button type="button" class="button secondary" id="sds-clear" <?php echo $adminReady ? '' : 'disabled'; ?>>Limpar</button>
          </div>
          <p class="form-status" id="sds-send-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">Caminho TetraLogic</div>
          <span><?php echo h($sds['sds_pty']); ?></span>
        </div>
        <dl class="kv compact-kv">
          <div><dt>SDS PTY</dt><dd><?php echo $sds['sds_pty_ready'] ? 'PRONTO' : 'NÃO ENCONTRADO'; ?></dd></div>
          <div><dt>Modelos</dt><dd id="sds-preset-count"><?php echo count($sds['presets']); ?></dd></div>
          <div><dt>Utilizadores</dt><dd><?php echo count($sds['users']); ?></dd></div>
          <div><dt>Modo</dt><dd><?php echo h($data['tetra']['mode']); ?></dd></div>
        </dl>
      </article>
    </section>

    <section class="grid sds-split">
      <article class="card">
        <div class="card-head">
          <div class="panel-title">Modelos SDS</div>
          <span>JSON em directo, sem reiniciar</span>
        </div>
        <div class="preset-list" id="sds-presets">
          <?php foreach ($sds['presets'] as $preset): ?>
            <button class="preset-item" type="button"
              data-id="<?php echo h($preset['id']); ?>"
              data-destination="<?php echo h($preset['destination']); ?>"
              data-type="<?php echo h($preset['type']); ?>"
              data-message="<?php echo h($preset['message']); ?>">
              <strong><?php echo h($preset['label']); ?></strong>
              <span><?php echo h(($preset['type'] === 'R' ? 'HEX' : 'TEXTO') . ' ' . ($preset['destination'] ?: 'sem destino')); ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <form class="form-stack preset-form" id="sds-preset-form">
          <input type="hidden" name="id" id="preset-id">
          <label>
            Nome
            <input name="label" id="preset-label" placeholder="Nome do modelo" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <label>
            Destino predefinido
            <input name="destination" id="preset-destination" inputmode="numeric" pattern="[0-9]*" placeholder="opcional" <?php echo $adminReady ? '' : 'disabled'; ?>>
          </label>
          <label>
            Tipo
            <select name="type" id="preset-type" <?php echo $adminReady ? '' : 'disabled'; ?>>
              <option value="T">Texto SDS</option>
              <option value="R">HEX bruto</option>
            </select>
          </label>
          <label>
            Mensagem
            <textarea name="message" id="preset-message" rows="3" maxlength="220" <?php echo $adminReady ? '' : 'disabled'; ?>></textarea>
          </label>
          <div class="button-row">
            <button type="submit" <?php echo $adminReady ? '' : 'disabled'; ?>>Guardar modelo</button>
            <button type="button" class="button secondary" id="preset-delete" <?php echo $adminReady ? '' : 'disabled'; ?>>Apagar</button>
          </div>
          <p class="form-status" id="sds-preset-status"></p>
        </form>
      </article>

      <article class="card">
        <div class="card-head">
          <div class="panel-title">Registo SDS</div>
          <span id="sds-log-count"><?php echo count($sds['log']); ?> entradas</span>
        </div>
        <div class="table-wrap">
          <table class="activity-table">
            <thead>
              <tr>
                <th>Hora</th>
                <th>Sentido</th>
                <th>Destino</th>
                <th>Tipo</th>
                <th>Mensagem</th>
              </tr>
            </thead>
            <tbody id="sds-log-body">
              <?php if (!$sds['log']): ?>
                <tr><td colspan="5" class="empty">Ainda não foram enviados SDS pelo painel</td></tr>
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

    <?php echo dashboard_footer(); ?>
  </main>

  <script>
    window.DMO_SDS = <?php echo json_encode($sds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script src="<?php echo asset_url('assets/theme.js'); ?>"></script>
  <script src="<?php echo asset_url('assets/i18n.js'); ?>"></script>
  <script src="assets/sds.js"></script>
</body>
</html>
