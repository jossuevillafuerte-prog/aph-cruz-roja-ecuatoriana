(() => {
    'use strict';

    const panel = document.querySelector('.assessment');
    const select = document.querySelector('#f-AE');
    if (!panel || !select) return;

    const zones = JSON.parse(panel.dataset.zones);
    const hint = panel.querySelector('.zone-hint');
    const suggestionsField = panel.querySelector('.suggestions-field');
    const suggestionsTitle = panel.querySelector('.suggestions-title');
    const suggestions = panel.querySelector('.body-map-suggestions');
    const searchZone = panel.querySelector('.cie-search-zone');
    const code = document.querySelector('#f-U');
    const description = document.querySelector('#cie-description');
    const picker = document.querySelector('#cie-picker');

    function normalize(value) {
        return value.trim().toUpperCase().replace('.', '');
    }

    // Zona que corresponde al código por su rango de traumatismos (S00–S99, T00–T07).
    function zoneForCode(value) {
        const prefix = normalize(value).slice(0, 3);
        if (!/^[A-Z]\d\d$/.test(prefix)) return '';
        return Object.keys(zones).find(zone => zones[zone].from && prefix >= zones[zone].from && prefix <= zones[zone].to) ?? '';
    }

    // La silueta y los botones solo cambian la lista: el borrador y el aviso de salida registran el cambio.
    function setZone(zone) {
        select.value = zone;
        select.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function showHint(text, zone) {
        hint.replaceChildren();
        hint.classList.toggle('is-warning', Boolean(zone));
        if (!text) return;
        hint.append(text);
        if (zone) {
            const change = document.createElement('button');
            change.type = 'button';
            change.textContent = 'Cambiar zona a ' + zone;
            change.addEventListener('click', () => { setZone(zone); hint.replaceChildren(); });
            hint.append(' ', change);
        }
    }

    function checkCode() {
        const expected = zoneForCode(code.value);
        if (!expected || expected === select.value) {
            showHint('');
        } else if (select.value === '') {
            setZone(expected);
            showHint(`Zona marcada según el código: ${expected}.`);
        } else {
            showHint(`${code.value.trim().toUpperCase()} corresponde a ${expected}.`, expected);
        }
    }

    function render() {
        const zone = select.value;
        for (const node of panel.querySelectorAll('[data-zone]')) {
            const active = node.dataset.zone === zone;
            node.classList.toggle('is-selected', active);
            if (node.tagName === 'BUTTON') node.setAttribute('aria-pressed', String(active));
        }
        const info = zones[zone];
        suggestions.replaceChildren();
        suggestionsField.hidden = !info;
        searchZone.querySelector('span').textContent = info?.range ? `Buscar en ${zone}` : 'Buscar en la CIE-10';
        if (info) {
            suggestionsTitle.textContent = info.range ? `Códigos frecuentes (${info.range})` : 'Códigos frecuentes';
            for (const [visible, text] of info.codes) {
                const button = document.createElement('button');
                button.type = 'button';
                const strong = document.createElement('strong');
                strong.textContent = visible;
                button.append(strong, ' ', text);
                const chosen = normalize(code.value) === normalize(visible);
                button.setAttribute('aria-pressed', String(chosen));
                if (chosen && description && !description.textContent.trim()) description.textContent = text;
                button.addEventListener('click', () => {
                    code.value = visible;
                    code.dispatchEvent(new Event('change', {bubbles: true}));
                    if (description) description.textContent = text;
                    render();
                });
                suggestions.append(button);
            }
        }
        checkCode();
    }

    panel.querySelectorAll('[data-zone]').forEach(node => node.addEventListener('click', () => setZone(select.value === node.dataset.zone ? '' : node.dataset.zone)));
    select.addEventListener('change', render);
    code.addEventListener('change', render);
    searchZone.addEventListener('click', () => {
        picker.dataset.zone = zones[select.value]?.range ? select.value : '';
        picker.open ? picker.dispatchEvent(new Event('toggle')) : (picker.open = true);
        document.querySelector('#cie-search')?.focus();
    });
    render();
})();
