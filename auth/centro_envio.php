<?php
$RAP_SIN_CENTRO = true;
require_once __DIR__ . '/../app/sesion.php';
require_once __DIR__ . '/../app/acceso_lib.php';
if (!rap_abrir_centro((int) $_SESSION['user']['NoUsuario'], (int) ($_GET['id'] ?? 0))) {
    header('Location: /auth/centro.php'); exit;
}
header('Location: /index.php'); exit;
