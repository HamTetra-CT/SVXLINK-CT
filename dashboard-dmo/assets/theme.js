'use strict';

function applyTheme(theme) {
  const selected = theme === 'light' ? 'light' : 'dark';
  document.documentElement.dataset.theme = selected;
  const button = document.getElementById('theme-toggle');
  if (button) {
    const label = selected === 'light' ? 'Modo noite' : 'Modo dia';
    const icon = selected === 'light' ? '☾' : '☀';
    const text = window.SVX_I18N ? window.SVX_I18N.t(label) : label;
    button.setAttribute('aria-label', text);
    button.setAttribute('title', text);
    button.innerHTML = '<span class="theme-icon" aria-hidden="true">' + icon + '</span>';
  }
}

function updateLocalTime() {
  document.querySelectorAll('[data-local-time]').forEach((el) => {
    el.textContent = new Date().toLocaleTimeString('pt-PT', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const stored = localStorage.getItem('svxlinkCtTheme') || 'dark';
  applyTheme(stored);
  const button = document.getElementById('theme-toggle');
  if (!button) return;
  button.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    localStorage.setItem('svxlinkCtTheme', next);
    applyTheme(next);
    if (window.SVX_I18N) window.SVX_I18N.translatePage();
  });
  updateLocalTime();
  setInterval(updateLocalTime, 1000);
});
