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

  function mapRuleFactory(DEFAULT_MESSAGES) {
    // constructeurs par type de règle
    const withMsg = (fallback) => (r) => ({ errorMessage: r.message || fallback });
    const required = (r) => ({ rule: 'required', ...withMsg(DEFAULT_MESSAGES.required)(r) });
    const email    = (r) => ({ rule: 'email',    ...withMsg(DEFAULT_MESSAGES.email)(r) });
    const regex    = (r) => ({ rule: 'customRegexp', value: r.value, ...withMsg(DEFAULT_MESSAGES.invalid)(r) });
    const validator= (r) => ({ validator: r.validator, ...withMsg(DEFAULT_MESSAGES.invalid)(r) });

    // ‘custom’ et ‘async’ partagent la même forme { validator }
    const BUILDERS = {
      required,
      email,
      regex,
      custom: validator,
      async:  validator,
    };

    return (r) => {
      const builder = r && BUILDERS[r.type];
      if (!builder) {
        console.warn(`[validation.factory] Unknown rule type: ${r?.type}`);
        return null;
      }
      return builder(r);
    };
  }

  function addRules(fieldSelector, rules = [], opts = {}) {
    const mapRule = mapRuleFactory(DEFAULT_MESSAGES);

    const mapped = rules.map(mapRule).filter(Boolean);

    // Paramètre optionnel unique pour éviter un if/else
    const thirdArg = (opts && opts.errorsContainer)
      ? { errorsContainer: opts.errorsContainer }
      : undefined;

    engine.addField(fieldSelector, mapped, thirdArg);
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
