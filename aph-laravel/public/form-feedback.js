(() => {
    'use strict';

    // Tras un envío rechazado, el resumen de errores recibe el foco salvo que un campo ya lo pida.
    const summary = document.getElementById('form-errors');
    if (summary && !document.querySelector('[autofocus]')) {
        summary.focus();
    }

    const touched = new WeakSet();

    function isChecked(field) {
        return field instanceof HTMLElement && field.id && field.willValidate && field.form?.method === 'post'
            && !['hidden', 'submit', 'button', 'checkbox', 'radio', 'search'].includes(field.type);
    }

    function messageFor(field, create) {
        let node = document.getElementById(field.id + '-error');
        if (!node && create) {
            node = document.createElement('p');
            node.className = 'field-error';
            node.id = field.id + '-error';
            (field.closest('.field') ?? field.parentElement).append(node);
        }
        return node;
    }

    function describedBy(field, id, add) {
        const ids = new Set((field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
        add ? ids.add(id) : ids.delete(id);
        ids.size ? field.setAttribute('aria-describedby', [...ids].join(' ')) : field.removeAttribute('aria-describedby');
    }

    function showError(field, text) {
        const node = messageFor(field, true);
        node.textContent = text;
        node.hidden = false;
        field.setAttribute('aria-invalid', 'true');
        describedBy(field, node.id, true);
    }

    function clearError(field) {
        const node = messageFor(field, false);
        field.removeAttribute('aria-invalid');
        if (node) {
            node.hidden = true;
            describedBy(field, node.id, false);
        }
    }

    // Un campo sin tocar no muestra errores; al corregirlo, el error desaparece de inmediato.
    function onEdit(event) {
        const field = event.target;
        if (!isChecked(field)) return;
        touched.add(field);
        if (field.getAttribute('aria-invalid') === 'true' && (field.checkValidity() || !field.validationMessage)) {
            clearError(field);
        }
    }

    document.addEventListener('input', onEdit);
    document.addEventListener('change', onEdit);
    document.addEventListener('focusout', event => {
        const field = event.target;
        if (!isChecked(field) || !touched.has(field)) return;
        if (field.checkValidity()) clearError(field);
        else showError(field, field.validationMessage);
    });
})();
