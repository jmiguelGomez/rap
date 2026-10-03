<?php
// Cierra <main> y carga el JS común (como app/inc_footer.php de ISORGA): Bootstrap, DataTables con
// autoinicio en .datatable-compact, DT_LANG_URL, SweetAlert2, Chart.js y confirmarBorrado().
$rapDtIdioma = ['es' => 'es-ES', 'ca' => 'ca', 'en' => 'en-GB', 'fr' => 'fr-FR'][$lang ?? 'es'] ?? 'es-ES';
?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php // Gráficos: la misma versión que audit (pie.php) y los valores por defecto de ISORGA (copia de assets/js/isorga-chart-defaults.js) ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="/assets/js/isorga-chart-defaults.js"></script>
<?php /*
  FullCalendar 6.1.15 — lo pide `calendario.php` (y en M19 también `index.php`), que llegan
  COPIADAS de ISORGA y hacen `new FullCalendar.Calendar(...)`. Sin estos scripts la pantalla
  entraba por su propio `catch` y enseñaba el aviso de error en vez del calendario.

  🔴 SIN hojas de estilo, y no es un olvido: FullCalendar 6 **inyecta su CSS desde el JS**.
     Los cinco `<link ... index.global.min.css>` que `app/includes/head.php` de ISORGA sigue
     cargando devuelven **404** — medido el 2026-09-30 —, así que copiarlos aquí habría sido
     añadir cinco peticiones muertas a cada página.

  Cinco plugins, no los siete de ISORGA: las pantallas usan `dayGridMonth`, `listYear` y
  `themeSystem: 'bootstrap5'`. `timegrid` e `interaction` no hacen falta (nada arrastra ni
  selecciona; `eventClick` funciona sin el plugin de interacción en la v6).
*/ ?>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/list@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales-all.global.min.js"></script>
<script>
var DT_LANG_URL = 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/<?= $rapDtIdioma ?>.json';
function initDataTableCompact(id, extra) {
    var el = document.getElementById(id);
    if (!el || $.fn.DataTable.isDataTable(el)) return;
    $(el).DataTable(Object.assign({ language: { url: DT_LANG_URL }, pageLength: 25, lengthMenu: [10, 25, 50, 100], order: [], autoWidth: false }, extra || {}));
}
function confirmarBorrado(url, texto) {
    Swal.fire({ title: <?= json_encode($language['CONFIRMAR'] ?? '¿Seguro?') ?>, text: texto || '', icon: 'warning', showCancelButton: true,
                confirmButtonText: <?= json_encode($language['ELIMINAR'] ?? 'Eliminar') ?>, cancelButtonText: <?= json_encode($language['CANCELAR'] ?? 'Cancelar') ?>,
                confirmButtonColor: '#dc3545' }).then(function (r) { if (r.isConfirmed) window.location = url; });
    return false;
}
$(function () {
    $('table.datatable-compact').each(function () { if (this.id) initDataTableCompact(this.id); });
});
</script>
<?php
// Fase 2 · IA: el panel de ISORGI solo en modo formulario (lo abre «Definir puesto con ISORGI»).
if (is_file(__DIR__ . '/dbchat/ia_gestion.php')) require_once __DIR__ . '/dbchat/ia_gestion.php';
if (function_exists('isorgi_autorizado') && isorgi_autorizado()) { // modo formulario: sin $_chatAsist no entra el motor del chat (ia_motor.php)
    $_chatAsist = null; $_hayAyuda = false; $_rutaAyuda = '';
    include __DIR__ . '/includes/chat_asistente.php';
}
