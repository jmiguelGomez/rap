<?php
// =====================================================================
// app/sesion.php — ÚNICO sitio donde se abre y se valida la sesión de rap.isorga.com.
//
// La identidad es la de ISORGA (tabla `usuarios` de la base `isorga`) y la sesión lleva las MISMAS
// claves que en ISORGA ($_SESSION['user'][NoUsuario, NbUsuario, autenticado, lang…] y
// $_SESSION['centro'][NoCentro, NbCentro, moduloN…]) para que el código portado de 21/ funcione tal cual.
//
// Las páginas de auth/ ponen $RAP_PUBLICA = true antes de incluir este fichero (no redirigen).
// En cada petición se revalida: persona activa y modulo42 >= 1 en el centro elegido. Si no, fuera.
// =====================================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure',   isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime',  '3600');
    session_start();
}
require_once __DIR__ . '/Conectar.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/funciones.php';

// Cierre por inactividad
if (!empty($_SESSION['user']['autenticado'])) {
    $ultima = (int) ($_SESSION['ultima_actividad'] ?? 0);
    if ($ultima > 0 && (time() - $ultima) >= APP_INACTIVIDAD_MINUTOS * 60) {
        $_SESSION = ['aviso' => 'inactividad'];
        if (!headers_sent()) session_regenerate_id(true);
    }
}
$_SESSION['ultima_actividad'] = time();

if (empty($RAP_PUBLICA)) {
    if (empty($_SESSION['user']['autenticado']) || (int) ($_SESSION['user']['NoUsuario'] ?? 0) <= 0) {
        header('Location: /auth/login.php');
        exit;
    }
    $rapYo = Conectar::una('SELECT usuarioActivo FROM usuarios WHERE usuarioId = ?', [(int) $_SESSION['user']['NoUsuario']]);
    if (!$rapYo || (int) $rapYo['usuarioActivo'] !== 1) {
        $_SESSION = [];
        session_destroy();
        header('Location: /auth/login.php?aviso=baja');
        exit;
    }
    if (empty($RAP_SIN_CENTRO)) {
        $rapCentro = (int) ($_SESSION['centro']['NoCentro'] ?? 0);
        $rapNivel = $rapCentro > 0 ? Conectar::una(
            'SELECT uc.modulo42 FROM usuariosxcentros uc JOIN centros c ON c.centroId = uc.ucCentro
              WHERE uc.ucUsuario = ? AND uc.ucCentro = ? AND c.centroActivo = 1',
            [(int) $_SESSION['user']['NoUsuario'], $rapCentro]) : null;
        if (!$rapNivel || (int) $rapNivel['modulo42'] < 1) {
            unset($_SESSION['centro']);
            header('Location: /auth/centro.php');
            exit;
        }
    }
}
