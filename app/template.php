<?php
// Mismo contrato que app/template.php de ISORGA: $titulo, $breadcrumb, $enlace_volver.
// 🔴 Deja DOS divs abiertos (la barra del título y la de botones) que la página cierra con </div></div>.
// Marcado de audit.isorga.com (page-heading, btn-volver con bi-arrow-left).
if (!isset($titulo))        $titulo = '';
if (!isset($breadcrumb))    $breadcrumb = false;
if (!isset($enlace_volver)) $enlace_volver = '/index.php';

// 🔴 Las páginas que abre el MENÚ LATERAL no llevan «volver»: son el primer nivel y no hay
//    a dónde volver. El enlace viene de ISORGA, donde a esas mismas pantallas se llega desde
//    otra (un cuadro de mando, un listado), así que allí sí tiene sentido.
//    Se decide aquí, y no pantalla a pantalla, por dos razones: son siete entradas hoy y
//    cualquiera que se añada mañana lo hereda sin acordarse; y las pantallas se GENERAN desde
//    ISORGA (`scripts/*_portar.php`), así que un cambio en ellas se perdería en el siguiente
//    porte. `$sbMenu` lo deja `app/sidebar.php`, que `inc_body.php` incluye antes que esto.
if (!empty($sbMenu) && is_array($sbMenu)) {
    $tplAqui = basename((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
    foreach ($sbMenu as $tplE) {
        if ($tplAqui !== '' && basename((string) ($tplE[2] ?? '')) === $tplAqui) { $breadcrumb = false; break; }
    }
}
?>
<div class="page-heading d-flex justify-content-between align-items-center mb-3 mt-2 flex-wrap gap-2">
    <div>
        <?php if ($breadcrumb): ?>
        <ul class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="<?= htmlspecialchars((string) $enlace_volver, ENT_QUOTES) ?>" id="link_volver" class="btn-volver">
                    <i class="bi bi-arrow-left"></i> <?= $language['VOLVER'] ?? 'Volver' ?>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        <h1 class="page-header mb-0"><?= $titulo ?></h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
