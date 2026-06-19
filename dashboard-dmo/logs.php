<?php
declare(strict_types=1);

require_once __DIR__ . '/include/functions.php';
$data = dashboard_data();
$events = array_reverse($data['events']);
$filter = strtolower(trim((string)($_GET['type'] ?? 'all')));
$query = trim((string)($_GET['q'] ?? ''));
$allEvents = $events;
$counts = [];
foreach ($allEvents as $event) {
    $type = strtolower((string)$event['type']);
    $counts[$type] = ($counts[$type] ?? 0) + 1;
}

if ($filter !== 'all') {
    $events = array_values(array_filter($events, static fn($event) => strtolower($event['type']) === $filter));
}
if ($query !== '') {
    $events = array_values(array_filter($events, static function ($event) use ($query) {
        return stripos($event['message'], $query) !== false
            || stripos($event['peer'], $query) !== false
            || stripos($event['issi'], $query) !== false
            || stripos($event['gssi'], $query) !== false
            || stripos($event['tg'], $query) !== false;
    }));
}
$events = array_slice($events, 0, 160);
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registos - <?php echo h($data['title']); ?> - <?php echo h($data['tetra']['callsign']); ?></title>
  <link rel="icon" type="image/png" href="<?php echo asset_url('assets/favicon.png'); ?>">
  <link rel="apple-touch-icon" href="<?php echo asset_url('assets/favicon.png'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/app.css'); ?>">
</head>
<body>
  <header class="topbar compact">
    <div class="brand-lockup">
      <img src="assets/hamtetra-ct-logo.jpg" alt="HAMTETRA-CT Portugal">
      <div>
        <div class="brand-title">SvxLink Dashboard TETRA <span>by HamTetra-CT</span></div>
        <h1>Registos</h1>
        <p><?php echo h($data['paths']['log']); ?></p>
      </div>
    </div>
    <nav class="nav">
      <a href="index.php">Painel</a>
      <a href="sds.php">SDS</a>
      <a href="admin.php">Administração</a>
      <a class="active" href="logs.php">Registos</a>
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
    </div>
  </header>

  <main class="layout">
    <form class="toolbar" method="get">
      <label>
        Tipo
        <select name="type">
          <?php foreach (['all', 'rx', 'tx', 'idle', 'sds', 'pei', 'rssi', 'reg', 'reflector', 'warn', 'system'] as $type): ?>
            <option value="<?php echo h($type); ?>" <?php echo $filter === $type ? 'selected' : ''; ?>><?php echo h(log_filter_label($type)); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Procurar
        <input type="search" name="q" value="<?php echo h($query); ?>" placeholder="ISSI, GSSI, TG, texto">
      </label>
      <button type="submit">Filtrar</button>
      <a class="button secondary" href="logs.php">Limpar</a>
      <span class="live-badge"><span></span> Em directo</span>
    </form>

    <section class="log-chips">
      <?php foreach (['tx', 'rx', 'sds', 'pei', 'rssi', 'warn', 'system'] as $type): ?>
        <a class="log-chip tag-<?php echo h($type); ?>" href="logs.php?type=<?php echo h($type); ?>">
          <span><?php echo h(log_filter_label($type)); ?></span>
          <strong><?php echo h((string)($counts[$type] ?? 0)); ?></strong>
        </a>
      <?php endforeach; ?>
      <div class="log-total">Total: <?php echo h((string)count($allEvents)); ?> entradas</div>
    </section>

    <section class="card activity-card">
      <div class="card-head">
        <div class="panel-title">Eventos interpretados</div>
        <span><?php echo count($events); ?> entradas</span>
      </div>
      <div class="table-wrap">
        <table class="activity-table logs-table">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Tipo</th>
              <th>ISSI</th>
              <th>GSSI/TG</th>
              <th>Mensagem</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$events): ?>
              <tr><td colspan="5" class="empty">Sem eventos encontrados</td></tr>
            <?php endif; ?>
            <?php foreach ($events as $event): ?>
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
    </section>

    <?php echo dashboard_footer(); ?>
  </main>
  <script src="<?php echo asset_url('assets/theme.js'); ?>"></script>
  <script src="<?php echo asset_url('assets/i18n.js'); ?>"></script>
</body>
</html>
