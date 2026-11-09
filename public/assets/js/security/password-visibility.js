document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#accountPasswordForm');

  if (!form) {
    return;
  }

  const toggles = form.querySelectorAll('[data-password-toggle]');

  toggles.forEach((toggle) => {
    const inputId = toggle.getAttribute('aria-controls');
    const input = inputId ? document.getElementById(inputId) : null;

    if (!input || input.type !== 'password') {
      return;
    }

    toggle.addEventListener('click', () => {
      const isVisible = input.type === 'text';

      input.type = isVisible ? 'password' : 'text';

      toggle.setAttribute('aria-pressed', String(!isVisible));
      toggle.setAttribute(
        'aria-label',
        isVisible ? 'Afficher le mot de passe' : 'Masquer le mot de passe',
      );

      const icon = toggle.querySelector('i');

      if (icon) {
        icon.classList.toggle('bi-eye-fill', isVisible);
        icon.classList.toggle('bi-eye-slash-fill', !isVisible);
      }
    });
  });
});
