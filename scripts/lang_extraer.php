<?php
declare(strict_types=1);
/**
 * Genera app/lang/{es,ca,en,fr}.php con SOLO las claves que usa esta app: las que aparecen como
 * $language['CLAVE'] en rap/, app/, auth/ e index.php, copiadas de los ficheros de idioma de ISORGA
 * (/var/www/isorga-net/app/lang/), más las propias de app/lang/_propias.php. Repetible: se relanza
 * cuando se porta de nuevo el módulo o cambian los textos en ISORGA. Uso: php scripts/lang_extraer.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$raiz = dirname(__DIR__);
$claves = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = substr($f->getPathname(), strlen($raiz) + 1);
    if ($f->getExtension() !== 'php' || !preg_match('#^(rap/|app/(?!lang/)|auth/|index\.php)#', $rel)) continue;
    preg_match_all('/\$language\[\s*[\'"]([A-Za-z0-9_ÁÉÍÓÚÑáéíóúñ ]+)[\'"]\s*\]/u', file_get_contents($f->getPathname()), $m);
    foreach ($m[1] as $k) $claves[$k] = true;
}
$propias = require $raiz . '/app/lang/_propias.php';
// Las propias van SIEMPRE: algunas se usan por variable ($language[$aviso]) y el grep no las ve (aviso de la sesión que rehace la cáscara).
foreach (array_keys($propias) as $k) { $claves[$k] = true; }
$cargar = static function (string $fich): array { $language = []; include $fich; return $language; };
$faltan = [];
foreach (['es', 'ca', 'en', 'fr'] as $idioma) {
    $isorga = $cargar('/var/www/isorga-net/app/lang/' . $idioma . '.php');
    $es = $idioma === 'es' ? $isorga : $cargar('/var/www/isorga-net/app/lang/es.php');
    $out = "<?php\n// GENERADO por scripts/lang_extraer.php (" . date('Y-m-d') . ") desde /var/www/isorga-net/app/lang/$idioma.php\n// + app/lang/_propias.php. No editar a mano: se sobrescribe.\n";
    ksort($claves);
    foreach (array_keys($claves) as $k) {
        if (isset($propias[$k])) { $v = $propias[$k][$idioma] ?? $propias[$k]['es']; }
        elseif (isset($isorga[$k])) { $v = $isorga[$k]; }
        elseif (isset($es[$k])) { $v = $es[$k]; }            // sin traducción: castellano, como hace ISORGA
        else { $faltan[$k] = true; continue; }
        $out .= '$language[' . var_export($k, true) . '] = ' . var_export($v, true) . ";\n";
    }
    file_put_contents($raiz . '/app/lang/' . $idioma . '.php', $out);
}
echo "Claves usadas: " . count($claves) . " · sin texto en ningún sitio: " . count($faltan) . ($faltan ? ' (' . implode(', ', array_slice(array_keys($faltan), 0, 20)) . ')' : '') . "\n";
