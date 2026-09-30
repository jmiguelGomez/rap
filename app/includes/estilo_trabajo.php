<?php
// COPIA de isorga-net/app/includes/estilo_trabajo.php (satelite_crear.php): mismo loader que ISORGA, config propia en app/config/estilo_trabajo.json.
/**
 * app/includes/estilo_trabajo.php — Aplica la config de trabajo (edición rápida).
 *
 * Fuente única: app/config/estilo_trabajo.json. Se incluye en head.php (plantilla
 * de toda la plataforma), DESPUÉS del CSS base, así que gana por orden.
 *
 * Palancas:
 *   - boton_primario_color / _hover → recolorea .btn-primary (Bootstrap lo trae fijo).
 *   - tablas_motor ("datatables"|"tabulator") → constante PHP ESTILO_TABLAS_MOTOR
 *     + window.ESTILO_TABLAS_MOTOR, para que cada página elija su tabla al migrarla.
 *   - tablas_excel_global (true) → aplica el look Excel (.isg-excel) a TODAS las
 *     .table. Por defecto false: el Excel es opt-in por tabla con class="isg-excel".
 *
 * Vacío/neutro = 0 cambios. Saneo estricto: solo hex válidos; nada de cerrar tags.
 */

$__cfgTrab = @json_decode((string) @file_get_contents(__DIR__ . '/../config/estilo_trabajo.json'), true);
if (!is_array($__cfgTrab)) $__cfgTrab = [];

/** Resuelve un color: acepta hex (#rgb / #rrggbb) o nombre Bootstrap (primary,
 *  success, danger, warning, info, secondary, dark, light). Devuelve hex o ''. */
$__color = static function ($v): string {
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v)) return $v;
    $bs = [
        'primary' => '#0d6efd', 'secondary' => '#6c757d', 'success' => '#198754',
        'danger'  => '#dc3545', 'warning'   => '#ffc107', 'info'    => '#0dcaf0',
        'dark'    => '#212529', 'light'     => '#f8f9fa',
    ];
    return $bs[strtolower($v)] ?? '';
};

// ── Motor de tablas (constante + global JS) ─────────────────────────────────
$__motor = ($__cfgTrab['tablas_motor'] ?? 'datatables') === 'tabulator' ? 'tabulator' : 'datatables';
if (!defined('ESTILO_TABLAS_MOTOR')) define('ESTILO_TABLAS_MOTOR', $__motor);
echo '<script>window.ESTILO_TABLAS_MOTOR = ' . json_encode($__motor) . ';</script>' . "\n";

// ── Color del botón primario ────────────────────────────────────────────────
$__btn      = $__color($__cfgTrab['boton_primario_color'] ?? '');
$__btnHover = $__color($__cfgTrab['boton_primario_hover'] ?? '');

/** Hex → "r, g, b" (para --bs-primary-rgb, que es lo que usan .bg-primary/.text-primary). */
$__rgb = static function (string $hex): string {
    $h = ltrim($hex, '#');
    if (strlen($h) === 3) $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    return hexdec(substr($h, 0, 2)) . ', ' . hexdec(substr($h, 2, 2)) . ', ' . hexdec(substr($h, 4, 2));
};

// ── Tablas Excel global (opt-in normalmente) ────────────────────────────────
$__excelGlobal = !empty($__cfgTrab['tablas_excel_global']);

if ($__btn === '' && !$__excelGlobal) return;   // nada que emitir
?>
<style id="isg-estilo-trabajo">
<?php if ($__btn !== ''): ?>
:root {
    --bs-primary: <?= $__btn ?>;
    --bs-primary-rgb: <?= $__rgb($__btn) ?>;
    --isg-accent: <?= $__btn ?>;
<?php if ($__btnHover !== ''): ?>    --isg-accent-strong: <?= $__btnHover ?>;
<?php endif; ?>    --bs-link-color: <?= $__btn ?>;
<?php if ($__btnHover !== ''): ?>    --bs-link-hover-color: <?= $__btnHover ?>;
<?php endif; ?>}
.btn-primary {
    --bs-btn-bg: <?= $__btn ?>;
    --bs-btn-border-color: <?= $__btn ?>;
    --bs-btn-hover-bg: <?= $__btnHover ?: $__btn ?>;
    --bs-btn-hover-border-color: <?= $__btnHover ?: $__btn ?>;
    --bs-btn-active-bg: <?= $__btnHover ?: $__btn ?>;
    --bs-btn-active-border-color: <?= $__btnHover ?: $__btn ?>;
    --bs-btn-disabled-bg: <?= $__btn ?>;
    --bs-btn-disabled-border-color: <?= $__btn ?>;
}
<?php endif; ?>
<?php if ($__excelGlobal): ?>
/* tablas_excel_global=true: look Excel a TODAS las tablas (rejilla + filas finas). */
table.table { font-size: .8rem; border: 1px solid var(--isg-border-solid, #d0d7de); }
table.table thead th { text-transform: uppercase; letter-spacing: .02em; font-size: .72rem; font-weight: 700; padding: .35rem .6rem !important; border: 1px solid var(--isg-border-solid, #d0d7de); white-space: nowrap; }
table.table tbody td { padding: .15rem .6rem !important; line-height: 1.55; border: 1px solid var(--isg-border, rgba(0,0,0,.08)); vertical-align: middle; }
table.table tbody tr:nth-child(even) td { background: rgba(0, 0, 0, .02); }
<?php endif; ?>
</style>
