const search = document.querySelector('#cie-search');


// El encabezado queda fijo arriba: su alto real ubica debajo la barra de pasos de la ficha.
const appHeader = document.querySelector('.app-header');
if (appHeader) {
    const setHeaderHeight = () => document.documentElement.style.setProperty('--app-header-height', appHeader.offsetHeight + 'px');
    new ResizeObserver(setHeaderHeight).observe(appHeader);
    setHeaderHeight();
}

const menu = document.querySelector('#app-menu');
if (menu) {
    const open = document.querySelector('#menu-open');
    open.addEventListener('click', () => {
        menu.showModal();
        open.setAttribute('aria-expanded', 'true');
    });
    document.querySelector('#menu-close').addEventListener('click', () => menu.close());
    menu.addEventListener('click', event => { if (event.target === menu) menu.close(); });
    menu.addEventListener('close', () => {
        open.setAttribute('aria-expanded', 'false');
        open.focus();
    });
}

const distance = document.querySelector('#km-recorridos');
if (distance) {
    const departure = document.querySelector('#f-Z');
    const arrival = document.querySelector('#f-AA');
    const numberFormat = new Intl.NumberFormat('es-EC', {maximumSignificantDigits: 12});
    function updateDistance() {
        const start = departure.valueAsNumber;
        const end = arrival.valueAsNumber;
        if (!Number.isFinite(start) || !Number.isFinite(end) || start < 0 || end < 0) {
            distance.value = '—';
        } else if (end < start) {
            distance.value = 'Revisa los kilometrajes';
        } else {
            distance.value = numberFormat.format(end - start) + ' km';
        }
    }
    departure.addEventListener('input', updateDistance);
    arrival.addEventListener('input', updateDistance);
    updateDistance();
}

if (search) {
    const picker = document.querySelector('#cie-picker');
    const previous = document.querySelector('#cie-prev');
    const next = document.querySelector('#cie-next');
    const code = document.querySelector('#f-U');
    const description = document.querySelector('#cie-description');
    const results = document.querySelector('#cie-results');
    const status = document.querySelector('#cie-status');
    let timer;
    let request;
    let page = 1;

    code.addEventListener('input', () => { description.textContent = ''; });
    function load() {
        clearTimeout(timer);
        request?.abort();
        results.replaceChildren();
        const term = search.value.trim();
        status.textContent = 'Cargando códigos…';
        previous.disabled = next.disabled = true;

        timer = setTimeout(async () => {
            request = new AbortController();
            try {
                const zone = picker.dataset.zone ?? '';
                const response = await fetch('?vista=cie&pagina=' + page + '&q=' + encodeURIComponent(term) + (zone ? '&zona=' + encodeURIComponent(zone) : ''), {signal: request.signal});
                if (!response.ok) throw new Error('Búsqueda no disponible');
                const {items, total} = await response.json();
                const first = (page - 1) * 50 + 1;
                status.textContent = (total ? `${first}–${first + items.length - 1} de ${total} códigos` : 'Sin coincidencias') + (zone ? ` en ${zone}` : '');
                previous.disabled = page === 1;
                next.disabled = page * 50 >= total;
                for (const entry of items) {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.textContent = entry.visible + ' · ' + entry.descripcion;
                    option.addEventListener('click', () => {
                        code.value = entry.visible;
                        code.dispatchEvent(new Event('change', {bubbles: true}));
                        description.textContent = entry.descripcion;
                        picker.open = false;
                        code.focus();
                    });
                    results.append(option);
                }
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'No se pudo buscar. Inténtalo de nuevo.';
            }
        }, 200);
    }
    // Al cerrarse, el listado deja de filtrar por la zona elegida en el mapa corporal.
    picker.addEventListener('toggle', () => { if (picker.open) { page = 1; load(); } else { delete picker.dataset.zone; } });
    search.addEventListener('input', () => { page = 1; load(); });
    previous.addEventListener('click', () => { page--; load(); });
    next.addEventListener('click', () => { page++; load(); });
}

const documentChoices = [...document.querySelectorAll('input[name="M"]')];
if (documentChoices.length) {
    const number = document.querySelector('#f-N');
    const help = document.querySelector('#documento-ayuda');
    const label = document.querySelector('label[for="f-N"]');
    function describeDocument() {
        const type = documentChoices.find(choice => choice.checked)?.value ?? '';
        number.inputMode = type === 'Cédula de identidad' ? 'numeric' : 'text';
        label.textContent = ({
            'Cédula de identidad': 'Número de cédula',
            'Pasaporte': 'Número de pasaporte',
            'Visado': 'Número de visado',
            'Carné de refugiado': 'Número de carné de refugiado',
        })[type] || 'Número de identificación';
        help.textContent = type === 'Cédula de identidad' ? 'Ingresa los 10 dígitos de la cédula.' :
            ['Pasaporte', 'Visado', 'Carné de refugiado'].includes(type) ? 'Ingresa el número tal como aparece en el documento.' :
            'Deja el número en blanco si no se dispone de un documento.';
    }
    documentChoices.forEach(choice => choice.addEventListener('change', describeDocument));
    describeDocument();
}
