'use strict';

const peiState = window.DMO_PEI || {};

function byId(id) {
  return document.getElementById(id);
}

function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function statusText(id, message, ok) {
  const el = byId(id);
  if (!el) return;
  el.textContent = message || '';
  el.classList.toggle('ok', !!ok);
  el.classList.toggle('error', !!message && !ok);
}

async function postApi(action, payload) {
  const response = await fetch('api.php?action=' + encodeURIComponent(action), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    cache: 'no-store',
    body: JSON.stringify(payload || {})
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) {
    throw new Error(data.error || 'Pedido falhou');
  }
  return data;
}

function renderPeiLog(log) {
  const body = byId('pei-log-body');
  if (!body) return;
  const items = Array.isArray(log) ? log : [];
  byId('pei-log-count').textContent = String(items.length) + ' entradas';
  if (!items.length) {
    body.innerHTML = '<tr><td colspan="4" class="empty">Ainda não foram enviados comandos PEI pelo painel</td></tr>';
    return;
  }
  body.innerHTML = items.map((entry) => {
    const date = entry.time ? new Date(entry.time) : new Date();
    const time = Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('pt-PT', { hour12: false });
    return '<tr>' +
      '<td>' + escapeHtml(time) + '</td>' +
      '<td>' + escapeHtml(entry.source || 'admin') + '</td>' +
      '<td>' + escapeHtml(entry.command) + '</td>' +
      '<td><span class="tag tag-pei">' + escapeHtml(String(entry.status || 'sent').toUpperCase()) + '</span></td>' +
      '</tr>';
  }).join('');
}

async function sendCommand(command, statusId) {
  statusText(statusId, 'A enviar...', true);
  const data = await postApi('pei_send', { command });
  statusText(statusId, 'Comando enviado. Confirma a resposta no registo do SvxLink.', true);
  if (data.state) renderPeiLog(data.state.log);
}

function bindPresetButtons() {
  document.querySelectorAll('.command-preset').forEach((button) => {
    button.addEventListener('click', async () => {
      const command = button.dataset.command || '';
      byId('pei-command').value = command;
      try {
        await sendCommand(command, 'pei-command-status');
      } catch (err) {
        statusText('pei-command-status', err.message, false);
      }
    });
  });
}

function bindCommandForm() {
  const form = byId('pei-command-form');
  if (!form) return;
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await sendCommand(byId('pei-command').value, 'pei-command-status');
    } catch (err) {
      statusText('pei-command-status', err.message, false);
    }
  });
}

function bindPowerForm() {
  const form = byId('power-form');
  if (!form) return;
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    statusText('power-status', 'A aplicar...', true);
    try {
      const data = await postApi('pei_power', { dbm: Number(byId('power-dbm').value) });
      statusText('power-status', 'Comando de potência enviado.', true);
      if (data.state) renderPeiLog(data.state.log);
    } catch (err) {
      statusText('power-status', err.message, false);
    }
  });
}

function bindPasswordForm() {
  const form = byId('admin-password-form');
  if (!form) return;
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const password = byId('admin-password').value;
    const confirmation = byId('admin-password-confirm').value;
    if (password !== confirmation) {
      statusText('admin-password-status', 'As palavras-passe não coincidem.', false);
      return;
    }
    statusText('admin-password-status', 'A guardar...', true);
    try {
      await postApi('admin_password', { password });
      byId('admin-password').value = '';
      byId('admin-password-confirm').value = '';
      statusText('admin-password-status', 'Palavra-passe guardada. O navegador pode pedir novo início de sessão.', true);
    } catch (err) {
      statusText('admin-password-status', err.message, false);
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindPresetButtons();
  bindCommandForm();
  bindPowerForm();
  bindPasswordForm();
  renderPeiLog(peiState.log || []);
});
