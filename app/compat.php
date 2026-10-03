<?php
// =====================================================================
// app/compat.php — las globales que el código portado de ISORGA espera encontrar y que allí monta
// app/config/variables.php. Mismos nombres, mismo origen ($_SESSION). GENERADO por isorga-net/scripts/satelite_crear.php.
// 🔴 Es un puente, no una licencia: código NUEVO de esta app lee $_SESSION y no añade globales aquí.
// =====================================================================
$_u = $_SESSION['user'] ?? []; $_c = $_SESSION['centro'] ?? [];
// USER
$usuario        = (int) ($_u['NoUsuario'] ?? 0);
$NoUsuario      = $usuario;
$NbUsuario      = (string) ($_u['NbUsuario'] ?? '');
$anterior       = $_u['anterior'] ?? null;
$dist           = (int) ($_u['distribuidor'] ?? 0);
$distribuidor   = $dist;
$NoDistribuidor = $dist;
$soporte        = (int) ($_u['soporte'] ?? 0);
$Soporte        = $soporte;
$Control        = (int) ($_u['control'] ?? 0);
$Admin          = (int) ($_u['admin'] ?? 0);
$MultiModulos   = $_u['multiModulos'] ?? null;
$Acceso_total   = $_u['acceso_total'] ?? null;
$Oscuro         = (int) ($_u['oscuro'] ?? 0);
// CENTRO
$centro         = (int) ($_c['NoCentro'] ?? 0);
$NoCentro       = $centro;
$NbCentro       = (string) ($_c['NbCentro'] ?? '');
$site           = (int) ($_c['site'] ?? 0);
$NoSite         = $site;
$Pack           = $_c['pack'] ?? null;
$Corporativo    = (int) ($_c['corporativo'] ?? 0);
$corporativo    = $Corporativo;
$multiCentros   = (int) ($_c['MultiCentros'] ?? 0);
$modulo         = $_c['moduloActual'] ?? null;
$Modulo         = $modulo;
$propios        = $_c['Propios'] ?? null;
$RespCalidad    = $_c['resp_calidad'] ?? null;
$Online         = (int) ($_c['online'] ?? 1);
for ($_n = 0; $_n <= 50; $_n++) { ${'Modulo' . $_n} = (int) ($_c['modulo' . $_n] ?? 0); }
// SESIÓN / AUX
$logo_correo    = $_SESSION['sesion']['logo_correo'] ?? null;
$correo_soporte = $_SESSION['sesion']['correo_soporte'] ?? null;
$PaginaAtras    = $_SESSION['aux']['origen'] ?? null;
$url            = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rap.isorga.com') . '/';
unset($_u, $_c, $_n);
// IDIOMA
$lang = (string) ($_SESSION['user']['lang'] ?? 'es');
if (!in_array($lang, ['es', 'ca', 'en', 'fr'], true)) { $lang = 'es'; }
$language = [];
require __DIR__ . '/lang/' . $lang . '.php';
// Clases de ISORGA que el módulo portado usa como estáticas (copiadas por scripts/rap_portar.php)
foreach (glob(__DIR__ . '/clases/*.php') as $_cl) { if (!preg_match('/^(Isorgi|LLMClient)/', basename($_cl))) require_once $_cl; }
