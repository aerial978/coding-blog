export function createFormValidator(formSelector, options = {}) {
  const formEl = document.querySelector(formSelector);
  if (!formEl) {
    console.warn(`[validation.factory] Form not found: ${formSelector}`);
    return null;
  }

  if (!window.JustValidate) {
    console.error('[validation.factory] JustValidate introuvable sur la page');
    return null;
  }

  const DEFAULT_MESSAGES = {
    required: 'Champ requis',
    email: 'Format d’email invalide',
    invalid: 'Valeur invalide',
  };

  const engine = new window.JustValidate(formSelector, {
    validateBeforeSubmitting: true,
    focusInvalidField: true,
    lockForm: true,
    errorFieldCssClass: 'is-invalid',
    successFieldCssClass: 'is-valid',
    errorLabelCssClass: 'jv-error-label',
    successLabelCssClass: 'jv-success-label',
    ...options,
  });

  function setSubmitting(state) {
    const submit = formEl.querySelector('[type="submit"]');
    if (submit) {
      submit.disabled = state;
      submit.setAttribute('aria-busy', state ? 'true' : 'false');
    }
  }

  function addRules(fieldSelector, rules = [], opts = {}) {
    const mapped = rules.map((r) => {
      switch (r.type) {
        case 'required':
          return { rule: 'required', errorMessage: r.message || DEFAULT_MESSAGES.required };
        case 'email':
          return { rule: 'email', errorMessage: r.message || DEFAULT_MESSAGES.email };
        case 'regex':
          return { rule: 'customRegexp', value: r.value, errorMessage: r.message || DEFAULT_MESSAGES.invalid };
        case 'custom':
          return { validator: r.validator, errorMessage: r.message || DEFAULT_MESSAGES.invalid };
        case 'async':
          return { validator: r.validator, errorMessage: r.message || DEFAULT_MESSAGES.invalid };
        default:
          console.warn(`[validation.factory] Unknown rule type: ${r.type}`);
          return null;
      }
    }).filter(Boolean);

    if (opts?.errorsContainer) {
      engine.addField(fieldSelector, mapped, { errorsContainer: opts.errorsContainer });
    } else {
      engine.addField(fieldSelector, mapped);
    }
  }

  function enableLiveValidation() {
    ['input', 'blur', 'change'].forEach((evt) => {
      formEl.addEventListener(evt, (e) => {
        const id = e.target?.id;
        if (id && typeof engine.revalidateField === 'function') {
          engine.revalidateField(`#${id}`);
        }
      }, { passive: true });
    });
  }

  engine.onSuccess((event) => {
    setSubmitting(true);
    event.target.submit();
  });
  engine.onFail?.(() => setSubmitting(false));

  return { engine, formEl, addRules, enableLiveValidation, setSubmitting };
}
