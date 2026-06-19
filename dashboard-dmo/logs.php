<?php
declare(strict_types=1);

require_once __DIR__ . '/include/functions.php';
$data = dashboard_data();
$events = array_reverse($data['events']);
$filter = strtolower(trim((string)($_GET['type'] ?? 'all')));
$query = trim((string)($_GET['q'] ?? ''));

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
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Logs - <?php echo h($data['tetra']['callsign']); ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <header class="topbar compact">
    <div>
      <div class="eyebrow"><?php echo h($data['site']); ?></div>
      <h1>Logs</h1>
      <p><?php echo h($data['paths']['log']); ?></p>
    </div>
    <nav class="nav">
      <a href="index.php">Dashboard</a>
      <a class="active" href="logs.php">Logs</a>
    </nav>
  </header>

  <main class="layout">
    <form class="toolbar" method="get">
      <label>
        Type
        <select name="type">
          <?php foreach (['all', 'rx', 'tx', 'idle', 'sds', 'pei', 'rssi', 'reg', 'reflector', 'warn', 'system'] as $type): ?>
            <option value="<?php echo h($type); ?>" <?php echo $filter === $type ? 'selected' : ''; ?>><?php echo h(strtoupper($type)); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Search
        <input type="search" name="q" value="<?php echo h($query); ?>" placeholder="ISSI, GSSI, TG, text">
      </label>
      <button type="submit">Filter</button>
      <a class="button secondary" href="logs.php">Reset</a>
    </form>

    <section class="card activity-card">
      <div class="card-head">
        <div class="panel-title">Parsed Events</div>
        <span><?php echo count($events); ?> entries</span>
      </div>
      <div class="table-wrap">
        <table class="activity-table logs-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Type</th>
              <th>ISSI</th>
              <th>GSSI/TG</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$events): ?>
              <tr><td colspan="5" class="empty">No events found</td></tr>
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
  </main>
</body>
</html>
