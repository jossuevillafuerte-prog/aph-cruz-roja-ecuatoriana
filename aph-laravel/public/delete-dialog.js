(() => {
    const dialog = document.querySelector('#delete-confirmation');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    let trigger;
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-delete-action]');
        if (!button) return;
        trigger = button;
        form.action = button.dataset.deleteAction;
        form.elements._province.value = button.dataset.province;
        form.elements.version.value = button.dataset.version;
        document.querySelector('#delete-sheet').textContent = button.dataset.sheet || 'sin número';
        dialog.showModal();
        document.querySelector('#delete-cancel').focus();
    });
    document.querySelector('#delete-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger?.focus());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
})();
