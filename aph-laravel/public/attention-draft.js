(() => {
    'use strict';

    const form = document.querySelector('form[data-draft-key]');
    if (!form) return;

    const key = form.dataset.draftKey;
    const version = form.dataset.draftVersion ?? '0';
    const status = document.createElement('p');
    status.className = 'draft-status';
    status.setAttribute('role', 'status');
    form.querySelector('.actions')?.before(status);
    const skipped = new Set(['_token', '_province', 'version', 'solicitud']);
    let timer = null;
    let inFlight = false;
    let dirty = false;

    function fields() {
        const values = {};
        for (const element of form.elements) {
            if (!element.name || skipped.has(element.name) || element.disabled || ['submit', 'button', 'file', 'password'].includes(element.type)) continue;
            if ((element.type === 'checkbox' || element.type === 'radio') && !element.checked) continue;
            values[element.name] = element.value;
        }
        return values;
    }

    async function request(method, body) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch(method === 'DELETE' ? '/drafts/discard' : '/drafts', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify(body),
            signal: AbortSignal.timeout(8000),
        });
        if (!response.ok) throw new Error(String(response.status));
        return response.json();
    }

    async function save() {
        if (inFlight) { timer = setTimeout(save, 1000); return; }
        inFlight = true;
        dirty = false;
        try {
            const result = await request('POST', {key, version, fields: fields()});
            status.textContent = `Borrador guardado a las ${result.saved_at}. Se borra al guardar la ficha.`;
        } catch {
            dirty = true;
            status.textContent = 'No se pudo guardar el borrador. Tus datos siguen en pantalla.';
        } finally {
            inFlight = false;
        }
    }

    function schedule() {
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(save, 3000);
    }

    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('submit', () => clearTimeout(timer));
    document.addEventListener('visibilitychange', () => { if (document.hidden && dirty) save(); });

    // Oferta para recuperar un borrador previo; nunca se aplica sin que la persona lo pida.
    const saved = form.dataset.draft ? JSON.parse(form.dataset.draft) : null;
    if (!saved) return;
    const notice = document.createElement('div');
    notice.className = 'notice draft-offer';
    notice.setAttribute('role', 'region');
    notice.setAttribute('aria-label', 'Borrador disponible');
    const text = document.createElement('p');
    const changed = Number(saved.version) !== Number(version);
    text.textContent = `Hay un borrador guardado el ${saved.saved_at}.` + (changed ? ' La ficha cambió después: revisa los datos antes de guardar.' : '');
    const restore = document.createElement('button');
    restore.type = 'button';
    restore.className = 'primary';
    restore.textContent = 'Restaurar borrador';
    const discard = document.createElement('button');
    discard.type = 'button';
    discard.textContent = 'Descartar borrador';
    const actions = document.createElement('div');
    actions.className = 'actions';
    actions.append(restore, discard);
    notice.append(text, actions);
    form.before(notice);

    restore.addEventListener('click', () => {
        for (const [name, value] of Object.entries(saved.fields)) {
            const element = form.elements.namedItem(name);
            if (!element) continue;
            if (element instanceof RadioNodeList) {
                element.value = value;
                element[0]?.dispatchEvent(new Event('change', {bubbles: true}));
                continue;
            }
            if (element.type === 'checkbox') element.checked = true;
            else element.value = value;
            element.dispatchEvent(new Event('change', {bubbles: true}));
        }
        notice.remove();
        status.textContent = 'Borrador restaurado. Revisa los datos y guarda la ficha.';
        form.querySelector('input:not([type=hidden]), select, textarea')?.focus();
    });
    discard.addEventListener('click', async () => {
        try {
            await request('DELETE', {key});
            notice.remove();
            status.textContent = 'Borrador descartado.';
        } catch {
            text.textContent = 'No se pudo descartar el borrador. Inténtalo de nuevo.';
        }
    });
})();
