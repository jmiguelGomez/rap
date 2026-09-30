<?php
// Salir. Si se entró desde ISORGA (auth/sso.php), se vuelve a su portada de bloques, donde la sesión de
// isorga.com sigue abierta (es otra cookie). Destino FIJO, nunca de la petición.
$RAP_PUBLICA = true;
require_once __DIR__ . '/../app/sesion.php';
$volver = !empty($_SESSION['entrada_isorga']);
$_SESSION = [];
session_destroy();
header('Location: ' . ($volver ? APP_URL_ISORGA . '/0/index_2.php' : '/auth/login.php'));
exit;
