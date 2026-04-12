import { messages } from './validation.rules.js';
import { createFormValidator } from './validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#forgotPasswordForm');
  if (!form) {return;}

  const FV = createFormValidator('#forgotPasswordForm');
  if (!FV) {return;}

  const { addRules, enableLiveValidation } = FV;

  addRules('#identifier', [
    { type: 'required', message: messages.required },
  ], { errorsContainer: '#identifier_error' });

  enableLiveValidation();
});