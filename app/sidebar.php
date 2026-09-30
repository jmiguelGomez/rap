<?php
// Barra superior + menú lateral colapsable, con el marcado y las clases de audit.isorga.com
// (app/sidebar.php de allí): topbar, sb-* e «Ir a…» (Ctrl K), que monta assets/js/workspace.js.
// Las ENTRADAS son las del módulo 42 «Envases y RAP» de ISORGA (sidebar_3.php), con /rap/ en vez de ../42/.
// Se generan desde isorga-net/scripts/satelites/rap.php (satelite_crear.php); para cambiarlas, editar allí y relanzar, o aquí a mano.
$sbRuta    = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$sbActual  = basename($sbRuta);
$sbCentro  = (string) ($_SESSION['centro']['NbCentro'] ?? '');
$sbNombre  = (string) ($_SESSION['user']['NbUsuario'] ?? '');
$sbVarios  = (int) ($_SESSION['centro']['MultiCentros'] ?? 0) === 1;
$sbDesdeIsorga = !empty($_SESSION['entrada_isorga']);
$sbE = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sbMay = static fn($v) => mb_strtoupper((string) $v, 'UTF-8');
// Las traducciones de ISORGA vienen unas en MAYÚSCULAS y otras no: en el menú, como en audit, frase normal
$sbFrase = static fn($v) => mb_strtoupper(mb_substr(mb_strtolower((string) $v, 'UTF-8'), 0, 1, 'UTF-8'), 'UTF-8') . mb_substr(mb_strtolower((string) $v, 'UTF-8'), 1, null, 'UTF-8');

$sbMenu = [
    ['grid-1x2', $language['RAP_INICIO'] ?? 'Inicio', '/rap/dashboard.php'],
];

// Título de la barra: la entrada del menú en la que se está; si no hay, el nombre de la app
$sbTitulo = APP_NOMBRE;
foreach ($sbMenu as [, $t, $u]) { if (basename($u) === $sbActual) { $sbTitulo = $sbFrase($t); } }
?>
<script>(function(){try{if(localStorage.getItem('rap-sidebar')==='collapsed')document.documentElement.classList.add('sb-collapsed');}catch(e){}})();</script>

<header class="topbar">
    <button type="button" class="sb-burger" id="sb-burger" aria-label="<?= $sbE($language['RAP_ABRIR_MENU'] ?? 'Abrir menú') ?>" aria-controls="sidebar" aria-expanded="false">&#9776;</button>
    <span class="topbar-title"><?= $sbE($sbTitulo) ?></span>
    <div class="topbar-spacer"></div>
    <button class="app-nav-btn" type="button" id="quick-open" aria-haspopup="dialog"><i class="bi bi-search" aria-hidden="true"></i><span><?= $sbE($language['RAP_IR_A'] ?? 'Ir a…') ?></span><kbd>Ctrl K</kbd></button>
    <?php if ($sbVarios): ?>
    <a class="app-nav-btn topbar-centre" href="/auth/centro.php" title="<?= $sbE($language['RAP_CAMBIAR_CENTRO'] ?? 'Cambiar') ?>">
        <i class="bi bi-building"></i><span><?= $sbE($sbCentro) ?></span><i class="bi bi-arrow-left-right"></i>
    </a>
    <?php else: ?>
    <span class="app-nav-btn topbar-centre" style="pointer-events:none"><i class="bi bi-building"></i><span><?= $sbE($sbCentro) ?></span></span>
    <?php endif; ?>
    <?php // Quien entró desde ISORGA (auth/sso.php) no «sale»: vuelve allí ?>
    <a class="app-nav-btn" href="/auth/logout.php"><?= $sbE($sbDesdeIsorga ? ($language['RAP_VOLVER_ISORGA'] ?? 'Volver a ISORGA') : ($language['RAP_SALIR'] ?? 'Salir')) ?></a>
</header>

<aside class="sidebar" id="sidebar">
    <div class="sb-head">
        <a href="/index.php" class="sb-brand is-dominio" title="rap.isorga.com">
            <div class="sb-logo is-marca"><img src="/assets/img/logo.png" alt="ISORGA" width="28" height="28"></div>
            <span class="sb-brand-txt">RAP</span>
        </a>
        <button type="button" class="sb-collapse" id="sb-collapse" aria-label="<?= $sbE($language['RAP_COLAPSAR_MENU'] ?? 'Colapsar menú') ?>" aria-controls="sidebar" aria-expanded="true">&#171;</button>
        <button type="button" class="sb-close" id="sb-close" aria-label="<?= $sbE($language['RAP_CERRAR_MENU'] ?? 'Cerrar menú') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>

    <nav class="sb-nav" aria-label="<?= $sbE($language['RAP_NAVEGACION'] ?? 'Navegación principal') ?>">
        <span class="sb-section-label"><?= $sbE($sbMay($language['RAP_ESPACIO'] ?? 'Espacio de trabajo')) ?></span>
        <?php foreach ($sbMenu as [$ico, $txt, $url]): $activo = basename($url) === $sbActual; $txt = $sbFrase($txt); ?>
        <a href="<?= $sbE($url) ?>" class="sb-link<?= $activo ? ' is-active' : '' ?>"<?= $activo ? ' aria-current="page"' : '' ?> title="<?= $sbE($txt) ?>">
            <span class="sb-ico" aria-hidden="true"><i class="bi bi-<?= $sbE($ico) ?>"></i></span>
            <span class="sb-txt"><?= $sbE($txt) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($sbNombre !== ''): ?>
    <div class="sb-foot">
        <div class="sb-user"><span class="sb-ico"><i class="bi bi-person"></i></span><span class="sb-txt"><?= $sbE($sbNombre) ?></span></div>
    </div>
    <?php endif; ?>
</aside>

<div class="sb-overlay" id="sb-overlay"></div>

<dialog id="quick-nav" aria-labelledby="quick-title">
    <div class="quick-head"><div><p class="eyebrow"><?= $sbE($sbMay($language['RAP_ACCESOS_RAPIDOS'] ?? 'Accesos rápidos')) ?></p><h2 id="quick-title"><?= $sbE($language['RAP_DONDE_IR'] ?? '¿Dónde quieres ir?') ?></h2></div><button type="button" class="btn btn-light" id="quick-close" aria-label="<?= $sbE($language['RAP_CERRAR'] ?? 'Cerrar') ?>"><i class="bi bi-x-lg"></i></button></div>
    <label class="visually-hidden" for="quick-search"><?= $sbE($language['RAP_BUSCAR_PANTALLA'] ?? 'Buscar pantalla') ?></label>
    <input type="search" class="form-control" id="quick-search" placeholder="<?= $sbE($language['RAP_BUSCAR_PANTALLA'] ?? 'Buscar pantalla') ?>" autocomplete="off">
    <nav id="quick-results" aria-label="<?= $sbE($language['RAP_ACCESOS_RAPIDOS'] ?? 'Accesos rápidos') ?>"></nav>
    <p id="quick-empty" hidden><?= $sbE($language['RAP_SIN_PANTALLAS'] ?? 'No hay pantallas con ese nombre.') ?></p>
    <p class="quick-help"><?= $sbE($language['RAP_AYUDA_IR_A'] ?? 'Selecciona un destino · Esc para cerrar') ?></p>
</dialog>
