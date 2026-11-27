(() => {
    'use strict';

    const form = document.querySelector('form[data-draft-key]');
    const steps = form ? [...form.querySelectorAll('.form-step')] : [];
    const nav = form?.querySelector('.step-nav');
    if (steps.length < 2 || !nav) return;

    const tabs = [...nav.querySelectorAll('[data-step-target]')];
    const previous = form.querySelector('.step-prev');
    const next = form.querySelector('.step-next');
    const closingStatus = form.querySelector('.closing-status');
    const closing = JSON.parse(form.dataset.closing || '{}');
    let current = 0;

    function titleOf(index) {
        return tabs[index].querySelector('strong').textContent;
    }

    function stepOf(element) {
        return steps.findIndex(step => step.contains(element));
    }

    function show(index, focusStep = false) {
        current = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach((step, position) => { step.hidden = position !== current; });
        tabs.forEach((tab, position) => { if (position !== current) tab.removeAttribute('aria-current'); else tab.setAttribute('aria-current', 'step'); });
        previous.hidden = current === 0;
        // Un solo botón principal por paso: Siguiente mientras quedan pasos, Cerrar atención en el último.
        form.querySelector('button[value="cerrar"]')?.classList.toggle('primary', current === steps.length - 1);
        form.classList.toggle('is-last-step', current === steps.length - 1);
        next.hidden = current === steps.length - 1;
        next.textContent = current < steps.length - 1 ? `Siguiente: ${titleOf(current + 1)}` : '';
        if (focusStep) {
            steps[current].scrollIntoView({block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
            steps[current].querySelector('legend')?.setAttribute('tabindex', '-1');
            steps[current].querySelector('legend')?.focus({preventScroll: true});
        }
    }

    const noDocument = ['No identificado', 'Rehusa identificarse'];
    const stateNames = {complete: 'completo', partial: 'parcial', empty: 'incompleto'};

    // Valor de un dato por su clave (f-K, f-J, opciones de un toque o dirección marcada en el mapa).
    function valueOf(key) {
        const element = document.getElementById('f-' + key);
        if (!element) return '';
        const value = element.type === 'radio' ? form.elements.namedItem(element.name).value : element.value;
        return (value ?? '').trim();
    }

    function requiredKeys() {
        return Object.keys(closing).filter(key => key !== 'N' || !noDocument.includes(valueOf('M')));
    }

    // Semáforo de cada paso según sus datos obligatorios: verde completo, amarillo parcial, rojo sin completar.
    function refresh() {
        const required = requiredKeys();
        const missing = required.filter(key => valueOf(key) === '');
        steps.forEach((step, index) => {
            const keys = required.filter(key => step.contains(document.getElementById('f-' + key)));
            const filled = keys.filter(key => valueOf(key) !== '').length;
            const invalid = step.querySelector('[aria-invalid="true"]');
            const state = !invalid && filled === keys.length ? 'complete' : filled > 0 && !invalid ? 'partial' : 'empty';
            tabs[index].dataset.state = state;
            tabs[index].querySelector('.step-state').textContent = invalid ? 'con datos por revisar' : stateNames[state];
        });
        const pending = steps.map((step, index) => index + 1).filter(number => tabs[number - 1].dataset.state !== 'complete');
        closingStatus.textContent = missing.length === 0 ? 'Lista para cerrar.'
            : missing.length <= 3 ? 'Para cerrar falta: ' + missing.map(key => closing[key]).join(', ') + '.'
            : `Para cerrar faltan ${missing.length} datos (paso${pending.length > 1 ? 's' : ''} ${pending.join(', ')}).`;
        closingStatus.classList.toggle('is-ready', missing.length === 0);
    }

    // Tocar de nuevo una opción marcada la desmarca, como dejar la lista sin seleccionar.
    form.querySelectorAll('.choice input[type="radio"]').forEach(radio => {
        let wasChecked = false;
        radio.addEventListener('pointerdown', () => { wasChecked = radio.checked; });
        radio.addEventListener('click', () => {
            if (wasChecked) {
                radio.checked = false;
                radio.dispatchEvent(new Event('change', {bubbles: true}));
            }
            wasChecked = false;
        });
    });

    tabs.forEach((tab, index) => tab.addEventListener('click', () => show(index, true)));
    previous.addEventListener('click', () => show(current - 1, true));
    next.addEventListener('click', () => show(current + 1, true));
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    // Un campo obligatorio en otro paso no puede recibir el foco si su paso está oculto.
    form.addEventListener('invalid', event => { const index = stepOf(event.target); if (index >= 0 && index !== current) show(index); }, true);
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href^="#f-"]');
        const target = link && document.getElementById(link.getAttribute('href').slice(1));
        if (target && stepOf(target) >= 0) {
            event.preventDefault();
            show(stepOf(target));
            (target.type === 'hidden' ? target.closest('.field')?.querySelector('.map-open') : target)?.focus();
        }
    });

    nav.hidden = false;
    form.querySelector('.step-legend')?.removeAttribute('hidden');
    const firstError = form.querySelector('[aria-invalid="true"]');
    show(firstError ? stepOf(firstError) : 0);
    refresh();
})();
