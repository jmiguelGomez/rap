<?php
// =====================================================================
// app/acceso_lib.php — abrir la sesión de PERSONA y la de CENTRO con las mismas claves que ISORGA
// (login_password.php y 0/centros_envio.php). Lo usan auth/login_envio.php, auth/centro_envio.php y
// auth/sso.php: un solo sitio que decide qué entra en $_SESSION.
// 🔴 La puerta es usuariosxcentros.modulo42 >= 1 en un centro ACTIVO. Nada más concede entrada.
// =====================================================================
require_once __DIR__ . '/Conectar.php';

/** Carga en sesión a la persona (fila de `usuarios`). Regenera el id de sesión. */
function rap_abrir_usuario(array $u, bool $desdeIsorga = false): void
{
    $idioma = (string) ($u['idioma'] ?? 'es');
    if (!in_array($idioma, ['es', 'ca', 'en', 'fr'], true)) { $idioma = 'es'; }
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['user'] = [
        'NoUsuario'    => (int) $u['usuarioId'],
        'usuarioId'    => (int) $u['usuarioId'],
        'NbUsuario'    => (string) $u['usuarioNombre'],
        'usuarioMail'  => (string) $u['usuarioMail'],
        'autenticado'  => true,
        'distribuidor' => (int) ($u['distribuidor'] ?? 0),
        'lang'         => $idioma,
        'qLenguaje'    => $idioma,
        'oscuro'       => (int) ($u['oscuro'] ?? 0),
        'demo'         => (int) ($u['usuarioDemo'] ?? 0),
    ];
    $_SESSION['ultima_actividad'] = time();
    if ($desdeIsorga) { $_SESSION['entrada_isorga'] = true; }
}

/** Centros activos en los que la persona tiene Envases y RAP (modulo42 >= 1). */
function rap_centros_disponibles(int $usuario): array
{
    $st = Conectar::varias(
        'SELECT c.centroId, c.centroNombre, uc.modulo42
           FROM usuariosxcentros uc JOIN centros c ON c.centroId = uc.ucCentro
          WHERE uc.ucUsuario = ? AND c.centroActivo = 1 AND uc.modulo42 >= 1
          ORDER BY c.centroNombre', [$usuario]);
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

/** Monta $_SESSION['centro'] como 0/centros_envio.php de ISORGA. false si no hay acceso a M21 en ese centro. */
function rap_abrir_centro(int $usuario, int $centro): bool
{
    $uc = Conectar::una('SELECT * FROM usuariosxcentros WHERE ucUsuario = ? AND ucCentro = ?', [$usuario, $centro]);
    $c  = Conectar::una('SELECT * FROM centros WHERE centroId = ? AND centroActivo = 1', [$centro]);
    if (!$uc || !$c || (int) ($uc['modulo42'] ?? 0) < 1) { return false; }
    $s = [
        'NoCentro'        => (int) $c['centroId'],
        'NbCentro'        => (string) $c['centroNombre'],
        'site'            => (int) ($c['site'] ?? 0),
        'pack'            => $c['pack'] ?? null,
        'corporativo'     => (int) ($c['corporativo'] ?? 0),
        'online'          => (int) ($c['online'] ?? 0),
        'Propios'         => (int) ($c['centroPropios'] ?? 0),
        'Demo'            => (int) ($c['centroDemo'] ?? 0),
        'resp_calidad'    => (int) ($c['resp_calidad'] ?? 0),
        'multiAprobacion' => (int) ($c['multiAprobacion'] ?? 0),
        'visual'          => $c['visual'] ?? null,
        'tiene_ia'        => (int) ($c['tiene_ia'] ?? 0),
        'chat_ia'         => (int) ($c['chat_ia'] ?? 0),
        'acciones'        => (int) ($c['acciones'] ?? 0),
        'tiene_idiomas'   => (int) ($c['tiene_idiomas'] ?? 0),
        'MultiCentros'    => count(rap_centros_disponibles($usuario)) > 1 ? 1 : 0,
    ];
    for ($n = 0; $n <= 50; $n++) { $s['modulo' . $n] = (int) ($uc['modulo' . $n] ?? 0); }
    $_SESSION['centro'] = $s;
    return true;
}
