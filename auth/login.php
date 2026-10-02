<?php
// Login con la cuenta de ISORGA. Aspecto: la pantalla partida de audit.isorga.com/auth/login.php
// (foto a la izquierda, tarjeta a la derecha); el CSS está en assets/css/acceso.css.
// 🔴 Con APP_LOGIN_CONTRASENA = false (app/config/config.php) no se pinta el formulario: a la app se entra solo
// desde ISORGA (auth/sso.php). El formulario se queda aquí para poder reactivarlo cambiando la constante.
$RAP_PUBLICA = true;
require_once __DIR__ . '/../app/sesion.php';
require_once __DIR__ . '/../app/compat.php';
if (!empty($_SESSION['user']['autenticado'])) { header('Location: /index.php'); exit; }
$_SESSION['csrf_login'] = $_SESSION['csrf_login'] ?? bin2hex(random_bytes(16));
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// Sin login con contraseña no hay pantalla de acceso propia: sin sesión, por inactividad o de baja, al login de ISORGA
// (con su aviso de sesión caducada cuando toca). Así ninguna entrada por URL se queda en la app.
if (!APP_LOGIN_CONTRASENA) {
    $inactividad = (($_SESSION['aviso'] ?? $_GET['aviso'] ?? '') === 'inactividad');
    unset($_SESSION['aviso']);
    header('Location: ' . APP_URL_ISORGA . '/login.php' . ($inactividad ? '?timeout=1' : ''));
    exit;
}
$avisos = ['error' => $language['RAP_ERROR_LOGIN'] ?? '', 'baja' => $language['RAP_BAJA'] ?? '',
           'inactividad' => $language['RAP_INACTIVIDAD'] ?? '', 'sincentro' => $language['RAP_SIN_CENTRO'] ?? ''];
$aviso = $avisos[$_GET['aviso'] ?? ($_SESSION['aviso'] ?? '')] ?? null;
unset($_SESSION['aviso']);
require __DIR__ . '/../app/acceso_cabeza.php';
?>
<div class="login-box">
    <div class="login-logo">
        <div class="marca-logo"><img src="/assets/img/logo.png" alt="ISORGA"></div>
        <h1 class="dominio"><?= $h(APP_NOMBRE) ?></h1>
        <p><?= $h($language['RAP_SUBTITULO'] ?? '') ?></p>
    </div>
    <?php if ($aviso): ?><div class="alert alert-danger mb-3"><?= $h($aviso) ?></div><?php endif; ?>
    <?php if (APP_LOGIN_CONTRASENA): ?>
    <form method="post" action="/auth/login_envio.php" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf_login']) ?>">
        <div class="mb-3">
            <label class="form-label" for="mail"><?= $h($language['RAP_CORREO'] ?? 'Correo') ?></label>
            <input class="form-control" type="email" id="mail" name="mail" required autofocus autocomplete="username" value="<?= $h($_GET['mail'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="pass"><?= $h($language['RAP_CONTRASENA'] ?? 'Contraseña') ?></label>
            <input class="form-control" type="password" id="pass" name="pass" required autocomplete="current-password">
        </div>
        <a href="/auth/recuperar.php" class="auth-link"><?= $h($language['RAP_OLVIDE'] ?? '') ?></a>
        <button class="btn btn-login" type="submit"><i class="fas fa-sign-in-alt me-1"></i> <?= $h($language['RAP_ENTRAR'] ?? 'Entrar') ?></button>
    </form>
    <?php else: ?>
    <p class="text-body-secondary"><?= $h($language['RAP_SOLO_ISORGA'] ?? '') ?></p>
    <?php endif; ?>
    <a class="btn btn-outline-primary w-100 mt-3" href="https://isorga.com/0/rap_entrar.php">
        <?= $h($language['RAP_ENTRAR'] ?? 'Entrar') ?> · ISORGA
    </a>
</div>
<?php require __DIR__ . '/../app/acceso_pie.php'; ?>
