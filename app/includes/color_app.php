<?php
// =====================================================================
// app/includes/color_app.php — pinta la app con SU color: el de su tarjeta en isorga.com/0/index_2.php (2026-10-03).
//
// 🔴 El color NO se elige aquí. Sale de /var/www/isorga-net/app/config/colores_apps.php, la fuente única que leen
//    también las tarjetas de index_2: cambiar allí la línea de esta app cambia su tarjeta y esta app a la vez.
//    Este fichero es IGUAL en todas las apps (el original está en el molde, /var/www/check); la app se reconoce
//    por su carpeta en /var/www. Si no tiene color en la fuente (ac, bjc…) o no se puede leer, no emite nada y la
//    app sigue con el azul de theme.css.
//
// Qué cambia: los tokens de theme.css que dan el azul — el degradado del menú lateral (--nav-*), el acento de
// botones, enlaces y enlace activo (--accent*), y --bs-primary. Se incluye DESPUÉS de todo el CSS y de
// estilo_trabajo.php, así que gana por orden con la misma especificidad. Si la app tiene un color de botón propio en
// app/config/estilo_trabajo.json (boton_primario_color), los botones se le dejan a ese.
//
// 🔴 El acento se oscurece solo lo justo para que el texto blanco pase de 4,5:1 (WCAG AA): un ámbar o un cian claros
//    con letras blancas no se leen. El menú lateral usa versiones muy oscuras del color, donde el texto claro sobra.
// =====================================================================
(static function (): void {
    if (!preg_match('#^/var/www/([a-z][a-z0-9]*)/#', __DIR__ . '/', $m)) return;
    $raiz = '/var/www/' . $m[1];
    $fuente = '/var/www/isorga-net/app/config/colores_apps.php';
    if (!is_readable($fuente)) return;
    require_once $fuente;
    $c = function_exists('isorga_color_app') ? isorga_color_app($m[1]) : null;
    if ($c === null) return;

    $rgb = static fn(string $h): array => array_map('hexdec', str_split(ltrim($h, '#'), 2));
    $hex = static fn(array $c): string => sprintf('#%02x%02x%02x', ...array_map(static fn($v) => (int) round(max(0, min(255, $v))), $c));
    $mezcla = static fn(array $c, array $con, float $t): array => [$c[0] + ($con[0] - $c[0]) * $t, $c[1] + ($con[1] - $c[1]) * $t, $c[2] + ($con[2] - $c[2]) * $t];
    $lum = static function (array $c): float {
        $l = array_map(static function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; }, $c);
        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
    };
    $negro = [0, 0, 0]; $blanco = [255, 255, 255];

    $base = $rgb($c['hex']);
    $acento = $base;
    for ($i = 0; $i < 30 && 1.05 / ($lum($acento) + 0.05) < 4.5; $i++) $acento = $mezcla($acento, $negro, 0.05);
    $fuerte = $mezcla($acento, $negro, 0.15);
    $a = $hex($acento); $f = $hex($fuerte);
    $aRgb = implode(',', array_map(static fn($v) => (int) round($v), $acento));
    $fRgb = implode(',', array_map(static fn($v) => (int) round($v), $fuerte));

    $css = ':root,:root[data-theme="light"]{'
        . "--accent:$a;--accent-strong:$f;--accent-soft:" . $hex($mezcla($base, $blanco, 0.88)) . ';'
        . '--border-strong:' . $hex($mezcla($base, $blanco, 0.55)) . ";--glow-accent:0 4px 12px {$a}22;"
        . '--nav-bg:' . $hex($mezcla($base, $negro, 0.72)) . ';--nav-active:' . $hex($mezcla($base, $negro, 0.5)) . ';'
        . '--nav-text:' . $hex($mezcla($base, $blanco, 0.9)) . ';--nav-muted:' . $hex($mezcla($base, $blanco, 0.72)) . ';'
        . "--bs-primary:$a;--bs-primary-rgb:$aRgb;--isg-accent:$a;--isg-accent-strong:$f;"
        . "--bs-link-color:$a;--bs-link-color-rgb:$aRgb;--bs-link-hover-color:$f;--bs-link-hover-color-rgb:$fRgb}";

    // Botones: los de la app mandan si los tiene (estilo_trabajo.json → boton_primario_color).
    $estilo = @json_decode((string) @file_get_contents("$raiz/app/config/estilo_trabajo.json"), true);
    if (trim((string) ($estilo['boton_primario_color'] ?? '')) === '') {
        $css .= ".btn-primary{--bs-btn-bg:$a;--bs-btn-border-color:$a;--bs-btn-hover-bg:$f;--bs-btn-hover-border-color:$f;"
            . "--bs-btn-active-bg:$f;--bs-btn-active-border-color:$f;--bs-btn-disabled-bg:$a;--bs-btn-disabled-border-color:$a}"
            . ".btn-outline-primary{--bs-btn-color:$a;--bs-btn-border-color:$a;--bs-btn-hover-bg:$a;--bs-btn-hover-border-color:$a;"
            . "--bs-btn-active-bg:$f;--bs-btn-active-border-color:$f;--bs-btn-disabled-color:$a;--bs-btn-disabled-border-color:$a}";
    }
    echo '<style id="color-app" data-app="' . htmlspecialchars($m[1], ENT_QUOTES) . '">' . $css . "</style>\n";
})();
