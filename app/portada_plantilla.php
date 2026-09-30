<?php
/** Portada pública: solo contenido editorial, sin base de datos ni sesión. */
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => $landing['nombre'] . ' · ISORGA',
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
    'url' => $landing['url'],
    'inLanguage' => 'es',
    'description' => $landing['descripcion'],
];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($landing['nombre']) ?> · ISORGA</title>
<meta name="description" content="<?= $h($landing['descripcion']) ?>">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="<?= $h($landing['url']) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= $h($landing['nombre']) ?>">
<meta property="og:title" content="<?= $h($landing['nombre']) ?> · ISORGA">
<meta property="og:description" content="<?= $h($landing['descripcion']) ?>">
<meta property="og:url" content="<?= $h($landing['url']) ?>">
<meta property="og:locale" content="es_ES">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/theme.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/theme.css') ?>">
<link rel="stylesheet" href="/assets/css/portada.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/portada.css') ?>">
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</head>
<body>
<header class="lp-nav">
  <div class="lp-wrap">
    <a href="/" class="lp-brand" aria-label="<?= $h($landing['nombre']) ?>, inicio"><img src="/assets/img/logo.png" alt=""><span><?= $h($landing['marca']) ?></span><small><?= $h($landing['etiqueta']) ?></small></a>
    <nav aria-label="Navegación principal">
      <a href="#que-es">Qué es</a><a href="#como-funciona">Cómo funciona</a><a href="#funciones">Funciones</a><a href="#demo">Demo</a>
      <a href="/auth/login.php" class="lp-btn lp-btn-accent">Entrar</a>
    </nav>
  </div>
</header>
<main>
<section class="lp-hero">
  <div class="lp-wrap">
    <p class="lp-eyebrow"><?= $h($landing['ceja']) ?></p>
    <h1><?= $h($landing['titular']) ?> <em><?= $h($landing['titular_acento']) ?></em></h1>
    <p class="lp-lede"><?= $h($landing['intro']) ?></p>
    <div class="lp-chips">
      <?php foreach ($landing['chips'] as [$icono, $texto]): ?><span class="lp-chip"><i class="<?= $h($icono) ?>" aria-hidden="true"></i><?= $h($texto) ?></span><?php endforeach; ?>
    </div>
    <div class="lp-cta"><a href="/auth/login.php" class="lp-btn lp-btn-accent">Entrar</a><a href="#demo" class="lp-btn">Pedir una demo</a></div>
  </div>
</section>
<section class="lp-sec" id="que-es">
  <div class="lp-wrap">
    <p class="lp-eyebrow"><?= $h($landing['seccion_ceja']) ?></p>
    <h2><?= $h($landing['seccion_titulo']) ?></h2>
    <p><?= $h($landing['seccion_texto']) ?></p>
    <div class="lp-pillars">
      <?php foreach ($landing['pilares'] as [$titulo, $texto]): ?><div class="lp-pillar"><strong><?= $h($titulo) ?></strong><p><?= $h($texto) ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>
<section class="lp-sec lp-sec-soft" id="como-funciona">
  <div class="lp-wrap">
    <p class="lp-eyebrow">Cómo funciona</p>
    <h2><?= $h($landing['flujo_titulo']) ?></h2>
    <p><?= $h($landing['flujo_intro']) ?></p>
    <div class="lp-steps">
      <?php foreach ($landing['pasos'] as $i => [$titulo, $texto]): ?><div class="lp-step"><span class="lp-step-number"><?= sprintf('%02d', $i + 1) ?></span><h3><?= $h($titulo) ?></h3><p><?= $h($texto) ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>
<section class="lp-sec" id="funciones">
  <div class="lp-wrap">
    <p class="lp-eyebrow">Funciones</p>
    <h2><?= $h($landing['funciones_titulo']) ?></h2>
    <div class="lp-grid">
      <?php foreach ($landing['funciones'] as [$icono, $titulo, $texto]): ?><article class="lp-card"><i class="lp-card-icon <?= $h($icono) ?>" aria-hidden="true"></i><h3><?= $h($titulo) ?></h3><p><?= $h($texto) ?></p></article><?php endforeach; ?>
    </div>
  </div>
</section>
<section class="lp-sec lp-demo" id="demo">
  <div class="lp-wrap">
    <p class="lp-eyebrow">Conoce la aplicación</p>
    <h2><?= $h($landing['demo_titulo']) ?></h2>
    <p><?= $h($landing['demo_texto']) ?></p>
    <div class="lp-cta"><a class="lp-btn lp-btn-accent" href="mailto:info@isorga.com?subject=<?= rawurlencode($landing['demo_asunto']) ?>">Pedir una demo</a><a class="lp-btn" href="/auth/login.php">Ya tengo acceso</a></div>
    <p class="lp-contacto">También puedes escribir a <a href="mailto:info@isorga.com">info@isorga.com</a>.</p>
  </div>
</section>
</main>
<footer class="lp-foot"><div class="lp-wrap"><span><?= $h($landing['nombre']) ?> · ISORGA</span><span>Desarrollada y operada por <a href="https://isorga.com/">Isorga.com</a></span></div></footer>
</body>
</html>
