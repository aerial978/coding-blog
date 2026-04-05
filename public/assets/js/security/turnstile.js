// public/assets/js/security/turnstile.js
(function () {
  // Permet de retrouver le bon form/message si on reste sur 1 widget par page.
  // (Si vous avez plusieurs widgets sur la même page, il faudra raffiner.)
  let lastActiveForm = null;

  function showMsg(form, text) {
    if (!form) return;
    const selector = form.getAttribute('data-turnstile-msg-selector') || '.js-turnstile-msg';
    const msgBox = form.querySelector(selector);
    if (!msgBox) return;

    msgBox.textContent = text;
    msgBox.style.display = 'block';
  }

  function hideMsg(form) {
    if (!form) return;
    const selector = form.getAttribute('data-turnstile-msg-selector') || '.js-turnstile-msg';
    const msgBox = form.querySelector(selector);
    if (!msgBox) return;

    msgBox.textContent = '';
    msgBox.style.display = 'none';
  }

  function getToken(form) {
    if (!form) return '';
    const tokenField = form.querySelector('input[name="cf-turnstile-response"]');
    const token = tokenField && typeof tokenField.value === 'string' ? tokenField.value.trim() : '';
    return token;
  }

  function isTurnstileEnabledOnForm(form) {
    // Vous activez Turnstile en mettant data-turnstile="1" sur le form
    return form && form.getAttribute('data-turnstile') === '1';
  }

  // Callbacks globaux (Turnstile les appelle via data-callback)
  window.onTurnstileSuccess = function (token) {
    if (typeof token === 'string' && token.length > 0) {
      hideMsg(lastActiveForm);
    }
  };

  window.onTurnstileExpired = function () {
    showMsg(lastActiveForm, 'Le challenge anti-robot a expiré. Veuillez le valider à nouveau.');
  };

  window.onTurnstileError = function () {
    showMsg(lastActiveForm, 'Impossible de valider le challenge anti-robot. Veuillez réessayer.');
  };

  // Init : attache le garde-fou submit à tous les formulaires concernés
  document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[data-turnstile="1"]');

    forms.forEach((form) => {
      // Si le widget n’est pas rendu (ex: turnstile_enabled=false), ne rien faire
      const hasWidget = !!form.querySelector('.js-turnstile-widget, .cf-turnstile');
      if (!hasWidget) return;

      form.addEventListener('submit', function (e) {
        lastActiveForm = form;

        if (!isTurnstileEnabledOnForm(form)) return;

        const token = getToken(form);
        if (!token) {
          e.preventDefault();
          showMsg(form, 'Veuillez valider le challenge anti-robot.');
        }
      });
    });
  });
})();
