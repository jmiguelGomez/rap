<?php
declare(strict_types=1);
/**
 * scripts/rap_portar.php — trae el módulo 42 de ISORGA a esta app. REPETIBLE: relanzarlo actualiza la copia
 * con lo que haya cambiado en ISORGA; `git diff` enseña qué cambió. No toca la base de datos (es la misma) ni la
 * cáscara (app/, auth/). Qué trae, qué sustituye y qué parchea lo dice el MANIFIESTO
 * (isorga-net/scripts/satelites/rap.php → 'portar'); este fichero no se edita.
 * Uso: php scripts/rap_portar.php [--ver]   (--ver: solo informa, no escribe; sale con 2 si hay diferencias = espejo roto)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$ver = in_array('--ver', $argv, true);
$ISO = '/var/www/isorga-net';
$APP = dirname(__DIR__);
$DIR = 'rap';
$M = require $ISO . '/scripts/satelites/' . $DIR . '.php';
$P = $M['portar'];

$escribir = static function (string $dest, string $contenido) use ($ver, $APP): bool {
    $ruta = $APP . '/' . $dest;
    $antes = is_file($ruta) ? file_get_contents($ruta) : null;
    if ($antes === $contenido) return false;
    if (!$ver) { @mkdir(dirname($ruta), 0775, true); file_put_contents($ruta, $contenido); }
    return true;
};
$cambios = []; $cuenta = []; $avisos = [];
$contar = static function (string $k, int $n) use (&$cuenta): void { if ($n) $cuenta[$k] = ($cuenta[$k] ?? 0) + $n; };

// 1) Los ficheros del módulo (y los de fuera que el manifiesto añada, p. ej. 0/flota*.php)
$origen = [];
foreach ($P['origen'] as $glob) foreach (glob($ISO . '/' . $glob) as $f) $origen[basename($f)] = $f;
foreach ($P['excluir'] ?? [] as $x) unset($origen[$x]);    // lo que el manifiesto deja fuera, con su motivo escrito allí
if (!$origen && !$P['origen']) { echo "Este módulo no tiene pantallas que portar (manifiesto con 'origen' vacío): la app es solo la cáscara.\n"; }
elseif (!$origen) { fwrite(STDERR, "No hay nada que portar\n"); exit(1); }
$portados = array_keys($origen);
foreach ($origen as $f => $ruta) {
    $s = file_get_contents($ruta);
    foreach ($P['sust'] as $de => $a) { $s = str_replace($de, $a, $s, $n); $contar("$de → $a", $n); }
    foreach ($P['parches'][$f] ?? [] as [$antes, $despues, $etiqueta]) {
        $s = str_replace($antes, $despues, $s, $n);
        if ($n !== 1) $avisos[] = "$f: parche «$etiqueta» esperaba 1 coincidencia y hay $n";
        $contar("$f: $etiqueta", $n);
    }
    // Comprobación: ningún formulario ni AJAX puede apuntar a un fichero que no exista en la app (un POST entre dominios llega sin sesión)
    preg_match_all('~(?:action=|url\s*:\s*|\$\.(?:get|post|getJSON)\()\s*["\']([a-z_0-9]+\.php)~', $s, $m);
    foreach ($m[1] as $dest) {
        if (!in_array($dest, $portados, true) && !in_array($dest, $P['ajax_ok'], true) && !is_file($APP . '/' . $DIR . '/' . $dest)) $avisos[] = "$f → $dest (formulario/AJAX a un fichero que NO está en la app)";
    }
    // Comprobación: ninguna ruta relativa a otro módulo de ISORGA se queda sin traducir
    if (preg_match_all('~\.\./(?!app/|assets/tcpdf/|DOCS/)([0-9a-z_]+)/~', $s, $m2)) {
        foreach (array_unique($m2[0]) as $r) $avisos[] = "$f: queda una ruta a ISORGA sin traducir: $r";
    }
    if ($escribir($DIR . '/' . $f, $s)) $cambios[] = $DIR . '/' . $f;
}

// 2) Clases de ISORGA que el módulo usa como estáticas (se cargan desde app/compat.php) y assets copiados tal cual
foreach ($P['clases'] as $c) { if ($escribir($c, file_get_contents($ISO . '/' . $c))) $cambios[] = $c; }
foreach ($P['assets'] ?? [] as $de => $a) { if ($escribir($a, file_get_contents($ISO . '/' . $de))) $cambios[] = $a; }

// 3) Funciones de isorga-net/app/config/funciones.php que el módulo usa (esta app no carga ese fichero entero)
$src = file_get_contents($ISO . '/app/config/funciones.php');
$extraer = static function (string $nombre) use ($src): string {
    $i = strpos($src, 'function ' . $nombre . '(');
    if ($i === false) return '';
    $j = strpos($src, '{', $i); $d = 0;
    for ($k = $j; $k < strlen($src); $k++) { if ($src[$k] === '{') $d++; elseif ($src[$k] === '}' && --$d === 0) break; }
    $pre = substr($src, 0, $i); $doc = '';
    if (preg_match('~(/\*\*(?:(?!\*/).)*\*/\s*)$~s', $pre, $m)) $doc = $m[1];
    return $doc . substr($src, $i, $k - $i + 1);
};
$fi = "<?php\n// " . $DIR . ".isorga.com · GENERADO por scripts/rap_portar.php: funciones de isorga-net/app/config/funciones.php que\n// usa el módulo portado. Esta app no carga ese fichero entero. No editar a mano.\n";
foreach ($P['funciones'] as $fn) {
    $cuerpo = $extraer($fn);
    if ($cuerpo === '') { $avisos[] = "funciones.php: no encuentro $fn()"; continue; }
    $fi .= "\nif (!function_exists('$fn')) {\n" . $cuerpo . "\n}\n";
}
if ($escribir('app/funciones_isorga.php', $fi)) $cambios[] = 'app/funciones_isorga.php';

// 4) Otros ficheros de ISORGA de los que solo se traen ALGUNAS funciones (p. ej. app/dbchat/ia_gestion.php, que arrastra el motor 0/ag_*)
//    'extraer' => [ 'destino/en/la/app.php' => ['de' => 'ruta/en/isorga.php', 'funciones' => ['f1', 'f2']] ]
foreach ($P['extraer'] ?? [] as $dest => $e) {
    $srcX = file_get_contents($ISO . '/' . $e['de']);
    $extraerDe = static function (string $nombre) use ($srcX): string {
        $i = strpos($srcX, 'function ' . $nombre . '(');
        if ($i === false) return '';
        $j = strpos($srcX, '{', $i); $d = 0;
        for ($k = $j; $k < strlen($srcX); $k++) { if ($srcX[$k] === '{') $d++; elseif ($srcX[$k] === '}' && --$d === 0) break; }
        $pre = substr($srcX, 0, $i); $doc = '';
        if (preg_match('~(/\*\*(?:(?!\*/).)*\*/\s*)$~s', $pre, $m)) $doc = $m[1];
        return $doc . substr($srcX, $i, $k - $i + 1);
    };
    $out = "<?php\n// " . $DIR . ".isorga.com · GENERADO por scripts/rap_portar.php: funciones de isorga-net/" . $e['de'] . " que usa el módulo\n// portado (el resto de ese fichero no se trae). No editar a mano.\n";
    foreach ($e['requiere'] ?? [] as $r) $out .= "require_once __DIR__ . '/" . $r . "';\n";
    foreach ($e['funciones'] as $fn) {
        $cuerpo = $extraerDe($fn);
        if ($cuerpo === '') { $avisos[] = $e['de'] . ": no encuentro $fn()"; continue; }
        $out .= "\nif (!function_exists('$fn')) {\n" . $cuerpo . "\n}\n";
    }
    if ($escribir($dest, $out)) $cambios[] = $dest;
}

// 5) Shims: ficheros VACÍOS que el módulo incluye y que en ISORGA no existen (allí solo dan un aviso; aquí ni eso)
foreach ($P['shims'] ?? [] as $sh) {
    if ($escribir($sh, "<?php\n// Shim vacío (scripts/rap_portar.php): el módulo lo incluye y en ISORGA este fichero NO existe. Aquí existe y no hace nada.\n")) $cambios[] = $sh;
}

echo ($ver ? "[--ver] " : "") . count($portados) . " ficheros portados a $DIR/\n";
echo "Ficheros " . ($ver ? "que cambiarían" : "escritos") . ": " . count($cambios) . "\n";
foreach ($cuenta as $k => $v) echo "  · $k: $v\n";
if ($avisos) { echo "🔴 AVISOS:\n"; foreach (array_unique($avisos) as $a) echo "  - $a\n"; exit(1); }
echo "Sin avisos.\n";
if ($ver && $cambios) exit(2);
