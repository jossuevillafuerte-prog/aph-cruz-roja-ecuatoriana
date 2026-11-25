for (const picker of document.querySelectorAll('.location-picker')) {
    const latitude = picker.querySelector('input[name$="_lat"]');
    const longitude = picker.querySelector('input[name$="_lon"]');
    const dialog = picker.querySelector('dialog');
    const open = picker.querySelector('.map-open');
    const cancel = picker.querySelector('.map-cancel');
    const field = document.getElementById(picker.dataset.field);
    const form = picker.closest('form');
    const token = form.querySelector('input[name="_token"]').value;
    const province = form.querySelector('input[name="_province"]')?.value ?? '';
    const status = picker.querySelector('.location-status');
    const mapStatus = picker.querySelector('.map-status');
    const confirm = picker.querySelector('.map-confirm');
    const clear = picker.querySelector('.map-clear');
    const useCenter = picker.querySelector('.map-center');
    let map;
    let marker;
    let draft;
    let searching = false;
    let suggestion = '';
    let automaticText = '';
    const useSuggestion = document.createElement('button');
    useSuggestion.type = 'button';
    useSuggestion.className = 'map-use-address';
    useSuggestion.textContent = 'Usar dirección sugerida';
    useSuggestion.hidden = true;
    status.after(useSuggestion);

    function canReplaceText() {
        const text = field.value.trim();
        return text === '' || text === automaticText || /^Punto de referencia \(-?[\d.]+, -?[\d.]+\)$/.test(text);
    }

    function offerSuggestion(address) {
        suggestion = address;
        useSuggestion.hidden = address === '';
    }

    function savedPoint() {
        return latitude.value !== '' && longitude.value !== ''
            ? [Number(latitude.value), Number(longitude.value)]
            : null;
    }

    function setDraft(point) {
        draft = point ? L.latLng(point).wrap() : null;
        if (!draft) {
            marker?.remove();
            marker = null;
        } else if (marker) {
            marker.setLatLng(draft);
        } else {
            marker = L.marker(draft, {
                draggable: true,
                title: 'Punto de referencia',
                icon: L.divIcon({className: 'location-pin', iconSize: [24, 24], iconAnchor: [12, 23]}),
            }).addTo(map);
            marker.on('dragend', () => setDraft(marker.getLatLng()));
        }
        confirm.disabled = !draft;
    }

    open.addEventListener('click', () => {
        dialog.showModal();
        if (!window.L) {
            useCenter.disabled = true;
            mapStatus.textContent = 'El mapa no está disponible. Puedes guardar la dirección escrita.';
            return;
        }
        const point = savedPoint();
        if (!map) {
            const center = picker.dataset.islands === '1' ? [-0.62, -90.55] : [-1.6, -78.4];
            map = L.map(picker.querySelector('.location-map')).setView(point ?? center, point ? 15 : 7);
            L.tileLayer(picker.dataset.tiles, {attribution: picker.dataset.attribution, maxZoom: 19})
                .on('tileerror', () => { mapStatus.textContent = 'Parte del mapa no cargó. También puedes usar la dirección escrita.'; })
                .addTo(map);
            map.on('click', event => { if (!searching) setDraft(event.latlng); });
        } else if (point) {
            map.panTo(point, {animate: false});
        }
        setDraft(point);
        requestAnimationFrame(() => map.invalidateSize());
    });

    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (searching) event.preventDefault(); });
    useCenter.addEventListener('click', () => { if (map) setDraft(map.getCenter()); });
    confirm.addEventListener('click', async () => {
        if (!draft || searching) return;
        searching = true;
        confirm.disabled = useCenter.disabled = cancel.disabled = true;
        marker.dragging.disable();
        mapStatus.textContent = 'Buscando dirección de referencia…';
        const lat = draft.lat.toFixed(6);
        const lon = draft.lng.toFixed(6);
        let address = '';
        let problem = 'Sin dirección disponible.';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 5500);
        try {
            const response = await fetch('?vista=direccion', {
                method: 'POST',
                headers: {Accept: 'application/json'},
                body: new URLSearchParams({_token: token, _province: province, lat, lon}),
                signal: controller.signal,
            });
            if (response.redirected || response.status === 401 || response.status === 419) {
                problem = 'No se pudo consultar la dirección: revisa tu sesión antes de guardar.';
            } else if (!response.ok) {
                problem = 'El servicio de direcciones no respondió.';
            } else {
                const result = await response.json();
                address = typeof result.direccion === 'string' ? result.direccion.trim() : '';
            }
        } catch (error) {
            // The coordinates can still be saved when lookup is unavailable.
            problem = error.name === 'AbortError' ? 'La búsqueda de la dirección tardó demasiado.' : 'No se pudo consultar la dirección.';
        } finally {
            clearTimeout(timeout);
        }
        latitude.value = lat;
        longitude.value = lon;
        offerSuggestion('');
        if (canReplaceText()) {
            automaticText = address || `Punto de referencia (${lat}, ${lon})`;
            field.value = automaticText;
            status.textContent = address ? 'Dirección aproximada completada' : `Punto marcado. ${problem}`;
        } else if (address && address !== field.value.trim()) {
            offerSuggestion(address);
            status.textContent = `Punto marcado. Se conservó el texto escrito. Dirección sugerida: ${address}`;
        } else {
            status.textContent = address ? 'Punto marcado. La dirección coincide.' : `Punto marcado. Se conservó el texto escrito. ${problem}`;
        }
        clear.hidden = false;
        searching = false;
        confirm.disabled = useCenter.disabled = cancel.disabled = false;
        marker.dragging.enable();
        mapStatus.textContent = '';
        dialog.close();
        open.focus();
    });
    useSuggestion.addEventListener('click', () => {
        if (suggestion === '') return;
        automaticText = suggestion;
        field.value = suggestion;
        offerSuggestion('');
        status.textContent = 'Dirección sugerida aplicada';
        field.focus();
    });
    clear.addEventListener('click', () => {
        latitude.value = longitude.value = '';
        offerSuggestion('');
        if (map) setDraft(null);
        status.textContent = 'Sin punto marcado';
        clear.hidden = true;
        open.focus();
    });
}
