<?php
// =====================================================================
// app/compat.php — las globales que el código portado de ISORGA (21/rap_*) espera encontrar y que
// allí monta app/config/variables.php. Mismos nombres, mismo origen ($_SESSION). Idioma incluido.
// 🔴 Es un puente, no una licencia: código NUEVO de esta app lee $_SESSION y no añade globales aquí.
// =====================================================================
$NoUsuario = (int) ($_SESSION['user']['NoUsuario'] ?? 0);
$usuario   = $NoUsuario;
$NbUsuario = (string) ($_SESSION['user']['NbUsuario'] ?? '');
$NoCentro  = (int) ($_SESSION['centro']['NoCentro'] ?? 0);
$NbCentro  = (string) ($_SESSION['centro']['NbCentro'] ?? '');
$NoDistribuidor = (int) ($_SESSION['user']['distribuidor'] ?? 0);
$Online    = 1;
$Oscuro    = (int) ($_SESSION['user']['oscuro'] ?? 0);
for ($rapN = 0; $rapN <= 50; $rapN++) {
    ${'Modulo' . $rapN} = (int) ($_SESSION['centro']['modulo' . $rapN] ?? 0);
}
$lang = (string) ($_SESSION['user']['lang'] ?? 'es');
if (!in_array($lang, ['es', 'ca', 'en', 'fr'], true)) { $lang = 'es'; }
$language = [];
require __DIR__ . '/lang/' . $lang . '.php';
// Clases de ISORGA que el módulo portado usa como estáticas (copiadas por scripts/rap_portar.php)
foreach (glob(__DIR__ . '/clases/*.php') as $_cl) { if (!preg_match('/^(Isorgi|LLMClient)/', basename($_cl))) require_once $_cl; }
