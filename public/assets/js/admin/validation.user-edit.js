import { regex, messages } from '../security/validation.rules.js';
import { createFormValidator } from '../security/validation.factory.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#adminUserEditForm');

  if (!form) {
    return;
  }

  const FV = createFormValidator('#adminUserEditForm');

  if (!FV) {
    return;
  }

  const { addRules, enableLiveValidation } = FV;

  addRules('#username', [
    { type: 'required', message: messages.required },
    { type: 'regex', value: regex.username, message: messages.username },
  ], { errorsContainer: '#username_error' });

  enableLiveValidation();
});