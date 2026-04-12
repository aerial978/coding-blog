import { messages } from './validation.rules.js';
import { createFormValidator } from './validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#loginForm');
  if (!form) {return;}

  const FV = createFormValidator('#loginForm');
  if (!FV) {return;}

  const { addRules, enableLiveValidation } = FV;

  // Identifier (email ou username) → requis uniquement
  addRules('#identifier', [
    { type: 'required', message: messages.required },
  ], { errorsContainer: '#identifier_error' });

  // Password → requis uniquement
  addRules('#password', [
    { type: 'required', message: messages.required },
  ], { errorsContainer: '#password_error' });

  enableLiveValidation();
});