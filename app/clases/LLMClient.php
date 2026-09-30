<?php
/**
 * =====================================================================
 * LLMClient — Capa de abstracción para llamadas a modelos de IA (v1)
 * ---------------------------------------------------------------------
 * Spec: /home/juanmi/.claude/plans/trabaja-en-modo-plan-wild-quokka.md
 *
 * Patrón: clase estática, no instanciable (idéntico a Conectar).
 *
 * Uso típico:
 *   require_once '../app/clases/LLMClient.php';
 *   $r = LLMClient::pedir('redaccion_accion_correctiva', $prompt);
 *   if ($r['ok']) { echo $r['respuesta']; }
 *
 * Reglas clave:
 *   - El módulo cliente NUNCA nombra a "Claude" ni a "OpenAI". Solo pasa
 *     el código de operación. El routing modelo/proveedor vive en la BD.
 *   - Toda llamada (incluso las que fallan) se registra en llm_llamadas
 *     y se agrega en llm_consumo_mensual.
 *   - Solo se guarda el SHA-256 del prompt (RGPD), nunca el prompt en claro.
 *   - Sin reintentos automáticos: el error se devuelve al cliente.
 *   - Las claves API se leen con getenv() — definidas en
 *     /etc/php/8.3/fpm/pool.d/www.conf para FPM, o en /etc/isorga.env
 *     para scripts CLI (cargado por el propio script antes de invocar).
 * =====================================================================
 */

class LLMClient
{
    // ---------------------------------------------------------------------
    // Configuración interna
    // ---------------------------------------------------------------------

    /** Timeout por defecto en segundos para cada llamada HTTP. */
    private const DEFAULT_TIMEOUT = 60;

    // ---------------------------------------------------------------------
    // VISIÓN — adjuntos multimodales (v16.612)
    // ---------------------------------------------------------------------
    // Añadido de forma ADITIVA: si no se pasa $opciones['adjuntos'], todo lo
    // de abajo queda inerte y el payload sale byte a byte como salía antes.
    // Ninguna de las 30 operaciones existentes pasa adjuntos.
    //
    // Antes de esto, el único código de la plataforma que usaba visión
    // (26/cae_ia_validar_doc.php) se saltaba esta clase: curl directo a
    // api.anthropic.com, modelo hardcodeado, clave leída con getenv() a pelo
    // y CERO registro en llm_llamadas — su gasto no contaba para ninguna
    // cuota ni aparecía en ningún panel. Esto existe para no repetirlo.
    // ---------------------------------------------------------------------

    /** MIME real de un adjunto → cómo lo empaqueta cada proveedor. Es la whitelist. */
    private const ADJUNTOS_MIME = [
        'application/pdf' => 'documento',
        'image/jpeg'      => 'imagen',
        'image/png'       => 'imagen',
        'image/webp'      => 'imagen',
        'image/gif'       => 'imagen',
    ];

    /** Tamaño máximo del conjunto de adjuntos, en bytes (antes de base64). */
    // Anthropic corta la petición entera en 32 MB. base64 infla un 33 %, así
    // que 20 MB de fichero son ~27 MB de payload: deja margen para el prompt.
    private const ADJUNTOS_MAX_BYTES = 20 * 1024 * 1024;

    /** Número máximo de adjuntos por llamada. */
    private const ADJUNTOS_MAX_NUM = 20;

    /** Caches por request para evitar releer la misma fila varias veces. */
    private static array $cacheOperaciones = [];
    private static array $cacheModelos = [];

    /** Cache del parseo de /etc/isorga.env (fallback CLI). null = no cargado todavía. */
    private static ?array $envFileCache = null;

    /** No instanciable. */
    private function __construct() {}

    // =====================================================================
    // API PÚBLICA
    // =====================================================================

    /**
     * Ejecuta una operación de IA.
     *
     * @param string $operacion  Código de operación (debe existir y estar activo en llm_operaciones).
     * @param string $prompt     Prompt completo. El cliente es responsable de construirlo.
     * @param array  $opciones   Claves opcionales:
     *                           - centroId        (int)    si no se pasa, se toma de la sesión
     *                           - usuarioId       (int)    si no se pasa, se toma de la sesión
     *                           - max_tokens      (int)    sobreescribe el de la operación
     *                           - temperatura     (float)  sobreescribe el de la operación
     *                           - timeout_segundos(int)    por defecto 60
     *                           - adjuntos        (array)  VISIÓN. Lista de ficheros a
     *                                             enviar junto al prompt. Cada uno:
     *                                                ['ruta' => '/ruta/absoluta',
     *                                                 'mime' => 'application/pdf']
     *                                             La clase los lee, valida y codifica.
     *                                             Se pasa la RUTA y no el base64 a
     *                                             propósito: así el límite de tamaño se
     *                                             comprueba aquí, antes de construir un
     *                                             payload de 40 MB que el proveedor va a
     *                                             rechazar igual.
     *                                             PDF solo lo admite 'anthropic'.
     *                                             No disponible en modo doble_motor.
     *
     * @return array  Modo simple:
     *                  ['ok'=>true, 'modo'=>'simple', 'respuesta'=>..., 'modelo_usado'=>...,
     *                   'tokens_entrada'=>..., 'tokens_salida'=>..., 'coste_estimado'=>..., 'latencia_ms'=>...]
     *
     *                Modo doble_motor:
     *                  ['ok'=>true, 'modo'=>'doble_motor', 'respuestas'=>[ {...}, {...} ]]
     *
     *                Error:
     *                  ['ok'=>false, 'error_tipo'=>..., 'mensaje'=>..., 'detalle_tecnico'=>...]
     */
    public static function pedir(string $operacion, string $prompt, array $opciones = []): array
    {
        $centroId  = self::resolverCentroId($opciones);
        $usuarioId = self::resolverUsuarioId($opciones);
        $timeout   = isset($opciones['timeout_segundos']) ? (int)$opciones['timeout_segundos'] : self::DEFAULT_TIMEOUT;

        // Visión. Sin esta clave, $adjuntos queda [] y NADA de lo que sigue
        // cambia respecto al comportamiento anterior a v16.612.
        $adjuntos = isset($opciones['adjuntos']) && is_array($opciones['adjuntos'])
            ? $opciones['adjuntos'] : [];

        // El hash del prompt solo incorpora los adjuntos SI los hay: así las
        // llamadas sin adjuntos siguen produciendo exactamente el mismo hash
        // que producían antes, y llm_llamadas.prompt_hash sigue siendo
        // comparable con el histórico.
        $promptHash = $adjuntos === []
            ? hash('sha256', $prompt)
            : hash('sha256', $prompt . '|adj:' . self::huellaAdjuntos($adjuntos));

        // 1) Cargar configuración de la operación
        $op = self::cargarOperacion($operacion);
        if ($op === null) {
            return self::errorConfig(
                "La operación '$operacion' no existe o no está activa en llm_operaciones.",
                "operacion_no_registrada"
            );
        }

        // 1-ter) Adjuntos de visión. Se validan aquí, ANTES de la cuota y del
        // payload: un fichero que no existe o que pesa 40 MB es un error del
        // llamante, no consume nada y no merece ni ensuciar el log de llamadas.
        // Si no hay adjuntos, validarAdjuntos() devuelve null sin hacer nada.
        if ($adjuntos !== []) {
            $errAdj = self::validarAdjuntos($adjuntos);
            if ($errAdj !== null) {
                return self::errorConfig($errAdj, 'adjunto_invalido');
            }
        }

        // 1-bis) Cuota de consumo del centro. Antes de construir el payload:
        // una llamada rechazada por tope no debe costar ni un token.
        $errCuota = self::comprobarCuota($op, $centroId);
        if ($errCuota !== null) {
            self::registrarLlamada($op, ['codigo' => '-'], [
                'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                'resultado' => 'error_cuota', 'mensaje_error' => $errCuota,
            ], $centroId, $usuarioId, $promptHash);
            return [
                'ok' => false,
                'error_tipo' => 'error_cuota',
                'mensaje' => $errCuota,
                'detalle_tecnico' => $errCuota,
            ];
        }

        $maxTokens   = isset($opciones['max_tokens']) ? (int)$opciones['max_tokens'] : (int)$op['max_tokens_salida'];
        $temperatura = isset($opciones['temperatura']) ? (float)$opciones['temperatura'] : (float)$op['temperatura'];

        // 2) Routing por modo
        if ($op['modo'] === 'simple') {
            $modelo = self::cargarModelo((int)$op['modelo_id']);
            if ($modelo === null || (int)$modelo['activo'] !== 1) {
                $msg = "El modelo asignado a '$operacion' no existe o no está activo.";
                self::registrarLlamada($op, ['codigo' => '?'], [
                    'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                    'resultado' => 'error_config', 'mensaje_error' => $msg,
                ], $centroId, $usuarioId, $promptHash);
                return self::errorConfig($msg, 'modelo_inactivo');
            }

            // Validar sensibilidad
            $errSens = self::validarSensibilidad($op, $modelo);
            if ($errSens !== null) {
                self::registrarLlamada($op, $modelo, [
                    'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                    'resultado' => 'error_sensibilidad', 'mensaje_error' => $errSens,
                ], $centroId, $usuarioId, $promptHash);
                return [
                    'ok' => false,
                    'error_tipo' => 'error_sensibilidad',
                    'mensaje' => $errSens,
                    'detalle_tecnico' => $errSens,
                ];
            }

            // Obtener clave API
            $apiKey = self::obtenerApiKey($modelo['proveedor']);
            if ($apiKey === null || $apiKey === '') {
                $msg = "Clave API de '{$modelo['proveedor']}' no configurada en el servidor.";
                self::registrarLlamada($op, $modelo, [
                    'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                    'resultado' => 'error_config', 'mensaje_error' => $msg,
                ], $centroId, $usuarioId, $promptHash);
                return self::errorConfig($msg, 'api_key_ausente');
            }

            // Adjuntos: comprobación que depende del PROVEEDOR. Un PDF solo lo
            // admite Anthropic; mandárselo a otro es gastar la llamada para
            // recibir un 400. Se comprueba con el modelo ya resuelto porque
            // cambiar de modelo es un UPDATE, no un despliegue: la operación
            // que hoy va a Anthropic mañana puede apuntar a otro sitio.
            if ($adjuntos !== []) {
                $errProv = self::validarAdjuntosProveedor($adjuntos, $modelo);
                if ($errProv !== null) {
                    self::registrarLlamada($op, $modelo, [
                        'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                        'resultado' => 'error_config', 'mensaje_error' => $errProv,
                    ], $centroId, $usuarioId, $promptHash);
                    return self::errorConfig($errProv, 'adjunto_no_soportado');
                }
            }

            // Ejecutar
            $payload = self::construirPayload($modelo, $prompt, $maxTokens, $temperatura, $adjuntos);
            $resp = self::ejecutarHttp($modelo, $apiKey, $payload, $timeout);

            // Registrar (siempre, ok o no)
            self::registrarLlamada($op, $modelo, [
                'tokens_in'     => $resp['tokens_in'] ?? 0,
                'tokens_out'    => $resp['tokens_out'] ?? 0,
                'latencia_ms'   => $resp['latencia_ms'] ?? 0,
                'resultado'     => $resp['ok'] ? 'ok' : ($resp['error_tipo'] ?? 'error_proveedor'),
                'mensaje_error' => $resp['ok'] ? null : ($resp['error'] ?? 'Error desconocido'),
            ], $centroId, $usuarioId, $promptHash);

            if (!$resp['ok']) {
                return [
                    'ok' => false,
                    'error_tipo' => $resp['error_tipo'] ?? 'error_proveedor',
                    'mensaje' => self::mensajeUsuario($resp['error_tipo'] ?? 'error_proveedor', $resp['error'] ?? ''),
                    'detalle_tecnico' => $resp['error'] ?? '',
                ];
            }

            return [
                'ok' => true,
                'modo' => 'simple',
                'respuesta' => $resp['texto'],
                'modelo_usado' => $modelo['codigo'],
                'tokens_entrada' => $resp['tokens_in'],
                'tokens_salida' => $resp['tokens_out'],
                'coste_estimado' => self::calcularCoste($resp['tokens_in'], $resp['tokens_out'], $modelo),
                'latencia_ms' => $resp['latencia_ms'],
            ];
        }

        if ($op['modo'] === 'doble_motor') {
            // 🔴 Visión NO en doble_motor, y es deliberado: el modo lanza la
            // misma pregunta a dos modelos para comparar, y no hay garantía de
            // que los dos admitan adjuntos. Enviar un PDF al que no puede
            // leerlo gasta la llamada entera para recibir un 400. Ninguna
            // operación de la plataforma lo necesita hoy; el día que haga
            // falta, se decide qué hacer cuando solo uno de los dos sabe ver.
            if ($adjuntos !== []) {
                return self::errorConfig(
                    "La operación '$operacion' está en modo doble_motor y ese modo no admite adjuntos de visión.",
                    'adjuntos_en_doble_motor'
                );
            }

            $modelo1 = self::cargarModelo((int)$op['modelo_id']);
            $modelo2 = self::cargarModelo((int)$op['modelo_id_2']);
            if ($modelo1 === null || $modelo2 === null
                || (int)$modelo1['activo'] !== 1 || (int)$modelo2['activo'] !== 1) {
                $msg = "Modo doble_motor requiere dos modelos activos en la operación '$operacion'.";
                return self::errorConfig($msg, 'modelos_doble_motor_invalidos');
            }

            // Validar sensibilidad para ambos
            foreach ([$modelo1, $modelo2] as $m) {
                $errSens = self::validarSensibilidad($op, $m);
                if ($errSens !== null) {
                    self::registrarLlamada($op, $m, [
                        'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                        'resultado' => 'error_sensibilidad', 'mensaje_error' => $errSens,
                    ], $centroId, $usuarioId, $promptHash);
                    return [
                        'ok' => false,
                        'error_tipo' => 'error_sensibilidad',
                        'mensaje' => $errSens,
                        'detalle_tecnico' => $errSens,
                    ];
                }
            }

            // Obtener claves API
            $jobs = [];
            foreach ([$modelo1, $modelo2] as $idx => $m) {
                $apiKey = self::obtenerApiKey($m['proveedor']);
                if ($apiKey === null || $apiKey === '') {
                    $msg = "Clave API de '{$m['proveedor']}' no configurada en el servidor.";
                    self::registrarLlamada($op, $m, [
                        'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                        'resultado' => 'error_config', 'mensaje_error' => $msg,
                    ], $centroId, $usuarioId, $promptHash);
                    return self::errorConfig($msg, 'api_key_ausente');
                }
                $jobs[] = [
                    'modelo'  => $m,
                    'apiKey'  => $apiKey,
                    'payload' => self::construirPayload($m, $prompt, $maxTokens, $temperatura),
                ];
            }

            // Lanzar las dos llamadas en paralelo
            $resultados = self::ejecutarHttpMulti($jobs, $timeout);

            // Registrar las dos filas con misma fecha_hora (precisión segundo)
            $fechaHora = date('Y-m-d H:i:s');
            $respuestas = [];
            foreach ($resultados as $i => $resp) {
                $m = $jobs[$i]['modelo'];
                self::registrarLlamada($op, $m, [
                    'tokens_in'     => $resp['tokens_in'] ?? 0,
                    'tokens_out'    => $resp['tokens_out'] ?? 0,
                    'latencia_ms'   => $resp['latencia_ms'] ?? 0,
                    'resultado'     => $resp['ok'] ? 'ok' : ($resp['error_tipo'] ?? 'error_proveedor'),
                    'mensaje_error' => $resp['ok'] ? null : ($resp['error'] ?? 'Error desconocido'),
                ], $centroId, $usuarioId, $promptHash, $fechaHora);

                $respuestas[] = [
                    'modelo_usado' => $m['codigo'],
                    'ok' => $resp['ok'],
                    'respuesta' => $resp['ok'] ? $resp['texto'] : null,
                    'error' => $resp['ok'] ? null : ($resp['error'] ?? 'Error desconocido'),
                    'tokens_entrada' => $resp['tokens_in'] ?? 0,
                    'tokens_salida'  => $resp['tokens_out'] ?? 0,
                    'coste_estimado' => $resp['ok'] ? self::calcularCoste($resp['tokens_in'], $resp['tokens_out'], $m) : 0.0,
                    'latencia_ms'    => $resp['latencia_ms'] ?? 0,
                ];
            }

            // Si AL MENOS uno fue ok, se considera ok=true (el cliente decide qué hacer)
            $hayAlMenosUnaOk = false;
            foreach ($respuestas as $r) { if ($r['ok']) { $hayAlMenosUnaOk = true; break; } }

            return [
                'ok' => $hayAlMenosUnaOk,
                'modo' => 'doble_motor',
                'respuestas' => $respuestas,
            ];
        }

        return self::errorConfig("Modo desconocido '{$op['modo']}' en operación '$operacion'.", 'modo_invalido');
    }

    /**
     * Genera un embedding vectorial para un texto.
     *
     * @param string $operacion Código de operación tipo embeddings (modelo debe ser
     *                          un modelo de embeddings, p.ej. text-embedding-3-small).
     * @param string $texto     Texto a vectorizar (se trunca a $opciones['max_chars']
     *                          si excede; OpenAI 3-small admite 8191 tokens ≈ 32K chars).
     * @param array  $opciones  centroId, usuarioId, timeout_segundos, max_chars.
     *
     * @return array
     *   ok:
     *     ['ok'=>true, 'vector'=>[float,…], 'dim'=>1536, 'modelo_usado'=>'text-embedding-3-small',
     *      'tokens'=>int, 'coste_estimado'=>float, 'latencia_ms'=>int]
     *   error:
     *     ['ok'=>false, 'error_tipo'=>..., 'mensaje'=>..., 'detalle_tecnico'=>...]
     */
    public static function embeddings(string $operacion, string $texto, array $opciones = []): array
    {
        $centroId  = self::resolverCentroId($opciones);
        $usuarioId = self::resolverUsuarioId($opciones);
        $timeout   = isset($opciones['timeout_segundos']) ? (int)$opciones['timeout_segundos'] : self::DEFAULT_TIMEOUT;
        $maxChars  = isset($opciones['max_chars']) ? (int)$opciones['max_chars'] : 30000; // ~7500 tokens
        $promptHash = hash('sha256', $texto);

        // Truncado seguro UTF-8 (mb_substr no parte caracteres multi-byte)
        if (mb_strlen($texto, 'UTF-8') > $maxChars) {
            $texto = mb_substr($texto, 0, $maxChars, 'UTF-8');
        }
        // Saneo: quitar caracteres de control (excepto \t \n \r) y bytes
        // inválidos UTF-8 que romperían el JSON de la request a OpenAI.
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $texto);
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
        }

        $op = self::cargarOperacion($operacion);
        if ($op === null) {
            return self::errorConfig(
                "La operación '$operacion' no existe o no está activa en llm_operaciones.",
                "operacion_no_registrada"
            );
        }
        if ($op['modo'] !== 'simple') {
            return self::errorConfig(
                "embeddings() requiere operación en modo='simple' — '$operacion' está en modo='{$op['modo']}'.",
                'modo_invalido'
            );
        }

        $modelo = self::cargarModelo((int)$op['modelo_id']);
        if ($modelo === null || (int)$modelo['activo'] !== 1) {
            $msg = "El modelo asignado a '$operacion' no existe o no está activo.";
            self::registrarLlamada($op, ['codigo' => '?'], [
                'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                'resultado' => 'error_config', 'mensaje_error' => $msg,
            ], $centroId, $usuarioId, $promptHash);
            return self::errorConfig($msg, 'modelo_inactivo');
        }

        $errSens = self::validarSensibilidad($op, $modelo);
        if ($errSens !== null) {
            self::registrarLlamada($op, $modelo, [
                'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                'resultado' => 'error_sensibilidad', 'mensaje_error' => $errSens,
            ], $centroId, $usuarioId, $promptHash);
            return [
                'ok' => false,
                'error_tipo' => 'error_sensibilidad',
                'mensaje' => $errSens,
                'detalle_tecnico' => $errSens,
            ];
        }

        $apiKey = self::obtenerApiKey($modelo['proveedor']);
        if ($apiKey === null || $apiKey === '') {
            $msg = "Clave API de '{$modelo['proveedor']}' no configurada en el servidor.";
            self::registrarLlamada($op, $modelo, [
                'tokens_in' => 0, 'tokens_out' => 0, 'latencia_ms' => 0,
                'resultado' => 'error_config', 'mensaje_error' => $msg,
            ], $centroId, $usuarioId, $promptHash);
            return self::errorConfig($msg, 'api_key_ausente');
        }

        // Payload OpenAI embeddings
        if ($modelo['proveedor'] !== 'openai') {
            $msg = "Proveedor '{$modelo['proveedor']}' no soportado para embeddings (solo OpenAI por ahora).";
            return self::errorConfig($msg, 'proveedor_no_soportado');
        }
        $payload = [
            'model' => $modelo['nombre_api'],
            'input' => $texto,
        ];

        $resp = self::ejecutarHttpEmbedding($modelo, $apiKey, $payload, $timeout);

        self::registrarLlamada($op, $modelo, [
            'tokens_in'     => $resp['tokens_in'] ?? 0,
            'tokens_out'    => 0,
            'latencia_ms'   => $resp['latencia_ms'] ?? 0,
            'resultado'     => $resp['ok'] ? 'ok' : ($resp['error_tipo'] ?? 'error_proveedor'),
            'mensaje_error' => $resp['ok'] ? null : ($resp['error'] ?? 'Error desconocido'),
        ], $centroId, $usuarioId, $promptHash);

        if (!$resp['ok']) {
            return [
                'ok' => false,
                'error_tipo' => $resp['error_tipo'] ?? 'error_proveedor',
                'mensaje' => self::mensajeUsuario($resp['error_tipo'] ?? 'error_proveedor', $resp['error'] ?? ''),
                'detalle_tecnico' => $resp['error'] ?? '',
            ];
        }

        return [
            'ok' => true,
            'vector' => $resp['vector'],
            'dim' => count($resp['vector']),
            'modelo_usado' => $modelo['codigo'],
            'tokens' => $resp['tokens_in'],
            'coste_estimado' => self::calcularCoste($resp['tokens_in'], 0, $modelo),
            'latencia_ms' => $resp['latencia_ms'],
        ];
    }

    /**
     * Empaqueta un vector float[] en bytes (float32 little-endian) para guardar en BD.
     */
    public static function empaquetarVector(array $vector): string
    {
        return pack('f*', ...$vector);
    }

    /**
     * Desempaqueta un blob a array de floats.
     */
    public static function desempaquetarVector(string $blob): array
    {
        $arr = unpack('f*', $blob);
        return $arr ? array_values($arr) : [];
    }

    /**
     * Similitud coseno entre dos vectores (mismo tamaño). Devuelve 0 si alguno es nulo.
     */
    public static function similitudCoseno(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) return 0.0;
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na <= 0 || $nb <= 0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * Llamada HTTP al endpoint /embeddings de OpenAI.
     */
    private static function ejecutarHttpEmbedding(array $modelo, string $apiKey, array $payload, int $timeout): array
    {
        $tStart = microtime(true);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $modelo['endpoint_url'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latencia = (int)round((microtime(true) - $tStart) * 1000);

        if ($errno !== 0) {
            $tipo = ($errno === CURLE_OPERATION_TIMEDOUT) ? 'error_timeout' : 'error_proveedor';
            return ['ok' => false, 'error_tipo' => $tipo, 'error' => "cURL error $errno: $errstr", 'latencia_ms' => $latencia];
        }
        if ($http < 200 || $http >= 300) {
            return ['ok' => false, 'error_tipo' => 'error_proveedor', 'error' => "HTTP $http: " . substr((string)$body, 0, 500), 'latencia_ms' => $latencia];
        }

        $data = json_decode((string)$body, true);
        $vector = $data['data'][0]['embedding'] ?? null;
        if (!is_array($vector) || empty($vector)) {
            return ['ok' => false, 'error_tipo' => 'error_proveedor', 'error' => 'Respuesta de OpenAI sin embedding', 'latencia_ms' => $latencia];
        }
        return [
            'ok' => true,
            'vector' => $vector,
            'tokens_in' => (int)($data['usage']['prompt_tokens'] ?? 0),
            'latencia_ms' => $latencia,
        ];
    }

    /**
     * Estima el coste en EUR sin hacer llamada. Útil para previews UI.
     */
    public static function estimarCoste(string $operacion, int $tokens_in_aprox, int $tokens_out_aprox): float
    {
        $op = self::cargarOperacion($operacion);
        if ($op === null) return 0.0;

        $coste = 0.0;
        foreach (['modelo_id', 'modelo_id_2'] as $col) {
            if (!empty($op[$col])) {
                $m = self::cargarModelo((int)$op[$col]);
                if ($m !== null) {
                    $coste += self::calcularCoste($tokens_in_aprox, $tokens_out_aprox, $m);
                }
            }
        }
        return round($coste, 6);
    }

    /**
     * Devuelve agregado mensual por centro.
     * @return array filas de llm_consumo_mensual ordenadas por coste_total desc
     */
    public static function consumoMensual(int $centroId, string $anio_mes): array
    {
        $sql = "SELECT operacion_codigo, modelo_codigo, numero_llamadas,
                       tokens_entrada_total, tokens_salida_total, coste_total, numero_errores
                FROM llm_consumo_mensual
                WHERE centroId = ? AND anio_mes = ?
                ORDER BY coste_total DESC";
        $stmt = Conectar::varias($sql, [$centroId, $anio_mes]);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Comprueba que una operación está registrada y activa.
     */
    public static function operacionExiste(string $operacion): bool
    {
        return self::cargarOperacion($operacion) !== null;
    }

    // =====================================================================
    // INTERNOS — carga y cache
    // =====================================================================

    private static function cargarOperacion(string $codigo): ?array
    {
        if (isset(self::$cacheOperaciones[$codigo])) {
            return self::$cacheOperaciones[$codigo];
        }
        // limite_centro_mes / cooldown_segundos: los añade
        // database/llm_operaciones_limites.sql. Si aún no está aplicado, el
        // SELECT falla, Conectar::una devuelve null y la operación entera
        // dejaría de funcionar — por eso se reintenta sin esas dos columnas.
        $base = "SELECT id, codigo, descripcion, sensibilidad, modo, modelo_id, modelo_id_2,
                        max_tokens_salida, temperatura, activo";
        $cola = " FROM llm_operaciones WHERE codigo = ? AND activo = 1 LIMIT 1";

        $row = Conectar::una($base . ", limite_centro_mes, cooldown_segundos" . $cola, [$codigo]);
        if ($row === null) {
            $row = Conectar::una($base . $cola, [$codigo]);
        }

        self::$cacheOperaciones[$codigo] = $row ?: null;
        return self::$cacheOperaciones[$codigo];
    }

    // =====================================================================
    // INTERNOS — cuota de consumo por centro
    //
    // El tope es un DATO de llm_operaciones, no código: se ajusta desde
    // int.momdel.net/ia/operaciones.php sin desplegar (era 0/llm_admin.php,
    // retirado el 2026-08-26). Con los dos valores a 0 (el default de
    // todas las operaciones existentes) no se ejecuta ninguna query extra,
    // así que esto no cuesta nada a quien no lo use.
    // =====================================================================

    /**
     * ¿Puede este centro lanzar la operación ahora?
     * @return string|null null si hay cuota; mensaje en español si no.
     */
    private static function comprobarCuota(array $op, int $centroId): ?string
    {
        $limite   = (int)($op['limite_centro_mes'] ?? 0);
        $cooldown = (int)($op['cooldown_segundos'] ?? 0);
        if (($limite <= 0 && $cooldown <= 0) || $centroId <= 0) {
            return null;
        }

        // Espera mínima entre llamadas: corta el doble clic y el dedo pegado
        // al botón, que el tope mensual no cubre.
        //
        // 🔴 Las fechas se calculan con el reloj de PHP, NO con NOW() de MySQL.
        //    registrarLlamada() escribe `fecha_hora` con date() de PHP, y en
        //    este servidor PHP va en UTC mientras MySQL va en CEST: dos horas
        //    de diferencia. Comparar contra NOW() haría que el cooldown no
        //    saltara nunca. Mismo criterio en llamadasOkDelMes().
        if ($cooldown > 0) {
            $desde = date('Y-m-d H:i:s', time() - $cooldown);
            $ultima = Conectar::una(
                "SELECT MAX(fecha_hora) AS f FROM llm_llamadas
                  WHERE centroId = ? AND operacion_codigo = ? AND fecha_hora >= ?",
                [$centroId, $op['codigo'], $desde]
            );
            if (!empty($ultima['f'])) {
                $restan = max(1, $cooldown - (time() - strtotime((string)$ultima['f'])));
                return 'Espere ' . $restan . ' segundos antes de volver a lanzar esta operación.';
            }
        }

        // Tope mensual: solo cuentan las llamadas que salieron bien. Un fallo
        // del proveedor no debe consumir cuota del cliente.
        if ($limite > 0) {
            $usadas = self::llamadasOkDelMes($op['codigo'], $centroId);
            if ($usadas >= $limite) {
                return 'Se ha alcanzado el máximo de ' . $limite
                     . ' usos de esta función para este mes. Estará disponible de nuevo el día 1.';
            }
        }

        return null;
    }

    /** Llamadas con resultado 'ok' de esta operación y centro en el mes en curso. */
    private static function llamadasOkDelMes(string $operacion, int $centroId): int
    {
        $row = Conectar::una(
            "SELECT COUNT(*) AS n FROM llm_llamadas
              WHERE centroId = ? AND operacion_codigo = ? AND resultado = 'ok'
                AND fecha_hora >= ?",
            [$centroId, $operacion, date('Y-m-01 00:00:00')]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * Estado de la cuota, para pintarlo en pantalla ANTES de gastarla.
     *
     * @return array{limite:int, usadas:int, quedan:int, ilimitado:bool, cooldown:int}
     */
    public static function cuotaOperacion(string $operacion, ?int $centroId = null): array
    {
        $centroId = $centroId ?? self::resolverCentroId([]);
        $op = self::cargarOperacion($operacion);

        $limite = (int)($op['limite_centro_mes'] ?? 0);
        if ($op === null || $limite <= 0) {
            return ['limite' => 0, 'usadas' => 0, 'quedan' => PHP_INT_MAX,
                    'ilimitado' => true, 'cooldown' => (int)($op['cooldown_segundos'] ?? 0)];
        }

        $usadas = self::llamadasOkDelMes($operacion, $centroId);
        return [
            'limite'    => $limite,
            'usadas'    => $usadas,
            'quedan'    => max(0, $limite - $usadas),
            'ilimitado' => false,
            'cooldown'  => (int)($op['cooldown_segundos'] ?? 0),
        ];
    }

    private static function cargarModelo(int $id): ?array
    {
        if (isset(self::$cacheModelos[$id])) {
            return self::$cacheModelos[$id];
        }
        $sql = "SELECT id, codigo, proveedor, nombre_api, endpoint_url,
                       coste_entrada_por_millon, coste_salida_por_millon,
                       soporta_zdr, activo
                FROM llm_modelos
                WHERE id = ? LIMIT 1";
        $row = Conectar::una($sql, [$id]);
        self::$cacheModelos[$id] = $row ?: null;
        return self::$cacheModelos[$id];
    }

    // =====================================================================
    // INTERNOS — validación de sensibilidad (spec §8)
    // =====================================================================

    /**
     * Devuelve null si la combinación operación/modelo es válida,
     * o un mensaje de error en español si no lo es.
     */
    private static function validarSensibilidad(array $op, array $modelo): ?string
    {
        $sens = $op['sensibilidad'];
        $prov = $modelo['proveedor'];
        $zdr  = (int)$modelo['soporta_zdr'] === 1;

        switch ($sens) {
            case 'baja':
                return null; // cualquier proveedor activo
            case 'media':
                if (in_array($prov, ['anthropic', 'openai', 'openrouter'], true)) return null;
                break;
            case 'alta':
                if ($prov === 'anthropic' && $zdr) return null;
                break;
            case 'critica':
                if ($prov === 'local') return null;
                break;
        }

        return "La operación '{$op['codigo']}' requiere sensibilidad '$sens' y el modelo "
             . "'{$modelo['codigo']}' (proveedor=$prov, zdr=" . ($zdr ? '1' : '0') . ") no la cumple. "
             . "Revisar configuración en llm_operaciones.";
    }

    // =====================================================================
    // INTERNOS — claves API
    // =====================================================================

    private static function obtenerApiKey(string $proveedor): ?string
    {
        $envKey = match ($proveedor) {
            'anthropic'  => 'ISORGA_ANTHROPIC_API_KEY',
            'openai'     => 'ISORGA_OPENAI_API_KEY',
            'openrouter' => 'ISORGA_OPENROUTER_API_KEY',
            'local'      => 'NO_API_KEY_NEEDED',
            default      => null,
        };
        if ($envKey === null) return null;
        if ($envKey === 'NO_API_KEY_NEEDED') return $envKey;

        // 1) PHP-FPM: las claves vienen de /etc/php/8.3/fpm/pool.d/www.conf.
        $val = getenv($envKey);
        if ($val !== false && $val !== '') return $val;

        // 2) CLI: fallback a /etc/isorga.env (www.conf no se carga en CLI).
        $env = self::leerEnvFile();
        $val = $env[$envKey] ?? null;
        return ($val !== null && $val !== '') ? $val : null;
    }

    /**
     * Fallback CLI: lee API keys desde /etc/isorga.env cuando getenv()
     * vuelve vacío (típico en CLI, donde el pool de PHP-FPM no se carga).
     *
     * IMPORTANTE: este es el ÚNICO consumidor de /etc/isorga.env en
     * el proyecto a fecha 2026-05-17. Si alguien plantea borrar ese
     * archivo "huérfano", romperá el acceso CLI a LLMClient.
     * Permisos esperados: root:devs 640.
     *
     * Cargado una sola vez por request — cache en $envFileCache.
     */
    private static function leerEnvFile(): array
    {
        if (self::$envFileCache !== null) {
            return self::$envFileCache;
        }
        $path = '/etc/isorga.env';
        self::$envFileCache = [];
        if (!is_readable($path)) {
            return self::$envFileCache;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return self::$envFileCache;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            // Quitar comillas envolventes ("…" o '…') si las hubiera.
            if (strlen($val) >= 2
                && ($val[0] === '"' || $val[0] === "'")
                && $val[strlen($val) - 1] === $val[0]) {
                $val = substr($val, 1, -1);
            }
            self::$envFileCache[$key] = $val;
        }
        return self::$envFileCache;
    }

    // =====================================================================
    // INTERNOS — construcción de payload por proveedor
    // =====================================================================

    /**
     * @param array $adjuntos Visión (v16.612). Con [] —el caso de las 30
     *                        operaciones existentes— el payload devuelto es
     *                        IDÉNTICO al de antes: `content` sigue siendo el
     *                        string del prompt, no un array de bloques.
     */
    private static function construirPayload(array $modelo, string $prompt, int $maxTokens, float $temperatura, array $adjuntos = []): array
    {
        // Con adjuntos, `content` deja de ser un string y pasa a ser la lista
        // de bloques del proveedor, con el texto SIEMPRE al final: tanto
        // Anthropic como OpenAI leen mejor "aquí tienes el documento, ahora la
        // instrucción" que al revés.
        $contenido = $adjuntos === []
            ? $prompt
            : array_merge(self::bloquesAdjuntos($adjuntos, $modelo['proveedor']),
                          [self::bloqueTexto($modelo['proveedor'], $prompt)]);

        switch ($modelo['proveedor']) {
            case 'anthropic':
                return [
                    'model'       => $modelo['nombre_api'],
                    'max_tokens'  => $maxTokens,
                    'temperature' => $temperatura,
                    'messages'    => [
                        ['role' => 'user', 'content' => $contenido],
                    ],
                ];

            // OpenRouter es OpenAI-compatible: mismo body que 'openai'.
            case 'openai':
            case 'openrouter':
                $payload = [
                    'model'       => $modelo['nombre_api'],
                    'messages'    => [
                        ['role' => 'user', 'content' => $contenido],
                    ],
                    'max_tokens'  => $maxTokens,
                    'temperature' => $temperatura,
                ];

                // 🔴 Razonamiento DESACTIVADO en OpenRouter (v16.536).
                // En la API OpenAI-compatible `max_tokens` acota reasoning +
                // content, no solo el content. Con un modelo de razonamiento
                // el modelo puede gastar TODO el presupuesto pensando y
                // devolver `content` vacío: la llamada se cobra y no hay
                // respuesta. Pasó con `analisis_contexto` sobre
                // deepseek-v4-flash (2.000/2.000 tokens de salida, contenido
                // vacío, 28,8 s). Subir max_tokens no vale: a ~70 tok/s el
                // doble de presupuesto se come el DEFAULT_TIMEOUT de 60 s.
                // Con esto `max_tokens` vuelve a significar "tokens de
                // respuesta", que es lo que asume toda la configuración de
                // `llm_operaciones`. OpenRouter descarta los parámetros que
                // el modelo no soporta, así que es inocuo para los que no
                // razonan. Si algún día hace falta razonamiento, va como
                // columna en `llm_operaciones`, no hardcodeado aquí.
                if ($modelo['proveedor'] === 'openrouter') {
                    $payload['reasoning'] = ['enabled' => false];
                }

                return $payload;

            default:
                return [];
        }
    }

    // =====================================================================
    // INTERNOS — VISIÓN (v16.612)
    // ---------------------------------------------------------------------
    // Todo este bloque es inerte si nadie pasa $opciones['adjuntos'].
    // =====================================================================

    /**
     * Huella de los adjuntos para el prompt_hash. No es criptografía: es para
     * poder distinguir en llm_llamadas dos llamadas con el mismo prompt sobre
     * documentos distintos. Se usa ruta+tamaño+mtime en vez del contenido para
     * no leer 20 MB solo para calcular un hash.
     */
    private static function huellaAdjuntos(array $adjuntos): string
    {
        $partes = [];
        foreach ($adjuntos as $a) {
            $ruta = (string)($a['ruta'] ?? '');
            $partes[] = $ruta . ':' . (@filesize($ruta) ?: 0) . ':' . (@filemtime($ruta) ?: 0);
        }
        return hash('sha256', implode('|', $partes));
    }

    /**
     * Validación que NO depende del proveedor: forma, existencia, tipo real y
     * tamaño. Devuelve null si está todo bien, o el mensaje del primer fallo.
     *
     * 🔴 El MIME se MIDE con finfo sobre los magic bytes; el que declare el
     * llamante solo se usa para comprobar que coincide. Un fichero renombrado
     * a .pdf no se cuela (CLAUDE.md §0.10).
     */
    private static function validarAdjuntos(array $adjuntos): ?string
    {
        if (count($adjuntos) > self::ADJUNTOS_MAX_NUM) {
            return 'Demasiados adjuntos: máximo ' . self::ADJUNTOS_MAX_NUM . ' por llamada.';
        }

        $total = 0;
        foreach ($adjuntos as $i => $a) {
            if (!is_array($a) || !isset($a['ruta'])) {
                return "Adjunto #$i mal formado: falta 'ruta'.";
            }
            $ruta = (string) $a['ruta'];

            // Ruta absoluta y real. realpath() resuelve enlaces y '..', así que
            // lo que se abre es lo que se ha comprobado.
            $real = realpath($ruta);
            if ($real === false || !is_file($real) || !is_readable($real)) {
                return "Adjunto #$i: el archivo no existe o no se puede leer.";
            }

            $tam = filesize($real);
            if ($tam === false || $tam <= 0) {
                return "Adjunto #$i: el archivo está vacío.";
            }
            $total += $tam;
            if ($total > self::ADJUNTOS_MAX_BYTES) {
                return 'Los adjuntos superan el máximo de '
                     . (int) (self::ADJUNTOS_MAX_BYTES / 1024 / 1024) . ' MB en total.';
            }

            $mimeReal = null;
            if (function_exists('finfo_open')) {
                $fi = finfo_open(FILEINFO_MIME_TYPE);
                if ($fi !== false) {
                    $mimeReal = finfo_file($fi, $real) ?: null;
                    finfo_close($fi);
                }
            }
            if ($mimeReal === null) {
                return "Adjunto #$i: no se ha podido determinar el tipo real del archivo.";
            }
            if (!isset(self::ADJUNTOS_MIME[$mimeReal])) {
                return "Adjunto #$i: tipo '$mimeReal' no admitido. Solo PDF, JPG, PNG, WEBP y GIF.";
            }
            if (isset($a['mime']) && $a['mime'] !== '' && $a['mime'] !== $mimeReal) {
                return "Adjunto #$i: el tipo declarado ('{$a['mime']}') no es el real ('$mimeReal').";
            }
        }
        return null;
    }

    /**
     * Validación que SÍ depende del proveedor. Devuelve null si vale.
     *
     * PDF nativo solo lo lee Anthropic (bloque 'document'). La API
     * OpenAI-compatible que usamos solo acepta imágenes por 'image_url', y
     * OpenRouter depende del modelo de destino. Antes de mandar un PDF a
     * cualquiera que no sea Anthropic, se falla en seco: es más barato y el
     * mensaje dice qué pasa.
     */
    private static function validarAdjuntosProveedor(array $adjuntos, array $modelo): ?string
    {
        $proveedor = (string) $modelo['proveedor'];

        if (!in_array($proveedor, ['anthropic', 'openai', 'openrouter'], true)) {
            return "El proveedor '$proveedor' no admite adjuntos de visión.";
        }

        foreach ($adjuntos as $i => $a) {
            $real  = realpath((string) $a['ruta']);
            $mime  = self::mimeDe($real);
            $clase = self::ADJUNTOS_MIME[$mime] ?? null;

            if ($clase === 'documento' && $proveedor !== 'anthropic') {
                return "Adjunto #$i: los PDF solo se pueden analizar con el proveedor 'anthropic', "
                     . "y la operación está asignada a '$proveedor'. Conviértelo a imagen o cambia el modelo.";
            }
        }
        return null;
    }

    /** MIME real medido. Devuelve '' si no se puede determinar. */
    private static function mimeDe(?string $ruta): string
    {
        if ($ruta === null || $ruta === false || !is_file($ruta) || !function_exists('finfo_open')) return '';
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi === false) return '';
        $m = finfo_file($fi, $ruta);
        finfo_close($fi);
        return $m ?: '';
    }

    /** El bloque de texto del prompt, en el formato de cada proveedor. */
    private static function bloqueTexto(string $proveedor, string $prompt): array
    {
        // Los dos formatos coinciden en este caso, pero se deja explícito para
        // que añadir un proveedor con otra forma no obligue a buscar dónde era.
        return ['type' => 'text', 'text' => $prompt];
    }

    /**
     * Convierte los adjuntos en los bloques de contenido del proveedor.
     *
     * Anthropic:  {type:'document'|'image', source:{type:'base64', media_type, data}}
     * OpenAI-compat: {type:'image_url', image_url:{url:'data:<mime>;base64,<...>'}}
     *
     * El base64 se hace aquí y no antes para que el llamante no tenga que
     * pasear cadenas de 27 MB por sus variables.
     */
    private static function bloquesAdjuntos(array $adjuntos, string $proveedor): array
    {
        $bloques = [];
        foreach ($adjuntos as $a) {
            $real = realpath((string) $a['ruta']);
            if ($real === false) continue;                 // ya validado; defensa en profundidad
            $mime = self::mimeDe($real);
            $b64  = base64_encode((string) file_get_contents($real));
            $esPdf = (self::ADJUNTOS_MIME[$mime] ?? '') === 'documento';

            if ($proveedor === 'anthropic') {
                $bloques[] = [
                    'type'   => $esPdf ? 'document' : 'image',
                    'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64],
                ];
            } else {
                // openai / openrouter. Los PDF ya se han rechazado antes en
                // validarAdjuntosProveedor(), así que aquí solo llegan imágenes.
                $bloques[] = [
                    'type'      => 'image_url',
                    'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64],
                ];
            }
        }
        return $bloques;
    }

    // =====================================================================
    // INTERNOS — HTTP (cURL nativo, síncrono)
    // =====================================================================

    /**
     * Ejecuta una llamada HTTP única.
     * @return array ['ok'=>bool, 'texto'=>string, 'tokens_in'=>int, 'tokens_out'=>int,
     *                'latencia_ms'=>int, 'error'=>?string, 'error_tipo'=>?string]
     */
    private static function ejecutarHttp(array $modelo, string $apiKey, array $payload, int $timeout): array
    {
        $tStart = microtime(true);
        $ch = curl_init();
        curl_setopt_array($ch, self::opcionesCurl($modelo, $apiKey, $payload, $timeout));

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latencia = (int)round((microtime(true) - $tStart) * 1000);

        if ($errno !== 0) {
            $tipo = ($errno === CURLE_OPERATION_TIMEDOUT) ? 'error_timeout' : 'error_proveedor';
            return [
                'ok' => false,
                'error_tipo' => $tipo,
                'error' => "cURL error $errno: $errstr",
                'latencia_ms' => $latencia,
            ];
        }

        if ($http < 200 || $http >= 300) {
            return [
                'ok' => false,
                'error_tipo' => 'error_proveedor',
                'error' => "HTTP $http: " . substr((string)$body, 0, 500),
                'latencia_ms' => $latencia,
            ];
        }

        return self::parsearRespuesta($modelo['proveedor'], (string)$body, $latencia);
    }

    /**
     * Ejecuta N llamadas HTTP en paralelo con curl_multi.
     * @param array $jobs lista de ['modelo','apiKey','payload']
     * @return array lista de resultados (mismo orden que $jobs), cada uno con
     *               la misma estructura que ejecutarHttp().
     */
    private static function ejecutarHttpMulti(array $jobs, int $timeout): array
    {
        $multi = curl_multi_init();
        $handles = [];
        $tInicio = [];

        foreach ($jobs as $i => $job) {
            $ch = curl_init();
            curl_setopt_array($ch, self::opcionesCurl($job['modelo'], $job['apiKey'], $job['payload'], $timeout));
            curl_multi_add_handle($multi, $ch);
            $handles[$i] = $ch;
            $tInicio[$i] = microtime(true);
        }

        $running = null;
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $resultados = [];
        foreach ($handles as $i => $ch) {
            $body  = curl_multi_getcontent($ch);
            $errno = curl_errno($ch);
            $errstr= curl_error($ch);
            $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $latencia = (int)round((microtime(true) - $tInicio[$i]) * 1000);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);

            if ($errno !== 0) {
                $tipo = ($errno === CURLE_OPERATION_TIMEDOUT) ? 'error_timeout' : 'error_proveedor';
                $resultados[$i] = [
                    'ok' => false,
                    'error_tipo' => $tipo,
                    'error' => "cURL error $errno: $errstr",
                    'latencia_ms' => $latencia,
                ];
                continue;
            }

            if ($http < 200 || $http >= 300) {
                $resultados[$i] = [
                    'ok' => false,
                    'error_tipo' => 'error_proveedor',
                    'error' => "HTTP $http: " . substr((string)$body, 0, 500),
                    'latencia_ms' => $latencia,
                ];
                continue;
            }

            $resultados[$i] = self::parsearRespuesta($jobs[$i]['modelo']['proveedor'], (string)$body, $latencia);
        }
        curl_multi_close($multi);
        return $resultados;
    }

    private static function opcionesCurl(array $modelo, string $apiKey, array $payload, int $timeout): array
    {
        $headers = ['content-type: application/json'];
        switch ($modelo['proveedor']) {
            case 'anthropic':
                $headers[] = 'x-api-key: ' . $apiKey;
                $headers[] = 'anthropic-version: 2023-06-01';
                break;
            case 'openai':
                $headers[] = 'authorization: Bearer ' . $apiKey;
                break;
            case 'openrouter':
                $headers[] = 'authorization: Bearer ' . $apiKey;
                // Opcionales recomendados por OpenRouter (ranking/dashboard).
                $headers[] = 'http-referer: https://isorga.com';
                $headers[] = 'x-title: ISORGA';
                break;
        }

        return [
            CURLOPT_URL            => $modelo['endpoint_url'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
    }

    // =====================================================================
    // INTERNOS — parseo de respuesta por proveedor
    // =====================================================================

    private static function parsearRespuesta(string $proveedor, string $body, int $latencia): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [
                'ok' => false,
                'error_tipo' => 'error_proveedor',
                'error' => 'Respuesta malformada (no es JSON): ' . substr($body, 0, 300),
                'latencia_ms' => $latencia,
            ];
        }

        switch ($proveedor) {
            case 'anthropic':
                $texto = '';
                if (isset($data['content']) && is_array($data['content'])) {
                    foreach ($data['content'] as $bloque) {
                        if (isset($bloque['type']) && $bloque['type'] === 'text' && isset($bloque['text'])) {
                            $texto .= $bloque['text'];
                        }
                    }
                }
                return [
                    'ok'          => $texto !== '',
                    'texto'       => $texto,
                    'tokens_in'   => (int)($data['usage']['input_tokens']  ?? 0),
                    'tokens_out'  => (int)($data['usage']['output_tokens'] ?? 0),
                    'latencia_ms' => $latencia,
                    'error'       => $texto === '' ? 'Respuesta de Anthropic sin contenido textual' : null,
                    'error_tipo'  => $texto === '' ? 'error_proveedor' : null,
                ];

            // OpenRouter devuelve el mismo formato OpenAI (choices + usage).
            case 'openai':
            case 'openrouter':
                $texto     = (string)($data['choices'][0]['message']['content'] ?? '');
                $tokensIn  = (int)($data['usage']['prompt_tokens']     ?? 0);
                $tokensOut = (int)($data['usage']['completion_tokens'] ?? 0);

                // Diagnóstico cuando no hay contenido. Antes se devolvía
                // siempre "Respuesta del proveedor sin contenido", que no
                // decía POR QUÉ y dejaba el fallo sin pista en `llm_llamadas`.
                $error = null;
                if ($texto === '') {
                    $finish    = (string)($data['choices'][0]['finish_reason'] ?? '');
                    $razona    = trim((string)($data['choices'][0]['message']['reasoning'] ?? ''));
                    // OpenRouter puede devolver un error con HTTP 200.
                    $errProv   = $data['error']['message'] ?? null;

                    if ($errProv !== null) {
                        $error = 'El proveedor devolvió un error: ' . (string)$errProv;
                    } elseif ($finish === 'length' && $razona !== '') {
                        $error = "Truncado por max_tokens: el modelo gastó los $tokensOut tokens "
                               . 'de salida en razonamiento y no llegó a redactar la respuesta. '
                               . 'Sube max_tokens_salida de la operación o usa un modelo sin razonamiento.';
                    } elseif ($finish === 'length') {
                        $error = "Truncado por max_tokens ($tokensOut tokens de salida consumidos) "
                               . 'sin contenido. Sube max_tokens_salida de la operación.';
                    } elseif ($razona !== '') {
                        $error = 'El modelo solo devolvió razonamiento, sin respuesta '
                               . "(finish_reason='$finish').";
                    } else {
                        $error = 'Respuesta del proveedor sin contenido'
                               . ($finish !== '' ? " (finish_reason='$finish')" : '');
                    }
                }

                return [
                    'ok'          => $texto !== '',
                    'texto'       => $texto,
                    'tokens_in'   => $tokensIn,
                    'tokens_out'  => $tokensOut,
                    'latencia_ms' => $latencia,
                    'error'       => $error,
                    'error_tipo'  => $texto === '' ? 'error_proveedor' : null,
                ];

            default:
                return [
                    'ok' => false,
                    'error_tipo' => 'error_config',
                    'error' => "Proveedor desconocido para parseo: $proveedor",
                    'latencia_ms' => $latencia,
                ];
        }
    }

    // =====================================================================
    // INTERNOS — coste
    // =====================================================================

    private static function calcularCoste(int $tokensIn, int $tokensOut, array $modelo): float
    {
        $costeUsd = ($tokensIn  / 1_000_000.0) * (float)$modelo['coste_entrada_por_millon']
                  + ($tokensOut / 1_000_000.0) * (float)$modelo['coste_salida_por_millon'];
        return round($costeUsd * LLM_USD_EUR_RATE, 6);
    }

    // =====================================================================
    // INTERNOS — logging
    // =====================================================================

    /**
     * Inserta una fila en llm_llamadas y hace UPSERT en llm_consumo_mensual.
     * Si la inserción falla, lo silenciamos via try/catch para no romper la llamada
     * cliente (un fallo de log no debe propagarse al usuario final), pero queda en error_log.
     */
    private static function registrarLlamada(
        array $op,
        array $modelo,
        array $resultado,
        int $centroId,
        ?int $usuarioId,
        string $promptHash,
        ?string $fechaHora = null
    ): void {
        $fecha = $fechaHora ?? date('Y-m-d H:i:s');
        $anioMes = substr($fecha, 0, 7);
        $modeloCodigo = $modelo['codigo'] ?? '?';
        $tokensIn  = (int)($resultado['tokens_in']  ?? 0);
        $tokensOut = (int)($resultado['tokens_out'] ?? 0);
        $coste = ($modelo !== null && isset($modelo['coste_entrada_por_millon']))
            ? self::calcularCoste($tokensIn, $tokensOut, $modelo)
            : 0.0;
        $esError = ($resultado['resultado'] ?? 'ok') !== 'ok';

        try {
            // 1) log detallado
            $sqlLog = "INSERT INTO llm_llamadas
                (centroId, usuarioId, operacion_codigo, modelo_codigo,
                 tokens_entrada, tokens_salida, coste_estimado, latencia_ms,
                 resultado, mensaje_error, fecha_hora, prompt_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            Conectar::ejecutar($sqlLog, [
                $centroId,
                $usuarioId,
                $op['codigo'],
                $modeloCodigo,
                $tokensIn,
                $tokensOut,
                $coste,
                (int)($resultado['latencia_ms'] ?? 0),
                $resultado['resultado'] ?? 'ok',
                $resultado['mensaje_error'] ?? null,
                $fecha,
                $promptHash,
            ]);

            // 2) agregado mensual (UPSERT)
            $sqlAgg = "INSERT INTO llm_consumo_mensual
                (centroId, anio_mes, operacion_codigo, modelo_codigo,
                 numero_llamadas, tokens_entrada_total, tokens_salida_total, coste_total, numero_errores)
                VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    numero_llamadas      = numero_llamadas + 1,
                    tokens_entrada_total = tokens_entrada_total + ?,
                    tokens_salida_total  = tokens_salida_total + ?,
                    coste_total          = coste_total + ?,
                    numero_errores       = numero_errores + ?";
            $err = $esError ? 1 : 0;
            Conectar::ejecutar($sqlAgg, [
                $centroId, $anioMes, $op['codigo'], $modeloCodigo,
                $tokensIn, $tokensOut, $coste, $err,
                $tokensIn, $tokensOut, $coste, $err,
            ]);
        } catch (\Throwable $e) {
            error_log('[LLMClient] No se pudo registrar la llamada: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // INTERNOS — utilidades varias
    // =====================================================================

    private static function resolverCentroId(array $opciones): int
    {
        if (isset($opciones['centroId'])) return (int)$opciones['centroId'];
        if (isset($_SESSION['centro']['NoCentro'])) return (int)$_SESSION['centro']['NoCentro'];
        return 0;
    }

    private static function resolverUsuarioId(array $opciones): ?int
    {
        if (isset($opciones['usuarioId'])) return (int)$opciones['usuarioId'];
        if (isset($_SESSION['user']['NoUsuario'])) return (int)$_SESSION['user']['NoUsuario'];
        return null;
    }

    private static function errorConfig(string $msg, string $detalle): array
    {
        return [
            'ok' => false,
            'error_tipo' => 'error_config',
            'mensaje' => $msg,
            'detalle_tecnico' => $detalle,
        ];
    }

    /**
     * Traduce un error_tipo crudo a un mensaje legible en español para el usuario final.
     */
    private static function mensajeUsuario(string $tipo, string $detalle): string
    {
        switch ($tipo) {
            case 'error_proveedor':    return 'El proveedor de IA no respondió correctamente. Inténtelo más tarde.';
            case 'error_timeout':      return 'La consulta a IA tardó demasiado y se canceló. Inténtelo de nuevo.';
            case 'error_sensibilidad': return 'La operación solicitada no es compatible con el modelo asignado por sensibilidad de datos.';
            case 'error_config':       return 'Hay un problema de configuración del servicio de IA. Avise al administrador.';
            // El texto útil (cuántos usos quedan, cuántos segundos esperar) lo
            // compone comprobarCuota() y viaja ya en 'mensaje'.
            case 'error_cuota':        return $detalle !== '' ? $detalle : 'Ha alcanzado el límite de uso de esta función.';
            default:                   return 'Se ha producido un error con el servicio de IA.';
        }
    }
}
