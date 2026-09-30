<?php
/**
 * =====================================================================
 * MARCA DE CONTENIDO GENERADO POR IA — fuente única
 * ---------------------------------------------------------------------
 * 🔴 TODO texto que un modelo escriba y una persona lea sale marcado, y
 *    sale marcado DESDE AQUÍ. Un segundo sitio que lo pinte a mano es el
 *    principio de tener dos marcados distintos, que es donde estábamos.
 *
 * POR QUÉ EXISTE
 *   Art. 50 del Reglamento (UE) 2024/1689 (AI Act), exigible desde el
 *   02/08/2026: el contenido generado por IA que llega a una persona tiene
 *   que estar identificado como tal, de forma visible, en el propio
 *   documento. Medido el 2026-08-25, ISORGA tenía DOCE sitios que sacan
 *   texto de un modelo y solo DOS lo decían, cada uno a su manera:
 *
 *     · M5 (5/analisis_ia_*)   caja ámbar + texto... con una clave de
 *                              idioma, `M5_AI_DISCLAIMER`, QUE NO EXISTÍA
 *                              en ninguno de los 4 ficheros: siempre salía
 *                              el fallback castellano
 *     · M6 (6/asistida_informe_pdf.php)  un literal en el pie, hardcodeado
 *                              en castellano, sin clave y sin nada en el
 *                              cuerpo
 *
 * 🔴 LO QUE SE MARCA ES EL RESULTADO, NO EL BOTÓN. El fallo que se repite
 *    en seis módulos es etiquetar la ACCIÓN («Buscar con IA», «Analizar con
 *    IA») y no el TEXTO. En cuanto ese texto se graba, se imprime o se
 *    copia, la etiqueta se queda atrás y el contenido circula sin origen.
 *    Los casos graves son los que acaban en documentación del sistema de
 *    gestión (M35 contexto, M38 borradores): ahí se enseña en auditoría.
 *
 * 🔴 Y NO SE MARCAN LOS DATOS. En un informe con tablas + narración, la
 *    marca va PEGADA a la narración. Las cifras salen de la base de datos y
 *    marcarlas como generadas por IA sería mentir en el otro sentido.
 *
 * ⚠️ §27.2 — ningún nombre de proveedor. Se dice «inteligencia artificial»
 *    o «ISORGA IA». Nunca Anthropic, Claude, OpenAI, Gemini ni DeepSeek.
 *
 * USO
 *   include '../app/config/funciones.php';   // ya lo carga
 *
 *   echo marca_ia('html', ['tipo' => 'respuesta']);          // pantalla
 *   $pdf->writeHTML(nfc(marca_ia('pdf', ['tipo' => 'informe',
 *        'fecha' => $f, 'usuario' => $u])), true, false, true, false, '');
 *   $this->Cell(0, 5, marca_ia('pie'), 0, 0, 'C');           // pie de PDF
 *   marca_ia_meta($pdf);                                     // metadatos
 * =====================================================================
 */

if (!function_exists('marca_ia_texto')) {

/**
 * Los cuatro tipos, y por qué son cuatro y no uno.
 *
 * El aviso que sirve para un informe que se archiva no sirve para una
 * sugerencia que se acepta o se descarta en el acto. La diferencia que
 * importa es **qué puede pasar después con ese texto**:
 *
 *   informe     → se archiva y se enseña. Aviso más fuerte, el de M5
 *   documento   → 🔴 va a acabar en la documentación del sistema de
 *                 gestión. Es el más delicado de los cuatro
 *   respuesta   → se lee y se tira. Aviso corto
 *   sugerencia  → se acepta o se descarta ahora mismo
 */
function marca_ia_texto(string $tipo = 'respuesta'): string
{
    global $language;
    $l = is_array($language ?? null) ? $language : [];

    switch ($tipo) {
        case 'informe':
            return $l['IA_MARCA_INFORME']
                ?? 'Informe generado por inteligencia artificial. Verifique los datos '
                 . 'antes de utilizarlo como evidencia de gestión o ante auditoría externa.';
        case 'documento':
            return $l['IA_MARCA_DOCUMENTO']
                ?? 'Texto redactado por inteligencia artificial a partir de los datos '
                 . 'registrados en el sistema. Revíselo y edítelo antes de guardarlo: '
                 . 'pasará a formar parte de la documentación de su sistema de gestión.';
        case 'sugerencia':
            return $l['IA_MARCA_SUGERENCIA']
                ?? 'Sugerencias generadas por inteligencia artificial sobre los datos '
                 . 'de su centro. Revíselas antes de aceptarlas.';
        case 'respuesta':
        default:
            return $l['IA_MARCA_RESPUESTA']
                ?? 'Texto redactado por inteligencia artificial. Las cifras proceden de '
                 . 'la base de datos de su centro; la redacción, no.';
    }
}

/**
 * La línea corta. Pie de página de PDF, cabeceras de tabla, sitios donde
 * no cabe la frase entera.
 */
function marca_ia_pie(): string
{
    global $language;
    return ($language['IA_MARCA_PIE'] ?? null) ?: 'Con asistencia de IA';
}

/**
 * La marca, en el formato que toque.
 *
 * @param string $formato 'html' pantalla · 'pdf' TCPDF · 'texto' plano · 'pie' corto
 * @param array  $opt
 *        tipo      informe|documento|sugerencia|respuesta   (por defecto respuesta)
 *        fecha     fecha de generación (cualquier cosa que entienda strtotime)
 *        usuario   nombre de quien la pidió
 *        compacta  true = una línea sin caja (para pantallas apretadas)
 */
function marca_ia(string $formato = 'html', array $opt = []): string
{
    global $language;
    $l = is_array($language ?? null) ? $language : [];

    $texto = marca_ia_texto((string) ($opt['tipo'] ?? 'respuesta'));

    // Traza: cuándo y a petición de quién. No es decorativo — es lo que
    // permite reconstruir dentro de un año de dónde salió una frase.
    $extra = [];
    if (!empty($opt['fecha'])) {
        $t = is_numeric($opt['fecha']) ? (int) $opt['fecha'] : strtotime((string) $opt['fecha']);
        if ($t) $extra[] = ($l['IA_MARCA_GENERADO_EL'] ?? 'Generado el') . ' ' . date('d/m/Y H:i', $t);
    }
    if (!empty($opt['usuario'])) {
        $extra[] = ($l['IA_MARCA_A_PETICION_DE'] ?? 'A petición de') . ' ' . (string) $opt['usuario'];
    }
    $traza = $extra ? ' · ' . implode(' · ', $extra) : '';

    switch ($formato) {

        case 'pie':
            return marca_ia_pie();

        case 'texto':
            return $texto . $traza;

        // TCPDF: estilos EN LÍNEA obligatoriamente (no lee hojas de estilo).
        // Es el bloque de 5/analisis_ia_pdf.php, que ya está en producción.
        case 'pdf':
            return '<table width="100%" cellpadding="6" cellspacing="0"><tr>'
                 . '<td style="background-color:#fef9e7;border:1px solid #fde68a;'
                 . 'font-size:8px;color:#92400e;font-style:italic;">'
                 . htmlspecialchars($texto . $traza, ENT_QUOTES, 'UTF-8')
                 . '</td></tr></table>';

        // Pantalla. 🔴 Solo clases Bootstrap y tokens del tema (§0.3):
        // ni un color hexadecimal, ni una clase inventada, ni `text-dark`.
        case 'html':
        default:
            if (!empty($opt['compacta'])) {
                return '<p class="small text-body-secondary fst-italic mb-0">'
                     . '<i class="fas fa-circle-info me-1"></i>'
                     . htmlspecialchars($texto . $traza, ENT_QUOTES, 'UTF-8')
                     . '</p>';
            }
            return '<div class="alert alert-warning border-0 py-2 px-3 small fst-italic mb-3" role="note">'
                 . '<i class="fas fa-circle-info me-2"></i>'
                 . htmlspecialchars($texto . $traza, ENT_QUOTES, 'UTF-8')
                 . '</div>';
    }
}

/**
 * Metadatos del PDF.
 *
 * Es lo más parecido a «legible por máquina» que permite TCPDF sin salirse
 * del stack, y cuesta dos líneas. Un PDF que se reenvía por correo pierde la
 * pantalla que lo generó; los metadatos viajan con el fichero.
 *
 * ⚠️ Llamar DESPUÉS de SetCreator/SetKeywords propios, o los pisa.
 *
 * @param object $pdf   instancia de TCPDF
 * @param string $extra texto propio del módulo que se conserva delante
 */
function marca_ia_meta($pdf, string $extra = ''): void
{
    if (!is_object($pdf) || !method_exists($pdf, 'SetCreator')) return;

    $creator = trim(($extra !== '' ? $extra . ' · ' : '')
        . 'contenido asistido por inteligencia artificial');

    $pdf->SetCreator($creator);
    if (method_exists($pdf, 'SetKeywords')) {
        $pdf->SetKeywords('ai-generated, ISORGA');
    }
}

}
