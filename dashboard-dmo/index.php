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
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($tetra['callsign']); ?> - <?php echo h($data['title']); ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <header class="topbar">
    <div>
      <div class="eyebrow"><?php echo h($data['site']); ?></div>
      <h1><?php echo h($tetra['callsign']); ?></h1>
      <p><?php echo h($data['subtitle']); ?></p>
    </div>
    <nav class="nav">
      <a class="active" href="index.php">Dashboard</a>
      <a href="logs.php">Logs</a>
    </nav>
    <div class="service-pill service-<?php echo h($service['status']); ?>">
      <span></span>
      <strong id="service-status"><?php echo h(strtoupper($service['status'])); ?></strong>
    </div>
  </header>

  <main class="layout">
    <section class="state-panel state-<?php echo h($runtime['state']); ?>" id="state-panel">
      <div class="state-copy">
        <div class="panel-title">DMO State</div>
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
        <div><span>Mode</span><strong id="tetra-mode"><?php echo h($tetra['mode']); ?></strong></div>
        <div><span>GSSI</span><strong id="runtime-gssi"><?php echo h($runtime['gssi'] ?: $tetra['gssi']); ?></strong></div>
        <div><span>ISSI</span><strong><?php echo h($tetra['issi']); ?></strong></div>
        <div><span>PEI</span><strong id="runtime-pei"><?php echo h(strtoupper($runtime['pei'])); ?></strong></div>
      </div>
    </section>

    <section class="grid cards">
      <article class="card">
        <div class="panel-title">MTM5400</div>
        <dl class="kv">
          <div><dt>Radio</dt><dd><?php echo h($tetra['model']); ?></dd></div>
          <div><dt>Port</dt><dd><?php echo h($tetra['port']); ?></dd></div>
          <div><dt>Baud</dt><dd><?php echo h($tetra['baud']); ?></dd></div>
          <div><dt>MCC/MNC</dt><dd><?php echo h($tetra['mcc'] . '/' . $tetra['mnc']); ?></dd></div>
        </dl>
      </article>

      <article class="card">
        <div class="panel-title">Audio And PTT</div>
        <dl class="kv">
          <div><dt>RX</dt><dd><?php echo h($radio['rx_audio']); ?></dd></div>
          <div><dt>SQL</dt><dd><?php echo h($radio['sql_det']); ?></dd></div>
          <div><dt>TX</dt><dd><?php echo h($radio['tx_audio']); ?></dd></div>
          <div><dt>PTT</dt><dd><?php echo h($radio['ptt_type']); ?></dd></div>
        </dl>
      </article>

      <article class="card">
        <div class="panel-title">Reflector Bridge</div>
        <dl class="kv">
          <div><dt>Status</dt><dd id="reflector-status"><?php echo h(strtoupper($runtime['reflector'])); ?></dd></div>
          <div><dt>Callsign</dt><dd><?php echo h($reflector['callsign']); ?></dd></div>
          <div><dt>Default TG</dt><dd><?php echo h($reflector['default_tg']); ?></dd></div>
          <div><dt>Selected TG</dt><dd id="selected-tg"><?php echo h($runtime['selected_tg'] ?: 'None'); ?></dd></div>
        </dl>
      </article>

      <article class="card">
        <div class="panel-title">SDS And Users</div>
        <dl class="kv">
          <div><dt>Users</dt><dd><?php echo h((string)$tetra['users']); ?></dd></div>
          <div><dt>Heard</dt><dd id="mobiles-count"><?php echo h((string)$mobiles['count']); ?></dd></div>
          <div><dt>Status SDS</dt><dd><?php echo h((string)$tetra['status_count']); ?></dd></div>
          <div><dt>Gateway RSSI</dt><dd id="gateway-rssi"><?php echo $mobiles['gateway_rssi'] !== null ? h((string)$mobiles['gateway_rssi']) . ' dBm' : 'N/A'; ?></dd></div>
        </dl>
      </article>
    </section>

    <section class="grid main-grid">
      <article class="card activity-card">
        <div class="card-head">
          <div class="panel-title">DMO Activity</div>
          <span id="last-refresh"><?php echo h(date('H:i:s')); ?></span>
        </div>
        <div class="table-wrap">
          <table class="activity-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Peer</th>
                <th>GSSI/TG</th>
                <th>Message</th>
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
          <div class="panel-title">System</div>
          <dl class="kv">
            <div><dt>Hostname</dt><dd><?php echo h($hardware['hostname']); ?></dd></div>
            <div><dt>Kernel</dt><dd><?php echo h($hardware['kernel']); ?></dd></div>
            <div><dt>Arch</dt><dd><?php echo h($hardware['arch']); ?></dd></div>
            <div><dt>SvxLink</dt><dd><?php echo h($service['uptime'] ?: 'N/A'); ?></dd></div>
          </dl>
        </article>

        <article class="card">
          <div class="panel-title">Health</div>
          <div class="meter"><span>Load</span><strong><?php echo h($hardware['load']); ?></strong></div>
          <div class="meter"><span>CPU Temp</span><strong><?php echo h($hardware['temp']); ?></strong></div>
          <div class="meter-bar"><span style="width: <?php echo (int)$hardware['memory']['percent']; ?>%"></span></div>
          <div class="meter-caption">Memory <?php echo h($hardware['memory']['label']); ?></div>
          <div class="meter-bar disk"><span style="width: <?php echo (int)$hardware['disk_percent']; ?>%"></span></div>
          <div class="meter-caption">Disk <?php echo h((string)$hardware['disk_percent']); ?>%</div>
        </article>

        <article class="card warning-card">
          <div class="panel-title">Warnings</div>
          <div class="warning-count" id="warning-count"><?php echo h((string)$runtime['warnings']); ?></div>
          <p><span id="audio-clips"><?php echo h((string)$runtime['audio_clips']); ?></span> audio clipping events in recent log window</p>
        </article>

        <article class="card">
          <div class="card-head">
            <div class="panel-title">Mobiles</div>
            <span id="mobiles-note"><?php echo h($mobiles['rssi_note']); ?></span>
          </div>
          <div class="mini-table-wrap">
            <table class="mini-table">
              <thead>
                <tr>
                  <th>ISSI</th>
                  <th>Last</th>
                  <th>RSSI</th>
                </tr>
              </thead>
              <tbody id="mobiles-body">
                <?php if (!$mobiles['items']): ?>
                  <tr><td colspan="3" class="empty">No mobiles seen</td></tr>
                <?php endif; ?>
                <?php foreach ($mobiles['items'] as $mobile): ?>
                  <tr>
                    <td>
                      <strong><?php echo h($mobile['peer']); ?></strong>
                      <span><?php echo h($mobile['issi']); ?></span>
                    </td>
                    <td><?php echo h($mobile['last_seen']); ?></td>
                    <td><?php echo $mobile['rssi'] !== null ? h((string)$mobile['rssi']) . ' dBm' : 'N/A'; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </article>
      </aside>
    </section>
  </main>

  <script>
    window.DMO_DASH = {
      refreshSeconds: <?php echo max(1, DASH_REFRESH_SECONDS); ?>
    };
  </script>
  <script src="assets/app.js"></script>
</body>
</html>
