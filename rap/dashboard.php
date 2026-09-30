<?php
/**
 * rap/dashboard.php — La pantalla de entrada de rap.isorga.com: cifras del centro de sesión (base de ISORGA,
 * tablas del módulo 42 filtradas por centro, §0.1) y el menú lateral lleva a las pantallas portadas.
 * Generado por isorga-net/scripts/satelite_crear.php desde scripts/satelites/rap.php.
 */
declare(strict_types=1);

include '../app/inc_head.php';
include '../app/inc_body.php';

$centro = (int) ($_SESSION['centro']['NoCentro'] ?? 0);
$nivel  = (int) ($_SESSION['centro']['modulo42'] ?? 0);
$cuantos = static function (string $sql, array $p): int {
    $f = Conectar::una($sql, $p);
    return (int) ($f['n'] ?? 0);
};
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container-fluid py-3">
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-body-secondary small text-uppercase"><?= $h($language['RAP_TU_ACCESO'] ?? 'Tu acceso') ?></div>
                <div class="display-6"><?= $nivel ?></div>
                <div class="small text-body-secondary"><?= $h($language['RAP_NIVEL_CENTRO'] ?? 'nivel en este centro') ?></div>
            </div></div>
        </div>
    </div>
</div>
<?php include '../app/inc_footer.php'; ?>
