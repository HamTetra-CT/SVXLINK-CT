<?php
declare(strict_types=1);

require_once __DIR__ . '/include/functions.php';
$data = dashboard_data();
$runtime = $data['runtime'];
$tetra = $data['tetra'];
$radio = $data['radio'];
$reflector = $data['reflector'];
$hardware = $data['hardware'];
$service = $data['service'];
$mobiles = $data['mobiles'];
$latestEvent = $data['events'] ? $data['events'][count($data['events']) - 1] : null;
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($tetra['callsign']); ?> - <?php echo h($data['title']); ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <header class="topbar">
    <div class="brand-lockup">
      <img src="assets/hamtetra-ct-logo.jpg" alt="HAMTETRA-CT Portugal">
      <div>
        <div class="brand-title">SVXLINK-CT <span>by HamTetra-CT</span></div>
        <h1><?php echo h($tetra['callsign']); ?></h1>
        <p><?php echo h($data['subtitle']); ?></p>
      </div>
    </div>
    <nav class="nav">
      <a class="active" href="index.php">Painel</a>
      <a href="sds.php">SDS</a>
      <a href="admin.php">Administração</a>
      <a href="logs.php">Registos</a>
      <a href="#hardware">Hardware</a>
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
      <div class="service-pill service-<?php echo h($service['status']); ?>">
        <span></span>
        <strong id="service-status"><?php echo h(service_status_label((string)$service['status'])); ?></strong>
      </div>
    </div>
  </header>

  <main class="layout">
    <section class="state-panel state-<?php echo h($runtime['state']); ?>" id="state-panel">
      <div class="state-copy">
        <div class="panel-title">Estado DMO</div>
        <div class="state-label" id="state-label"><?php echo h($runtime['label']); ?></div>
        <div class="state-desc" id="state-desc"><?php echo h($runtime['description']); ?></div>
      </div>
      <div class="radio-visual" aria-hidden="true">
        <div class="antenna"></div>
        <div class="radio-body">
          <span></span><span></span><span></span>
        </div>
        <div class="wave wave-a"></div>
        <div class="wave wave-b"></div>
      </div>
      <div class="state-metrics">
        <div><span>Modo</span><strong id="tetra-mode"><?php echo h($tetra['mode']); ?></strong></div>
        <div><span>GSSI</span><strong id="runtime-gssi"><?php echo h($runtime['gssi'] ?: $tetra['gssi']); ?></strong></div>
        <div><span>ISSI</span><strong><?php echo h($tetra['issi']); ?></strong></div>
        <div><span>PEI</span><strong id="runtime-pei"><?php echo h(strtoupper($runtime['pei'])); ?></strong></div>
      </div>
    </section>

    <section class="grid quick-grid" id="hardware">
      <article class="card health-card">
        <div class="panel-title">Saúde Raspberry/NUC</div>
        <div class="meter"><span>Carga</span><strong id="hardware-load"><?php echo h($hardware['load']); ?></strong></div>
        <div class="meter"><span>Temp. CPU</span><strong id="hardware-temp"><?php echo h($hardware['temp']); ?></strong></div>
        <div class="meter-bar"><span id="memory-bar" style="width: <?php echo (int)$hardware['memory']['percent']; ?>%"></span></div>
        <div class="meter-caption">Memória <span id="memory-label"><?php echo h($hardware['memory']['label']); ?></span></div>
        <div class="meter-bar disk"><span id="disk-bar" style="width: <?php echo (int)$hardware['disk_percent']; ?>%"></span></div>
        <div class="meter-caption">Disco <span id="disk-label"><?php echo h((string)$hardware['disk_percent']); ?>%</span></div>
      </article>

      <article class="card warning-card">
        <div class="panel-title">Alertas áudio/rádio</div>
        <div class="warning-count" id="warning-count"><?php echo h((string)$runtime['warnings']); ?></div>
        <p><span id="audio-clips"><?php echo h((string)$runtime['audio_clips']); ?></span> eventos de saturação de áudio nos registos recentes</p>
      </article>

      <article class="card latest-card">
        <div class="panel-title">Última mensagem rádio</div>
        <div class="latest-message" id="latest-message"><?php echo h($latestEvent['message'] ?? 'Sem actividade recente'); ?></div>
        <div class="latest-meta">
          <span id="latest-time"><?php echo h($latestEvent['time'] ?? ''); ?></span>
          <span id="latest-type"><?php echo h($latestEvent['label'] ?? ''); ?></span>
        </div>
      </article>

      <article class="card service-card">
        <div class="panel-title">Serviço SvxLink</div>
        <div class="service-large" id="service-large"><?php echo h(service_status_label((string)$service['status'])); ?></div>
        <p>Ligado há <span id="service-uptime"><?php echo h($service['uptime'] ?: 'Indisponível'); ?></span></p>
      </article>
    </section>

    <section class="grid cards">
      <article class="card">
        <div class="panel-title">MTM5400</div>
        <dl class="kv">
          <div><dt>Rádio</dt><dd><?php echo h($tetra['model']); ?></dd></div>
          <div><dt>Porta</dt><dd><?php echo h($tetra['port']); ?></dd></div>
          <div><dt>Baud</dt><dd><?php echo h($tetra['baud']); ?></dd></div>
          <div><dt>MCC/MNC</dt><dd><?php echo h($tetra['mcc'] . '/' . $tetra['mnc']); ?></dd></div>
        </dl>
      </article>

      <article class="card">
        <div class="panel-title">Áudio e PTT</div>
        <dl class="kv">
          <div><dt>RX</dt><dd><?php echo h($radio['rx_audio']); ?></dd></div>
          <div><dt>SQL</dt><dd><?php echo h($radio['sql_det']); ?></dd></div>
          <div><dt>TX</dt><dd><?php echo h($radio['tx_audio']); ?></dd></div>
          <div><dt>PTT</dt><dd><?php echo h($radio['ptt_type']); ?></dd></div>
        </dl>
      </article>

      <article class="card">
        <div class="panel-title">Ligação ao refletor</div>
        <dl class="kv">
          <div><dt>Estado</dt><dd id="reflector-status"><?php echo h(service_status_label((string)$runtime['reflector'])); ?></dd></div>
          <div><dt>Indicativo</dt><dd><?php echo h($reflector['callsign']); ?></dd></div>
          <div><dt>TG predefinido</dt><dd><?php echo h($reflector['default_tg']); ?></dd></div>
          <div><dt>TG seleccionado</dt><dd id="selected-tg"><?php echo h($runtime['selected_tg'] ?: 'Nenhum'); ?></dd></div>
        </dl>
      </article>

      <article class="card">
        <div class="panel-title">SDS e utilizadores</div>
        <dl class="kv">
          <div><dt>Utilizadores</dt><dd><?php echo h((string)$tetra['users']); ?></dd></div>
          <div><dt>Ouvidos</dt><dd id="mobiles-count"><?php echo h((string)$mobiles['count']); ?></dd></div>
          <div><dt>Estados SDS</dt><dd><?php echo h((string)$tetra['status_count']); ?></dd></div>
          <div><dt>RSSI gateway</dt><dd id="gateway-rssi"><?php echo $mobiles['gateway_rssi'] !== null ? h((string)$mobiles['gateway_rssi']) . ' dBm' : 'Indisponível'; ?></dd></div>
        </dl>
      </article>
    </section>

    <section class="grid main-grid">
      <article class="card activity-card">
        <div class="card-head">
          <div class="panel-title">Actividade DMO</div>
          <span id="last-refresh"><?php echo h(date('H:i:s')); ?></span>
        </div>
        <div class="table-wrap">
          <table class="activity-table">
            <thead>
              <tr>
                <th>Hora</th>
                <th>Tipo</th>
                <th>Estação</th>
                <th>GSSI/TG</th>
                <th>Mensagem</th>
              </tr>
            </thead>
            <tbody id="activity-body">
              <?php foreach (array_reverse(array_slice(array_reverse($data['events']), 0, 24)) as $event): ?>
                <tr class="row-<?php echo h($event['type']); ?>">
                  <td><?php echo h($event['time']); ?></td>
                  <td><span class="tag tag-<?php echo h($event['type']); ?>"><?php echo h($event['label']); ?></span></td>
                  <td><?php echo h($event['peer'] ?: $event['issi']); ?></td>
                  <td><?php echo h($event['gssi'] ?: $event['tg']); ?></td>
                  <td><?php echo h($event['message']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>

      <aside class="side-stack">
        <article class="card">
          <div class="panel-title">Sistema</div>
          <dl class="kv">
            <div><dt>Nome</dt><dd><?php echo h($hardware['hostname']); ?></dd></div>
            <div><dt>Kernel</dt><dd><?php echo h($hardware['kernel']); ?></dd></div>
            <div><dt>Arquitectura</dt><dd><?php echo h($hardware['arch']); ?></dd></div>
            <div><dt>SvxLink</dt><dd><?php echo h($service['uptime'] ?: 'Indisponível'); ?></dd></div>
          </dl>
        </article>

        <article class="card">
          <div class="card-head">
            <div class="panel-title">Terminais</div>
            <span id="mobiles-note"><?php echo h($mobiles['rssi_note']); ?></span>
          </div>
          <div class="mini-table-wrap">
            <table class="mini-table">
              <thead>
                <tr>
                  <th>ISSI</th>
                  <th>Última vez</th>
                  <th>RSSI</th>
                </tr>
              </thead>
              <tbody id="mobiles-body">
                <?php if (!$mobiles['items']): ?>
                  <tr><td colspan="3" class="empty">Sem terminais observados</td></tr>
                <?php endif; ?>
                <?php foreach ($mobiles['items'] as $mobile): ?>
                  <tr>
                    <td>
                      <strong><?php echo h($mobile['peer']); ?></strong>
                      <span><?php echo h($mobile['issi']); ?></span>
                    </td>
                    <td><?php echo h($mobile['last_seen']); ?></td>
                    <td><?php echo $mobile['rssi'] !== null ? h((string)$mobile['rssi']) . ' dBm' : 'Indisponível'; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </article>
      </aside>
    </section>

    <?php echo dashboard_footer(); ?>
  </main>

  <script>
    window.DMO_DASH = {
      refreshSeconds: <?php echo max(1, DASH_REFRESH_SECONDS); ?>
    };
  </script>
  <script src="assets/theme.js"></script>
  <script src="assets/i18n.js"></script>
  <script src="assets/app.js"></script>
</body>
</html>
