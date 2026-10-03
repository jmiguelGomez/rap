<?php
/**
 * auth/tema_envio.php — guarda el modo claro/oscuro de esta persona EN ESTA APP (2026-10-03).
 *
 * Lo llama el botón luna/sol de la barra (app/sidebar.php). POST con `oscuro` (0/1) y `t` (token de la sesión,
 * $_SESSION['tema_token']); la app sale de su carpeta en /var/www, NUNCA de la petición. Escribe solo en
 * `usuarios_modo` (nunca en `usuarios`) con isorga_modo_guardar() de isorga-net/app/includes/modo_tema.php.
 * Responde {"ok":true|false}; con false el botón vuelve al modo anterior (p. ej. si la tabla aún no existe).
 * Sin sesión, app/sesion.php manda al login como en cualquier pantalla.
 */
require_once __DIR__ . '/../app/sesion.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$ok = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && !empty($_SESSION['user']['autenticado'])
    && !empty($_SESSION['tema_token'])
    && hash_equals((string) $_SESSION['tema_token'], (string) ($_POST['t'] ?? ''))
    && is_readable('/var/www/isorga-net/app/includes/modo_tema.php')) {
    require_once '/var/www/isorga-net/app/includes/modo_tema.php';
    $ok = isorga_modo_guardar((int) $_SESSION['user']['NoUsuario'], isorga_modo_app_de_ruta(__DIR__), (string) ($_POST['oscuro'] ?? '') === '1');
}
if (!$ok) http_response_code(400);
echo json_encode(['ok' => $ok]);
