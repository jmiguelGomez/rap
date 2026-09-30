# rap.isorga.com — Envases y RAP

Aplicación propia para el **módulo 42 de ISORGA** («Envases y RAP»). Generada con
`isorga-net/scripts/satelite_crear.php` desde el manifiesto `isorga-net/scripts/satelites/rap.php`,
a partir del molde de `check.isorga.com` (2026-09-30).

## Lo que hay que saber antes de tocar nada

🔴 **La base de datos es la de ISORGA (`isorga`), con su mismo usuario MySQL.** No es una copia: lo que se
graba aquí se ve en `isorga.com/42/` y al revés. Son **dos puertas sobre los mismos datos**, no dos aplicaciones.

🔴 **La identidad también es la de ISORGA.** La tabla `usuarios` es la real: aquí no hay recuperación de
contraseña (`auth/recuperar.php` manda a isorga.com). La puerta es **`usuariosxcentros.modulo42 >= 1`** en un
centro **activo**, y se revalida en cada petición (`app/sesion.php`).

🔴 **El centro se elige, nunca viaja por la URL.** `auth/centro.php` ofrece solo los centros donde esa persona
tiene el módulo. Si tiene uno, entra directo; si no tiene ninguno, fuera.

🔴 **Las pantallas son COPIAS de `isorga-net/42/`**, traídas por `scripts/rap_portar.php` (repetible; `--ver`
es el comprobador de espejo: sale con 2 si la copia difiere). Un arreglo del módulo se hace en ISORGA y se
vuelve a portar; aquí no se retocan a mano.

## Instalación desde cero

```bash
cp app/config.example.php app/config.php     # base isorga + sso_secret propio
chmod 640 app/config.php
# vhost: /etc/nginx/sites-available/rap.isorga.com (SYMLINK en sites-enabled, no copia)
sudo certbot --nginx -d rap.isorga.com
php scripts/rap_portar.php && php scripts/lang_extraer.php
```

En ISORGA hace falta `RAP_SSO_SECRET` en `app/config/.env` con **el mismo valor** que `sso_secret` de aquí,
y la puerta `0/rap_entrar.php`. 🔴 En ese `.env` **solo `CLAVE=valor`**: un comentario con `#` tumbó toda
la web de ISORGA durante tres minutos el 2026-09-29.

## Comprobación

```
http://  → 301 · / → 200 (portada pública) · /auth/login.php → 200
/app/config.php · /DOCS/ · /database/ → 403 · /DOCS/1858/x.php → 403 (no se ejecuta)
/rap/dashboard.php sin sesión → 302 al login
```

Y el ciclo sin contraseña: enlace firmado desde `isorga.com/0/rap_entrar.php` → entra · el mismo enlace por
segunda vez → 404 · firma alterada → 404 · caducado → 404 · centro sin permiso → a elegir centro.
