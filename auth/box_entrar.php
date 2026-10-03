<?php
// El centro y el usuario salen únicamente de la sesión validada de esta app.
require_once __DIR__ . '/../app/sesion.php';
require_once '/var/www/box/app/entrada_desde_app.php';
box_entrar_desde_app(basename(dirname(__DIR__)));
