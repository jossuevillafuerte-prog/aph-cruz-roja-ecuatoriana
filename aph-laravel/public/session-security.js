(() => {
    'use strict';

    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (!tokenMeta?.content) return;

    const pageUser = document.querySelector('meta[name="session-user"]')?.content ?? '';
    const warningTime = 120000;
    const requestTimeout = 8000;
    const vaultLifetime = 30 * 60000;
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('aph-session') : null;
    let idle = Date.now() + 600000;
    let absolute = Date.now() + 28800000;
    let lastSent = 0;
    let pending = null;
    let absoluteNoticeSeen = false;
    let vault = null;
    let vaultExpires = 0;
    let panelStatus = null;
    let announced = false;

    const dialog = document.createElement('dialog');
    const dialogTitle = element('h2', 'Tu sesión está por vencer');
    const dialogText = element('p');
    const countdown = element('p');
    const dialogStatus = element('p');
    const dialogButton = element('button', 'Continuar trabajando');
    dialogTitle.id = 'session-warning-title';
    dialog.setAttribute('aria-labelledby', dialogTitle.id);
    dialogStatus.setAttribute('role', 'status');
    dialogButton.type = 'button';
    dialog.append(dialogTitle, dialogText, countdown, dialogStatus, dialogButton);
    document.body.append(dialog);

    function element(tag, text = '', className = '') {
        const node = document.createElement(tag);
        node.textContent = text;
        if (className) node.className = className;
        return node;
    }

    function hardLock() {
        vault = null;
        window.aphSessionLeaving = true;
        document.body.replaceChildren();
        location.replace('/login');
    }

    function fieldChanged(field) {
        if (!field.name || field.disabled || ['hidden', 'password', 'submit', 'button', 'file'].includes(field.type)) return false;
        if (field.type === 'checkbox' || field.type === 'radio') return field.checked !== field.defaultChecked;
        if (field instanceof HTMLSelectElement) {
            const initial = [...field.options].findLastIndex(option => option.defaultSelected);
            return field.selectedIndex !== Math.max(0, initial);
        }
        return field.value !== field.defaultValue;
    }

    function hasUnsavedChanges() {
        const forms = [...document.querySelectorAll('form')].filter(form => form.method === 'post');
        return forms.some(form => [...form.elements].some(fieldChanged)) || document.querySelector('main form[method="post"]') !== null && document.querySelector('main .errors') !== null;
    }

    function softLock(message) {
        if (vault) {
            panelStatus.textContent = message;
            return;
        }
        document.querySelectorAll('dialog[open]').forEach(openDialog => openDialog.close());
        document.querySelectorAll('input[type="password"]').forEach(field => { field.value = ''; });
        // Los datos salen del documento, pero se conservan en memoria para no perder la ficha.
        vault = document.createDocumentFragment();
        vault.append(...document.body.childNodes);
        vaultExpires = Date.now() + vaultLifetime;

        const main = element('main', '', 'login');
        const card = element('div', '', 'card');
        const actions = element('div', '', 'actions');
        const login = element('a', 'Iniciar sesión en otra pestaña', 'button primary');
        const resume = element('button', 'Reanudar');
        const discard = element('button', 'Descartar cambios', 'danger');
        panelStatus = element('p', message);
        panelStatus.setAttribute('role', 'alert');
        login.href = '/login';
        login.target = '_blank';
        login.rel = 'noopener';
        resume.type = discard.type = 'button';
        resume.addEventListener('click', () => tryResume());
        discard.addEventListener('click', hardLock);
        actions.append(login, resume, discard);
        card.append(element('h1', 'Trabajo en pausa'), panelStatus,
            element('p', 'Tus cambios no se han enviado. Se conservan ocultos en esta pestaña durante 30 minutos: no la cierres ni la recargues. Ingresa con el mismo usuario para reanudar.'), actions);
        main.append(card);
        document.body.append(main);
        document.documentElement.style.visibility = '';
        resume.focus();
    }

    function expire(message = 'La sesión venció.') {
        if (vault || hasUnsavedChanges()) softLock(message);
        else hardLock();
    }

    async function tryResume() {
        panelStatus.textContent = 'Comprobando la sesión…';
        const result = await sync();
        if (!vault) return;
        if (result === 'expired') softLock('La sesión sigue cerrada. Ingresa en otra pestaña y vuelve aquí.');
        if (result === 'error') softLock('No se pudo comprobar la sesión. Revisa la conexión y pulsa Reanudar.');
        if (result === 'ok') {
            const content = vault;
            vault = null;
            document.body.replaceChildren(content);
            const notice = element('p', 'Sesión reanudada. Revisa los datos y guarda los cambios.', 'notice');
            notice.setAttribute('role', 'status');
            (document.querySelector('main') ?? document.body).prepend(notice);
        }
    }

    function applyStatus(data) {
        if (pageUser && String(data.user) !== pageUser) {
            hardLock();
            return 'expired';
        }
        idle = Date.now() + (data.idle_until - data.server_time) * 1000;
        absolute = Date.now() + (data.absolute_until - data.server_time) * 1000;
        tokenMeta.content = data.csrf_token;
        [document, vault].forEach(root => root?.querySelectorAll('input[name="_token"]').forEach(field => { field.value = data.csrf_token; }));
        return 'ok';
    }

    async function request(activity) {
        try {
            const response = await fetch(activity ? '/session/activity' : '/session/status', {
                method: activity ? 'POST' : 'GET',
                headers: {Accept: 'application/json', 'X-CSRF-TOKEN': tokenMeta.content},
                cache: 'no-store',
                signal: AbortSignal.timeout(requestTimeout),
            });
            if (response.status === 401) return 'expired';
            // Un token renovado en otra pestaña no significa que la sesión haya terminado.
            if (response.status === 419 && activity) {
                const refreshed = await request(false);
                return refreshed === 'ok' ? request(true) : refreshed;
            }
            if (!response.ok) return 'error';
            return applyStatus(await response.json());
        } catch {
            return 'error';
        }
    }

    async function sync(activity = false) {
        if (pending) {
            const result = await pending;
            if (!activity) return result;
        }
        pending = request(activity);
        const result = await pending;
        pending = null;
        if (result !== 'ok') return result;

        document.documentElement.style.visibility = '';
        if (activity) {
            lastSent = Date.now();
            channel?.postMessage({idle});
        }
        if (!announced && !vault) {
            announced = true;
            channel?.postMessage('login');
        }
        return result;
    }

    async function check(activity = false) {
        const result = await sync(activity);
        if (result === 'expired' && !vault) {
            channel?.postMessage('expired');
            expire();
        }
        return result;
    }

    function onActivity(event) {
        if (!event.isTrusted || document.hidden || dialog.open || vault) return;
        if (Date.now() - lastSent > 30000) {
            lastSent = Date.now();
            check(true);
        }
    }

    async function continueWorking() {
        if (absolute - Date.now() <= warningTime) {
            absoluteNoticeSeen = true;
            dialog.close();
            return;
        }
        dialogStatus.textContent = 'Renovando la sesión…';
        const result = await check(true);
        if (result === 'ok') dialog.close();
        if (result === 'error') dialogStatus.textContent = 'No se pudo renovar la sesión. Revisa la conexión y vuelve a pulsar Continuar trabajando.';
    }

    function showWarning(remaining) {
        const byAbsoluteLimit = absolute - Date.now() <= warningTime;
        if (byAbsoluteLimit && absoluteNoticeSeen) return;
        if (!dialog.open) {
            dialogStatus.textContent = '';
            dialog.showModal();
        }
        dialogText.textContent = byAbsoluteLimit
            ? 'La sesión termina al cumplir 8 horas y no se puede ampliar. Guarda tus cambios ahora.'
            : 'Continúa si todavía estás trabajando. Al vencer el tiempo se ocultarán los datos.';
        dialogButton.textContent = byAbsoluteLimit ? 'Volver y guardar' : 'Continuar trabajando';
        countdown.textContent = `Tiempo restante: ${Math.ceil(remaining / 1000)} segundos.`;
    }

    function formPath(form) {
        return new URL(form.getAttribute('action') || location.href, location.href).pathname;
    }

    function showFormError(form, message) {
        let error = form.querySelector('[data-connection-error]');
        if (!error) {
            error = element('p', '', 'errors');
            error.dataset.connectionError = '';
            error.setAttribute('role', 'alert');
            form.append(error);
        }
        error.textContent = message;
    }

    const checkedForms = new WeakSet();
    const formsInCheck = new WeakSet();
    async function guardSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method !== 'post') return;
        if (['/logout', '/security/revoke'].includes(formPath(form))) {
            channel?.postMessage('logout');
            return;
        }
        if (checkedForms.delete(form)) return;
        event.preventDefault();
        if (formsInCheck.has(form)) return;
        formsInCheck.add(form);
        const submitter = event.submitter;
        const result = await sync();
        formsInCheck.delete(form);
        if (result === 'ok') {
            form.querySelector('[data-connection-error]')?.remove();
            checkedForms.add(form);
            form.requestSubmit(submitter);
        } else if (result === 'expired') {
            softLock('La sesión venció antes de guardar.');
        } else {
            showFormError(form, 'No se envió: no hay conexión con el servidor. Tus datos siguen aquí; vuelve a intentarlo.');
        }
    }

    channel?.addEventListener('message', event => {
        if (event.data === 'logout') hardLock();
        else if (event.data === 'expired') check();
        else if (event.data === 'login' && vault) tryResume();
        else if (event.data?.idle) idle = event.data.idle;
    });
    ['keydown', 'pointerdown', 'input', 'wheel'].forEach(name => document.addEventListener(name, onActivity, {passive: true}));
    dialog.addEventListener('cancel', event => event.preventDefault());
    dialogButton.addEventListener('click', continueWorking);
    document.addEventListener('submit', guardSubmit);
    window.addEventListener('pagehide', () => { document.documentElement.style.visibility = 'hidden'; });
    window.addEventListener('pageshow', async event => {
        const result = await check();
        if (event.persisted && result === 'error') softLock('No se pudo comprobar la sesión. Revisa la conexión y pulsa Reanudar.');
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        if (vault) tryResume();
        else check();
    });
    setInterval(() => {
        if (vault) {
            if (Date.now() > vaultExpires) hardLock();
            return;
        }
        const remaining = Math.min(idle, absolute) - Date.now();
        if (remaining <= 0) expire();
        else if (remaining <= warningTime) showWarning(remaining);
        else if (dialog.open) dialog.close();
    }, 1000);
    setInterval(() => { if (!vault) check(); }, 30000);
})();
