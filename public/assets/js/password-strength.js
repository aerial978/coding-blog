(function () {
  function mount(root) {
    const input   = root.querySelector('input.form-control');
    const toggle  = root.querySelector('.toggle-password');
    const items   = root.querySelectorAll('.pw-criteria [data-check]');
    const bar     = root.querySelector('.progress-bar');
    const levelEl = root.querySelector('.pw-level');
    const ariaOut = root.querySelector('.pw-aria');
    const form    = root.closest('form');
    const submit  = form ? form.querySelector('[type="submit"]') : null;

    // Tests critère par critère (on conserve les 5)
    const tests = {
      lower:   v => /[a-z]/.test(v),
      upper:   v => /[A-Z]/.test(v),
      digit:   v => /\d/.test(v),
      special: v => /[^A-Za-z0-9]/.test(v),
      length:  v => v.length >= 12
    };

    // Seuils longueur → couleur/libellé
    function tierFromLength(len) {
      if (len >= 16) return { cls: 'bg-success', label: 'Fort'  };
      if (len >= 12) return { cls: 'bg-warning', label: 'Moyen' };
      return            { cls: 'bg-danger',  label: 'Faible' };
    }

    function update(v) {
      const value = v ?? '';
      const len = value.length;

      // 1) Met à jour les critères (couleurs des puces et compteur)
      let passed = 0;
      items.forEach(li => {
        const name = li.getAttribute('data-check');
        const ok = (tests[name] ? tests[name](value) : false);
        li.classList.toggle('text-success', ok);
        li.classList.toggle('text-danger', !ok);
        if (ok) passed++;
      });

      // 2) Jauge uniquement basée sur la longueur
      const tier = tierFromLength(len);

      // Largeur proportionnelle à la longueur (16 = 100%)
      const width = Math.min(100, Math.round((len / 16) * 100));
      bar.style.width = width + '%';
      bar.setAttribute('aria-valuenow', String(width));

      // Couleurs de la barre
      bar.classList.remove('bg-danger','bg-warning','bg-success','bg-info');
      bar.classList.add(tier.cls);

      // 3) Libellé à droite
      levelEl.textContent = tier.label;
      levelEl.classList.remove('text-danger','text-warning','text-success');
      if (tier.label === 'Fort')       levelEl.classList.add('text-success');
      else if (tier.label === 'Moyen') levelEl.classList.add('text-warning');
      else                              levelEl.classList.add('text-danger');

      // 4) ARIA + blocage submit si tu veux garder la règle stricte “5/5”
      if (ariaOut) ariaOut.textContent = `Robustesse ${tier.label}, ${passed} critères sur 5 remplis.`;
      if (submit) submit.disabled = (passed < 5); // -> pour ne pas bloquer: commente cette ligne
    }

    // Écouteurs
    input.addEventListener('input', e => update(e.target.value));

    // Toggle œil
    if (toggle) {
      toggle.addEventListener('click', () => {
        const isPwd = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPwd ? 'text' : 'password');
        toggle.setAttribute('aria-pressed', String(isPwd));
        const icon = toggle.querySelector('i');
        if (icon) {
          icon.classList.toggle('bi-eye-fill', !isPwd);
          icon.classList.toggle('bi-eye-slash-fill', isPwd);
        }
      });
    }

    // Init
    update(input.value || '');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id$="password-field"]').forEach(mount);
  });
})();
