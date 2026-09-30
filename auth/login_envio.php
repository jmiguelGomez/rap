<?php
// Login con la cuenta de ISORGA: `usuarios` de la base `isorga`, usuarioMail + password_verify(usuarioPasswordHash),
// activa y no bloqueada. Si el mismo correo existe en varios distribuidores, vale la cuenta cuya contraseña casa.
$RAP_PUBLICA = true;
require_once __DIR__ . '/../app/sesion.php';
require_once __DIR__ . '/../app/acceso_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string) ($_SESSION['csrf_login'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    header('Location: /auth/login.php'); exit;
}
$mail = trim((string) ($_POST['mail'] ?? ''));
$pass = (string) ($_POST['pass'] ?? '');
$u = null;
if ($mail !== '' && $pass !== '') {
    $st = Conectar::varias('SELECT * FROM usuarios WHERE usuarioMail = ? AND usuarioActivo = 1', [$mail]);
    foreach ($st ? $st->fetchAll(PDO::FETCH_ASSOC) : [] as $fila) {
        if ((int) ($fila['bloqueado'] ?? 0) === 1 || empty($fila['usuarioPasswordHash'])) continue;
        if (password_verify($pass, $fila['usuarioPasswordHash'])) { $u = $fila; break; }
    }
}
if (!$u) {
    usleep(400000);
    error_log('[RAP] login rechazado para ' . $mail);
    header('Location: /auth/login.php?aviso=error&mail=' . urlencode($mail)); exit;
}
rap_abrir_usuario($u);
header('Location: /auth/centro.php'); exit;
