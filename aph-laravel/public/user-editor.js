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
    function openEditor(id) {
        form.action = '/users/' + encodeURIComponent(id);
        form.elements.edit_user_id.value = id;
        form.elements.password.value = '';
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
            openEditor(button.dataset.id);
        });
    });
    form.elements.role.addEventListener('change', syncRole);
    dialog.querySelectorAll('[data-close-editor]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => {form.elements.password.value = '';opener?.focus();});
    if (/^\d+$/.test(form.dataset.reopen)) openEditor(form.dataset.reopen);
})();
