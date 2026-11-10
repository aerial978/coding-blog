import { regex, messages } from './validation.rules.js';
import { createFormValidator } from './validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#accountPasswordForm');

  if (!form) {
    return;
  }

  const FV = createFormValidator('#accountPasswordForm');

  if (!FV) {
    return;
  }

  const { addRules, enableLiveValidation, engine } = FV;

  // Current password: required only.
  addRules('#current_password', [
    { type: 'required', message: messages.required },
  ], { errorsContainer: '#current_password_error' });

  // New password: required and compliant with password policy.
  addRules('#new_password', [
    { type: 'required', message: messages.required },
    { type: 'regex', value: regex.password, message: messages.password },
  ], { errorsContainer: '#new_password_error' });

  // Password confirmation: required and identical to new password.
  addRules('#password_confirmation', [
    { type: 'required', message: messages.required },
    {
      type: 'custom',
      validator: (value, fields) => {
        const password = fields['#new_password']?.elem?.value || '';
        return value === password;
      },
      message: 'Les mots de passe ne correspondent pas',
    },
  ], { errorsContainer: '#password_confirmation_error' });

  enableLiveValidation();

  // Revalidate confirmation when the new password changes.
  form.querySelector('#new_password')?.addEventListener('input', () => {
    const confirmation = form.querySelector('#password_confirmation');

    if (confirmation?.value) {
      engine.revalidateField('#password_confirmation');
    }
  });
});
