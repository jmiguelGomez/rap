# CHANGELOG — rap.isorga.com

## v0.014 — 2026-10-03
### Modo claro / oscuro

- Selector de modo claro / oscuro en la barra superior (luna/sol). Cada persona elige el modo en cada app por separado; por defecto, claro. El oscuro es sobrio, en grises, con el color de la app.

## v0.013 — 2026-10-03
### Puesta al día del 2-3 de octubre (12 cambios)

- «Volver a ISORGA» vuelve a isorga.com/0/index_2.php, como antes.
- El menú lateral y los botones primarios llevan el color de la tarjeta de la app en index_2.
- Botón Isorgi en la barra superior (de momento sin acción).
- Isorgi en la barra es la mascota, como en la cabecera de ISORGA.
- «Cambio usuario» de soporte en el pie del menú lateral.
- Con soporte, el icono y el nombre del usuario son «Cambio usuario».
- Foto del usuario en el pie del menú lateral, en miniatura.
- «Cambio usuario» de soporte acaba en la portada de bloques, no vuelve a la app.
- App/style_guide.php: las tarjetas, tablas y botones «Imprimir PDF» salían sin clases.
- Sin título en la barra superior (repetía el de la página).
- Cabecera como las demás apps — Box documental y Acciones, sin «Ir a…».
- Cáscara al día con el generador de satélites.


## v0.012 — 2026-10-02
### Sin sesión, al salir y por inactividad: al login de ISORGA

Decisión de Juan Miguel (2026-10-02): a la app se entra solo por isorga.com, así que con `APP_LOGIN_CONTRASENA = false`:
- quien llega por la URL sin sesión —la raíz, la portada o cualquier pantalla— va a **`https://isorga.com/login.php`**; la portada pública deja de verse (decisión suya);
- **«Salir»** lleva siempre a `isorga.com/login.php`;
- la sesión **caducada por inactividad** va a `isorga.com/login.php?timeout=1`, que enseña el aviso de sesión caducada de ISORGA.

Todo en `auth/login.php`, `auth/logout.php` y `app/portada_plantilla.php`, detrás del mismo interruptor: con `true` vuelven el login, la portada y el «Salir» de antes.


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
