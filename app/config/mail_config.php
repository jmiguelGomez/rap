<?php
// rap.isorga.com: COPIA de isorga-net/app/config/mail_config.php (scripts/rap_portar.php). En ISORGA la carga de
// TenantResolver la hace variables.php; aquí, esta línea. Las credenciales SMTP llegan por el entorno de PHP-FPM (pool www, el mismo).
if (!class_exists('TenantResolver')) require_once __DIR__ . '/../TenantResolver.php';

// if (!class_exists('TenantResolver')) require_once __DIR__ . '/../TenantResolver.php';


/**
 * ISORGA — SMTP robusto sin dependencias externas
 * - SSL implícito (465) y STARTTLS (587)
 * - AUTH PLAIN/LOGIN real (sin “autenticado=true” falso)
 * - Adjuntos también en envío SMTP
 * - Respuestas multilínea, dot-stuffing, headers MIME correctos
 * - Fallback opcional a mail()
 *
 * Recomendado: definir variables de entorno en el servidor (ej. en Apache/Nginx o .env de tu bootstrap):
 *   ISORGA_SMTP_HOST, ISORGA_SMTP_PORT, ISORGA_SMTP_USER, ISORGA_SMTP_PASS, ISORGA_SMTP_SECURE (ssl|tls|none)
 */

// ============================
// CONFIGURACIÓN
// ============================
define('SMTP_HOST',     getenv('ISORGA_SMTP_HOST')   ?: 'smtp.serviciodecorreo.es');
define('SMTP_PORT',    (int)(getenv('ISORGA_SMTP_PORT') ?: 465));
define('SMTP_USERNAME', getenv('ISORGA_SMTP_USER')   ?: 'mails@isorga.com');
define('SMTP_PASSWORD', getenv('ISORGA_SMTP_PASS')   ?: ''); // ← ROTA Y NO LO DEJES EN CÓDIGO
define('SMTP_SECURE',   strtolower(getenv('ISORGA_SMTP_SECURE') ?: 'ssl')); // 'ssl' | 'tls' | 'none'
define('SMTP_VERIFY_PEER', getenv('ISORGA_SMTP_VERIFY') === '0' ? false : true); // true en prod

// define('MAIL_FROM',       TenantResolver::get('email_remitente',       getenv('ISORGA_MAIL_FROM')      ?: 'mails@isorga.com'));
// define('MAIL_FROM_NAME',  TenantResolver::get('email_remitente_nombre', getenv('ISORGA_MAIL_FROM_NAME') ?: 'Sistema ISORGA'));
// define('MAIL_REPLY_TO',   TenantResolver::get('email_remitente',       getenv('ISORGA_MAIL_REPLY_TO')  ?: 'mails@isorga.com'));

define('MAIL_FROM',       getenv('ISORGA_MAIL_FROM')      ?: 'mails@isorga.com');
define('MAIL_FROM_NAME',  getenv('ISORGA_MAIL_FROM_NAME') ?: 'Sistema ISORGA');
define('MAIL_REPLY_TO',   getenv('ISORGA_MAIL_REPLY_TO')  ?: 'mails@isorga.com');

define('ADMIN_MAIL',   getenv('ISORGA_ADMIN_MAIL')  ?: 'mails@isorga.com');
define('SYSTEM_MAIL',  getenv('ISORGA_SYSTEM_MAIL') ?: 'mails@isorga.com');

// ============================
// API PRINCIPAL
// ============================
function enviarCorreoIsorga($destinatario, $asunto, $mensajeHtml, $adjuntos = array(), $bcc = array())
{
    $destinatarios = validarEmails($destinatario);
    $bcc           = $bcc ? validarEmails($bcc) : array();
    if (empty($destinatarios)) {
        registrarIntentoCorreo($destinatario, $asunto, false, 'Dirección/es inválida/s');
        return false;
    }

    $ok = enviarCorreoSMTPNuevo($destinatarios, $asunto, $mensajeHtml, $adjuntos, $bcc);
    if ($ok) {
        registrarIntentoCorreo(implode(',', $destinatarios), $asunto, true);
        return true;
    }

    error_log("⚠️ SMTP falló, intentando con mail() nativo");
    $ok2 = enviarCorreoNativo($destinatarios, $asunto, $mensajeHtml, $adjuntos, $bcc);
    registrarIntentoCorreo(implode(',', $destinatarios), $asunto, $ok2, $ok2 ? '' : 'Fallback nativo falló');
    return $ok2;
}

// ============================
// ENVÍO SMTP ROBUSTO
// ============================
function enviarCorreoSMTPNuevo(array $destinatarios, $asunto, $mensajeHtml, $adjuntos = array(), array $bcc = array())
{
    // $bcc entra en el envelope (RCPT TO) pero NUNCA en la cabecera To:.
    // Es copia oculta de verdad: el destinatario no la ve.
    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $helloName  = preg_replace('/[^A-Za-z0-9\.\-]/', '', $serverName) ?: 'localhost';

    $scheme = (SMTP_SECURE === 'ssl' || (SMTP_SECURE === 'auto' && (int)SMTP_PORT === 465)) ? 'ssl://' : 'tcp://';

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => SMTP_VERIFY_PEER,
            'verify_peer_name'  => SMTP_VERIFY_PEER,
            'allow_self_signed' => !SMTP_VERIFY_PEER,
        ],
    ]);

    $socket = @stream_socket_client(
        $scheme . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        error_log("❌ [SMTP] Conexión fallida: $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, 30);

    // 220
    $resp = smtp_read($socket);
    if (!smtp_is($resp, 220)) {
        error_log("❌ [SMTP] Respuesta inicial no válida: $resp");
        @fclose($socket);
        return false;
    }

    // EHLO
    if (!smtp_cmd_expect($socket, "EHLO $helloName", 250, $ehloResp)) {
        // Prueba HELO si EHLO falla
        if (!smtp_cmd_expect($socket, "HELO $helloName", 250)) {
            @fclose($socket);
            return false;
        }
    }

    // STARTTLS si procede
    $ehloText = is_array($ehloResp) ? implode("\n", $ehloResp) : (string)$ehloResp;
    $supportsStartTLS = stripos($ehloText, 'STARTTLS') !== false;
    if (SMTP_SECURE === 'tls') {
        if (!$supportsStartTLS) {
            error_log("❌ [SMTP] Servidor no soporta STARTTLS");
            @fclose($socket);
            return false;
        }
        if (!smtp_cmd_expect($socket, "STARTTLS", 220)) {
            @fclose($socket);
            return false;
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("❌ [SMTP] No se pudo negociar TLS");
            @fclose($socket);
            return false;
        }
        // EHLO de nuevo tras TLS
        if (!smtp_cmd_expect($socket, "EHLO $helloName", 250, $ehloResp)) {
            @fclose($socket);
            return false;
        }
        $ehloText = is_array($ehloResp) ? implode("\n", $ehloResp) : (string)$ehloResp;
    }

    // AUTH si hay credenciales
    $autenticado = false;
    if (!empty(SMTP_USERNAME)) {
        $methods = smtp_auth_methods($ehloText);

        // AUTH PLAIN en una sola línea
        if (in_array('PLAIN', $methods)) {
            $auth = base64_encode("\0" . SMTP_USERNAME . "\0" . SMTP_PASSWORD);
            if (smtp_cmd_expect($socket, "AUTH PLAIN $auth", 235)) {
                $autenticado = true;
            }
        }

        // AUTH LOGIN
        if (!$autenticado && in_array('LOGIN', $methods)) {
            if (smtp_cmd_expect($socket, "AUTH LOGIN", 334)) {
                if (
                    smtp_cmd_expect($socket, base64_encode(SMTP_USERNAME), 334) &&
                    smtp_cmd_expect($socket, base64_encode(SMTP_PASSWORD), 235)
                ) {
                    $autenticado = true;
                }
            }
        }

        // Algunos servidores responden 530 en MAIL FROM si no se autenticó: reintentamos más tarde
    }

    // Construir mensaje (headers + cuerpo), incluyendo adjuntos si hay
    $payload = smtp_build_message($destinatarios, $asunto, $mensajeHtml, $adjuntos);

    // Intento de envío con eventual 530 → auth → reintento
    $trySend = function () use ($socket, $destinatarios, $payload, $bcc) {
        // Envelope sender
        if (!smtp_cmd_expect($socket, "MAIL FROM:<" . MAIL_FROM . ">", 250, $respMF)) {
            return ['ok' => false, 'resp' => $respMF];
        }

        foreach ($destinatarios as $rcpt) {
            if (!smtp_cmd_expect($socket, "RCPT TO:<$rcpt>", [250, 251], $respR)) {
                return ['ok' => false, 'resp' => $respR];
            }
        }
        // Copias ocultas: si una falla no se tumba el envío al destinatario real.
        foreach ($bcc as $rcpt) {
            if (!smtp_cmd_expect($socket, "RCPT TO:<$rcpt>", [250, 251], $respB)) {
                error_log('[SMTP] copia oculta rechazada para ' . $rcpt);
            }
        }

        if (!smtp_cmd_expect($socket, "DATA", 354, $respD)) {
            return ['ok' => false, 'resp' => $respD];
        }

        // Enviar payload crudo (incluye headers y cuerpo) + terminador
        if (fwrite($socket, $payload) === false) {
            return ['ok' => false, 'resp' => 'Error al escribir payload'];
        }
        if (!smtp_raw_expect($socket, "\r\n.\r\n", 250, $respDataEnd)) {
            return ['ok' => false, 'resp' => $respDataEnd];
        }
        return ['ok' => true, 'resp' => $respDataEnd];
    };

    $result = $trySend();

    // Si falla por 530 (auth requerida), intentamos autenticarnos y reintentamos
    if (!$result['ok'] && smtp_code($result['resp']) === 530 && !$autenticado && !empty(SMTP_USERNAME)) {
        // Intentar AUTH LOGIN como reintento universal
        if (
            smtp_cmd_expect($socket, "AUTH LOGIN", 334) &&
            smtp_cmd_expect($socket, base64_encode(SMTP_USERNAME), 334) &&
            smtp_cmd_expect($socket, base64_encode(SMTP_PASSWORD), 235)
        ) {

            // Reintento completo
            $result = $trySend();
        }
    }

    // Cerrar sesión
    smtp_cmd_expect($socket, "QUIT", 221);
    @fclose($socket);

    if (!$result['ok']) {
        error_log("❌ [SMTP] Fallo envío: " . (is_array($result['resp']) ? implode(" | ", $result['resp']) : $result['resp']));
    } else {
        error_log("✅ [SMTP] Envío correcto");
    }
    return $result['ok'];
}

// ============================
// CONSTRUCCIÓN DEL MENSAJE
// ============================
function smtp_build_message(array $destinatarios, $asunto, $html, array $adjuntos)
{
    $crlf = "\r\n";

    $subjectEnc = encode_mime_header($asunto);
    $fromEnc    = encode_mime_header(MAIL_FROM_NAME) . " <" . MAIL_FROM . ">";
    $toHeader   = implode(', ', $destinatarios);
    $messageId  = generate_message_id();

    $headers = [];
    $headers[] = "From: $fromEnc";
    $headers[] = "Reply-To: " . MAIL_REPLY_TO;
    $headers[] = "To: $toHeader";
    $headers[] = "Subject: $subjectEnc";
    $headers[] = "Date: " . date('r');
    $headers[] = "Message-ID: <$messageId>";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "X-Mailer: ISORGA/SMTP";

    $body = '';

    if (!empty($adjuntos)) {
        $boundary = '=-ISORGA-' . bin2hex(random_bytes(8));
        $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";

        // Parte 1: HTML (base64)
        $body .= "--$boundary$crlf";
        $body .= "Content-Type: text/html; charset=UTF-8$crlf";
        $body .= "Content-Transfer-Encoding: base64$crlf$crlf";
        $body .= chunk_split(base64_encode(normalize_eol($html, $crlf))) . $crlf;

        // Partes de adjuntos
        foreach ($adjuntos as $a) {
            if (empty($a['path']) || !is_readable($a['path'])) {
                continue;
            }
            $name = isset($a['name']) ? $a['name'] : basename($a['path']);
            $type = isset($a['type']) ? $a['type'] : 'application/octet-stream';
            $data = chunk_split(base64_encode(file_get_contents($a['path'])));

            $body .= "--$boundary$crlf";
            $body .= "Content-Type: $type; name=\"" . addcslashes($name, '"') . "\"$crlf";
            $body .= "Content-Transfer-Encoding: base64$crlf";
            $body .= "Content-Disposition: attachment; filename=\"" . addcslashes($name, '"') . "\"$crlf$crlf";
            $body .= $data . $crlf;
        }

        $body .= "--$boundary--$crlf";
    } else {
        // Solo HTML
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: base64";

        $body = chunk_split(base64_encode(normalize_eol($html, $crlf))) . $crlf;
    }

    // Ensamblar mensaje completo con CRLF
    $message = implode($crlf, $headers) . $crlf . $crlf . $body;

    // Dot-stuffing (si una línea empieza por '.', se duplica)
    $message = preg_replace('/(?<=\r\n)\./', '..', $message);

    // Al enviar con DATA se debe terminar con "\r\n.\r\n".
    // Aquí devolvemos solo el payload (headers+cuerpo); el cierre se añade en smtp_raw_expect().
    return $message;
}

// ============================
// SMTP UTILS
// ============================
function smtp_read($socket)
{
    $lines = [];
    while (($line = fgets($socket, 2048)) !== false) {
        $lines[] = rtrim($line, "\r\n");
        // Fin de multilínea: "XYZ " (espacio) o línea sin prefijo "XYZ-"
        if (preg_match('/^\d{3}\s/', $line) || !preg_match('/^\d{3}-/', $line)) {
            break;
        }
    }
    $out = $lines ?: ['(sin respuesta)'];
    foreach ($out as $l) {
        error_log("📡 [SMTP] $l");
    }
    return $out;
}

function smtp_code($resp)
{
    $line = is_array($resp) ? end($resp) : (string)$resp;
    if (preg_match('/^(\d{3})/', $line, $m)) return (int)$m[1];
    return 0;
}

function smtp_is($resp, $expected)
{
    $code = smtp_code($resp);
    if (is_array($expected)) return in_array($code, $expected, true);
    return ((int)$expected) === $code;
}

function smtp_cmd_expect($socket, $cmd, $expected, &$respOut = null)
{
    // 🔴 NUNCA volcar la línea de autenticación: `AUTH PLAIN <base64>` lleva
    // usuario y contraseña del buzón en claro (base64 no es cifrado). Los crons
    // redirigen stdout+stderr a logs/*.log, y esa carpeta está dentro del root
    // web. Fuga real detectada el 2026-08-09 en logs/notificar_commit.log.
    $cmdLog = preg_match('/^AUTH\b/i', $cmd) ? preg_replace('/^(AUTH\s+\S+).*/i', '$1 ***', $cmd) : $cmd;
    error_log("➡️  [SMTP] $cmdLog");
    if (fwrite($socket, $cmd . "\r\n") === false) return false;
    $resp = smtp_read($socket);
    $respOut = $resp;
    return smtp_is($resp, $expected);
}

function smtp_raw_expect($socket, $rawTail, $expected, &$respOut = null)
{
    if (fwrite($socket, $rawTail) === false) return false;
    $resp = smtp_read($socket);
    $respOut = $resp;
    return smtp_is($resp, $expected);
}

function smtp_auth_methods($ehloText)
{
    $methods = [];
    if (preg_match('/^250[ -]AUTH (.+)$/mi', $ehloText, $m)) {
        $list = preg_split('/\s+/', trim($m[1]));
        foreach ($list as $meth) {
            $methods[] = strtoupper($meth);
        }
    }
    return $methods;
}

// ============================
// FALLBACK NATIVO (opcional)
// ============================
function enviarCorreoNativo(array $destinatarios, $asunto, $mensajeHtml, $adjuntos = array(), array $bcc = array())
{
    $to       = implode(', ', $destinatarios);
    $subject  = encode_mime_header($asunto);
    $fromEnc  = encode_mime_header(MAIL_FROM_NAME) . " <" . MAIL_FROM . ">";
    $crlf     = "\r\n";

    $headers = [];
    $headers[] = "From: $fromEnc";
    $headers[] = "Reply-To: " . MAIL_REPLY_TO;
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "X-Mailer: ISORGA/mail()";
    $body = '';

    if (!empty($adjuntos)) {
        $boundary = '=-ISORGA-' . bin2hex(random_bytes(8));
        $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";

        $body .= "--$boundary$crlf";
        $body .= "Content-Type: text/html; charset=UTF-8$crlf";
        $body .= "Content-Transfer-Encoding: base64$crlf$crlf";
        $body .= chunk_split(base64_encode(normalize_eol($mensajeHtml, $crlf))) . $crlf;

        foreach ($adjuntos as $a) {
            if (empty($a['path']) || !is_readable($a['path'])) continue;
            $name = isset($a['name']) ? $a['name'] : basename($a['path']);
            $type = isset($a['type']) ? $a['type'] : 'application/octet-stream';
            $data = chunk_split(base64_encode(file_get_contents($a['path'])));

            $body .= "--$boundary$crlf";
            $body .= "Content-Type: $type; name=\"" . addcslashes($name, '"') . "\"$crlf";
            $body .= "Content-Transfer-Encoding: base64$crlf";
            $body .= "Content-Disposition: attachment; filename=\"" . addcslashes($name, '"') . "\"$crlf$crlf";
            $body .= $data . $crlf;
        }
        $body .= "--$boundary--$crlf";
    } else {
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: base64";
        $body = chunk_split(base64_encode(normalize_eol($mensajeHtml, $crlf))) . $crlf;
    }

    $ok = @mail($to, $subject, $body, implode($crlf, $headers));
    if (!$ok) error_log("❌ [mail()] Fallback fallido");
    return $ok;
}

// ============================
// UTILIDADES COMUNES
// ============================
function normalize_eol($text, $to = "\r\n")
{
    $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
    return str_replace("\n", $to, $text);
}

function encode_mime_header($text)
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function generate_message_id()
{
    $domain = $_SERVER['SERVER_NAME'] ?? 'isorga.local';
    $domain = preg_replace('/[^A-Za-z0-9\.\-]/', '', $domain) ?: 'isorga.local';
    return bin2hex(random_bytes(8)) . '@' . $domain;
}

function limpiarEmail($email)
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validarEmails($emails)
{
    $emails_array = is_array($emails) ? $emails : preg_split('/[,;]+/', $emails);
    $validos = [];
    foreach ($emails_array as $em) {
        $e = limpiarEmail($em);
        if (filter_var($e, FILTER_VALIDATE_EMAIL)) $validos[] = $e;
    }
    return array_values(array_unique($validos));
}

function registrarIntentoCorreo($destinatario, $asunto, $exito, $error = '')
{
    $fecha   = date('Y-m-d H:i:s');
    $usuario = isset($_SESSION['NoUsuario']) ? $_SESSION['NoUsuario'] : 'SISTEMA';

    $log = [
        'fecha'        => $fecha,
        'usuario'      => $usuario,
        'destinatario' => $destinatario,
        'asunto'       => $asunto,
        'exito'        => $exito ? 'SI' : 'NO',
        'error'        => $error
    ];
    error_log("📧 INTENTO CORREO: " . json_encode($log, JSON_UNESCAPED_UNICODE));

    // Guardado opcional en BD (asume Conectar::una disponible)
    try {
        $sql = "INSERT INTO log_correos (fecha, usuario, destinatario, asunto, exito, error) 
                VALUES ('" . addslashes($fecha) . "',
                        '" . addslashes($usuario) . "',
                        '" . addslashes($destinatario) . "',
                        '" . addslashes($asunto) . "',
                        '" . ($exito ? 'SI' : 'NO') . "',
                        '" . addslashes($error) . "')";
        if (class_exists('Conectar')) {
            Conectar::una($sql);
        }
    } catch (Exception $e) {
        error_log("⚠️ Error al guardar log de correo en BD: " . $e->getMessage());
    }
}

// ============================
// DIAGNÓSTICO / VERIFICACIÓN
// ============================
function verificarConfiguracionCorreo()
{
    return [
        'php_mail'        => function_exists('mail'),
        'openssl'         => extension_loaded('openssl'),
        'smtp_configurado' => !empty(SMTP_HOST) && !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD),
        'smtp_host'       => SMTP_HOST,
        'smtp_puerto'     => SMTP_PORT,
        'smtp_seguridad'  => SMTP_SECURE,
        'smtp_verify'     => SMTP_VERIFY_PEER,
        'smtp_usuario'    => SMTP_USERNAME ? '[definido]' : '[vacío]',
    ];
}

function probarConexionSMTP()
{
    $scheme = (SMTP_SECURE === 'ssl' || ((int)SMTP_PORT === 465 && SMTP_SECURE !== 'tls')) ? 'ssl://' : 'tcp://';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => SMTP_VERIFY_PEER,
            'verify_peer_name'  => SMTP_VERIFY_PEER,
            'allow_self_signed' => !SMTP_VERIFY_PEER,
        ],
    ]);

    $sock = @stream_socket_client($scheme . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$sock) return ['error' => "Conexión fallida: $errstr ($errno)"];
    $resp = smtp_read($sock);

    // Si STARTTLS requerido y estamos en tcp:// con SECURE=tls, intentamos handshake básico
    if ($scheme === 'tcp://' && SMTP_SECURE === 'tls') {
        smtp_cmd_expect($sock, "EHLO localhost", 250, $ehlo);
        if (!smtp_cmd_expect($sock, "STARTTLS", 220)) {
            @fclose($sock);
            return ['error' => 'Servidor no soporta STARTTLS'];
        }
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            @fclose($sock);
            return ['error' => 'Fallo negociando TLS'];
        }
    }
    @fclose($sock);

    return ['success' => true, 'message' => "Conexión OK a " . SMTP_HOST . ":" . SMTP_PORT, 'banner' => is_array($resp) ? implode(" | ", $resp) : $resp];
}

// ============================
// PLANTILLA DE NOTIFICACIÓN
// ============================
function enviarNotificacionSistema($destinatario, $titulo, $mensaje, $tipo = 'sistema', $bcc = array())
{
    switch ($tipo) {
        case 'evaluacion':
            $icono = '📋';
            break;
        case 'recordatorio':
            $icono = '📅';
            break;
        case 'alerta':
            $icono = '⚠️';
            break;
        case 'exito':
            $icono = '✅';
            break;
        default:
            $icono = '🔔';
    }
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
      <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; text-align: center;'>
        <h1 style='margin:0;'>{$icono} " . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . "</h1>
      </div>
      <div style='padding:20px; background:#f8f9fa;'>
        $mensaje
      </div>
      <div style='background:#e9ecef; padding:15px; text-align:center; color:#6c757d; font-size:12px;'>
        <p>Este es un correo automático del sistema de gestión de ISORGA.</p>
        <p>No responda a este correo. Para consultas, contacte con soporte@isorga.com.</p>
       
      </div>
    </div>";
    return enviarCorreoIsorga($destinatario, "[ISORGA] $titulo", $html, array(), $bcc);

//     <p>No responda a este correo. Para consultas, contacte con " . TenantResolver::get('email_soporte', 'soporte@isorga.com') . ".</p>
}

// =====================================================================
// PLANTILLA DE CREDENCIALES — misma estética que los satélites (AUDIT, ADR)
// ---------------------------------------------------------------------
// Los correos que llevan una contraseña (recuperación y alta de usuario)
// NO usan enviarNotificacionSistema(): esa función mete su propia cabecera
// con degradado y prefija el asunto con «[ISORGA]». Aquí el correo se
// construye entero — cabecera de marca, caja con la contraseña, botón de
// entrada y aviso — como en audit.isorga.com y adr.isorga.com.
//
// 🔴 Quien llame COMPRUEBA el valor devuelto por enviarCorreoIsorga (§28.1).
// =====================================================================

if (!function_exists('isorga_mail_marca')) {

    /**
     * Marca del tenant activo para los correos: nombre, dominio y color.
     * Funciona aunque TenantResolver no esté cargado (CLI, includes sueltos).
     */
    function isorga_mail_marca(): array
    {
        $cfg = [];
        if (!class_exists('TenantResolver')) {
            $f = __DIR__ . '/../TenantResolver.php';
            if (file_exists($f)) { include_once $f; }
        }
        if (class_exists('TenantResolver')) {
            $cfg = TenantResolver::resolve();
        }

        return [
            'nombre'  => $cfg['nombre']        ?? 'ISORGA',
            'dominio' => $cfg['dominio']       ?? 'isorga.com',
            'color'   => $cfg['color_primary'] ?? '#0ea5e9',
            'soporte' => $cfg['email_soporte'] ?? 'soporte@isorga.com',
            'idioma'  => $cfg['idioma_defecto'] ?? 'es',
        ];
    }

    /**
     * Plantilla HTML común (cabecera de marca + título + cuerpo + pie).
     * Estética oscura, tablas y estilos inline: es lo único que renderizan
     * igual todos los clientes de correo.
     */
    function isorga_mail_plantilla(string $titulo, string $cuerpoHtml, string $idioma = 'es'): string
    {
        $m     = isorga_mail_marca();
        $t     = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $marca = htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8');

        $lemas = [
            'es' => 'Sistemas de gestión',
            'fr' => 'Systèmes de management',
            'en' => 'Management systems',
            'ca' => 'Sistemes de gestió',
        ];
        $pies = [
            'es' => 'Este mensaje es automático; no respondas a esta dirección.',
            'fr' => 'Ce message est automatique ; ne répondez pas à cette adresse.',
            'en' => 'This is an automated message; do not reply to this address.',
            'ca' => 'Aquest missatge és automàtic; no responguis a aquesta adreça.',
        ];
        $lema = $lemas[$idioma] ?? $lemas['es'];
        $pie  = $pies[$idioma]  ?? $pies['es'];

        // 🔴 URL absoluta y pública: los clientes de correo no resuelven rutas
        // relativas y bloquean los `data:` en <img>. Es el mismo isotipo que
        // llevan los correos de AUDIT, ADR y CAE.
        $logo = 'https://isorga.com/assets/img/isorga-logo.png';

        return <<<HTML
<!DOCTYPE html>
<html lang="{$idioma}"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#0d1117;font-family:Arial,Helvetica,sans-serif;color:#c9d1d9;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#161b22;border:1px solid #30363d;">
    <tr><td style="padding:24px 28px;border-bottom:1px solid #30363d;">
      <table role="presentation" cellpadding="0" cellspacing="0"><tr>
        <td style="padding-right:12px;" valign="middle">
          <img src="{$logo}" width="40" height="40" alt="ISORGA"
               style="display:block;width:40px;height:40px;background:#ffffff;border-radius:20px;">
        </td>
        <td valign="middle">
          <div style="font-size:20px;font-weight:700;letter-spacing:2px;color:#e6edf3;">{$marca}</div>
          <div style="font-size:12px;color:#8b949e;">{$lema}</div>
        </td>
      </tr></table>
    </td></tr>
    <tr><td style="padding:28px;">
      <h1 style="margin:0 0 16px;font-size:17px;color:#e6edf3;font-weight:600;">{$t}</h1>
      {$cuerpoHtml}
    </td></tr>
    <tr><td style="padding:16px 28px;border-top:1px solid #30363d;font-size:11px;color:#6e7681;">
      {$pie}
    </td></tr>
  </table>
</body></html>
HTML;
    }

    /**
     * Correo de credenciales, listo para enviar.
     *
     * @param array $d  nombre · usuario · password (opcional) · tipo
     *                  ('recuperacion' | 'alta' | 'centro') · centro (opcional)
     *                  · idioma (opcional, por defecto el del tenant)
     * @return array [asunto, html]
     */
    function isorga_mail_credenciales(array $d): array
    {
        $m      = isorga_mail_marca();
        $idioma = $d['idioma'] ?? $m['idioma'];
        $tipo   = $d['tipo']   ?? 'recuperacion';
        $marca  = htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8');
        $color  = $m['color'];
        // 🔴 `url` ES OPCIONAL, Y AÑADIRLO NO ES UN CAPRICHO (2026-09-29).
        //    Por defecto es el login de ISORGA, que es lo que quieren los tres
        //    llamadores de siempre (alta de usuario y recuperación).
        //
        //    Pero el M26 manda credenciales a una CONTRATA, que no tiene cuenta en
        //    ISORGA: entra por `cae.isorga.com/auth/login.php`. Mandarle el login de
        //    ISORGA sería mandarla a una puerta donde no existe. Por eso puede
        //    pasar la suya, y por eso este correo sigue maquetándose AQUÍ y no a
        //    mano en el módulo (§28.1: «un correo con contraseña no se maqueta a
        //    mano»).
        $url    = (string) ($d['url'] ?? ('https://' . $m['dominio'] . '/login.php'));

        $nombre = htmlspecialchars((string)($d['nombre']   ?? ''), ENT_QUOTES, 'UTF-8');
        $user   = htmlspecialchars((string)($d['usuario']  ?? ''), ENT_QUOTES, 'UTF-8');
        $pass   = htmlspecialchars((string)($d['password'] ?? ''), ENT_QUOTES, 'UTF-8');
        $centro = htmlspecialchars((string)($d['centro']   ?? ''), ENT_QUOTES, 'UTF-8');

        $T = [
            'es' => [
                'titulo_recuperacion' => 'Contraseña nueva',
                'titulo_alta'         => 'Tu acceso a la plataforma',
                'titulo_centro'       => 'Nuevo centro asignado',
                'asunto_recuperacion' => $marca . ' · Tu contraseña nueva',
                'asunto_alta'         => $marca . ' · Tu acceso a la plataforma',
                'asunto_centro'       => $marca . ' · Nuevo centro asignado',
                'intro_recuperacion'  => 'Hola ' . $nombre . ', esta es tu <strong style="color:#e6edf3;">contraseña nueva</strong> para entrar en ' . $marca . ':',
                'intro_alta'          => 'Hola ' . $nombre . ', ya tienes acceso a ' . $marca . '. Estas son tus credenciales:',
                'intro_centro'        => 'Hola ' . $nombre . ', se te ha dado acceso a un centro nuevo en ' . $marca . '. Entra con tu usuario y contraseña de siempre.',
                'centro'              => 'Centro',
                'usuario'             => 'Usuario',
                'nota_recuperacion'   => 'La contraseña anterior ya no funciona. Entra con esta y cámbiala cuando quieras.',
                'nota_alta'           => 'Entra con esta contraseña y cámbiala cuando quieras.',
                'boton'               => 'Entrar en ' . $marca,
                'aviso_titulo'        => '¿No has sido tú?',
                'aviso_texto'         => 'Alguien ha pedido una contraseña nueva para tu cuenta. Si no has sido tú, entra con esta y cámbiala: la anterior ya no sirve.',
            ],
            'fr' => [
                'titulo_recuperacion' => 'Nouveau mot de passe',
                'titulo_alta'         => 'Votre accès à la plateforme',
                'titulo_centro'       => 'Nouveau site attribué',
                'asunto_recuperacion' => $marca . ' · Votre nouveau mot de passe',
                'asunto_alta'         => $marca . ' · Votre accès à la plateforme',
                'asunto_centro'       => $marca . ' · Nouveau site attribué',
                'intro_recuperacion'  => 'Bonjour ' . $nombre . ', voici votre <strong style="color:#e6edf3;">nouveau mot de passe</strong> pour accéder à ' . $marca . ' :',
                'intro_alta'          => 'Bonjour ' . $nombre . ', votre accès à ' . $marca . ' est ouvert. Voici vos identifiants :',
                'intro_centro'        => 'Bonjour ' . $nombre . ', un nouveau site vous a été attribué sur ' . $marca . '. Connectez-vous avec vos identifiants habituels.',
                'centro'              => 'Site',
                'usuario'             => 'Utilisateur',
                'nota_recuperacion'   => "L'ancien mot de passe ne fonctionne plus. Connectez-vous avec celui-ci et changez-le quand vous voulez.",
                'nota_alta'           => 'Connectez-vous avec ce mot de passe et changez-le quand vous voulez.',
                'boton'               => 'Se connecter à ' . $marca,
                'aviso_titulo'        => "Ce n'était pas vous ?",
                'aviso_texto'         => "Quelqu'un a demandé un nouveau mot de passe pour votre compte. Si ce n'était pas vous, connectez-vous avec celui-ci et changez-le : l'ancien ne sert plus.",
            ],
            'en' => [
                'titulo_recuperacion' => 'New password',
                'titulo_alta'         => 'Your access to the platform',
                'titulo_centro'       => 'New site assigned',
                'asunto_recuperacion' => $marca . ' · Your new password',
                'asunto_alta'         => $marca . ' · Your access to the platform',
                'asunto_centro'       => $marca . ' · New site assigned',
                'intro_recuperacion'  => 'Hi ' . $nombre . ', this is your <strong style="color:#e6edf3;">new password</strong> to sign in to ' . $marca . ':',
                'intro_alta'          => 'Hi ' . $nombre . ', your access to ' . $marca . ' is ready. These are your credentials:',
                'intro_centro'        => 'Hi ' . $nombre . ', a new site has been assigned to you in ' . $marca . '. Sign in with your usual credentials.',
                'centro'              => 'Site',
                'usuario'             => 'User',
                'nota_recuperacion'   => 'The previous password no longer works. Sign in with this one and change it whenever you want.',
                'nota_alta'           => 'Sign in with this password and change it whenever you want.',
                'boton'               => 'Sign in to ' . $marca,
                'aviso_titulo'        => "Wasn't you?",
                'aviso_texto'         => 'Someone requested a new password for your account. If it was not you, sign in with this one and change it: the previous one no longer works.',
            ],
            'ca' => [
                'titulo_recuperacion' => 'Contrasenya nova',
                'titulo_alta'         => 'El teu accés a la plataforma',
                'titulo_centro'       => 'Nou centre assignat',
                'asunto_recuperacion' => $marca . ' · La teva contrasenya nova',
                'asunto_alta'         => $marca . ' · El teu accés a la plataforma',
                'asunto_centro'       => $marca . ' · Nou centre assignat',
                'intro_recuperacion'  => 'Hola ' . $nombre . ', aquesta és la teva <strong style="color:#e6edf3;">contrasenya nova</strong> per entrar a ' . $marca . ':',
                'intro_alta'          => 'Hola ' . $nombre . ', ja tens accés a ' . $marca . '. Aquestes són les teves credencials:',
                'intro_centro'        => 'Hola ' . $nombre . ', se t\'ha assignat un centre nou a ' . $marca . '. Entra amb el teu usuari i contrasenya de sempre.',
                'centro'              => 'Centre',
                'usuario'             => 'Usuari',
                'nota_recuperacion'   => 'La contrasenya anterior ja no funciona. Entra amb aquesta i canvia-la quan vulguis.',
                'nota_alta'           => 'Entra amb aquesta contrasenya i canvia-la quan vulguis.',
                'boton'               => 'Entrar a ' . $marca,
                'aviso_titulo'        => 'No has estat tu?',
                'aviso_texto'         => 'Algú ha demanat una contrasenya nova per al teu compte. Si no has estat tu, entra amb aquesta i canvia-la: l\'anterior ja no serveix.',
            ],
        ];
        $t = $T[$idioma] ?? $T['es'];

        $cuerpo  = '<p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#c9d1d9;">'
                 . $t['intro_' . $tipo] . '</p>';

        // Caja con la contraseña (solo cuando viaja una)
        if ($pass !== '') {
            $cuerpo .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">'
                     . '<tr><td style="background:#0d1117;border:1px solid #30363d;padding:16px 26px;">'
                     . '<span style="font-family:\'Courier New\',monospace;font-size:22px;letter-spacing:3px;color:#e6edf3;font-weight:700;">'
                     . $pass . '</span></td></tr></table>';
        }

        // Usuario y centro
        $datos = '';
        if ($user !== '') {
            $datos .= '<tr><td style="padding:0 16px 6px 0;font-size:12px;color:#8b949e;">' . $t['usuario'] . '</td>'
                    . '<td style="padding:0 0 6px;font-size:13px;color:#e6edf3;">' . $user . '</td></tr>';
        }
        if ($centro !== '') {
            $datos .= '<tr><td style="padding:0 16px 6px 0;font-size:12px;color:#8b949e;">' . $t['centro'] . '</td>'
                    . '<td style="padding:0 0 6px;font-size:13px;color:#e6edf3;">' . $centro . '</td></tr>';
        }
        if ($datos !== '') {
            $cuerpo .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">' . $datos . '</table>';
        }

        if ($tipo !== 'centro') {
            $cuerpo .= '<p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#c9d1d9;">'
                     . ($tipo === 'recuperacion' ? $t['nota_recuperacion'] : $t['nota_alta']) . '</p>';
        }

        $cuerpo .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">'
                 . '<tr><td style="background:' . $color . ';">'
                 . '<a href="' . $url . '" style="display:inline-block;padding:12px 26px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;letter-spacing:1px;text-transform:uppercase;">'
                 . $t['boton'] . '</a></td></tr></table>';

        if ($tipo === 'recuperacion') {
            $cuerpo .= '<p style="margin:0;padding:12px 14px;background:#0d1117;border-left:3px solid #d29922;font-size:12px;line-height:1.6;color:#8b949e;">'
                     . '<strong style="color:#d29922;">' . $t['aviso_titulo'] . '</strong><br>'
                     . $t['aviso_texto'] . '</p>';
        }

        return [
            'asunto' => $t['asunto_' . $tipo],
            'html'   => isorga_mail_plantilla($t['titulo_' . $tipo], $cuerpo, $idioma),
        ];
    }
}
