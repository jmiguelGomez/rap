<?php

// Resolución única del logo de centro para PDFs y documentos generados.
//
// Convención canónica del proyecto: /LOGOS/{centroId}.png en la raíz del repo.
// Si el centro no tiene logo propio se cae al estándar (centro 1858) y, como
// último recurso, al logo genérico de ISORGA.
//
// Fichero autónomo a propósito: las clases base de PDF de /36/, /37/ y /38/ no
// incluyen clases.php ni inc_head.php, así que necesitan poder cargar esto solo
// sin arrastrar Conectar, variables.php ni session_start().
//
// 🔴 NO pasar el resultado a SetHeaderData(): el Header() por defecto de TCPDF
//    antepone K_PATH_IMAGES al nombre del logo, así que una ruta absoluta se
//    concatena y deja de existir. Para pintar el logo, usar pdfDibujarLogo()
//    dentro de un Header() propio.

if (!defined('LOGO_CENTRO_ESTANDAR')) define('LOGO_CENTRO_ESTANDAR', 1858);

if (!function_exists('logoCentroRuta')) {
    /**
     * Ruta absoluta del logo a usar en un documento generado.
     *
     * @param int|null $centroId Centro explícito. Obligatorio en endpoints
     *                           públicos por token y en CLI/cron, donde no hay
     *                           sesión. Si es null se toma el de sesión.
     * @return string Ruta absoluta, o '' si faltasen también los respaldos.
     */
    function logoCentroRuta(?int $centroId = null): string
    {
        // Header() se ejecuta una vez por página: sin caché, un informe de 40
        // páginas haría 40 getimagesize() sobre un PNG de 220 KB.
        static $cache = [];

        if ($centroId === null) {
            $centroId = (int) ($_SESSION['centro']['NoCentro'] ?? 0);
        }
        if (isset($cache[$centroId])) return $cache[$centroId];

        $raiz = __DIR__ . '/../../';   // app/config → raíz del proyecto

        $candidatos = [];
        if ($centroId > 0) $candidatos[] = $raiz . 'LOGOS/' . $centroId . '.png';
        $candidatos[] = $raiz . 'LOGOS/' . LOGO_CENTRO_ESTANDAR . '.png';
        $candidatos[] = $raiz . 'assets/img/logo_pdf.png';

        foreach ($candidatos as $candidato) {
            $real = realpath($candidato);
            // getimagesize además de realpath: un PNG corrupto o de 0 bytes
            // debe caer al siguiente candidato, no reventar el PDF.
            if ($real !== false && @getimagesize($real) !== false) {
                return $cache[$centroId] = $real;
            }
        }

        return $cache[$centroId] = '';
    }
}

if (!function_exists('pdfDibujarLogo')) {
    /**
     * Pinta el logo del centro encajado en la caja indicada, sin deformarlo.
     * Silencioso si no hay logo disponible.
     *
     * Los valores por defecto son la caja estándar de cabecera de la plataforma.
     * El tipo se pasa vacío para que TCPDF lo deduzca del contenido: algunos
     * ficheros de /LOGOS/ son JPEG con extensión .png.
     *
     * @param TCPDF    $pdf
     * @param int|null $centroId Ver logoCentroRuta().
     */
    function pdfDibujarLogo($pdf, float $x = 15, float $y = 10, float $w = 35, float $h = 16, ?int $centroId = null): void
    {
        $ruta = logoCentroRuta($centroId);
        if ($ruta === '') return;

        // El parámetro 15 ($fitbox = 'LT') encaja la imagen dentro de la caja
        // conservando su proporción y alineándola arriba-izquierda.
        $pdf->Image($ruta, $x, $y, $w, $h, '', '', 'T', false, 300, '', false, false, 0, 'LT');
    }
}

if (!function_exists('logoCentroRutaHeaderData')) {
    /**
     * Ruta del logo apta para el PRIMER argumento de TCPDF::SetHeaderData().
     *
     * Ese argumento no admite rutas absolutas: el Header() por defecto de TCPDF
     * le antepone K_PATH_IMAGES (tcpdf.php:3473), que aquí vale
     * assets/tcpdf/examples/images/. Una ruta absoluta queda pegada detrás, el
     * fichero no existe e Image() devuelve false en silencio — que es por lo
     * que hoy varios PDF salen sin logo. Se devuelve, pues, la ruta relativa
     * desde K_PATH_IMAGES.
     *
     * @param int|null $centroId Ver logoCentroRuta().
     * @return string '' si no hay logo o si TCPDF no está cargado todavía.
     */
    function logoCentroRutaHeaderData(?int $centroId = null): string
    {
        $abs = logoCentroRuta($centroId);
        if ($abs === '' || !defined('K_PATH_IMAGES')) return '';

        $base    = trim(K_PATH_IMAGES, '/');
        $niveles = $base === '' ? 0 : substr_count($base, '/') + 1;

        return str_repeat('../', $niveles) . ltrim($abs, '/');
    }
}
