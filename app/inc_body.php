<?php
// <body> + barra superior + menú lateral (como app/inc_body.php de ISORGA), con la estructura de
// audit.isorga.com: la clase audit-workspace es la que espera workspace.css. <main> lo cierra inc_footer.php.
?>
<body class="audit-workspace">
<a class="skip-link" href="#main-content"><?= $language['RAP_SALTAR'] ?? 'Saltar al contenido' ?></a>
<?php require __DIR__ . '/sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="container-fluid py-4">
