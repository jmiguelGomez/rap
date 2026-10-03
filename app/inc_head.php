<?php
// Arranque de una PANTALLA (como app/inc_head.php de ISORGA): sesión + globales + <head>.
// El aspecto es el de audit.isorga.com (theme/intranet/workspace + paginas.css, copias de allí).
// isorga-tema.css va PRIMERO: trae los tokens --isg-* y las utilidades .isg-* que usan las pantallas
// portadas de ISORGA; lo de audit va detrás y manda en la cáscara.
// 🔴 Modo claro fijo, como en audit: data-theme="light" activa el bloque forzado de theme.css.
ob_start();
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/compat.php';
$rapH = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$rapCss = static fn(string $f) => '/assets/' . $f . '?v=' . (int) @filemtime(__DIR__ . '/../assets/' . $f);
?><!DOCTYPE html>
<html lang="<?= $rapH($lang) ?>" data-bs-theme="light" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $rapH(APP_NOMBRE . ' · ' . $NbCentro) ?></title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="<?= $rapCss('css/isorga-tema.css') ?>" rel="stylesheet">
    <link href="<?= $rapCss('css/theme.css') ?>" rel="stylesheet">
    <link href="<?= $rapCss('css/intranet.css') ?>" rel="stylesheet">
    <link href="<?= $rapCss('css/paginas.css') ?>" rel="stylesheet">
    <link href="<?= $rapCss('css/workspace.css') ?>" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?= $rapCss('js/workspace.js') ?>" defer></script>
    <?php // Tablas estilo Excel en toda la app (tablas_excel_global en app/config/estilo_trabajo.json), como en ISORGA ?>
    <?php include __DIR__ . '/includes/estilo_trabajo.php'; ?>
    <?php // Color de la app = el de su tarjeta en isorga.com/0/index_2.php (fuente: isorga-net/app/config/colores_apps.php) ?>
    <?php include __DIR__ . '/includes/color_app.php'; ?>
</head>
