import '../../../resources/css/weather.css';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
const number = (value, digits = 0) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? new Intl.NumberFormat('fr-FR', { maximumFractionDigits: digits, minimumFractionDigits: digits }).format(Number(value)) : '—';
const weatherLabel = (code) => {
    if (code === 0) return ['☀️', 'Ciel dégagé'];
    if ([1, 2, 3].includes(code)) return ['🌤️', 'Partiellement nuageux'];
    if ([45, 48].includes(code)) return ['🌫️', 'Brouillard'];
    if ([51, 53, 55, 56, 57].includes(code)) return ['🌦️', 'Bruine'];
    if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return ['🌧️', 'Pluie ou averses'];
    if ([71, 73, 75, 77, 85, 86].includes(code)) return ['🌨️', 'Neige'];
    if ([95, 96, 99].includes(code)) return ['⛈️', 'Orage prévu'];
    return ['🌤️', 'Conditions variables'];
};
const direction = (degrees) => {
    if (!Number.isFinite(Number(degrees))) return '—';
    return `${Math.round(Number(degrees))}° (${['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'][Math.round(Number(degrees) / 45) % 8]})`;
};
const formatTime = (value) => {
    const match = String(value).match(/T(\d{2}:\d{2})/);
    return match ? match[1] : escapeHtml(value);
};
const formatDay = (value, timezone) => {
    const date = new Date(`${value}T12:00:00`);
    return new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short', timeZone: timezone || 'Africa/Tunis' }).format(date);
};

function renderChart(container, hourly) {
    const count = Math.min(12, hourly.time.length);
    const temps = hourly.temperature_2m.slice(0, count).map((value) => value === null ? Number.NaN : Number(value));
    const rain = hourly.precipitation.slice(0, count).map((value) => value === null ? Number.NaN : Number(value));
    const valid = temps.filter(Number.isFinite);
    if (!count || !valid.length) { container.innerHTML = '<p class="weather-empty">Graphique indisponible pour ces données.</p>'; return; }
    const min = Math.min(...valid) - 2, max = Math.max(...valid) + 2, span = Math.max(1, max - min);
    const points = temps.map((temp, index) => Number.isFinite(temp) ? `${30 + index * (640 / Math.max(1, count - 1))},${155 - ((temp - min) / span) * 120}` : '').filter(Boolean).join(' ');
    const bars = rain.map((amount, index) => {
        const height = Number.isFinite(amount) ? Math.min(36, Math.max(0, amount) * 4) : 0;
        return `<rect x="${22 + index * (640 / count)}" y="${172 - height}" width="22" height="${height}" rx="4" fill="#8bc2dc" opacity=".8"><title>${escapeHtml(formatTime(hourly.time[index]))}: ${number(amount, 1)} mm</title></rect>`;
    }).join('');
    const labels = hourly.time.slice(0, count).map((time, index) => index % 3 === 0 ? `<text x="${30 + index * (640 / Math.max(1, count - 1))}" y="190" text-anchor="middle" font-size="11" fill="#64748b">${escapeHtml(formatTime(time))}</text>` : '').join('');
    container.innerHTML = `<svg viewBox="0 0 700 200" role="img" aria-label="Graphique des températures et précipitations horaires"><line x1="20" y1="174" x2="680" y2="174" stroke="#dce8e0"/>${bars}<polyline points="${points}" fill="none" stroke="#287052" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><text x="22" y="14" font-size="11" fill="#287052">Température °C</text><text x="610" y="14" font-size="11" fill="#4786a2">Pluie mm</text>${labels}</svg>`;
}

function renderAdvice(target, advice) {
    if (!advice.length) { target.innerHTML = '<p class="weather-empty">Aucun conseil particulier ne ressort des prévisions actuelles.</p>'; return; }
    target.innerHTML = advice.map((item) => `<article class="weather-advice-card" data-priority="${escapeHtml(item.priority)}" data-type="${escapeHtml(item.type)}"><span class="weather-badge">${({ high: 'Risque élevé', watch: 'Vigilance', info: 'Information' })[item.priority] || 'Information'}</span><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.description)}</p><p><strong>${escapeHtml(item.phenomenon)} :</strong> ${escapeHtml(item.value)} · ${escapeHtml(item.period)}</p><p class="weather-advice-action">À envisager : ${escapeHtml(item.action)}</p></article>`).join('');
}

export function initProducerWeather() {
    const root = document.querySelector('[data-producer-weather]');
    if (!root) return;
    const farm = root.querySelector('[data-weather-farm]');
    if (!farm) return;
    const message = root.querySelector('[data-weather-message]');
    const content = root.querySelector('[data-weather-content]');
    const refreshButton = root.querySelector('[data-weather-refresh]');
    let latestAdvice = [];

    const applyFilters = () => {
        const priority = root.querySelector('[data-filter-priority]')?.value || 'all';
        const type = root.querySelector('[data-filter-type]')?.value || 'all';
        const filtered = latestAdvice.filter((item) => (priority === 'all' || item.priority === priority) && (type === 'all' || item.type === type));
        renderAdvice(root.querySelector('[data-weather-advice]'), filtered);
    };
    root.querySelector('[data-filter-priority]')?.addEventListener('change', applyFilters);
    root.querySelector('[data-filter-type]')?.addEventListener('change', applyFilters);

    const load = async (refresh = false) => {
        if (!farm.value) return;
        message.hidden = true;
        message.replaceChildren();
        content.setAttribute('aria-busy', 'true');
        content.classList.add('weather-loading');
        refreshButton.disabled = true;
        try {
            const url = new URL(root.dataset.endpoint, window.location.origin);
            url.searchParams.set('farm_id', farm.value);
            if (refresh) url.searchParams.set('refresh', '1');
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (data.status === 'missing_coordinates') {
                message.dataset.kind = 'notice';
                message.innerHTML = `${escapeHtml(data.message)}<br><a href="${escapeHtml(data.edit_url)}">Renseigner les coordonnées GPS de cette exploitation</a>`;
                message.hidden = false;
                content.querySelector('[data-weather-current]').innerHTML = '';
                ['[data-weather-chart]', '[data-weather-hourly]', '[data-weather-daily]', '[data-weather-advice]', '[data-weather-alerts]', '[data-weather-impacts]'].forEach((selector) => { const el = root.querySelector(selector); if (el) el.innerHTML = '<p class="weather-empty">Aucune donnée disponible sans coordonnées GPS.</p>'; });
                return;
            }
            if (!response.ok || data.status !== 'ok') throw new Error(data.message || 'Les prévisions sont momentanément indisponibles. Réessayez plus tard.');
            const { forecast } = data;
            const current = forecast.current, daily = forecast.daily, hourly = forecast.hourly;
            const [icon, label] = weatherLabel(Number(current.weather_code));
            const valueAt = (array, index = 0) => array?.[index] ?? null;
            const card = (labelText, value, note = '') => `<article class="weather-metric"><div class="weather-metric-label">${escapeHtml(labelText)}</div><div class="weather-metric-value">${escapeHtml(value)}</div>${note ? `<div class="weather-metric-note">${escapeHtml(note)}</div>` : ''}</article>`;
            content.querySelector('[data-weather-current]').innerHTML = [
                card(`${icon} ${label}`, `${number(current.temperature_2m, 1)} °C`, `Ressentie ${number(current.apparent_temperature, 1)} °C`),
                card('Min. / max. aujourd’hui', `${number(valueAt(daily.temperature_2m_min))}° / ${number(valueAt(daily.temperature_2m_max))}°`),
                card('Humidité relative', `${number(current.relative_humidity_2m)} %`),
                card('Pluie actuelle / probabilité', `${number(current.precipitation, 1)} mm / ${number(valueAt(hourly.precipitation_probability))} %`, `Cumul prévu aujourd’hui ${number(valueAt(daily.precipitation_sum), 1)} mm`),
                card('Vent / rafales', `${number(current.wind_speed_10m)} / ${number(current.wind_gusts_10m)} km/h`, `Direction ${direction(current.wind_direction_10m)}`),
                card('ET₀ de référence', `${number(valueAt(daily.et0_fao_evapotranspiration), 1)} mm/j`, 'Indicateur météo, pas un besoin d’irrigation'),
            ].join('');
            renderChart(root.querySelector('[data-weather-chart]'), hourly);
            root.querySelector('[data-weather-hourly]').innerHTML = hourly.time.slice(0, 12).map((time, i) => {
                const [hourIcon] = weatherLabel(Number(hourly.weather_code?.[i] ?? current.weather_code));
                return `<div class="weather-hour"><span>${escapeHtml(formatTime(time))}</span><strong>${hourIcon} ${number(hourly.temperature_2m[i])}°</strong><span>${number(hourly.precipitation_probability[i])}% pluie</span><span>${number(hourly.precipitation[i], 1)} mm</span></div>`;
            }).join('');
            root.querySelector('[data-weather-daily]').innerHTML = daily.time.slice(0, 7).map((day, i) => {
                const [dayIcon, dayLabel] = weatherLabel(Number(daily.weather_code[i]));
                return `<div class="weather-day"><span class="weather-day-name">${escapeHtml(formatDay(day, forecast.timezone))}</span><span title="${escapeHtml(dayLabel)}">${dayIcon}</span><strong class="weather-day-range">${number(daily.temperature_2m_min[i])}° / ${number(daily.temperature_2m_max[i])}°</strong><span class="weather-day-rain">${number(daily.precipitation_sum[i], 1)} mm · ${number(daily.precipitation_probability_max[i])}%</span></div>`;
            }).join('');
            latestAdvice = data.advice || [];
            applyFilters();
            const alerts = latestAdvice.filter((item) => item.priority === 'high' || item.priority === 'watch');
            root.querySelector('[data-weather-alerts]').innerHTML = alerts.length ? alerts.map((item) => `<article class="weather-alert-card" data-priority="${escapeHtml(item.priority)}"><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.value)} · ${escapeHtml(item.period)}</p></article>`).join('') : '<p class="weather-empty">Aucune alerte particulière dans les prévisions actuelles.</p>';
            root.querySelector('[data-weather-impacts]').innerHTML = data.harvest_impacts?.length ? data.harvest_impacts.map((plan) => `<article class="weather-impact"><h3>${escapeHtml(plan.product)} · ${escapeHtml(plan.reference)}</h3><p>Récolte planifiée : ${escapeHtml(plan.period)} · ${escapeHtml(number(plan.estimated_quantity, 1))} ${escapeHtml(plan.unit)}</p>${plan.risks?.length ? `<p><strong>Risques météo sur cette période :</strong> ${plan.risks.map((risk) => escapeHtml(risk.title)).join(' · ')}</p>` : '<p class="weather-no-risk">Aucun risque météo particulier détecté sur ces dates.</p>'}</article>`).join('') : data.other_farm_harvests?.length ? `<p class="weather-empty">Aucune récolte planifiée sur cette exploitation pendant les sept prochains jours. Des récoltes sont enregistrées sur : ${data.other_farm_harvests.map((item) => `${escapeHtml(item.name)} (${number(item.count)} récolte(s))`).join(', ')}. Sélectionnez cette exploitation pour afficher leur impact météo.</p>` : '<p class="weather-empty">Aucune récolte planifiée sur cette exploitation pendant les sept prochains jours.</p>';
            root.querySelector('[data-weather-updated]').textContent = `Données récupérées le ${new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short', timeZone: forecast.timezone }).format(new Date(forecast.fetched_at))}`;
            if (data.location_accuracy === 'approximate') {
                const link = document.createElement('a');
                link.href = data.edit_url;
                link.textContent = 'Enregistrer les coordonnées GPS exactes';
                message.replaceChildren(document.createTextNode(`Météo indicative pour ${data.matched_location}. Les prévisions utilisent le point géographique de la localité, pas la position exacte de la parcelle. `), link);
                message.dataset.kind = 'notice';
                message.hidden = false;
            } else {
                message.hidden = true;
            }
        } catch (error) {
            message.dataset.kind = 'error';
            message.textContent = error.message || 'Impossible de récupérer les prévisions météo.';
            message.hidden = false;
        } finally {
            content.setAttribute('aria-busy', 'false');
            content.classList.remove('weather-loading');
            refreshButton.disabled = false;
        }
    };

    farm.addEventListener('change', () => load(false));
    refreshButton.addEventListener('click', () => load(true));
    if (root.dataset.selectedFarm) farm.value = root.dataset.selectedFarm;
    load(false);
}
