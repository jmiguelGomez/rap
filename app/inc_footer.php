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
