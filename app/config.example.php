<?php
// =====================================================================
// app/config.php — SECRETOS de rap.isorga.com. Fuera de git (.gitignore). Copia sin secretos: config.example.php
// La base es la de ISORGA (`isorga`), con el MISMO usuario MySQL que la plataforma (decisión de Juan Miguel,
// 2026-09-29). `sso_secret` es la misma clave que RAP_SSO_SECRET en /var/www/isorga-net/app/config/.env.
// =====================================================================
return [
    'db' => [
        'host'    => 'localhost',
        'dbname'  => 'isorga',
        'user'    => 'isorga',
        'pass'    => '***',
        'charset' => 'utf8mb4',
    ],
    'sso_secret' => '***',
    'docs_path'  => '/var/www/isorga-net/DOCS',
];
