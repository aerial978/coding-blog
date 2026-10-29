
import { regex, messages } from './validation.rules.js';
import { createFormValidator } from './validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#accountUsernameForm');
  if (!form) { return; }

  const FV = createFormValidator('#accountUsernameForm');
  if (!FV) { return; }

  const { addRules, enableLiveValidation } = FV;

  addRules('#username', [
    { type: 'required', message: messages.required },
    { type: 'regex', value: regex.username, message: messages.username },
  ], { errorsContainer: '#username_error' });

  enableLiveValidation();
});
