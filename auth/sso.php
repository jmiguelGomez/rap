<?php
declare(strict_types=1);
/**
 * auth/sso.php — ENTRADA SIN LOGIN DESDE ISORGA. Mismo patrón que cae.isorga.com/auth/sso.php, pero como
 * la base es la misma, la carga lleva el ID: d = base64url("1|usuarioId|centroId|caduca|nonce"),
 * s = HMAC-SHA256 con `sso_secret` (app/config.php) = RAP_SSO_SECRET del .env de ISORGA.
 * Se comprueba firma, caducidad (60 s), nonce de un uso, persona activa y modulo42 >= 1 en ese centro.
 * No concede nada: si no cuadra, 404 o al login normal.
 */
$RAP_PUBLICA = true;
require_once __DIR__ . '/../app/sesion.php';
require_once __DIR__ . '/../app/acceso_lib.php';
$cfg = require __DIR__ . '/../app/config.php';
$secret = (string) ($cfg['sso_secret'] ?? '');
$d = (string) ($_GET['d'] ?? ''); $s = (string) ($_GET['s'] ?? '');
$fuera = static function (string $m): never { error_log('[RAP] sso rechazado: ' . $m); http_response_code(404); exit; };
if (strlen($secret) < 32 || !preg_match('/^[a-f0-9]{64}$/', $s) || $d === '' || strlen($d) > 300) $fuera('parametros');
$carga = base64_decode(strtr($d, '-_', '+/'), true);
if ($carga === false || !hash_equals(hash_hmac('sha256', $carga, $secret), $s)) $fuera('firma');
$p = explode('|', $carga);
// 2026-10-03: un 6.º campo opcional `s` dice que en ISORGA la sesión es de SOPORTE (0/<app>_entrar.php lo añade).
// Va dentro de la carga firmada, así que no se puede añadir desde fuera. Solo sirve para enseñar «Cambio usuario»
// en el pie del menú; no concede nada aquí (la suplantación la hace y la comprueba ISORGA).
if (!(count($p) === 5 || (count($p) === 6 && $p[5] === 's')) || $p[0] !== '1') $fuera('formato');
$soporteIsorga = count($p) === 6;
[, $usuario, $centro, $caduca, $nonce] = array_slice($p, 0, 5);
$usuario = (int) $usuario; $centro = (int) $centro; $caduca = (int) $caduca;
if ($caduca < time() || $caduca > time() + 120 || !preg_match('/^[a-f0-9]{32}$/', $nonce) || $usuario <= 0 || $centro <= 0) $fuera('caducado');
$dir = sys_get_temp_dir() . '/rap_sso';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
foreach (glob($dir . '/*') ?: [] as $v) { if (filemtime($v) < time() - 300) @unlink($v); }
if (file_exists($dir . '/' . $nonce) || @file_put_contents($dir . '/' . $nonce, (string) time(), LOCK_EX) === false) $fuera('reutilizado');
$u = Conectar::una('SELECT * FROM usuarios WHERE usuarioId = ? AND usuarioActivo = 1', [$usuario]);
if (!$u || (int) ($u['bloqueado'] ?? 0) === 1) { header('Location: /auth/login.php?aviso=baja'); exit; }
rap_abrir_usuario($u, true);
if (!rap_abrir_centro($usuario, $centro)) { header('Location: /auth/centro.php'); exit; }
$_SESSION['soporte_isorga'] = $soporteIsorga;
header('Location: /index.php');
exit;
