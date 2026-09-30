<?php
/**
 * ISORGI · panel lateral compartido por conversación y ayuda contextual.
 * La conversación mantiene el gate isorgi_autorizado() (apertura general desde 2026-09-09).
 * La ayuda puede abrir este panel en modo de solo lectura para cualquier
 * usuario autenticado, sin enviar contenido al modelo ni consumir IA.
 *
 * 🔴 Desde la v16.726 este panel es la ENTRADA ÚNICA del asistente: por aquí
 * se pregunta tanto por DATOS del centro (documentos, NC, requisitos) como
 * por GESTIÓN (los 32 indicadores de M0). Es el mismo endpoint, la misma
 * memoria y el mismo hilo; lo que cambia es la herramienta que elige el
 * selector. Ver `app/dbchat/ia_gestion.php`.
 *
 * Estilo tipo asistente ISORGA v17 / ARGOS: panel FIJO a la derecha (20% de la
 * pantalla), cabecera con título+subtítulo+limpiar, chips de sugerencia,
 * caja-pista, e input abajo con botón enviar. Abre/cierra con #chat-asist-btn.
 *
 * ⚠️ El CSS va INLINE aquí (autocontenido) a propósito: si viviera en
 * studio-theme-toggle.css el navegador podía servir una versión cacheada sin
 * estas reglas y el panel salía sin estilo. Colores vía tokens --isg-* con
 * fallback, así se adapta a claro/oscuro pero funciona aunque no resuelvan.
 * Backend general: /0/chat_datos_responder.php (catálogo de herramientas, §25).
 * Las ayudas de formulario configuran un endpoint cerrado de su módulo mediante
 * window.isorgaFormularioAbrir(); comparten panel, no selector ni memoria.
 * El endpoint antiguo /0/chat_asistente_responder.php (chatbot_iso) se retiró
 * en v16.594: llevaba desde el cambio al catálogo sin que nadie lo llamara.
 */
$_isorgiConversacional = !empty($_chatAsist);
$_isorgiAyudaContextual = !empty($_hayAyuda) && !empty($_rutaAyuda);
$_chips = [];

if ($_isorgiConversacional) {
    isorgi_preparar_contexto();

// Chips de sugerencia, FILTRADOS POR PERMISO (v16.596): cada chip declara el
// módulo del que presume, y solo se pinta si el usuario lo tiene en este
// centro — la MISMA regla que aplica el selector (ia_cat_niveles_usuario),
// una sola fuente de verdad. Ofrecer un chip de NC a quien no tiene M5 era
// invitarle a una pregunta que iba a acabar en «no sé responder».
// Los de módulo 0 se pintan siempre: son la red de que nadie quede sin chips.
$_chips = [
    ['modulo' => 10, 'sug' => $language['CHAT_DATOS_SUG1'] ?? 'Documentos',
     'q' => $language['CHAT_DATOS_Q1'] ?? '¿Cuántos documentos tenemos?'],
    ['modulo' => 5,  'sug' => $language['CHAT_DATOS_SUG2'] ?? 'NC abiertas',
     'q' => $language['CHAT_DATOS_Q2'] ?? '¿Cuántas no conformidades abiertas hay?'],
    ['modulo' => 4,  'sug' => $language['CHAT_DATOS_SUG3'] ?? 'Revisiones',
     'q' => $language['CHAT_DATOS_Q3'] ?? '¿Qué revisiones de equipos tengo próximas?'],
    ['modulo' => 1,  'sug' => $language['CHAT_DATOS_SUG4'] ?? 'Requisitos',
     'q' => $language['CHAT_DATOS_Q4'] ?? '¿Qué requisitos legales tengo con seguimiento?'],
    // Buscador IA de legislación (antes, pantalla 1/1_buscador_ia.php).
    ['modulo' => 1,  'sug' => $language['CHAT_DATOS_SUG6'] ?? 'Legislación IA',
     'q' => $language['CHAT_DATOS_Q6'] ?? '¿Qué legislación hay sobre legionela?'],
    // Módulo 0 = ayuda de uso: la ve TODO el mundo. Además de orientar, es la
    // red de que nadie se quede con la barra de chips vacía.
    ['modulo' => 0,  'sug' => $language['CHAT_DATOS_SUG5'] ?? '¿Cómo se usa?',
     'q' => $language['CHAT_DATOS_Q5'] ?? '¿Cómo doy de alta una no conformidad?'],
    // ── GESTIÓN (v16.726) ──────────────────────────────────────────────
    // 🔴 Módulo 0 A PROPÓSITO, igual que las herramientas gestion_*: si
    // llevaran su módulo real, el filtro de arriba los escondería a quien no
    // lo tenga, y eso es justo lo que la regla del reparto evita — se
    // ejecutan todos los indicadores y los que la persona no puede ver salen
    // nombrados como «sin acceso», no desaparecidos.
    // El texto de la pregunta sale de AG_G_*, que ya está en los 4 idiomas:
    // no se duplica una clave para decir lo mismo.
    ['modulo' => 0,  'sug' => $language['CHAT_DATOS_SUG7'] ?? 'Hoy',
     'q' => $language['AG_G_HOY'] ?? '¿Qué tengo que hacer hoy?'],
    ['modulo' => 0,  'sug' => $language['CHAT_DATOS_SUG8'] ?? 'Auditor',
     'q' => $language['AG_G_AUDITOR'] ?? '¿Qué me va a preguntar el auditor?'],
];
try {
    require_once __DIR__ . '/../dbchat/ia_motor.php';   // solo define funciones
    $_niv = ia_cat_niveles_usuario(
        (int)($_SESSION['user']['NoUsuario'] ?? 0),
        (int)($_SESSION['centro']['NoCentro'] ?? 0)
    );
    $_chips = array_values(array_filter($_chips, fn($c) =>
        $c['modulo'] === 0 || (($_niv[$c['modulo']] ?? 0) >= 1)));
} catch (Throwable $e) {
    // Este include lo comparte TODO el piloto: un fallo aquí no puede tirar
    // la cabecera. Falla cerrado a los chips de módulo 0 (los universales).
    error_log('[chat_asistente] filtro de chips falló: ' . $e->getMessage());
    $_chips = array_values(array_filter($_chips, fn($c) => $c['modulo'] === 0));
}
}
?>
<style>
:root{--chat-asist-w:clamp(360px,20vw,520px);}
/* Empuje tipo Gemini: al abrir el chat, el layout se encoge por la derecha (no se superpone).
   El sidebar (izquierda) no se toca; header y contenido ceden el ancho del panel. */
@media(min-width:901px){
  #app{transition:padding-right .25s ease;}
  /* 🔴 La cabecera NO se mueve: queda fija a todo el ancho, así el botón de chat/ayuda
     siempre queda en el mismo sitio. Solo el contenido cede el ancho del panel. */
  body.chat-asist-shift #app{padding-right:calc(24px + var(--chat-asist-w)) !important;}
}
/* El panel arranca DEBAJO de la cabecera (no la tapa): top = altura del header. */
#chat-asist-panel{position:fixed;top:var(--bs-app-header-height,3.75rem);right:0;height:calc(100vh - var(--bs-app-header-height,3.75rem));width:var(--chat-asist-w);min-width:360px;max-width:520px;
  background:var(--isg-surface,#fff);border-left:1px solid var(--isg-border-solid,#c5d8ee);
  box-shadow:-12px 0 34px rgba(0,0,0,.18);transform:translateX(105%);transition:transform .25s ease;
  z-index:1100;display:flex;flex-direction:column;font-size:.85rem;}
#chat-asist-panel.open{transform:translateX(0);}
/* Al restaurar el panel tras cambiar de página no queremos que se deslice cada vez. */
.chat-sin-anim #chat-asist-panel,
.chat-sin-anim .app-header,
.chat-sin-anim #app{transition:none !important;}
#chat-asist-panel *{box-sizing:border-box;}
#chat-asist-panel .cap-head{display:flex;align-items:flex-start;gap:.5rem;padding:.85rem 1rem;
  border-bottom:1px solid var(--isg-border,rgba(11,109,181,.22));background:var(--isg-hover-bg,rgba(11,109,181,.07));}
#chat-asist-panel .cap-head-main{display:flex;align-items:center;gap:.6rem;flex:1;min-width:0;}
#chat-asist-panel .cap-head-main>i{color:var(--isg-accent,#0b6db5);font-size:1.15rem;}
/* El búho de ISORGI como avatar de la cabecera. Va aquí y no en el CSS de
   tema porque este include viaja con su estilo (ver la cabecera del fichero).
   `flex-shrink:0` para que el título largo no lo aplaste, y sin fondo: el PNG
   ya viene recortado, así que se ve igual en Blueprint que en HUD. */
#chat-asist-panel .cap-avatar{--isg-isorgi-vivo-tam:2.9rem;}
#chat-asist-panel .cap-title{font-weight:800;letter-spacing:.05em;text-transform:uppercase;font-size:.8rem;color:var(--isg-text,#13202d);line-height:1.15;}
#chat-asist-panel .cap-sub{font-size:.7rem;color:var(--isg-text-muted,#5a6b78);}
#chat-asist-panel .cap-head-tools{display:flex;gap:.15rem;}
#chat-asist-panel .cap-head-tools button{background:transparent;border:0;color:var(--isg-text-muted,#5a6b78);padding:.25rem .45rem;border-radius:.4rem;cursor:pointer;line-height:1;font-size:.9rem;}
#chat-asist-panel .cap-head-tools button:hover{background:var(--isg-hover-bg,rgba(11,109,181,.10));color:var(--isg-accent,#0b6db5);}
#chat-asist-panel .cap-chips{display:flex;flex-wrap:wrap;gap:.35rem;padding:.6rem 1rem;border-bottom:1px solid var(--isg-border,rgba(11,109,181,.22));}
#chat-asist-panel .cap-chip{font-size:.72rem;padding:.25rem .65rem;border:1px solid var(--isg-border-solid,#c5d8ee);background:transparent;color:var(--isg-text,#13202d);border-radius:1rem;cursor:pointer;white-space:nowrap;}
#chat-asist-panel .cap-chip:hover{border-color:var(--isg-accent,#0b6db5);color:var(--isg-accent,#0b6db5);}
#chat-asist-panel .cap-help-trigger{background:var(--bs-primary);border-color:var(--bs-primary);color:var(--bs-white);font-weight:700;box-shadow:0 .2rem .5rem rgba(var(--bs-primary-rgb),.25);}
#chat-asist-panel .cap-help-trigger:hover{background:var(--isg-accent-strong,var(--bs-primary));border-color:var(--isg-accent-strong,var(--bs-primary));color:var(--bs-white);transform:translateY(-1px);}
#chat-asist-panel .cap-body{flex:1;overflow-y:auto;padding:.65rem;display:flex;flex-direction:column;gap:.35rem;}
#chat-asist-panel .cap-hint{border:1px dashed var(--isg-border-solid,#c5d8ee);border-radius:.6rem;padding:1rem;color:var(--isg-text-muted,#5a6b78);font-size:.8rem;text-align:center;line-height:1.45;}
#chat-asist-panel .cap-msg{max-width:88%;padding:.35rem .6rem;border-radius:.75rem;line-height:1.25;word-wrap:break-word;font-size:.82rem;}
/* 🔴 `pre-wrap` SOLO en los mensajes del usuario, y es un arreglo, no un
   capricho. Estaba en `.cap-msg` —o sea, en los dos— y las respuestas del
   asistente contaban CADA SALTO DOS VECES: el servidor las manda por
   `nl2br()`, que inserta el `<br>` pero DEJA el `\n` detrás, y `pre-wrap`
   pintaba además ese `\n`. Con una narración de gestión, que va en
   párrafos, salían tres líneas en blanco entre frase y frase.
   El del usuario sí lo necesita: su texto se escapa sin `nl2br` (viene de
   un textarea y puede traer saltos), así que ahí los pinta el CSS. */
#chat-asist-panel .cap-msg.user{white-space:pre-wrap;}
#chat-asist-panel .cap-msg.user{align-self:flex-end;background:var(--isg-accent,#0b6db5);color:#fff;border-bottom-right-radius:.2rem;}
#chat-asist-panel .cap-msg.bot{align-self:flex-start;background:var(--isg-hover-bg,rgba(11,109,181,.10));color:var(--isg-text,#13202d);border-bottom-left-radius:.2rem;}
#chat-asist-panel .cap-msg.err{align-self:flex-start;background:rgba(220,53,69,.12);color:#dc3545;}
#chat-asist-panel .cap-ayuda{align-self:stretch;border:1px solid var(--isg-border-solid,#c5d8ee);border-radius:.7rem;padding:1rem;background:var(--isg-surface,#fff);color:var(--isg-text,#13202d);line-height:1.5;}
#chat-asist-panel .cap-ayuda-tit{display:flex;align-items:center;gap:.45rem;font-weight:700;color:var(--isg-accent,#0b6db5);margin-bottom:.8rem;}
#chat-asist-panel .cap-ayuda img{max-width:100%;height:auto;}
/* Pie del mensaje cuando la respuesta larga se ha ido al visor de resultados. */
#chat-asist-panel .cap-verres{margin-top:.4rem;font-size:.75rem;opacity:.75;display:flex;align-items:center;gap:.35rem;}
/* Botón «ver el detalle»: abre el visor ancho por ESTA respuesta. Solo
   aparece cuando hay algo que el panel no puede enseñar bien —una tabla de
   ocho columnas, o los bloques de indicador con sus cinco estados—. Si la
   respuesta cabe en el panel, no hay botón: no se manda a nadie a otra
   pantalla para leer lo que ya tiene delante. */
#chat-asist-panel .cap-ver{margin-top:.5rem;display:inline-flex;align-items:center;gap:.4rem;
  background:transparent;border:1px solid var(--isg-accent,#0b6db5);color:var(--isg-accent,#0b6db5);
  border-radius:.5rem;padding:.25rem .6rem;font-size:.75rem;cursor:pointer;line-height:1.3;}
#chat-asist-panel .cap-ver:hover{background:var(--isg-accent,#0b6db5);color:#fff;}
/* Lista compacta de resultados DENTRO del chat (enlaces a ficha + archivo) */
#chat-asist-panel .cap-msg-list{align-self:stretch;max-width:100%;background:transparent;padding:0;}

/* ── El búho junto a cada respuesta ───────────────────────────────────────
   Se hace con `::before`, sin tocar ni el JS ni el PHP. Importa porque las
   burbujas se crean por TRES caminos —add() al responder, pintarListaChat()
   y el bucle PHP que repinta el hilo desde $_SESSION['db_chat'] al recargar
   la página—: con un <img> en el marcado habría que acordarse en los tres,
   y el tercero es el que se olvida.
   El hueco lo abre el margen; el búho se posiciona dentro de él. */
#chat-asist-panel .cap-msg.bot{position:relative;margin-left:2.9rem;}
#chat-asist-panel .cap-msg.bot::before{
  content:"";position:absolute;left:-2.9rem;top:-.15rem;width:2.4rem;height:2.4rem;
  background:url('/assets/img/isorgi_3d_sin.png') center/contain no-repeat;}
/* Solo en la PRIMERA de una tanda seguida: una respuesta que llega partida
   en dos burbujas no la firman dos búhos. El hueco sí se mantiene, para que
   las burbujas de la misma tanda queden alineadas entre sí. */
#chat-asist-panel .cap-msg.bot + .cap-msg.bot::before{content:none;}
/* La tabla de resultados no es una frase del asistente: va a ancho completo
   y sin avatar, como estaba. */
#chat-asist-panel .cap-msg-list{margin-left:0;}
#chat-asist-panel .cap-msg-list::before{content:none;}
/* La burbuja que solo contiene el botón: sin fondo y sin búho —no es una
   frase del asistente—, pero conserva la sangría para que quede alineada
   bajo la respuesta que abre. */
/* 🔴 `.cap-msg.bot.cap-msg-ver`, con las DOS clases: `.cap-msg.bot` pinta el
   fondo con id + 2 clases, así que un `.cap-msg-ver` a secas (id + 1 clase)
   pierde y la burbuja seguía saliendo con su recuadro azul detrás del
   botón. */
#chat-asist-panel .cap-msg.bot.cap-msg-ver{background:transparent;padding:0;}
#chat-asist-panel .cap-msg.bot.cap-msg-ver::before{content:none;}
#chat-asist-panel .cap-list{display:flex;flex-direction:column;gap:.4rem;}
#chat-asist-panel .cap-item{border:1px solid var(--isg-border-solid,#c5d8ee);border-radius:.6rem;padding:.5rem .65rem;background:var(--isg-surface,#fff);}
#chat-asist-panel .cap-item-tit{font-weight:600;color:var(--isg-accent,#0b6db5);text-decoration:none;display:block;line-height:1.25;}
#chat-asist-panel .cap-item-tit:hover{text-decoration:underline;}
#chat-asist-panel .cap-item-meta{font-size:.72rem;color:var(--isg-text-muted,#5a6b78);margin-top:.15rem;}
#chat-asist-panel .cap-item-file{display:inline-block;margin-top:.35rem;font-size:.75rem;color:var(--isg-accent,#0b6db5);text-decoration:none;word-break:break-all;}
#chat-asist-panel .cap-item-file:hover{text-decoration:underline;}
#chat-asist-panel .cap-foot{border-top:1px solid var(--isg-border,rgba(11,109,181,.22));padding:.7rem;background:var(--isg-surface,#fff);}
#chat-asist-panel .cap-inputbar{display:flex;gap:.5rem;align-items:flex-end;margin:0;}
#chat-asist-panel .cap-inputbar textarea{flex:1;resize:none;border:1px solid var(--isg-border-solid,#c5d8ee);border-radius:.7rem;padding:.5rem .75rem;background:var(--isg-surface,#fff);color:var(--isg-text,#13202d);font-size:.85rem;line-height:1.35;outline:none;max-height:120px;font-family:inherit;}
#chat-asist-panel .cap-inputbar textarea:focus{border-color:var(--isg-accent,#0b6db5);box-shadow:0 0 0 .15rem rgba(11,109,181,.22);}
#chat-asist-panel .cap-send{flex-shrink:0;width:2.3rem;height:2.3rem;border-radius:50%;border:0;background:var(--isg-accent,#0b6db5);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;}
#chat-asist-panel .cap-send:hover{background:var(--isg-accent-strong,#085a97);}
#chat-asist-panel .cap-voice.active{background:var(--bs-danger,#dc3545);}
#chat-asist-panel .cap-voice:disabled{opacity:.45;cursor:not-allowed;}
#chat-asist-btn.active .nav-icon{color:var(--isg-accent,#0b6db5);}
@media(max-width:900px){#chat-asist-panel{width:100%;min-width:0;max-width:none;}}

/* Resultado integrado EN el contenido principal (sustituye la página; el sidebar y la
   cabecera se mantienen). El contenido de la página se oculta por JS y se muestra solo
   #chat-asist-resultado; "Volver" lo quita y restaura la página (sin destruirla). */
#chat-asist-resultado{padding-top:1rem;}
#chat-asist-resultado .car-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;}
#chat-asist-resultado .car-title{font-weight:800;letter-spacing:.02em;font-size:1.05rem;color:var(--isg-text,#13202d);margin:0;display:flex;align-items:center;gap:.5rem;}
#chat-asist-resultado .car-count{font-size:.8rem;color:var(--isg-text-muted,#5a6b78);}
#chat-asist-resultado .car-table{width:100%;border-collapse:collapse;font-size:.82rem;color:var(--isg-text,#13202d);}
#chat-asist-resultado .car-table th{background:var(--isg-hover-bg,rgba(11,109,181,.07));text-align:left;font-weight:700;font-size:.72rem;text-transform:uppercase;color:var(--isg-text-muted,#5a6b78);padding:.5rem .7rem;border-bottom:1px solid var(--isg-border,rgba(11,109,181,.22));white-space:nowrap;}
#chat-asist-resultado .car-table td{padding:.5rem .7rem;border-bottom:1px solid var(--isg-border,rgba(11,109,181,.14));vertical-align:top;}
#chat-asist-resultado .car-table tr:hover td{background:var(--isg-hover-bg,rgba(11,109,181,.05));}
</style>

<div id="chat-asist-panel" aria-hidden="true">

    <div class="cap-head">
        <div class="cap-head-main">
            <?= isorgi_avatar_vivo('cap-avatar') ?>
            <div>
                <div class="cap-title"><?= $language['CHAT_ASISTENTE'] ?? 'ISORGI' ?></div>
                <div class="cap-sub"><?= $_isorgiConversacional ? ($language['CHAT_DATOS_SUBTITULO'] ?? 'datos de tu centro') : ($language['AYU_TITULO_PANEL'] ?? 'Ayuda de la página') ?></div>
            </div>
        </div>
        <div class="cap-head-tools">
            <?php if ($_isorgiConversacional): ?>
            <button type="button" id="chat-asist-clear" title="<?= $language['CHAT_ASIST_LIMPIAR'] ?? 'Limpiar conversación' ?>"><i class="fas fa-trash"></i></button>
            <?php endif; ?>
            <button type="button" id="chat-asist-close" title="<?= $language['CANCELAR'] ?? 'Cerrar' ?>"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <div class="cap-chips">
        <?php
        require_once __DIR__ . '/../clases/IsorgiContexto.php';
        if ($_isorgiConversacional && IsorgiContexto::accesoGeneral(
            (int) ($_SESSION['user']['NoUsuario'] ?? 0), (int) ($_SESSION['centro']['NoCentro'] ?? 0))
            && (int) ($_SESSION['centro']['modulo0'] ?? 0) >= 1): ?>
        <a class="cap-chip text-uppercase" href="/0/isorgi_contexto.php"><?= htmlspecialchars($language['ISC_TITULO'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
        <?php if ($_isorgiAyudaContextual): ?>
        <button type="button" class="cap-chip cap-help-trigger">
            <i class="far fa-question-circle me-1"></i><?= htmlspecialchars($language['AYU_TITULO_PANEL'] ?? 'Ayuda de la página') ?>
        </button>
        <?php endif; ?>
        <?php foreach ($_chips as $_c): ?>
        <button type="button" class="cap-chip cap-chat-chip"
                data-q="<?= htmlspecialchars($_c['q'], ENT_QUOTES) ?>"><?= htmlspecialchars($_c['sug']) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="cap-body" id="chat-asist-body">
        <?php $__chatHist = $_isorgiConversacional ? ($_SESSION['db_chat'] ?? []) : []; if (!is_array($__chatHist)) $__chatHist = []; ?>
        <?php if (empty($__chatHist)): ?>
        <div class="cap-hint" id="chat-asist-hint"><?= $_isorgiConversacional ? ($language['CHAT_DATOS_SALUDO'] ?? 'Soy ISORGI. Pregúntame por los datos de tu centro o por cómo va tu sistema de gestión.') : ($language['AYU_TITULO_PANEL'] ?? 'Ayuda de la página') ?></div>
        <?php else: foreach ($__chatHist as $__m):
            $__cls = (($__m['rol'] ?? '') === 'user') ? 'user' : 'bot';
            $__txt = (string)($__m['texto'] ?? '');
            $__html = ($__cls === 'user') ? htmlspecialchars($__txt, ENT_QUOTES, 'UTF-8') : nl2br(htmlspecialchars($__txt, ENT_QUOTES, 'UTF-8'));
            $__marcaTipo = (string)($__m['marca_tipo'] ?? 'respuesta');
        ?>
        <div class="cap-msg <?= $__cls ?>">
            <?= $__html ?>
            <?php if ($__cls === 'bot' && !empty($__m['narracion_ia']) && function_exists('marca_ia')): ?>
                <?= marca_ia('html', ['tipo' => $__marcaTipo, 'compacta' => true]) ?>
            <?php endif; ?>
        </div>

        <?php endforeach; endif; ?>
    </div>

    <?php if (true): // rap.isorga.com: el panel solo se abre desde el asistente de formulario, que necesita la barra ?>
    <div class="cap-foot">
        <form id="chat-asist-form" class="cap-inputbar">
            <button type="button" class="cap-send cap-voice d-none" id="chat-asist-voice"
                    aria-label="<?= htmlspecialchars($language['M1_IA_VOZ_INICIAR'] ?? 'Dictar respuesta', ENT_QUOTES, 'UTF-8') ?>"
                    title="<?= htmlspecialchars($language['M1_IA_VOZ_INICIAR'] ?? 'Dictar respuesta', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas fa-microphone"></i>
            </button>
            <textarea id="chat-asist-input" rows="1" placeholder="<?= $language['CHAT_ASIST_PLACEHOLDER'] ?? 'Escribe tu pregunta…' ?>"></textarea>
            <button type="submit" class="cap-send" aria-label="<?= htmlspecialchars($language['ENVIAR'] ?? 'Enviar', ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var panel = document.getElementById('chat-asist-panel');
    var btn   = document.getElementById('chat-asist-btn');
    if (!panel || !btn || panel.__bound) return; panel.__bound = true;

    var closeB = document.getElementById('chat-asist-close');
    var clearB = document.getElementById('chat-asist-clear');
    var body   = document.getElementById('chat-asist-body');
    var form   = document.getElementById('chat-asist-form');
    var input  = document.getElementById('chat-asist-input');
    var voiceB = document.getElementById('chat-asist-voice');
    var conversacional = <?= $_isorgiConversacional ? 'true' : 'false' ?>;
    var rutaAyuda = <?= json_encode($_isorgiAyudaContextual ? (string)$_rutaAyuda : '', JSON_UNESCAPED_SLASHES) ?>;
    var ayudaCargando = <?= json_encode($language['AYU_CARGANDO'] ?? 'Cargando ayuda…', JSON_UNESCAPED_UNICODE) ?>;
    var ayudaNoDisponible = <?= json_encode($language['AYU_NO_DISPONIBLE'] ?? 'No hay ayuda disponible para esta página.', JSON_UNESCAPED_UNICODE) ?>;
    var ayudaTitulo = <?= json_encode($language['AYU_TITULO_PANEL'] ?? 'Ayuda de la página', JSON_UNESCAPED_UNICODE) ?>;
    // Hint fijo para el botón "limpiar": NO usamos body.innerHTML porque al recargar
    // la página el body puede venir ya con la conversación restaurada del servidor.
    var hintHTML = '<div class="cap-hint" id="chat-asist-hint">' + <?= json_encode($language['CHAT_DATOS_SALUDO'] ?? 'Pregúntame por los datos de tu centro: documentos, no conformidades, carpetas…', JSON_UNESCAPED_UNICODE) ?> + '</div>';
    body.scrollTop = body.scrollHeight;   // si hay conversación restaurada, baja al final
    var historial = [];
    var formularioActivo = null;
    var siguienteAccionFormulario = 'mensaje';
    var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    var reconocimientoVoz = null;
    var vozDeseada = false;
    var vozBase = '';
    var VOZ = {
        iniciar: <?= json_encode($language['M1_IA_VOZ_INICIAR'] ?? 'Dictar respuesta', JSON_UNESCAPED_UNICODE) ?>,
        detener: <?= json_encode($language['M1_IA_VOZ_DETENER'] ?? 'Detener dictado', JSON_UNESCAPED_UNICODE) ?>,
        noSoportada: <?= json_encode($language['M1_IA_VOZ_NO_SOPORTADA'] ?? 'Tu navegador no soporta dictado por voz.', JSON_UNESCAPED_UNICODE) ?>,
        error: <?= json_encode($language['M1_IA_VOZ_ERROR'] ?? 'No se pudo usar el micrófono. Revisa el permiso del navegador.', JSON_UNESCAPED_UNICODE) ?>
    };

    // Resultado integrado EN el contenido principal: sustituye la página (el sidebar y la
    // cabecera se mantienen); "Volver" restaura la página anterior sin destruirla.
    // Etiquetas del botón que abre el visor. %s = el número.
    var VER = <?= json_encode([
        'resultados' => $language['CHAT_VER_RESULTADOS'] ?? 'Ver los %s resultados',
        'detalle'    => $language['CHAT_VER_DETALLE']    ?? 'Ver el detalle (%s indicadores)',
    ], JSON_UNESCAPED_UNICODE) ?>;

    var RES_LBLS = {
        volver: <?= json_encode($language['VOLVER'] ?? 'Volver') ?>,
        titulo: <?= json_encode($language['CHAT_DATOS_RESULTADO'] ?? 'Resultado') ?>
    };
    // Errores del propio JS. Antes eran dos literales castellanos en duro, los
    // únicos del panel que no pasaban por $language.
    var ERR = <?= json_encode([
        'respuesta' => $language['CHAT_ERROR']     ?? 'No se pudo obtener respuesta.',
        'red'       => $language['CHAT_ERROR_RED'] ?? 'Error de conexión. Inténtalo de nuevo.',
    ], JSON_UNESCAPED_UNICODE) ?>;
    var FORMULARIO_APLICAR = <?= json_encode($language['ISORGI_FORM_APLICAR_PROPUESTA'] ?? 'Pasar propuesta al formulario', JSON_UNESCAPED_UNICODE) ?>;
    var MARCA_IA_RESPUESTA = <?= json_encode(function_exists('marca_ia') ? marca_ia('html', ['tipo' => 'respuesta', 'compacta' => true]) : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var MARCA_IA_SUGERENCIA = <?= json_encode(function_exists('marca_ia') ? marca_ia('html', ['tipo' => 'sugerencia', 'compacta' => true]) : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    // El contenido de página vive dentro de #app (junto al header/drawer fijos). Ocultamos
    // solo los hijos VISIBLES en flujo (offsetParent != null excluye fijos y ya-ocultos).
    var contentHost = document.getElementById('app') || document.body;
    var ocultos = [];
    function hijosPagina() {
        return Array.prototype.filter.call(contentHost.children, function (el) {
            if (el.id === 'chat-asist-resultado') return false;
            if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE') return false;
            return el.offsetParent !== null;   // visible y en flujo (no fixed, no display:none)
        });
    }
    function cerrarResultado() {
        ocultos.forEach(function (el) { el.style.display = el.dataset.chatPrevDisplay || ''; delete el.dataset.chatPrevDisplay; });
        ocultos = [];
        var box = document.getElementById('chat-asist-resultado'); if (box) box.remove();
    }
    function pintarTabla(filas) {
        if (!filas || !filas.length) return;
        // Ocultar la página actual (una sola vez; si ya hay resultado, solo se refresca)
        if (!ocultos.length) {
            ocultos = hijosPagina();
            ocultos.forEach(function (el) { el.dataset.chatPrevDisplay = el.style.display; el.style.display = 'none'; });
        }
        var box = document.getElementById('chat-asist-resultado');
        if (!box) { box = document.createElement('div'); box.id = 'chat-asist-resultado'; contentHost.appendChild(box); }
        var cols = Object.keys(filas[0]);
        var h = '<div class="car-head">'
              + '<h2 class="car-title"><i class="fas fa-table text-primary"></i>' + esc(RES_LBLS.titulo)
              + ' <span class="car-count">(' + filas.length + ')</span></h2>'
              + '<button type="button" id="chat-asist-volver" class="btn btn-outline-secondary btn-sm">'
              + '<i class="fas fa-arrow-left me-2"></i>' + esc(RES_LBLS.volver) + '</button></div>';
        h += '<div class="card border-0 shadow-sm rounded-4"><div class="card-body p-2"><div class="table-responsive">';
        h += '<table class="car-table"><thead><tr>';
        cols.forEach(function (k) { h += '<th>' + esc(k) + '</th>'; });
        h += '</tr></thead><tbody>';
        filas.forEach(function (r) { h += '<tr>'; cols.forEach(function (k) { h += '<td>' + esc(r[k]) + '</td>'; }); h += '</tr>'; });
        h += '</tbody></table></div></div></div>';
        box.innerHTML = h;
        document.getElementById('chat-asist-volver').addEventListener('click', cerrarResultado);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    function quitarHint() { var h = document.getElementById('chat-asist-hint'); if (h) h.remove(); }
    function add(html, cls) {
        quitarHint();
        var d = document.createElement('div'); d.className = 'cap-msg ' + cls; d.innerHTML = html;
        body.appendChild(d); body.scrollTop = body.scrollHeight; return d;
    }
    var ayudaBox = null;
    var ayudaCargada = false;
    function mostrarAyuda() {
        if (!rutaAyuda) return;
        abrir(true, true);
        quitarHint();
        if (!ayudaBox || !ayudaBox.isConnected) {
            ayudaBox = document.createElement('div');
            ayudaBox.className = 'cap-ayuda';
            body.appendChild(ayudaBox);
        }
        if (ayudaCargada) {
            ayudaBox.scrollIntoView({ block: 'start', behavior: 'smooth' });
            return;
        }
        ayudaBox.innerHTML = '<div class="cap-ayuda-tit"><i class="far fa-question-circle"></i>' + esc(ayudaTitulo) + '</div><div class="text-muted">' + esc(ayudaCargando) + '</div>';
        fetch('/app/ayuda_ver.php?ruta=' + encodeURIComponent(rutaAyuda), { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function (html) {
                ayudaBox.innerHTML = '<div class="cap-ayuda-tit"><i class="far fa-question-circle"></i>' + esc(ayudaTitulo) + '</div>' + html;
                ayudaCargada = true;
                ayudaBox.scrollIntoView({ block: 'start', behavior: 'smooth' });
            })
            .catch(function () {
                ayudaBox.innerHTML = '<div class="cap-ayuda-tit"><i class="far fa-question-circle"></i>' + esc(ayudaTitulo) + '</div><div class="text-muted">' + esc(ayudaNoDisponible) + '</div>';
            });
    }
    // Lista compacta de resultados DENTRO del chat: cada fila enlaza a su ficha
    // (misma pestaña; el panel sigue abierto al navegar) y, si trae archivo,
    // un enlace para abrirlo (a través de la ficha, que es la vía validada por centro).
    function pintarListaChat(filas) {
        if (!filas || !filas.length) return;
        var h = '<div class="cap-list">';
        filas.forEach(function (r) {
            var titulo = r.nombre || r.titulo || r.numero || r.codigo || '—';
            var meta = [];
            if (r.codigo && r.codigo !== titulo) meta.push(r.codigo);
            if (r.numero && r.numero !== titulo) meta.push(r.numero);
            if (r.estado)  meta.push(r.estado);
            if (r.carpeta) meta.push(r.carpeta);
            if (r.fecha)   meta.push(r.fecha);
            var it = '<div class="cap-item">';
            it += r.url
                ? '<a class="cap-item-tit" href="' + esc(r.url) + '">' + esc(titulo) + '</a>'
                : '<span class="cap-item-tit">' + esc(titulo) + '</span>';
            if (meta.length) it += '<div class="cap-item-meta">' + esc(meta.join(' · ')) + '</div>';
            if (r.archivo && r.url) {
                it += '<a class="cap-item-file" href="' + esc(r.url) + '" target="_blank" rel="noopener">'
                    +  '<i class="fas fa-paperclip me-1"></i>' + esc(r.archivo) + '</a>';
            }
            it += '</div>';
            h += it;
        });
        h += '</div>';
        var d = document.createElement('div');
        d.className = 'cap-msg bot cap-msg-list';
        d.innerHTML = h;
        quitarHint();
        body.appendChild(d); body.scrollTop = body.scrollHeight;
    }

    function vozPintar(activa) {
        if (!voiceB) return;
        voiceB.classList.toggle('active', activa);
        voiceB.setAttribute('aria-label', activa ? VOZ.detener : VOZ.iniciar);
        voiceB.title = activa ? VOZ.detener : VOZ.iniciar;
        var icono = voiceB.querySelector('i');
        if (icono) icono.className = activa ? 'fas fa-stop' : 'fas fa-microphone';
    }

    function vozDetener() {
        vozDeseada = false;
        if (reconocimientoVoz) {
            try { reconocimientoVoz.stop(); } catch (e) {}
        }
        vozPintar(false);
    }

    function vozConfigurar(habilitada) {
        vozDetener();
        if (!voiceB) return;
        voiceB.classList.toggle('d-none', !habilitada);
        voiceB.disabled = !!habilitada && !SpeechRec;
        if (habilitada && !SpeechRec) voiceB.title = VOZ.noSoportada;
    }

    function vozIniciar() {
        if (!voiceB || voiceB.classList.contains('d-none') || !input) return;
        if (!SpeechRec) {
            add(esc(VOZ.noSoportada), 'err');
            return;
        }
        if (vozDeseada) {
            vozDetener();
            return;
        }
        if (!reconocimientoVoz) {
            reconocimientoVoz = new SpeechRec();
            reconocimientoVoz.continuous = true;
            reconocimientoVoz.interimResults = true;
            reconocimientoVoz.lang = <?= json_encode([
                'es' => 'es-ES', 'en' => 'en-US', 'fr' => 'fr-FR', 'ca' => 'ca-ES',
            ][strtolower(substr((string)($_SESSION['user']['qLenguaje'] ?? $_SESSION['user']['lang'] ?? 'es'), 0, 2))] ?? 'es-ES') ?>;
            reconocimientoVoz.onresult = function (e) {
                var finalTexto = '', provisional = '';
                for (var i = 0; i < e.results.length; i++) {
                    if (e.results[i].isFinal) finalTexto += e.results[i][0].transcript;
                    else provisional += e.results[i][0].transcript;
                }
                input.value = [vozBase, finalTexto, provisional].filter(Boolean).join(' ').trim();
                input.dispatchEvent(new Event('input', {bubbles:true}));
            };
            reconocimientoVoz.onerror = function (e) {
                if (e.error === 'no-speech' || e.error === 'aborted') return;
                vozDeseada = false;
                vozPintar(false);
                add(esc(VOZ.error), 'err');
            };
            reconocimientoVoz.onend = function () {
                if (vozDeseada) {
                    vozBase = input.value.trim();
                    try { reconocimientoVoz.start(); } catch (e) {
                        vozDeseada = false;
                        vozPintar(false);
                    }
                } else {
                    vozPintar(false);
                    if (input) input.focus();
                }
            };
        }
        vozBase = input.value.trim();
        vozDeseada = true;
        vozPintar(true);
        try {
            reconocimientoVoz.start();
        } catch (e) {
            vozDeseada = false;
            vozPintar(false);
            add(esc(VOZ.error), 'err');
        }
    }

    function abrir(v, noFocus) {
        if (typeof v === 'boolean') panel.classList.toggle('open', v);
        else panel.classList.toggle('open');
        var abierto = panel.classList.contains('open');
        document.body.classList.toggle('chat-asist-shift', abierto);   // empuja el contenido (tipo Gemini)
        btn.classList.toggle('active', abierto);
        panel.setAttribute('aria-hidden', abierto ? 'false' : 'true');
        // Recordar el estado para mantenerlo al cambiar de página (recarga entera).
        try { localStorage.setItem('isorgaChatAbierto', abierto ? '1' : '0'); } catch (e) {}
        if (abierto) body.scrollTop = body.scrollHeight;
        if (abierto && !noFocus && input) setTimeout(function () { input.focus(); }, 60);
    }

    btn.addEventListener('click', function (e) { e.preventDefault(); abrir(); });
    closeB.addEventListener('click', function () { vozDetener(); abrir(false); });
    if (clearB) clearB.addEventListener('click', function () {
        historial = []; body.innerHTML = hintHTML;
        ayudaBox = null; ayudaCargada = false;
        cerrarResultado();
        if (formularioActivo) {
            fetch(formularioActivo.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    accion: 'reset',
                    objeto_id: formularioActivo.objetoId,
                    csrf: formularioActivo.csrf
                })
            });
        } else {
            fetch('/0/chat_datos_responder.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'reset=1' });
        }
    });

    function marcarRespuesta(nodo, respuesta) {
        if (!nodo || !respuesta || !respuesta.narracion_ia) return;
        var marca = respuesta.marca_tipo === 'sugerencia'
            ? MARCA_IA_SUGERENCIA
            : MARCA_IA_RESPUESTA;
        if (marca) nodo.insertAdjacentHTML('beforeend', marca);
    }

    function activarPropuesta(boton, propuesta, respuesta) {
        if (!boton || !propuesta || !formularioActivo) return;
        var configuracion = formularioActivo;
        boton.addEventListener('click', function () {
            window.dispatchEvent(new CustomEvent(configuracion.evento || 'isorga:formulario-propuesta', {
                detail: {
                    codigo: respuesta && respuesta.contexto ? respuesta.contexto.codigo : configuracion.codigo,
                    objeto_id: respuesta && respuesta.contexto ? respuesta.contexto.objeto_id : configuracion.objetoId,
                    propuesta: propuesta
                }
            }));
        });
    }

    function pintarPropuestaFormulario(propuesta, respuesta) {
        if (!propuesta || !formularioActivo) return;
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'cap-ver cap-form-apply';
        boton.innerHTML = '<i class="fas fa-file-import"></i>'
            + esc(formularioActivo.etiquetaAplicar || FORMULARIO_APLICAR);
        activarPropuesta(boton, propuesta, respuesta);
        var cont = document.createElement('div');
        cont.className = 'cap-msg bot cap-msg-ver';
        cont.appendChild(boton);
        body.appendChild(cont);
        body.scrollTop = body.scrollHeight;
    }

    function enviar(texto) {
        if (!conversacional || !input) return;
        var msg = (texto != null ? texto : input.value).trim(); if (!msg) return;
        vozDetener();
        add(esc(msg), 'user'); input.value = '';
        historial.push({ rol: 'user', texto: msg });
        var pensando = add('<span class="spinner-border spinner-border-sm"></span>', 'bot');
        if (window.isorgiEstado) window.isorgiEstado('pensando');

        var endpoint = formularioActivo ? formularioActivo.endpoint : '/0/chat_datos_responder.php';
        var parametros = formularioActivo
            ? {
                accion: siguienteAccionFormulario,
                mensaje: msg,
                objeto_id: formularioActivo.objetoId,
                csrf: formularioActivo.csrf
            }
            : { mensaje: msg };
        siguienteAccionFormulario = 'mensaje';

        fetch(endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(parametros)
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            pensando.remove();
            if (window.isorgiEstado) window.isorgiEstado('reposo');
            if (j && j.ok) {
                historial.push({ rol: 'assistant', texto: j.texto_plano || '' });

                // 🔴 Costura de salida. Si la página trae un visor de resultados
                // (chat_cabecera.php define `isorgaChatSalida`), la respuesta se
                // pinta ALLÍ —modal ancho, tabla de verdad, botón de PDF— y el
                // panel se queda solo con el hilo de la conversación.
                // Si no lo trae, el panel se comporta exactamente como siempre.
                // Es lo que permite cambiar la salida del usuario 7 sin tocar una
                // línea del piloto francés (1260), que no carga ese include.
                if (!formularioActivo && typeof window.isorgaChatSalida === 'function') {
                    // 🔴 EL VISOR YA NO SE ABRE SOLO (v16.727). Antes cada
                    //    respuesta lo desplegaba encima de la página en la
                    //    que estabas trabajando, aunque solo quisieras leer
                    //    dos frases. Ahora:
                    //      · el panel trae la RESPUESTA ENTERA — no un
                    //        recorte de 240 caracteres que obligaba a abrir
                    //        el visor para terminar de leer una frase;
                    //      · y solo si hay algo que el panel NO puede
                    //        enseñar bien (una tabla ancha, los bloques de
                    //        indicador) sale un botón para abrirlo.
                    //    Se sigue alimentando el visor SIEMPRE: guarda el
                    //    hilo entero y es lo que imprime el PDF.
                    var ancla = window.isorgaChatSalida(msg, j, false);

                    var respuestaBot = add(j.respuesta, 'bot');
                    marcarRespuesta(respuestaBot, j);
                    pintarPropuestaFormulario(j.propuesta, j);

                    var nFil = (j.filas && j.filas.length) ? j.filas.length : 0;
                    var nInd = (j.gestion && j.gestion.resultados) ? j.gestion.resultados.length : 0;
                    if (nFil || nInd) {
                        var txt = nFil
                            ? VER.resultados.replace('%s', nFil)
                            : VER.detalle.replace('%s', nInd);
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'cap-ver';
                        b.innerHTML = '<i class="fas fa-' + (nFil ? 'table' : 'list-check') + '"></i>'
                                    + esc(txt);
                        b.addEventListener('click', function () { window.isorgaChatVer(ancla); });
                        var cont = document.createElement('div');
                        cont.className = 'cap-msg bot cap-msg-ver';
                        cont.appendChild(b);
                        body.appendChild(cont);
                        body.scrollTop = body.scrollHeight;
                    }
                } else {
                    var respuestaBot = add(j.respuesta, 'bot');
                    marcarRespuesta(respuestaBot, j);
                    pintarPropuestaFormulario(j.propuesta, j);
                    // La respuesta se queda SOLO en el chat. NO se vuelca la tabla a la
                    // página principal: la página que hay detrás se mantiene intacta.
                    if (j.filas && j.filas.length) pintarListaChat(j.filas);
                }
            } else { add(esc((j && j.mensaje) || ERR.respuesta), 'err'); }
        })
        .catch(function () {
            pensando.remove();
            if (window.isorgiEstado) window.isorgiEstado('reposo');
            add(ERR.red, 'err');
        });
    }

    // El visor de resultados necesita poder devolver una repregunta al panel, que
    // es quien tiene el input y la memoria del hilo. Una sola vía de envío.
    window.isorgaChatEnviar = function (texto) {
        if (formularioActivo) {
            historial = [];
            body.innerHTML = hintHTML;
        }
        formularioActivo = null;
        vozConfigurar(false);
        abrir(true, true);
        enviar(texto);
    };

    /**
     * Entrada neutral para ayudas contextuales sobre formularios. El modulo
     * aporta una ruta cerrada, el id del objeto, CSRF, etiqueta y evento.
     */
    window.isorgaFormularioAbrir = function (configuracion) {
        if (!configuracion || !configuracion.endpoint || configuracion.objetoId == null
            || !Number.isInteger(Number(configuracion.objetoId)) || Number(configuracion.objetoId) < 0
            || !configuracion.csrf || !configuracion.mensajeInicial) return;
        conversacional = true;
        formularioActivo = {
            codigo: String(configuracion.codigo || ''),
            endpoint: String(configuracion.endpoint),
            objetoId: Number(configuracion.objetoId),
            csrf: String(configuracion.csrf),
            evento: String(configuracion.evento || 'isorga:formulario-propuesta'),
            etiquetaAplicar: String(configuracion.etiquetaAplicar || FORMULARIO_APLICAR),
            voz: configuracion.voz === true
        };
        historial = [];
        body.innerHTML = '';
        ayudaBox = null;
        ayudaCargada = false;
        cerrarResultado();
        siguienteAccionFormulario = 'iniciar';
        vozConfigurar(formularioActivo.voz);
        // En modo escrito el cursor debe quedar listo en el chat. En modo voz
        // no se fuerza el foco porque el mismo gesto de apertura inicia el
        // reconocimiento del micrófono.
        abrir(true, formularioActivo.voz);
        if (formularioActivo.voz && !SpeechRec) add(esc(VOZ.noSoportada), 'err');
        enviar(String(configuracion.mensajeInicial));
        if (formularioActivo.voz && configuracion.vozAutoIniciar === true) vozIniciar();
    };

    panel.querySelectorAll('.cap-help-trigger').forEach(function (c) {
        c.addEventListener('click', mostrarAyuda);
    });
    panel.querySelectorAll('.cap-chat-chip').forEach(function (c) {
        c.addEventListener('click', function () {
            if (formularioActivo) {
                historial = [];
                body.innerHTML = hintHTML;
            }
            formularioActivo = null;
            vozConfigurar(false);
            enviar(c.getAttribute('data-q'));
        });
    });
    if (voiceB) voiceB.addEventListener('click', vozIniciar);
    if (form) form.addEventListener('submit', function (e) { e.preventDefault(); enviar(); });
    if (input) input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); }
    });

    // Mantener el panel abierto al navegar: ISORGA recarga la página entera, así
    // que restauramos el estado guardado. La conversación ya viene repintada del
    // servidor (sesión), por lo que reaparece tal cual al reabrir.
    try {
        if (localStorage.getItem('isorgaChatAbierto') === '1') {
            document.documentElement.classList.add('chat-sin-anim');
            abrir(true, true);
            void panel.offsetWidth;   // reflow: aplica el estado abierto sin animar
            requestAnimationFrame(function () { document.documentElement.classList.remove('chat-sin-anim'); });
        }
    } catch (e) {}
})();
</script>
