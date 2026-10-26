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

  const { enableLiveValidation } = FV;

  enableLiveValidation();
});
