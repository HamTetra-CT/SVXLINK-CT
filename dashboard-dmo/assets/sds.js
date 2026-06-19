'use strict';

const state = window.DMO_SDS || {};

function $(id) {
  return document.getElementById(id);
}

function esc(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function setStatus(id, message, ok) {
  const el = $(id);
  if (!el) return;
  el.textContent = message || '';
  el.classList.toggle('ok', !!ok);
  el.classList.toggle('error', !!message && !ok);
}

async function api(action, payload) {
  const response = await fetch('api.php?action=' + encodeURIComponent(action), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    cache: 'no-store',
    body: JSON.stringify(payload || {})
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) {
    throw new Error(data.error || 'Request failed');
  }
  return data;
}

function fillSendForm(preset) {
  if (!$('sds-destination') || !preset) return;
  $('sds-destination').value = preset.destination || '';
  $('sds-type').value = preset.type || 'T';
  $('sds-message').value = preset.message || '';
}

function fillPresetForm(preset) {
  if (!$('preset-id') || !preset) return;
  $('preset-id').value = preset.id || '';
  $('preset-label').value = preset.label || '';
  $('preset-destination').value = preset.destination || '';
  $('preset-type').value = preset.type || 'T';
  $('preset-message').value = preset.message || '';
}

function renderPresets(presets) {
  const wrap = $('sds-presets');
  if (!wrap) return;
  const items = Array.isArray(presets) ? presets : [];
  $('sds-preset-count').textContent = String(items.length);
  if (!items.length) {
    wrap.innerHTML = '<div class="empty">No presets</div>';
    return;
  }
  wrap.innerHTML = items.map((preset) => {
    const meta = (preset.type === 'R' ? 'RAW' : 'TEXT') + ' ' + (preset.destination || 'sem destino');
    return '<button class="preset-item" type="button"' +
      ' data-id="' + esc(preset.id) + '"' +
      ' data-destination="' + esc(preset.destination) + '"' +
      ' data-type="' + esc(preset.type) + '"' +
      ' data-message="' + esc(preset.message) + '"' +
      ' data-label="' + esc(preset.label) + '">' +
      '<strong>' + esc(preset.label) + '</strong>' +
      '<span>' + esc(meta) + '</span>' +
      '</button>';
  }).join('');
  bindPresetButtons();
}

function renderLog(log) {
  const body = $('sds-log-body');
  if (!body) return;
  const items = Array.isArray(log) ? log : [];
  $('sds-log-count').textContent = String(items.length) + ' entries';
  if (!items.length) {
    body.innerHTML = '<tr><td colspan="5" class="empty">No SDS sent from dashboard yet</td></tr>';
    return;
  }
  body.innerHTML = items.map((entry) => {
    const date = entry.time ? new Date(entry.time) : new Date();
    const time = Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('pt-PT', { hour12: false });
    return '<tr>' +
      '<td>' + esc(time) + '</td>' +
      '<td><span class="tag tag-sds">' + esc(String(entry.direction || 'tx').toUpperCase()) + '</span></td>' +
      '<td>' + esc(entry.destination) + '</td>' +
      '<td>' + esc(entry.type || 'T') + '</td>' +
      '<td>' + esc(entry.message) + '</td>' +
      '</tr>';
  }).join('');
}

function bindPresetButtons() {
  document.querySelectorAll('.preset-item').forEach((button) => {
    button.addEventListener('click', () => {
      const preset = {
        id: button.dataset.id || '',
        label: button.dataset.label || button.querySelector('strong')?.textContent || '',
        destination: button.dataset.destination || '',
        type: button.dataset.type || 'T',
        message: button.dataset.message || ''
      };
      fillSendForm(preset);
      fillPresetForm(preset);
      setStatus('sds-send-status', 'Preset loaded.', true);
    });
  });
}

function bindForms() {
  const sendForm = $('sds-send-form');
  if (sendForm) {
    sendForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      setStatus('sds-send-status', 'Sending...', true);
      try {
        const data = await api('sds_send', {
          destination: $('sds-destination').value,
          type: $('sds-type').value,
          message: $('sds-message').value
        });
        setStatus('sds-send-status', 'SDS queued in TetraLogic.', true);
        if (data.state) {
          renderLog(data.state.log);
        }
      } catch (err) {
        setStatus('sds-send-status', err.message, false);
      }
    });
  }

  const clear = $('sds-clear');
  if (clear) {
    clear.addEventListener('click', () => {
      $('sds-destination').value = '';
      $('sds-message').value = '';
      setStatus('sds-send-status', '', true);
    });
  }

  const presetForm = $('sds-preset-form');
  if (presetForm) {
    presetForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      setStatus('sds-preset-status', 'Saving...', true);
      try {
        const data = await api('sds_save_preset', {
          id: $('preset-id').value,
          label: $('preset-label').value,
          destination: $('preset-destination').value,
          type: $('preset-type').value,
          message: $('preset-message').value
        });
        setStatus('sds-preset-status', 'Preset saved.', true);
        if (data.state) {
          renderPresets(data.state.presets);
        }
      } catch (err) {
        setStatus('sds-preset-status', err.message, false);
      }
    });
  }

  const del = $('preset-delete');
  if (del) {
    del.addEventListener('click', async () => {
      const id = $('preset-id').value;
      if (!id) {
        setStatus('sds-preset-status', 'Load a preset first.', false);
        return;
      }
      setStatus('sds-preset-status', 'Deleting...', true);
      try {
        const data = await api('sds_delete_preset', { id });
        fillPresetForm({ id: '', label: '', destination: '', type: 'T', message: '' });
        setStatus('sds-preset-status', 'Preset deleted.', true);
        if (data.state) {
          renderPresets(data.state.presets);
        }
      } catch (err) {
        setStatus('sds-preset-status', err.message, false);
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  bindPresetButtons();
  bindForms();
  renderLog(state.log || []);
});
