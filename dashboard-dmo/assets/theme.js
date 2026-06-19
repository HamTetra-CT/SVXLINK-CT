'use strict';

function applyTheme(theme) {
  const selected = theme === 'light' ? 'light' : 'dark';
  document.documentElement.dataset.theme = selected;
  const button = document.getElementById('theme-toggle');
  if (button) {
    button.textContent = selected === 'light' ? 'Modo noite' : 'Modo dia';
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
