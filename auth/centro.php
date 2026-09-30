<?php
// Elegir centro entre los activos con modulo42 >= 1. Uno solo: directo. Ninguno: fuera, con aviso.
// Mismo aspecto que el login (pantalla partida de audit.isorga.com).
$RAP_SIN_CENTRO = true;
require_once __DIR__ . '/../app/sesion.php';
require_once __DIR__ . '/../app/compat.php';
require_once __DIR__ . '/../app/acceso_lib.php';
$centros = rap_centros_disponibles((int) $_SESSION['user']['NoUsuario']);
if (count($centros) === 1) { header('Location: /auth/centro_envio.php?id=' . (int) $centros[0]['centroId']); exit; }
if (!$centros) { $_SESSION = ['aviso' => 'sincentro']; session_regenerate_id(true); header('Location: /auth/login.php'); exit; }
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require __DIR__ . '/../app/acceso_cabeza.php';
?>
<div class="login-box">
    <div class="login-logo">
        <div class="marca-logo"><img src="/assets/img/logo.png" alt="ISORGA"></div>
        <h1 class="dominio"><?= $h($language['RAP_ELEGIR_CENTRO'] ?? 'Elige el centro') ?></h1>
        <p><?= $h($_SESSION['user']['NbUsuario'] ?? '') ?></p>
    </div>
    <div class="centros-lista">
        <?php foreach ($centros as $c): ?>
        <a href="/auth/centro_envio.php?id=<?= (int) $c['centroId'] ?>"><span><?= $h($c['centroNombre']) ?></span><i class="bi bi-chevron-right"></i></a>
        <?php endforeach; ?>
    </div>
    <a href="/auth/logout.php" class="auth-salir"><?= $h($language['RAP_SALIR'] ?? 'Salir') ?></a>
</div>
<?php require __DIR__ . '/../app/acceso_pie.php'; ?>
