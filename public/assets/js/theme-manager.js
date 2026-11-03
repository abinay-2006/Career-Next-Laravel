/** Shared, independent color and appearance preferences for Career Next. */
(function () {
  'use strict';

  const COLOR_KEY = 'careernext_color_preference';
  const APPEARANCE_KEY = 'careernext_appearance_preference';
  const LEGACY_KEY = 'careernext_theme_preference';
  const COLORS = [
    { id: 'purple', name: 'Purple', color: '#667eea' },
    { id: 'blue', name: 'Blue', color: '#2563eb' },
    { id: 'green', name: 'Green', color: '#16834a' },
    { id: 'red', name: 'Red', color: '#c53030' },
    { id: 'yellow', name: 'Yellow', color: '#facc15' },
    { id: 'orange', name: 'Orange', color: '#ea580c' }
  ];
  const VALID_COLORS = new Set(COLORS.map(item => item.id));
  const VALID_APPEARANCES = new Set(['light', 'dark']);
  const root = document.documentElement;
  let initialized = false;

  function getStored(key) { try { return localStorage.getItem(key); } catch (_) { return null; } }
  function setStored(key, value) { try { localStorage.setItem(key, value); } catch (_) { /* storage may be disabled */ } }
  function readPreferences() {
    let color = getStored(COLOR_KEY);
    let appearance = getStored(APPEARANCE_KEY);
    // Migrate the prior single color key without discarding its value.
    const legacy = getStored(LEGACY_KEY);
    if (!VALID_COLORS.has(color) && VALID_COLORS.has(legacy)) color = legacy;
    if (!VALID_COLORS.has(color) && legacy === 'pink') color = 'red';
    if (!VALID_COLORS.has(color)) color = 'purple';

    // Older builds stored appearance in the legacy single preference key.
    if (!VALID_APPEARANCES.has(appearance) && VALID_APPEARANCES.has(legacy)) {
      appearance = legacy;
    }
    if (!VALID_APPEARANCES.has(appearance)) appearance = 'light';
    return { color, appearance };
  }

  function syncPickers() {
    const state = readPreferences();
    const current = COLORS.find(item => item.id === state.color) || COLORS[0];
    document.querySelectorAll('.theme-toggle').forEach(button => {
      button.setAttribute('aria-label', `Customize theme. ${current.name}, ${state.appearance} mode`);
      button.setAttribute('aria-expanded', String(button.closest('.theme-picker')?.classList.contains('is-open') || false));
      const label = button.querySelector('.theme-current-name');
      const swatch = button.querySelector('.theme-current-swatch');
      if (label) label.textContent = current.name;
      if (swatch) swatch.style.setProperty('--theme-swatch', current.color);
    });
    document.querySelectorAll('[data-color-option]').forEach(option => {
      const selected = option.dataset.colorOption === state.color;
      option.setAttribute('aria-checked', String(selected));
      option.classList.toggle('is-selected', selected);
      const check = option.querySelector('.theme-option-check');
      if (check) check.textContent = selected ? '\u2713' : '';
    });
    document.querySelectorAll('[data-appearance-option]').forEach(option => {
      const selected = option.dataset.appearanceOption === state.appearance;
      option.setAttribute('aria-checked', String(selected));
      option.classList.toggle('is-selected', selected);
    });
  }

  function apply(color, appearance, persist = true) {
    if (VALID_COLORS.has(color)) root.setAttribute('data-color', color);
    if (VALID_APPEARANCES.has(appearance)) root.setAttribute('data-appearance', appearance);
    if (persist) {
      setStored(COLOR_KEY, root.dataset.color || 'purple');
      setStored(APPEARANCE_KEY, root.dataset.appearance || 'light');
    }
    syncPickers();
    document.dispatchEvent(new CustomEvent('themeChanged', { detail: { color: root.dataset.color || 'purple', appearance: root.dataset.appearance || 'light' } }));
  }

  function makePicker(button) {
    if (button.closest('.theme-picker')) return;
    const wrapper = button.closest('.theme-toggle-wrapper') || document.createElement('div');
    wrapper.classList.add('theme-picker', 'theme-toggle-wrapper');
    if (!wrapper.contains(button)) { button.parentNode.insertBefore(wrapper, button); wrapper.appendChild(button); }
    button.type = 'button';
    // The admin drawer used a fixed-size icon-only class; the shared picker
    // needs its normal flexible width and padding after it is initialized.
    button.classList.remove('cn-admin-theme-toggle');
    button.setAttribute('aria-haspopup', 'true');
    button.innerHTML = '<span class="theme-current-swatch" aria-hidden="true"></span><span class="theme-current-name">Purple</span><span aria-hidden="true">\u2699</span>';

    const menu = document.createElement('div');
    menu.className = 'theme-palette';
    menu.setAttribute('aria-label', 'Theme customization');
    menu.innerHTML = '<button class="theme-palette-close" type="button" aria-label="Close theme selector">\u00d7</button><div class="theme-palette-heading">Color theme</div>' + COLORS.map(item => `<button class="theme-option" type="button" role="radio" aria-checked="false" data-color-option="${item.id}" style="--theme-swatch:${item.color}"><span class="theme-option-swatch" aria-hidden="true"></span><span>${item.name}</span><span class="theme-option-check" aria-hidden="true"></span></button>`).join('') + '<div class="theme-palette-heading">Appearance</div><div class="theme-mode-options" role="radiogroup" aria-label="Appearance">' + '<button class="theme-mode-option" type="button" role="radio" aria-checked="false" data-appearance-option="light"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>Light</button><button class="theme-mode-option" type="button" role="radio" aria-checked="false" data-appearance-option="dark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>Dark</button></div>';
    wrapper.appendChild(menu);
  }

  function ensurePickers(container = document) { container.querySelectorAll?.('.theme-toggle').forEach(makePicker); }
  function closePickers(restoreFocus = false) {
    document.querySelectorAll('.theme-picker.is-open').forEach(picker => {
      picker.classList.remove('is-open');
      const trigger = picker.querySelector('.theme-toggle');
      trigger?.setAttribute('aria-expanded', 'false');
      if (restoreFocus) trigger?.focus();
    });
  }
  function init() {
    if (initialized) return;
    initialized = true;
    const saved = readPreferences();
    apply(saved.color, saved.appearance, false);
    // Persist migrated preferences separately while retaining the legacy key.
    setStored(COLOR_KEY, saved.color); setStored(APPEARANCE_KEY, saved.appearance);
    ensurePickers(); syncPickers();
    document.addEventListener('click', event => {
      const trigger = event.target.closest?.('.theme-toggle');
      if (trigger) {
        event.preventDefault();
        const picker = trigger.closest('.theme-picker');
        const opening = !picker.classList.contains('is-open');
        document.querySelectorAll('.theme-picker.is-open').forEach(other => { other.classList.remove('is-open'); other.querySelector('.theme-toggle')?.setAttribute('aria-expanded', 'false'); });
        picker.classList.toggle('is-open', opening); trigger.setAttribute('aria-expanded', String(opening)); return;
      }
      const closeOption = event.target.closest?.('.theme-palette-close');
      if (closeOption) { event.preventDefault(); closePickers(true); return; }
      const colorOption = event.target.closest?.('[data-color-option]');
      const appearanceOption = event.target.closest?.('[data-appearance-option]');
      if (colorOption || appearanceOption) {
        const state = readPreferences();
        if (colorOption) state.color = colorOption.dataset.colorOption;
        if (appearanceOption) state.appearance = appearanceOption.dataset.appearanceOption;
        apply(state.color, state.appearance); return;
      }
      if (!event.target.closest?.('.theme-picker')) closePickers();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closePickers(true);
    });
    window.addEventListener('storage', event => {
      if (event.key === COLOR_KEY || event.key === APPEARANCE_KEY) {
        const latest = readPreferences();
        apply(latest.color, latest.appearance, false);
      }
    });
    if ('MutationObserver' in window && document.body) {
      new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          if (node.matches('.theme-toggle')) makePicker(node);
          ensurePickers(node);
        }
      }))).observe(document.body, { childList: true, subtree: true });
    }
  }

  window.CareerNextThemeManager = {
    init, setColor: color => apply(color, readPreferences().appearance),
    setAppearance: appearance => apply(readPreferences().color, appearance),
    getPreferences: readPreferences, getColors: () => COLORS.map(item => ({ ...item }))
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
