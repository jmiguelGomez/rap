# CHANGELOG — rap.isorga.com

## v0.011 — 2026-10-02
### Sin login con contraseña: a la app se entra solo desde ISORGA

Decisión de Juan Miguel (2026-10-02): a todas las apps se entra a través de isorga.com, salvo el CAE. Nueva constante **`APP_LOGIN_CONTRASENA`** en `app/config/config.php` (a `false`): `auth/login.php` no pinta el formulario —explica que se entra desde ISORGA y deja el botón «Entrar · ISORGA»— y `auth/login_envio.php` contesta **404**. **El formulario sigue en el fichero**: ponerla a `true` lo reactiva (o `'login_contrasena' => true` en `isorga-net/scripts/satelites/rap.php` y relanzar el generador).

Probado por HTTP: login sin `<form>`, `POST /auth/login_envio.php` → 404, enlace firmado desde ISORGA → panel.


## v0.010 — 2026-10-01
### El selector de centro vuelve a la barra, y ahora es un desplegable

Petición de Juan Miguel (2026-10-01). El chip del centro vuelve a ser **pulsable**, pero en vez de salir a la pantalla de elección abre un **desplegable en la propia barra** con **todos** mis centros activos. Los que tienen el módulo de esta aplicación a **0 salen igual, en gris y con un candado**, y no se pueden pulsar; el actual sale marcado.

🔴 **Pulsar no concede nada.** El enlace va a `/auth/centro_envio.php`, que vuelve a comprobar **en base** el permiso del módulo antes de tocar la sesión: el «no pulsable» es cortesía para quien mira, no la puerta. Probado forzando por URL un centro sin el módulo — no cambia de centro y devuelve a la pantalla de elección.

⚠️ **Se enseña también a quien entró desde ISORGA.** Algunas aplicaciones lo escondían en ese caso; es justo lo que se ha pedido activar.

🔴 **La consulta agrupa por centro, y no es cosmético**: `usuariosxcentros` tiene **filas repetidas** para la misma pareja usuario-centro —medido: 23 parejas, 33 filas de más, hasta 7 para una sola— y sin agrupar el mismo centro salía dos veces en la lista. Se queda el nivel más alto de las copias.

Sin JavaScript propio: es un desplegable de Bootstrap, que ya estaba cargado, vestido con una clase del tema que estaba escrita y no usaba nadie.


## v0.002 — 2026-09-30
### Todas las tablas con aspecto Excel

Como en ISORGA: el mismo cargador de estilo (`app/includes/estilo_trabajo.php`) con `tablas_excel_global` en `app/config/estilo_trabajo.json`. Rejilla, cabeceras compactas y filas finas en todas las tablas de la aplicación.

## v0.001 — 2026-09-30
### La aplicación: entra con la cuenta de ISORGA, elige centro y trae las pantallas del módulo 42

Generada con `isorga-net/scripts/satelite_crear.php` desde el molde de check.isorga.com: portada pública,
login con la cuenta de ISORGA (con contraseña o sin ella desde ISORGA), selector de centro y las pantallas de
«Envases y RAP» copiadas de ISORGA con `scripts/rap_portar.php`.
