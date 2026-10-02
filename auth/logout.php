<?php
// Salir. Si se entró desde ISORGA (auth/sso.php), se vuelve a su portada de bloques, donde la sesión de
// isorga.com sigue abierta (es otra cookie). Destino FIJO, nunca de la petición.
$RAP_PUBLICA = true;
require_once __DIR__ . '/../app/sesion.php';
$volver = !empty($_SESSION['entrada_isorga']);
$_SESSION = [];
session_destroy();
// «Volver a ISORGA» vuelve a la portada de bloques (0/index_2.php), como antes de la v16.858: lo pidió
// Juan Miguel el 2026-10-02. Sin login con contraseña también se va ahí, porque ISORGA es la única
// puerta; si la sesión de isorga.com ha caducado, index_2.php ya manda a su login.
header('Location: ' . (($volver || !APP_LOGIN_CONTRASENA) ? APP_URL_ISORGA . '/0/index_2.php' : '/auth/login.php'));
exit;
