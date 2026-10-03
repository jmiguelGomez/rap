<?php
// =====================================================================
// app/funciones.php — funciones de ISORGA que usan las páginas portadas del módulo 21.
// COPIADAS TAL CUAL de /var/www/isorga-net/app/config/funciones.php (2026-09-29). Si allí
// cambian, se vuelven a copiar con scripts/rap_portar.php. No añadir aquí funciones nuevas
// de la app: van en su propio fichero.
// =====================================================================

if (!function_exists('nfc')) {
function nfc($s)
    {
        if ($s === null || $s === '') return '';
        if (!is_scalar($s)) return $s;
        if (class_exists('Normalizer')) {
            $r = \Normalizer::normalize((string)$s, \Normalizer::FORM_C);
            return $r === false ? (string)$s : $r;
        }
        return (string)$s;
    }
}

if (!function_exists('subidaVerificar')) {
function subidaVerificar(string $rutaAbsoluta, int $tamanoEsperado = 0): array
    {
        clearstatcache(true, $rutaAbsoluta);
        $existe   = is_file($rutaAbsoluta);
        $bytes    = $existe ? (int) filesize($rutaAbsoluta) : 0;
        // Si no se pasa tamaño esperado (0), basta con que exista y tenga bytes.
        $coincide = $tamanoEsperado <= 0 ? ($existe && $bytes > 0) : ($existe && $bytes === $tamanoEsperado);
        return [
            'ok'          => $existe && $coincide,
            'existe'      => $existe,
            'bytes'       => $bytes,
            'bytes_human' => subidaBytesHumano($bytes),
            'coincide'    => $coincide,
            'verificado'  => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('poner0')) {
function poner0($number, $n)
{
  return str_pad((int) $number, $n, "0", STR_PAD_LEFT);
}
}

if (!function_exists('invierte_fecha')) {
function invierte_fecha($fecha)
{
  $dia = substr($fecha, 8, 2);
  $mes = substr($fecha, 5, 2);
  $anio = substr($fecha, 0, 4);
  $correcta = $dia . "/" . $mes . "/" . $anio;

  return $correcta;
}
}

// marca_ia(): ISORGA la carga desde funciones.php (§27, AI Act art. 50)
require_once __DIR__ . '/config/marca_ia.php';
// pdfDibujarLogo(): en ISORGA está siempre cargada; los PDF portados la llaman sin require
require_once __DIR__ . '/config/logo_pdf.php';

// Funciones de ISORGA que necesita el módulo portado (generado por scripts/rap_portar.php)
if (is_file(__DIR__ . '/funciones_isorga.php')) require_once __DIR__ . '/funciones_isorga.php';
// Constantes BRAND_* de ISORGA (app/config/branding.php), si el porte las trajo: las usan pantallas y PDF del módulo
if (is_file(__DIR__ . '/config/branding.php')) require_once __DIR__ . '/config/branding.php';
