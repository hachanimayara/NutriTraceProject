import { Calendar } from 'fullcalendar';
import dayGridPlugin from 'fullcalendar/daygrid';
import interactionPlugin from 'fullcalendar/interaction';
import timeGridPlugin from 'fullcalendar/timegrid';
import themePlugin from 'fullcalendar/themes/classic';
import frLocale from 'fullcalendar/locales/fr';
import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/classic/palette.css';
import 'fullcalendar/themes/classic/theme.css';

export function initHarvestCalendar() {
    const element = document.querySelector('#harvest-calendar');
    if (!element) return;
    const filterForm = document.querySelector('#harvest-filters');
    const createUrl = element.dataset.createUrl;
    const eventUrl = element.dataset.eventsUrl;
    const today = new Date();
    const todayKey = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
    const calendar = new Calendar(element, {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, themePlugin],
        themeSystem: 'classic',
        initialView: window.innerWidth < 640 ? 'dayGridDay' : 'dayGridMonth',
        locales: [frLocale],
        locale: frLocale,
        firstDay: 1,
        validRange: { start: todayKey },
        height: 'auto',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridDay,timeGridWeek,dayGridMonth' },
        buttonText: { today: "Aujourd'hui", day: 'Jour', week: 'Semaine', month: 'Mois' },
        events(info, success, failure) {
            const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
            if (filterForm) new FormData(filterForm).forEach((value, key) => { if (value) params.set(key, value); });
            fetch(`${eventUrl}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(response => { if (!response.ok) throw new Error('Chargement du calendrier impossible.'); return response.json(); })
                .then(success).catch(failure);
        },
        dateClick(info) {
            if (info.dateStr < todayKey) {
                window.alert('Une récolte ne peut pas être planifiée dans le passé.');
                return;
            }
            window.location.href = `${createUrl}?date=${encodeURIComponent(info.dateStr)}`;
        },
        eventClick(info) { if (info.event.url) { info.jsEvent.preventDefault(); window.location.href = info.event.url; } },
        eventDidMount(info) { info.el.title = `${info.event.extendedProps.farm} · ${info.event.extendedProps.status}`; },
    });
    calendar.render();
}
