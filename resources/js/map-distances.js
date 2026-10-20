import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.querySelector('[data-map-distance-app]');
if (root) {
    const stores = JSON.parse(document.getElementById('map-store-data')?.textContent || '[]');
    const distributions = JSON.parse(document.getElementById('map-distribution-data')?.textContent || '[]');
    const config = JSON.parse(document.getElementById('map-config')?.textContent || '{}');
    const mapNode = document.getElementById('distance-map');
    const status = document.getElementById('map-status');
    const result = document.getElementById('route-result');
    const mode = document.getElementById('route-mode');
    const originSelect = document.getElementById('route-origin-store');
    const destinationSelect = document.getElementById('route-destination-store');
    const distributionSelect = document.getElementById('route-distribution');
    const useLocation = document.getElementById('use-current-location');
    const calculate = document.getElementById('calculate-route');
    const reset = document.getElementById('reset-route');
    let currentPosition = null;
    let routeLayer = null;
    let positionMarker = null;
    let routing = false;

    const map = L.map(mapNode, { scrollWheelZoom: false }).setView([34.0, 9.0], 6);
    const tileLayer = L.tileLayer(config.tileUrl, { maxZoom: 19, attribution: config.tileAttribution }).addTo(map);
    const storeIcon = L.divIcon({ className: 'nt-map-marker', html: '<span></span>', iconSize: [24, 24], iconAnchor: [12, 12] });
    const positionIcon = L.divIcon({ className: 'nt-map-position', html: '<span></span>', iconSize: [26, 26], iconAnchor: [13, 13] });
    const valid = point => point && point.latitude !== null && point.latitude !== '' && point.longitude !== null && point.longitude !== '' && Number.isFinite(Number(point.latitude)) && Number.isFinite(Number(point.longitude)) && Math.abs(Number(point.latitude)) <= 90 && Math.abs(Number(point.longitude)) <= 180;
    const coords = point => [Number(point.latitude), Number(point.longitude)];
    const safeText = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
    const storeById = new Map(stores.map(store => [String(store.id), store]));
    const markerPoints = [];

    stores.forEach(store => {
        if (!valid(store)) return;
        const marker = L.marker(coords(store), { icon: storeIcon }).addTo(map);
        marker.bindPopup(`<strong>${safeText(store.name)}</strong><br>${safeText(store.address)}${store.city ? `<br>${safeText(store.city)}` : ''}`);
        markerPoints.push(coords(store));
    });
    if (markerPoints.length) map.fitBounds(markerPoints, { padding: [28, 28], maxZoom: 12 });

    function setMessage(message, kind = 'info') {
        status.hidden = !message;
        status.textContent = message;
        status.dataset.kind = kind;
    }

    let tileErrorShown = false;
    tileLayer.on('tileerror', () => {
        if (tileErrorShown) return;
        tileErrorShown = true;
        setMessage('Le fond de carte est indisponible pour le moment. Vérifiez la configuration du fournisseur de tuiles.', 'error');
    });

    function updateMode() {
        const selected = mode.value;
        document.querySelectorAll('[data-route-field]').forEach(field => {
            field.hidden = !field.dataset.routeField.split(' ').includes(selected);
        });
        useLocation.hidden = selected !== 'current-store';
        calculate.textContent = selected === 'distribution' ? 'Calculer l’itinéraire de la distribution' : 'Calculer la distance';
        setMessage('');
        result.hidden = true;
    }
    mode.addEventListener('change', updateMode);

    useLocation.addEventListener('click', () => {
        setMessage('');
        if (!('geolocation' in navigator)) {
            setMessage('La géolocalisation n’est pas prise en charge par ce navigateur. Choisissez plutôt un point de vente comme départ.', 'error');
            return;
        }
        useLocation.disabled = true;
        useLocation.textContent = 'Localisation en cours…';
        navigator.geolocation.getCurrentPosition(position => {
            currentPosition = { latitude: position.coords.latitude, longitude: position.coords.longitude };
            if (positionMarker) positionMarker.remove();
            positionMarker = L.marker(coords(currentPosition), { icon: positionIcon }).addTo(map).bindPopup('Votre position actuelle (visible uniquement dans cette session)');
            map.setView(coords(currentPosition), 13);
            setMessage('Position récupérée. Elle reste dans cette session et n’est pas enregistrée.', 'success');
            useLocation.disabled = false;
            useLocation.textContent = 'Actualiser ma position';
        }, error => {
            const message = error.code === 1 ? 'Autorisation de localisation refusée. Choisissez un point de vente comme départ ou autorisez la position dans votre navigateur.' : error.code === 2 ? 'Position indisponible. Réessayez ou choisissez un point de vente comme départ.' : 'La demande de position a expiré. Réessayez.';
            setMessage(message, 'error');
            useLocation.disabled = false;
            useLocation.textContent = 'Utiliser ma position';
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
    });

    function selectedPoints() {
        if (mode.value === 'current-store') {
            const destination = storeById.get(destinationSelect.value);
            return { origin: currentPosition, destination, originName: 'Ma position', destinationName: destination?.name };
        }
        if (mode.value === 'store-store') {
            const origin = storeById.get(originSelect.value);
            const destination = storeById.get(destinationSelect.value);
            return { origin, destination, originName: origin?.name, destinationName: destination?.name };
        }
        const distribution = distributions.find(item => String(item.id) === distributionSelect.value);
        if (!distribution) return {};
        const destination = storeById.get(String(distribution.store_id));
        const origin = { latitude: distribution.origin_latitude, longitude: distribution.origin_longitude };
        return { origin, destination, originName: distribution.origin_name || `Départ de ${distribution.reference}`, destinationName: destination?.name, distribution };
    }

    calculate.addEventListener('click', async () => {
        if (routing) return;
        const points = selectedPoints();
        if (!points.origin || !points.destination) {
            setMessage('Sélectionnez un départ et une destination.', 'error');
            return;
        }
        if (!valid(points.origin)) {
            const message = mode.value === 'distribution' ? 'Cette distribution n’a pas de coordonnées de départ. Renseignez-les dans le formulaire de distribution.' : 'Le lieu de départ n’a pas de coordonnées valides.';
            setMessage(message, 'error');
            return;
        }
        if (!valid(points.destination)) {
            setMessage('La destination sélectionnée n’a pas de coordonnées. Ajoutez-les dans la fiche du point de vente.', 'error');
            return;
        }
        if (coords(points.origin).every((value, index) => value === coords(points.destination)[index])) {
            setMessage('Le départ et la destination sont identiques.', 'error');
            return;
        }

        routing = true;
        calculate.disabled = true;
        calculate.textContent = 'Calcul de l’itinéraire…';
        result.hidden = true;
        setMessage('Recherche d’un itinéraire routier…');
        try {
            const [lat1, lon1] = coords(points.origin);
            const [lat2, lon2] = coords(points.destination);
            const endpoint = `${String(config.routingUrl).replace(/\/$/, '')}/${lon1},${lat1};${lon2},${lat2}?overview=full&geometries=geojson`;
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(20000) });
            if (!response.ok) throw new Error('Le service de calcul d’itinéraire est temporairement indisponible. Réessayez plus tard.');
            const payload = await response.json();
            if (payload.code !== 'Ok' || !payload.routes?.length) throw new Error('Aucun itinéraire routier n’a été trouvé entre ces deux lieux.');
            const route = payload.routes[0];
            if (!Number.isFinite(route.distance) || !Number.isFinite(route.duration) || !route.geometry?.coordinates?.length) throw new Error('Le service a renvoyé un itinéraire incomplet.');
            const latLngs = route.geometry.coordinates.map(([longitude, latitude]) => [latitude, longitude]);
            if (routeLayer) routeLayer.remove();
            routeLayer = L.polyline(latLngs, { color: '#28724d', weight: 5, opacity: 0.85 }).addTo(map);
            map.fitBounds(routeLayer.getBounds(), { padding: [35, 35] });
            document.getElementById('route-distance').textContent = `${(route.distance / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} km`;
            const minutes = Math.round(route.duration / 60);
            document.getElementById('route-duration').textContent = minutes >= 60 ? `${Math.floor(minutes / 60)} h ${minutes % 60} min` : `${minutes} min`;
            document.getElementById('route-summary').textContent = `${points.originName} → ${points.destinationName}`;
            document.getElementById('route-navigation').href = `https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=${encodeURIComponent(`${lat1},${lon1};${lat2},${lon2}`)}`;
            result.hidden = false;
            setMessage('Itinéraire routier calculé. La durée est estimative et ne tient pas compte du trafic en temps réel.', 'success');
        } catch (error) {
            const message = error.name === 'TimeoutError'
                ? 'Le service de routage met trop de temps à répondre. Réessayez plus tard.'
                : error instanceof TypeError
                    ? 'Impossible de joindre le service de routage. Vérifiez votre connexion ou réessayez plus tard.'
                    : (error.message || 'Le calcul a échoué. Vérifiez votre connexion et réessayez.');
            setMessage(message, 'error');
        } finally {
            routing = false;
            calculate.disabled = false;
            calculate.textContent = mode.value === 'distribution' ? 'Calculer l’itinéraire de la distribution' : 'Calculer la distance';
        }
    });

    reset.addEventListener('click', () => {
        mode.value = 'current-store';
        originSelect.value = '';
        destinationSelect.value = '';
        distributionSelect.value = '';
        if (routeLayer) routeLayer.remove();
        routeLayer = null;
        result.hidden = true;
        setMessage('');
        updateMode();
        if (markerPoints.length) map.fitBounds(markerPoints, { padding: [28, 28], maxZoom: 12 });
        else map.setView([34.0, 9.0], 6);
    });

    updateMode();
    window.setTimeout(() => map.invalidateSize(), 100);
}
