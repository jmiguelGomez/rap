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
    <?php // ISORGI (2026-10-03, Juan Miguel): la MASCOTA, como en la cabecera de ISORGA (index_2.php): solo la imagen
          //    3D a 2.25rem, sin texto ni recuadro. De momento SIN ninguna acción; se irá conectando (gancho data-isorgi).
          //    En todas las apps menos cae y bjc (legal ya tiene el suyo, con su panel). Imagen reducida a 72 px (9,7 KB;
          //    el original isorgi_3d_sin.png pesa 1,4 MB). ?>
    <button type="button" class="app-isorgi" data-isorgi title="Isorgi" aria-label="Isorgi"
            style="background:none;border:0;padding:0 .25rem;line-height:0;cursor:pointer">
        <img src="/assets/img/isorgi_3d_72.png" alt="" style="width:2.25rem;height:2.25rem;object-fit:contain;display:block">
    </button>
    <button class="app-nav-btn" type="button" id="quick-open" aria-haspopup="dialog"><i class="bi bi-search" aria-hidden="true"></i><span><?= $sbE($language['RAP_IR_A'] ?? 'Ir a…') ?></span><kbd>Ctrl K</kbd></button>
    <?php
    // ── Selector de centro ───────────────────────────────────────────────────────
    // Petición de Juan Miguel (2026-10-01): en vez de salir a la pantalla de elección,
    // un desplegable aquí mismo con TODOS mis centros. Los que tienen el módulo de esta
    // app a 0 **salen igual, pero no se pueden pulsar**.
    //
    // 🔴 El número de módulo sale de APP_MODULO y se interpola con (int): el nombre de
    //    columna NUNCA viene de la petición, así que no hay por dónde inyectar.
    // 🔴 Pulsar no concede nada. Lleva a /auth/centro_envio.php, que vuelve a comprobar
    //    en base el permiso del módulo antes de tocar la sesión: el «no pulsable» es
    //    cortesía para la persona, no la puerta. La puerta sigue donde estaba.
    // ⚠️ Se enseña a todo el mundo, también a quien entró desde ISORGA: antes algunas apps
    //    lo escondían en ese caso (`!$sbDesdeIsorga`) y es justo lo que se ha pedido activar.
    $sbMod = (int) (defined('APP_MODULO') ? APP_MODULO : 0);
    $sbCentros = [];
    if ($sbMod > 0) {
        // 🔴 `GROUP BY` + `MAX`, no un SELECT pelado: `usuariosxcentros` TIENE filas repetidas
        //    para la misma pareja usuario-centro — medido el 2026-10-01: 23 parejas con 33 filas
        //    de más, hasta 7 para una sola (usuario 2553 en el centro 4246). Sin agrupar, el mismo
        //    centro salía dos veces en el desplegable (visto en flota con el usuario 2). `MAX` se
        //    queda además con el nivel más alto, que es lo que vale cuando las copias discrepan.
        $sbSt = Conectar::varias(
            'SELECT c.centroId, c.centroNombre, MAX(uc.modulo' . $sbMod . ') AS nivel
               FROM usuariosxcentros uc
               JOIN centros c ON c.centroId = uc.ucCentro
              WHERE uc.ucUsuario = ? AND c.centroActivo = 1
              GROUP BY c.centroId, c.centroNombre
              ORDER BY c.centroNombre',
            [(int) ($_SESSION['user']['NoUsuario'] ?? 0)]
        );
        $sbCentros = $sbSt ? $sbSt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
    $sbActualId = (int) ($_SESSION['centro']['NoCentro'] ?? 0);
    ?>
    <?php if (count($sbCentros) > 1): ?>
    <?php // Desplegable de Bootstrap (ya cargado en app/inc_footer.php) vestido con `.app-cfg-menu`,
          // que estaba en el CSS y no la usaba nadie. Cero JavaScript propio. ?>
    <div class="dropdown topbar-centro-sel">
        <button class="app-nav-btn" type="button" data-bs-toggle="dropdown" data-bs-display="static"
                aria-expanded="false" title="<?= $sbE($language['RAP_CAMBIAR_CENTRO'] ?? 'Cambiar') ?>">
            <i class="bi bi-building"></i><span><?= $sbE($sbCentro) ?></span><i class="bi bi-chevron-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end app-cfg-menu sb-centros">
            <li><h6 class="dropdown-header"><?= $sbE($language['RAP_ELEGIR_CENTRO'] ?? 'Elige el centro') ?></h6></li>
            <?php foreach ($sbCentros as $sbC): $sbCid = (int) $sbC['centroId']; ?>
                <?php if ((int) $sbC['nivel'] < 1): ?>
            <li><span class="dropdown-item disabled" aria-disabled="true"
                      title="<?= $sbE($language['RAP_CENTRO_SIN_MODULO'] ?? 'En este centro no tienes este módulo') ?>">
                <i class="bi bi-lock"></i> <?= $sbE($sbC['centroNombre']) ?>
            </span></li>
                <?php elseif ($sbCid === $sbActualId): ?>
            <li><span class="dropdown-item active" aria-current="true"><i class="bi bi-check2"></i> <?= $sbE($sbC['centroNombre']) ?></span></li>
                <?php else: ?>
            <li><a class="dropdown-item" href="/auth/centro_envio.php?id=<?= $sbCid ?>"><i class="bi bi-building"></i> <?= $sbE($sbC['centroNombre']) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
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
        <?php
        // «Cambio usuario» (2026-10-03, Juan Miguel): con soporte, el ICONO Y EL NOMBRE del usuario son el enlace (lo mismo
        // que «Cambio usuario» en el menú de usuario de ISORGA). Soporte = el usuario de la app tiene usuarios.soporte = 1,
        // o en ISORGA la sesión es de soporte aunque esté actuando como otra persona (marca `s` firmada en la entrada,
        // auth/sso.php → $_SESSION['soporte_isorga']). Lleva a la pantalla de ISORGA de siempre (0/usuarios_soporte.php),
        // diciendo de qué app viene; tras elegir persona y centro, ISORGA vuelve a entrar aquí como ella. Toda la
        // comprobación la hacen 0/usuarios_soporte.php y 0/login_entrada.php. Sin soporte, el nombre se ve como siempre.
        $sbSoporte = !empty($_SESSION['soporte_isorga']);
        if (!$sbSoporte && (int) ($_SESSION['user']['NoUsuario'] ?? 0) > 0) {
            $sbSop = Conectar::una('SELECT soporte FROM usuarios WHERE usuarioId = ? AND usuarioActivo = 1', [(int) $_SESSION['user']['NoUsuario']]);
            $sbSoporte = $sbSop && (int) $sbSop['soporte'] === 1;
        }
        $sbCambio = (defined('APP_URL_ISORGA') ? APP_URL_ISORGA : 'https://isorga.com') . '/0/usuarios_soporte.php?app=' . basename(dirname(__DIR__));
        ?>
        <?php if ($sbSoporte): ?>
        <a class="sb-user sb-user-cambio" href="<?= $sbE($sbCambio) ?>" title="Cambio usuario" style="text-decoration:none;cursor:pointer"><span class="sb-ico"><i class="bi bi-person"></i></span><span class="sb-txt"><?= $sbE($sbNombre) ?></span></a>
        <?php else: ?>
        <div class="sb-user"><span class="sb-ico"><i class="bi bi-person"></i></span><span class="sb-txt"><?= $sbE($sbNombre) ?></span></div>
        <?php endif; ?>
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
