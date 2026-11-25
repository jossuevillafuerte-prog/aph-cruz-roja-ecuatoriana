(() => {
    const dialog = document.querySelector('#user-editor');
    if (!dialog) return;
    const form = document.querySelector('#user-edit-form');
    let opener;
    function syncRole() {
        const national = form.elements.role.value === 'superadmin';
        form.elements.province.disabled = national;
        form.elements.province.required = !national;
        if (national) form.elements.province.value = '';
    }
    function openEditor(id, updateUrl) {
        if (updateUrl) form.setAttribute('action', updateUrl);
        form.elements.edit_user_id.value = id;
        form.elements.password.value = '';
        dialog.querySelectorAll('[data-error-user-id]').forEach(error => { error.hidden = error.dataset.errorUserId !== String(id); });
        syncRole();
        dialog.showModal();
        form.elements.first_name.focus();
    }
    document.querySelectorAll('.edit-account').forEach(button => {
        button.addEventListener('click', () => {
            opener = button;
            form.elements.first_name.value = button.dataset.firstName;
            form.elements.last_name.value = button.dataset.lastName;
            form.elements.email.value = button.dataset.email;
            form.elements.role.value = button.dataset.role;
            form.elements.province.value = button.dataset.province;
            openEditor(button.dataset.id, button.dataset.updateUrl);
        });
    });
    form.elements.role.addEventListener('change', syncRole);
    dialog.querySelectorAll('[data-close-editor]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => {dialog.querySelectorAll('[data-error-user-id]').forEach(error => error.remove());form.elements.password.value = '';opener?.focus();});
    if (/^\d+$/.test(form.dataset.reopen)) openEditor(form.dataset.reopen);
})();

(() => {
    const dialog = document.querySelector('#deactivate-confirmation');
    if (!dialog) return;
    let pending = null;
    let opener = null;
    let accepted = false;
    document.querySelectorAll('form[data-deactivate]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirmed === '1') return;
        // Se detiene aquí para que ningún otro manejador envíe la desactivación sin confirmar.
        event.preventDefault();
        event.stopImmediatePropagation();
        pending = form;
        opener = event.submitter ?? form.querySelector('button');
        accepted = false;
        dialog.querySelector('#deactivate-name').textContent = form.dataset.deactivate;
        dialog.showModal();
        dialog.querySelector('[data-cancel-deactivate]').focus();
    }));
    dialog.querySelector('[data-cancel-deactivate]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-accept-deactivate]').addEventListener('click', () => {
        accepted = true;
        pending.dataset.confirmed = '1';
        dialog.close();
        pending.requestSubmit();
    });
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => { if (!accepted) opener?.focus(); });
})();
