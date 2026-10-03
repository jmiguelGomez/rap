<?php
// Foto de quien tiene la sesión, en miniatura, para el pie del menú lateral (2026-10-03). Solo la PROPIA: el usuario sale
// de la sesión validada, nunca de la petición. La miniatura la hace /var/www/isorga-net/app/includes/foto_miniatura.php.
require_once __DIR__ . '/../app/sesion.php';
require_once '/var/www/isorga-net/app/includes/foto_miniatura.php';
$u = (int) ($_SESSION['user']['NoUsuario'] ?? 0);
try {
    $f = $u > 0 ? Conectar::una('SELECT usuarioFoto, usuarioImagen FROM usuarios WHERE usuarioId = ?', [$u]) : null;
} catch (\Throwable $e) {
    $f = null;
}
isorga_foto_miniatura_enviar($f && (int) ($f['usuarioFoto'] ?? 0) === 1 ? (string) ($f['usuarioImagen'] ?? '') : '');
