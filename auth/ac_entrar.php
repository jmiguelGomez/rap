<?php
// Botón «Acciones» de la barra: el centro y el usuario salen únicamente de la sesión validada de esta app.
require_once __DIR__ . '/../app/sesion.php';
require_once '/var/www/ac/app/entrada_desde_app.php';
ac_entrar_desde_app(basename(dirname(__DIR__)));
