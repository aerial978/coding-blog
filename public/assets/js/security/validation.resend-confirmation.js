import { messages } from './validation.rules.js';
import { createFormValidator } from './validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#resendConfirmForm');
  if (!form) return;

  const FV = createFormValidator('#resendConfirmForm');
  if (!FV) return;

  const { addRules, enableLiveValidation } = FV;

  addRules('#email', [
    { type: 'required', message: messages.required },
    { type: 'email',    message: messages.email },
  ], { errorsContainer: '#email_error' });

  enableLiveValidation();
});
