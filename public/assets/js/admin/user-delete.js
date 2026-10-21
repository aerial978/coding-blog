'use strict';

const dialog = document.querySelector('#delete-user-dialog');
const form = document.querySelector('#delete-user-form');
const username = document.querySelector('#delete-user-username');
const email = document.querySelector('#delete-user-email');
const cancelButton = document.querySelector('#cancel-user-delete');
const deleteButtons = document.querySelectorAll('[data-delete-user-id]');

if (dialog && form && username && email && cancelButton) {
    let triggerButton = null;

    deleteButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const userId = button.dataset.deleteUserId;

            if (!userId || !/^[1-9]\d*$/.test(userId)) {
                return;
            }

            triggerButton = button;

            username.textContent = button.dataset.deleteUsername ?? '';
            email.textContent = button.dataset.deleteEmail ?? '';

            form.action = button.dataset.deleteUrl;

            dialog.showModal();
        });
    });

    cancelButton.addEventListener('click', () => {
        dialog.close();
    });

    dialog.addEventListener('close', () => {
        triggerButton?.focus();
        triggerButton = null;
    });
}