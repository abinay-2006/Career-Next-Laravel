/** Set persisted theme attributes before the page's stylesheets are painted. */
(function () {
  'use strict';

  const root = document.documentElement;
  const colors = ['purple', 'blue', 'green', 'red', 'yellow', 'orange'];
  let color = 'purple';
  let appearance = 'light';

  try {
    const legacy = localStorage.getItem('careernext_theme_preference');
    const savedColor = localStorage.getItem('careernext_color_preference');
    const savedAppearance = localStorage.getItem('careernext_appearance_preference');
    color = colors.includes(savedColor) ? savedColor : (colors.includes(legacy) ? legacy : (legacy === 'pink' ? 'red' : 'purple'));
    appearance = ['light', 'dark'].includes(savedAppearance)
      ? savedAppearance
      : (['light', 'dark'].includes(legacy) ? legacy : 'light');
  } catch (_) {
    // Storage can be disabled; the Career Next default is purple light.
  }

  root.setAttribute('data-color', color);
  root.setAttribute('data-appearance', appearance);
})();
