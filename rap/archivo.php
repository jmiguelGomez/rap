<?php
/**
 * archivo.php — Sirve un fichero de DOCS/{centro de sesión}/ (fotos, adjuntos, PDF del módulo).
 * En ISORGA estos enlaces van por ruta directa a /DOCS/, que allí sirve nginx sin comprobar nada; aquí /DOCS/
 * está denegado en el vhost y se pasa por esta puerta: sesión con el módulo (app/sesion.php, revalidada en cada
 * petición) y ruta SIEMPRE dentro de DOCS/{NoCentro de sesión}/ (realpath + prefijo: sin `..`, sin enlaces fuera).
 * Generado por isorga-net/scripts/satelite_crear.php.
 */
declare(strict_types=1);
require_once '../app/includes/clases.php';
$centro = (int) ($_SESSION['centro']['NoCentro'] ?? 0);
$f = str_replace('\\', '/', (string) ($_GET['f'] ?? ''));
if ($centro <= 0 || $f === '' || str_contains($f, "\0") || str_contains($f, '../') || str_starts_with($f, '/')) { http_response_code(404); exit; }
$base = realpath(__DIR__ . '/../DOCS/' . $centro);
$ruta = $base ? realpath($base . '/' . $f) : false;
if ($base === false || $ruta === false || !str_starts_with($ruta, $base . '/') || !is_file($ruta)) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta) ?: 'application/octet-stream';
if (str_starts_with($mime, 'text/') || str_contains($mime, 'php') || str_contains($mime, 'html')) $mime = 'application/octet-stream';   // nunca se ejecuta ni se pinta como HTML
$inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($ruta));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode(basename($ruta)) . '"');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
