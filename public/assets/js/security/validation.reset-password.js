import { regex, messages } from './validation.rules.js';
import { createFormValidator } from './validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#resetPasswordForm');
  if (!form) {return;}

  const FV = createFormValidator('#resetPasswordForm');
  if (!FV) {return;}

  const { addRules, enableLiveValidation } = FV;

  // Nouveau mot de passe
  addRules('#password', [
    { type: 'required', message: messages.required },
    { type: 'regex', value: regex.password, message: messages.password },
  ], { errorsContainer: '#password_error' });

  // Confirmation du mot de passe
  addRules('#password_confirm', [
    { type: 'required', message: messages.required },
    {
      type: 'custom',
      validator: (value, fields) => {
        const password = fields['#password']?.elem?.value || '';
        return value === password;
      },
      message: 'Les mots de passe ne correspondent pas',
    },
  ], { errorsContainer: '#password_confirm_error' });

  enableLiveValidation();
});