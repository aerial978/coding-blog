import { regex, messages } from './validation.rules.js';
import { createFormValidator } from './validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#registerForm');
  if (!form) {return;}

  const FV = createFormValidator('#registerForm');
  if (!FV) {return;}

  const { addRules, enableLiveValidation } = FV;

  addRules('#username', [
    { type: 'required', message: messages.required },
    { type: 'regex', value: regex.username, message: messages.username },
  ], { errorsContainer: '#username_error' });

  addRules('#email', [
    { type: 'required', message: messages.required },
    { type: 'email', message: messages.email },
  ], { errorsContainer: '#email_error' });

  //const hasPasswordErrorContainer = !!document.querySelector('#password_error');
  addRules('#password', [
  { type: 'required', message: messages.required },
  { type: 'regex', value: regex.password, message: messages.password },
], { errorsContainer: '#password_error' });

  enableLiveValidation();
});
