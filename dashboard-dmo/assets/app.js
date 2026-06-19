'use strict';

const cfg = window.DMO_DASH || { refreshSeconds: 5 };
const apiUrl = 'api.php?action=dashboard';
let lastEventsHtml = '';
let lastMobilesHtml = '';

function statusLabel(value) {
  const map = {
    active: 'ATIVO',
    inactive: 'INATIVO',
    failed: 'FALHA',
    connected: 'LIGADO',
    down: 'EM BAIXO',
    ready: 'PRONTO',
    unknown: 'DESCONHECIDO'
  };
  const key = String(value || 'unknown').toLowerCase();
  const label = map[key] || String(value || '').toUpperCase();
  return window.SVX_I18N ? window.SVX_I18N.t(label) : label;
}

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
    const emptyText = window.SVX_I18N ? window.SVX_I18N.t('Sem eventos encontrados') : 'Sem eventos encontrados';
    body.innerHTML = '<tr><td colspan="5" class="empty">' + esc(emptyText) + '</td></tr>';
    lastEventsHtml = body.innerHTML;
    return;
  }
  const html = latest.map((event) => {
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
  if (html !== lastEventsHtml) {
    body.innerHTML = html;
    lastEventsHtml = html;
  }
}

function renderMobiles(mobiles) {
  const body = document.getElementById('mobiles-body');
  if (!body || !mobiles) return;

  text('mobiles-count', String(mobiles.count || 0));
  const unavailable = window.SVX_I18N ? window.SVX_I18N.t('Indisponível') : 'Indisponível';
  text('gateway-rssi', mobiles.gateway_rssi === null || mobiles.gateway_rssi === undefined ? unavailable : mobiles.gateway_rssi + ' dBm');
  text('mobiles-note', mobiles.rssi_note || '');

  const items = Array.isArray(mobiles.items) ? mobiles.items.slice(0, 12) : [];
  if (!items.length) {
    const emptyText = window.SVX_I18N ? window.SVX_I18N.t('Sem terminais observados') : 'Sem terminais observados';
    body.innerHTML = '<tr><td colspan="3" class="empty">' + esc(emptyText) + '</td></tr>';
    lastMobilesHtml = body.innerHTML;
    return;
  }
  const html = items.map((mobile) => {
    const rssi = mobile.rssi === null || mobile.rssi === undefined ? unavailable : mobile.rssi + ' dBm';
    return '<tr>' +
      '<td><strong>' + esc(mobile.peer || mobile.issi) + '</strong><span>' + esc(mobile.issi) + '</span></td>' +
      '<td>' + esc(mobile.last_seen || '') + '</td>' +
      '<td>' + esc(rssi) + '</td>' +
      '</tr>';
  }).join('');
  if (html !== lastMobilesHtml) {
    body.innerHTML = html;
    lastMobilesHtml = html;
  }
}

function renderHardware(hardware, service) {
  if (!hardware) return;
  const unavailable = window.SVX_I18N ? window.SVX_I18N.t('Indisponível') : 'Indisponível';
  text('hardware-load', hardware.load || unavailable);
  text('hardware-temp', hardware.temp || unavailable);
  if (hardware.memory) {
    text('memory-label', hardware.memory.label || unavailable);
    text('memory-main', hardware.memory.label || unavailable);
    const memoryBar = document.getElementById('memory-bar');
    if (memoryBar) memoryBar.style.width = Math.max(0, Math.min(100, Number(hardware.memory.percent) || 0)) + '%';
  }
  text('disk-label', (hardware.disk_percent === undefined ? '0' : String(hardware.disk_percent)) + '%');
  const diskBar = document.getElementById('disk-bar');
  if (diskBar) diskBar.style.width = Math.max(0, Math.min(100, Number(hardware.disk_percent) || 0)) + '%';
  if (service) {
    text('service-large', statusLabel(service.status));
    text('service-uptime', service.uptime || unavailable);
  }
}

function renderLatestEvent(events) {
  if (!Array.isArray(events) || !events.length) {
    text('latest-message', window.SVX_I18N ? window.SVX_I18N.t('Sem actividade recente') : 'Sem actividade recente');
    text('latest-time', '');
    text('latest-type', '');
    return;
  }
  const event = events[events.length - 1] || {};
  text('latest-message', event.message || (window.SVX_I18N ? window.SVX_I18N.t('Sem actividade recente') : 'Sem actividade recente'));
  text('latest-time', event.time || '');
  text('latest-type', event.label || '');
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

    text('state-label', window.SVX_I18N ? window.SVX_I18N.t(runtime.label || 'EM ESPERA') : (runtime.label || 'EM ESPERA'));
    text('state-desc', window.SVX_I18N ? window.SVX_I18N.t(runtime.description || 'A aguardar actividade DMO') : (runtime.description || 'A aguardar actividade DMO'));
    text('runtime-gssi', runtime.gssi || (data.tetra ? data.tetra.gssi : ''));
    text('runtime-pei', statusLabel(runtime.pei));
    text('service-status', statusLabel(service.status));
    text('reflector-status', statusLabel(runtime.reflector));
    text('selected-tg', runtime.selected_tg || (window.SVX_I18N ? window.SVX_I18N.t('Nenhum') : 'Nenhum'));
    text('warning-count', String(runtime.warnings || 0));
    text('audio-clips', String(runtime.audio_clips || 0));
    renderHardware(data.hardware || {}, service);
    renderLatestEvent(data.events || []);

    const servicePill = document.querySelector('.service-pill');
    cls(servicePill, 'service-', service.status || 'unknown');

    const generated = data.generated_at ? new Date(data.generated_at) : new Date();
    text('last-refresh', generated.toLocaleTimeString('pt-PT', { hour12: false }));
    renderEvents(data.events || []);
    renderMobiles(data.mobiles || {});
    if (window.SVX_I18N) window.SVX_I18N.translatePage();
  } catch (err) {
    // Keep the last visible state if the Raspberry is busy.
  }
}

document.addEventListener('DOMContentLoaded', () => {
  setInterval(refresh, Math.max(1, Number(cfg.refreshSeconds) || 2) * 1000);
});
