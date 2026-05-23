(function () {
  const TESTS = new Map([
    ['lower', (v) => /[a-z]/.test(v)],
    ['upper', (v) => /[A-Z]/.test(v)],
    ['digit', (v) => /\d/.test(v)],
    ['special', (v) => /[^A-Za-z0-9]/.test(v)],
    ['length', (v) => v.length >= 12],
  ]);

  const ALLOWED_KEYS = new Set(['lower', 'upper', 'digit', 'special', 'length']);

  Object.freeze(TESTS);

  function getElements(root) {
    const form = root.closest('form');

    return {
      input: root.querySelector('input.form-control'),
      toggle: root.querySelector('.toggle-password'),
      items: root.querySelectorAll('.pw-criteria [data-check]'),
      bar: root.querySelector('.progress-bar'),
      levelEl: root.querySelector('.pw-level'),
      ariaOut: root.querySelector('.pw-aria'),
      submit: form ? form.querySelector('[type="submit"]') : null,
    };
  }

  function hasRequiredElements(elements) {
    return Boolean(
      elements.input
      && elements.items.length
      && elements.bar
      && elements.levelEl,
    );
  }

  function tierFromLength(len) {
    if (len >= 16) {
      return { cls: 'bg-success', label: 'Fort' };
    }

    if (len >= 12) {
      return { cls: 'bg-warning', label: 'Moyen' };
    }

    return { cls: 'bg-danger', label: 'Faible' };
  }

  function countPassedCriteria(items, value) {
    let passed = 0;

    items.forEach((li) => {
      const nameAttr = li.getAttribute('data-check');
      const name = typeof nameAttr === 'string' ? nameAttr.trim() : '';
      const fn = ALLOWED_KEYS.has(name) ? TESTS.get(name) : undefined;
      const ok = typeof fn === 'function' ? Boolean(fn(value)) : false;

      li.classList.toggle('text-success', ok);
      li.classList.toggle('text-danger', !ok);

      if (ok) {
        passed++;
      }
    });

    return passed;
  }

  function updateProgressBar(bar, len, tier) {
    const width = Math.min(100, Math.round((len / 16) * 100));

    bar.style.width = width + '%';
    bar.setAttribute('aria-valuenow', String(width));
    bar.classList.remove('bg-danger', 'bg-warning', 'bg-success', 'bg-info');
    bar.classList.add(tier.cls);
  }

  function updateLevelLabel(levelEl, label) {
    levelEl.textContent = label;
    levelEl.classList.remove('text-danger', 'text-warning', 'text-success');

    if (label === 'Fort') {
      levelEl.classList.add('text-success');
      return;
    }

    if (label === 'Moyen') {
      levelEl.classList.add('text-warning');
      return;
    }

    levelEl.classList.add('text-danger');
  }

  function updateAriaOutput(ariaOut, tier, passed) {
    if (!ariaOut) {
      return;
    }

    ariaOut.textContent = `Robustesse ${tier.label}, ${passed} critères sur 5 remplis.`;
  }

  function updateSubmitState(submit, passed) {
    if (!submit) {
      return;
    }

    const shouldDisable = passed < 5;

    submit.disabled = shouldDisable;
    submit.setAttribute('aria-disabled', String(shouldDisable));
  }

  function update(elements, value) {
    const safeValue = value ?? '';
    const len = safeValue.length;
    const passed = countPassedCriteria(elements.items, safeValue);
    const tier = tierFromLength(len);

    updateProgressBar(elements.bar, len, tier);
    updateLevelLabel(elements.levelEl, tier.label);
    updateAriaOutput(elements.ariaOut, tier, passed);
    updateSubmitState(elements.submit, passed);
  }

  function togglePasswordVisibility(input, toggle) {
    const isPwd = input.getAttribute('type') === 'password';

    input.setAttribute('type', isPwd ? 'text' : 'password');
    toggle.setAttribute('aria-pressed', String(isPwd));

    const icon = toggle.querySelector('i');

    if (!icon) {
      return;
    }

    icon.classList.toggle('bi-eye-fill', !isPwd);
    icon.classList.toggle('bi-eye-slash-fill', isPwd);
  }

  function bindEvents(elements) {
    elements.input.addEventListener('input', (event) => {
      update(elements, event.target.value);
    });

    if (!elements.toggle) {
      return;
    }

    elements.toggle.addEventListener('click', () => {
      togglePasswordVisibility(elements.input, elements.toggle);
    });
  }

  function mount(root) {
    const elements = getElements(root);

    if (!hasRequiredElements(elements)) {
      console.warn('[password-strength] Structure incomplète pour', root);
      return;
    }

    bindEvents(elements);
    update(elements, elements.input.value || '');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id$="password-field"]').forEach(mount);
  });
})();