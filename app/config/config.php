<?php
// app/config/config.php — constantes de rap.isorga.com (sin secretos: esos van en app/config.php, fuera de git).
// Generada por isorga-net/scripts/satelite_crear.php (2026-09-30) desde el molde de check.isorga.com.
if (defined('APP_NOMBRE')) { return; }
define('APP_NOMBRE', 'RAP · ISORGA');
define('APP_VERSION', 'v0.001');            // sube en cada commit (CHANGELOG.md)
define('APP_MODULO', 42);                   // usuariosxcentros.modulo42 es la puerta
define('APP_INACTIVIDAD_MINUTOS', 60);      // como la sesión de ISORGA (gc_maxlifetime 3600)
define('APP_URL_ISORGA', 'https://isorga.com');
// Cambio USD→EUR para el coste de la IA en llm_llamadas: el MISMO valor que LLM_USD_EUR_RATE de isorga-net/app/config/variables.php.
if (!defined('LLM_USD_EUR_RATE')) define('LLM_USD_EUR_RATE', 0.92);
