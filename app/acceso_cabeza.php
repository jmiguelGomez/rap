<?php
// Cabeza común de las pantallas de acceso (auth/login.php y auth/centro.php): la pantalla partida de
// audit.isorga.com. Espera $h y $language. Modo claro fijo, como el resto de la app. Cierra app/acceso_pie.php.
?><!DOCTYPE html>
<html lang="<?= $h($lang) ?>" data-bs-theme="light" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= $h(defined('APP_TITULO') ? APP_TITULO : APP_NOMBRE . ' · ' . ($language['RAP_ACCESO'] ?? 'Acceso')) ?></title>
<link rel="icon" type="image/png" href="/favicon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="/assets/css/theme.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/theme.css') ?>" rel="stylesheet">
<link href="/assets/css/acceso.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/acceso.css') ?>" rel="stylesheet">
<link href="/assets/css/workspace.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/workspace.css') ?>" rel="stylesheet">
</head>
<body>
<div class="acceso">
    <div class="acceso-foto"><div class="access-story">
        <span class="eyebrow"><?= $h(mb_strtoupper($language['RAP_HISTORIA_ETIQUETA'] ?? '', 'UTF-8')) ?></span>
        <h2><?= nl2br($h($language['RAP_HISTORIA_LEMA'] ?? '')) ?></h2>
        <p><?= nl2br($h($language['RAP_HISTORIA_TEXTO'] ?? '')) ?></p>
        <div class="access-layers" aria-hidden="true">
            <span>01 · <?= $h($language['RAP_HISTORIA_1'] ?? '') ?></span><span>02 · <?= $h($language['RAP_HISTORIA_2'] ?? '') ?></span><span>03 · <?= $h($language['RAP_HISTORIA_3'] ?? '') ?></span>
        </div>
    </div></div>
    <div class="acceso-panel">
