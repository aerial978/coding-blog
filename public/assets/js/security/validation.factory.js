const DEFAULT_MESSAGES = {
  required: 'Champ requis',
  email: 'Format d’email invalide',
  invalid: 'Valeur invalide',
};

function findForm(formSelector) {
  const formEl = document.querySelector(formSelector);

  if (!formEl) {
    console.warn(`[validation.factory] Form not found: ${formSelector}`);
    return null;
  }

  return formEl;
}

function hasJustValidate() {
  if (window.JustValidate) {
    return true;
  }

  console.error('[validation.factory] JustValidate introuvable sur la page');
  return false;
}

function createEngine(formSelector, options) {
  return new window.JustValidate(formSelector, {
    validateBeforeSubmitting: true,
    focusInvalidField: true,
    lockForm: true,
    errorFieldCssClass: 'is-invalid',
    successFieldCssClass: 'is-valid',
    errorLabelCssClass: 'jv-error-label',
    successLabelCssClass: 'jv-success-label',
    ...options,
  });
}

function setSubmitState(formEl, state) {
  const submit = formEl.querySelector('[type="submit"]');

  if (!submit) {
    return;
  }

  submit.disabled = state;
  submit.setAttribute('aria-busy', state ? 'true' : 'false');
}

function withMessage(fallback) {
  return (rule) => ({
    errorMessage: rule.message || fallback,
  });
}

function buildValidatorRule(rule) {
  return {
    validator: rule.validator,
    ...withMessage(DEFAULT_MESSAGES.invalid)(rule),
  };
}

function createRuleBuilders() {
  return {
    required: (rule) => ({
      rule: 'required',
      ...withMessage(DEFAULT_MESSAGES.required)(rule),
    }),
    email: (rule) => ({
      rule: 'email',
      ...withMessage(DEFAULT_MESSAGES.email)(rule),
    }),
    regex: (rule) => ({
      rule: 'customRegexp',
      value: rule.value,
      ...withMessage(DEFAULT_MESSAGES.invalid)(rule),
    }),
    custom: buildValidatorRule,
    async: buildValidatorRule,
  };
}

function mapRule(rule) {
  const builders = createRuleBuilders();
  const builder = rule ? builders[rule.type] : null;

  if (!builder) {
    console.warn(`[validation.factory] Unknown rule type: ${rule?.type}`);
    return null;
  }

  return builder(rule);
}

function addRulesToEngine(engine, fieldSelector, rules = [], opts = {}) {
  const mapped = rules.map(mapRule).filter(Boolean);
  const thirdArg = opts.errorsContainer
    ? { errorsContainer: opts.errorsContainer }
    : undefined;

  engine.addField(fieldSelector, mapped, thirdArg);
}

function revalidateTargetField(engine, event) {
  const id = event.target?.id;

  if (id && typeof engine.revalidateField === 'function') {
    engine.revalidateField(`#${id}`);
  }
}

function enableLiveValidationFor(formEl, engine) {
  ['input', 'blur', 'change'].forEach((eventName) => {
    formEl.addEventListener(eventName, (event) => {
      revalidateTargetField(engine, event);
    }, { passive: true });
  });
}

function bindSubmitLifecycle(engine, formEl) {
  engine.onSuccess((event) => {
    setSubmitState(formEl, true);
    event.target.submit();
  });

  engine.onFail?.(() => {
    setSubmitState(formEl, false);
  });
}

export function createFormValidator(formSelector, options = {}) {
  const formEl = findForm(formSelector);

  if (!formEl || !hasJustValidate()) {
    return null;
  }

  const engine = createEngine(formSelector, options);

  bindSubmitLifecycle(engine, formEl);

  return {
    engine,
    formEl,
    addRules(fieldSelector, rules = [], opts = {}) {
      addRulesToEngine(engine, fieldSelector, rules, opts);
    },
    enableLiveValidation() {
      enableLiveValidationFor(formEl, engine);
    },
    setSubmitting(state) {
      setSubmitState(formEl, state);
    },
  };
}