'use strict';

const cfg = window.DMO_DASH || { refreshSeconds: 2 };
const apiUrl = 'api.php?action=dashboard';

function text(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value || '';
}

function cls(el, prefix, value) {
  if (!el) return;
  Array.from(el.classList).forEach((name) => {
    if (name.startsWith(prefix)) el.classList.remove(name);
  });
  el.classList.add(prefix + value);
}

function tagClass(type) {
  return 'tag tag-' + (type || 'info');
}

function renderEvents(events) {
  const body = document.getElementById('activity-body');
  if (!body || !Array.isArray(events)) return;
  const latest = events.slice(-24);
  if (!latest.length) {
    body.innerHTML = '<tr><td colspan="5" class="empty">No events found</td></tr>';
    return;
  }
  body.innerHTML = latest.map((event) => {
    const peer = event.peer || event.issi || '';
    const group = event.gssi || event.tg || '';
    return '<tr class="row-' + esc(event.type) + '">' +
      '<td>' + esc(event.time) + '</td>' +
      '<td><span class="' + tagClass(event.type) + '">' + esc(event.label) + '</span></td>' +
      '<td>' + esc(peer) + '</td>' +
      '<td>' + esc(group) + '</td>' +
      '<td>' + esc(event.message) + '</td>' +
      '</tr>';
  }).join('');
}

function renderMobiles(mobiles) {
  const body = document.getElementById('mobiles-body');
  if (!body || !mobiles) return;

  text('mobiles-count', String(mobiles.count || 0));
  text('gateway-rssi', mobiles.gateway_rssi === null || mobiles.gateway_rssi === undefined ? 'N/A' : mobiles.gateway_rssi + ' dBm');
  text('mobiles-note', mobiles.rssi_note || '');

  const items = Array.isArray(mobiles.items) ? mobiles.items.slice(0, 12) : [];
  if (!items.length) {
    body.innerHTML = '<tr><td colspan="3" class="empty">No mobiles seen</td></tr>';
    return;
  }
  body.innerHTML = items.map((mobile) => {
    const rssi = mobile.rssi === null || mobile.rssi === undefined ? 'N/A' : mobile.rssi + ' dBm';
    return '<tr>' +
      '<td><strong>' + esc(mobile.peer || mobile.issi) + '</strong><span>' + esc(mobile.issi) + '</span></td>' +
      '<td>' + esc(mobile.last_seen || '') + '</td>' +
      '<td>' + esc(rssi) + '</td>' +
      '</tr>';
  }).join('');
}

function esc(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

async function refresh() {
  try {
    const response = await fetch(apiUrl + '&_=' + Date.now(), { cache: 'no-store' });
    if (!response.ok) return;
    const data = await response.json();
    const runtime = data.runtime || {};
    const service = data.service || {};

    const panel = document.getElementById('state-panel');
    cls(panel, 'state-', runtime.state || 'idle');

    text('state-label', runtime.label || 'IDLE');
    text('state-desc', runtime.description || 'Waiting for DMO activity');
    text('runtime-gssi', runtime.gssi || (data.tetra ? data.tetra.gssi : ''));
    text('runtime-pei', String(runtime.pei || 'unknown').toUpperCase());
    text('service-status', String(service.status || 'unknown').toUpperCase());
    text('reflector-status', String(runtime.reflector || 'unknown').toUpperCase());
    text('selected-tg', runtime.selected_tg || 'None');
    text('warning-count', String(runtime.warnings || 0));
    text('audio-clips', String(runtime.audio_clips || 0));

    const servicePill = document.querySelector('.service-pill');
    cls(servicePill, 'service-', service.status || 'unknown');

    const generated = data.generated_at ? new Date(data.generated_at) : new Date();
    text('last-refresh', generated.toLocaleTimeString('pt-PT', { hour12: false }));
    renderEvents(data.events || []);
    renderMobiles(data.mobiles || {});
  } catch (err) {
    // Keep the last visible state if the Raspberry is busy.
  }
}

document.addEventListener('DOMContentLoaded', () => {
  setInterval(refresh, Math.max(1, Number(cfg.refreshSeconds) || 2) * 1000);
});
