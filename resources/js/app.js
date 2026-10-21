import { createPopper } from '@popperjs/core';
import './bootstrap';
import Alpine from 'alpinejs';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/form-validation.css';
import '../css/date-picker.css';
import './components/date-constraints';
import './components/form-validation';
window.Alpine = Alpine;
window.createPopper = createPopper;
window.flatpickr = flatpickr;

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-1')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartOne();
        });
    }
    if (document.querySelector('#chartTwo')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-2')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartTwo();
        });
    }
    if (document.querySelector('#chartThree')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-3')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartThree();
        });
    }
    if (document.querySelector('#chartSix')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-6')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartSix();
        });
    }
    if (document.querySelector('#chartEight')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-8')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartEight();
        });
    }
    if (document.querySelector('#chartThirteen')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-13')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartThirteen();
        });
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
    if (document.querySelector('#harvest-calendar')) {
        import('./components/harvest-calendar').then(module => module.initHarvestCalendar());
    }
    if (document.querySelector('[data-producer-weather]')) {
        import('./components/producer-weather').then(module => module.initProducerWeather());
    }
    if (document.querySelector('[data-messaging]')) {
        import('./components/messaging').then(module => module.initMessaging());
    }
});
