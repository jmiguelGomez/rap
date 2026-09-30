(function () {
    'use strict';

    function normalizar(valor) {
        return String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var app = document.getElementById('m21_org_aplicacion');
        if (!app) return;

        var arbol = document.getElementById('m21_org_arbol');
        var items = arbol ? Array.prototype.slice.call(arbol.querySelectorAll('.m21-org2-item')) : [];
        var buscador = document.getElementById('m21_org_buscar');
        var resultado = document.getElementById('m21_org_resultado');
        var vista = document.getElementById('m21_org_vista');

        if (vista && arbol) vista.addEventListener('click', function () {
            var esGrafo = app.classList.toggle('m21-org2-grafo');
            vista.classList.toggle('btn-primary', esGrafo);
            vista.classList.toggle('btn-outline-primary', !esGrafo);
            vista.querySelector('span').textContent = esGrafo ? vista.dataset.vistaLista : vista.dataset.vistaGrafo;
            vista.querySelector('i').className = esGrafo ? 'fas fa-list me-1' : 'fas fa-sitemap me-1';
        });

        function actualizarToggle(item) {
            var boton = item.querySelector(':scope > .m21-org2-node .m21-org2-toggle');
            if (boton) boton.setAttribute('aria-expanded', item.classList.contains('is-collapsed') ? 'false' : 'true');
        }

        items.forEach(function (item) {
            var boton = item.querySelector(':scope > .m21-org2-node .m21-org2-toggle');
            if (!boton) return;
            actualizarToggle(item);
            boton.addEventListener('click', function () {
                item.classList.toggle('is-collapsed');
                actualizarToggle(item);
            });
        });

        function filtrar() {
            var termino = normalizar(buscador ? buscador.value : '');
            var visibles = 0;
            items.forEach(function (item) {
                item.hidden = false;
                item.classList.remove('is-search-match');
            });
            if (!termino) {
                if (resultado) resultado.textContent = '';
                return;
            }
            items.forEach(function (item) {
                var coincide = normalizar(item.dataset.search).indexOf(termino) !== -1;
                item.hidden = !coincide;
                if (coincide) {
                    visibles++;
                    item.classList.add('is-search-match');
                    var padre = item.parentElement ? item.parentElement.closest('.m21-org2-item') : null;
                    while (padre) {
                        padre.hidden = false;
                        padre.classList.remove('is-collapsed');
                        actualizarToggle(padre);
                        padre = padre.parentElement ? padre.parentElement.closest('.m21-org2-item') : null;
                    }
                }
            });
            if (resultado) {
                resultado.textContent = visibles > 0
                    ? visibles + ' ' + app.dataset.coincidencias
                    : app.dataset.sinCoincidencias;
            }
        }
        if (buscador) buscador.addEventListener('input', filtrar);

        var expandir = document.getElementById('m21_org_expandir');
        if (expandir) expandir.addEventListener('click', function () {
            items.forEach(function (item) { item.classList.remove('is-collapsed'); actualizarToggle(item); });
        });
        var contraer = document.getElementById('m21_org_contraer');
        if (contraer) contraer.addEventListener('click', function () {
            items.forEach(function (item) {
                item.classList.toggle('is-collapsed', Number(item.dataset.depth || 1) >= 2 && !!item.querySelector(':scope > ul'));
                actualizarToggle(item);
            });
        });

        var plazaForm = document.getElementById('m21_org_plaza_form');
        var nuevaPlaza = document.getElementById('m21_org_nueva_plaza');
        function prepararPlaza(datos) {
            if (!plazaForm) return;
            plazaForm.reset();
            document.getElementById('m21_org_plaza_id').value = datos.id || '0';
            document.getElementById('m21_org_plaza_codigo').value = datos.codigo || '';
            document.getElementById('m21_org_plaza_nombre').value = datos.nombre || '';
            document.getElementById('m21_org_plaza_puesto').value = datos.puestoId || '';
            document.getElementById('m21_org_plaza_padre').value = datos.padreId && datos.padreId !== '0' ? datos.padreId : '';
            document.getElementById('m21_org_plaza_orden').value = datos.orden || '0';
        }
        if (nuevaPlaza) nuevaPlaza.addEventListener('click', function () { prepararPlaza({}); });
        document.querySelectorAll('.m21-org2-editar').forEach(function (boton) {
            boton.addEventListener('click', function () {
                prepararPlaza({
                    id: boton.dataset.id,
                    codigo: boton.dataset.codigo,
                    nombre: boton.dataset.nombre,
                    puestoId: boton.dataset.puestoId,
                    padreId: boton.dataset.padreId,
                    orden: boton.dataset.orden
                });
            });
        });

        var datosAsignaciones = document.getElementById('m21_org_asignaciones');
        var asignaciones = [];
        if (datosAsignaciones) {
            try { asignaciones = JSON.parse(datosAsignaciones.textContent || '[]'); } catch (error) { asignaciones = []; }
        }
        document.querySelectorAll('.m21-org2-colocar').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var plazaId = boton.dataset.plazaId;
                var puestoId = Number(boton.dataset.puestoId || 0);
                var select = document.getElementById('m21_org_ocupacion_asignacion');
                var fecha = document.getElementById('m21_org_ocupacion_fecha');
                document.getElementById('m21_org_ocupacion_plaza_id').value = plazaId;
                document.getElementById('m21_org_ocupacion_plaza').textContent = boton.dataset.plaza || '';
                select.replaceChildren();
                var vacia = document.createElement('option');
                vacia.value = '';
                vacia.textContent = '—';
                select.appendChild(vacia);
                asignaciones.filter(function (fila) { return Number(fila.puesto_id) === puestoId; }).forEach(function (fila) {
                    var opcion = document.createElement('option');
                    opcion.value = fila.id;
                    opcion.textContent = fila.nombre;
                    opcion.dataset.desde = fila.fecha_desde;
                    opcion.dataset.hasta = fila.fecha_hasta || '';
                    select.appendChild(opcion);
                });
                select.disabled = select.options.length === 1;
                select.addEventListener('change', function () {
                    var opcion = select.options[select.selectedIndex];
                    if (!opcion) return;
                    fecha.min = opcion.dataset.desde || '';
                    fecha.max = opcion.dataset.hasta || '';
                    if (fecha.min && fecha.value < fecha.min) fecha.value = fecha.min;
                    if (fecha.max && fecha.value > fecha.max) fecha.value = fecha.max;
                }, { once: true });
            });
        });
    });
}());
