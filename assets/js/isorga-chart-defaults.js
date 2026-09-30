/**
 * ISORGA — Chart.js defaults globales
 * Aplica colores de ejes, leyendas, grid y tooltips leyendo
 * desde window.app.color (que se rellena de las CSS vars --bs-*).
 *
 * Se invoca:
 *   1) Al cargar la página (DOMContentLoaded), después de que
 *      app.min.js haya inicializado app.color.
 *   2) Cada vez que se dispara 'theme-reload' (tras toggle de tema).
 *
 * NO toca series ni datasets. Solo defaults globales.
 */
(function () {
    'use strict';

    function applyIsorgaChartDefaults() {
        if (typeof Chart === 'undefined') return;
        if (typeof window.app === 'undefined' || !window.app.color) return;

        var c = window.app.color;
        var f = window.app.font || {};

        // Fuente
        if (f.bodyFontFamily) {
            Chart.defaults.font.family = f.bodyFontFamily;
        }
        if (c.bodyColor) {
            Chart.defaults.color = c.bodyColor;
        }
        if (c.borderColor) {
            Chart.defaults.borderColor = c.borderColor;
        }

        // Leyenda
        if (Chart.defaults.plugins && Chart.defaults.plugins.legend) {
            Chart.defaults.plugins.legend.labels =
                Chart.defaults.plugins.legend.labels || {};
            if (c.bodyColor) {
                Chart.defaults.plugins.legend.labels.color = c.bodyColor;
            }
        }

        // Tooltip
        if (Chart.defaults.plugins && Chart.defaults.plugins.tooltip) {
            if (c.componentBg) {
                Chart.defaults.plugins.tooltip.backgroundColor = c.componentBg;
            }
            if (c.componentColor) {
                Chart.defaults.plugins.tooltip.titleColor = c.componentColor;
                Chart.defaults.plugins.tooltip.bodyColor = c.componentColor;
            }
            if (c.borderColor) {
                Chart.defaults.plugins.tooltip.borderColor = c.borderColor;
                Chart.defaults.plugins.tooltip.borderWidth = 1;
            }
        }

        // Ejes (Chart.js 4: scales.x / scales.y bajo defaults.scale o
        // bajo cada tipo de escala — usamos el método robusto vía
        // defaults.scale que afecta a todas las escalas)
        if (Chart.defaults.scale) {
            Chart.defaults.scale.ticks = Chart.defaults.scale.ticks || {};
            Chart.defaults.scale.grid = Chart.defaults.scale.grid || {};
            if (c.bodyColor) {
                Chart.defaults.scale.ticks.color = c.bodyColor;
            }
            if (c.borderColor) {
                Chart.defaults.scale.grid.color = c.borderColor;
            }
        }
    }

    /**
     * Itera todos los <canvas> del DOM y devuelve las instancias
     * activas de Chart.js. Usa Chart.getChart() que es la API
     * canónica en Chart.js 4.x.
     */
    function getActiveCharts() {
        if (typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
            return [];
        }
        var canvases = document.querySelectorAll('canvas');
        var charts = [];
        for (var i = 0; i < canvases.length; i++) {
            var ch = Chart.getChart(canvases[i]);
            if (ch) charts.push(ch);
        }
        return charts;
    }

    // Aplicar defaults al cargar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyIsorgaChartDefaults);
    } else {
        applyIsorgaChartDefaults();
    }

    // Reaplicar y redibujar al cambiar tema
    document.addEventListener('theme-reload', function () {
        applyIsorgaChartDefaults();
        getActiveCharts().forEach(function (ch) {
            try {
                ch.update();
            } catch (e) {
                console.warn('No se pudo actualizar gráfico:', e);
            }
        });
    });

    // Exponer las funciones por si en el futuro queremos llamarlas
    // desde otro sitio (ej. tras crear un gráfico nuevo dinámicamente)
    window.IsorgaCharts = {
        applyDefaults: applyIsorgaChartDefaults,
        getActiveCharts: getActiveCharts
    };
})();
