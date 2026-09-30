<?php
/**
 * Tenant base / fallback.
 *
 * Se carga cuando el dominio HTTP no coincide con ningún tenant
 * configurado. Apunta al tenant principal (id=1, España).
 *
 * También sirve como documentación de todos los campos disponibles.
 */
return [
    // ── Identidad ──────────────────────────────────────────────────────
    'tenant_id'              => 1,               // FK a distribuidor.id
    'nombre'                 => 'ISORGA',
    'dominio'                => 'isorga.com',

    // ── Idioma y locale ────────────────────────────────────────────────
    'idioma_defecto'         => 'es',            // Código ISO 639-1
    'idiomas_activos'        => ['es', 'en', 'ca', 'fr'],    // Idiomas disponibles en este tenant
    'timezone'               => 'Europe/Madrid',
    'locale'                 => 'es_ES',

    // ── Branding ───────────────────────────────────────────────────────
    // Rutas relativas a assets/tenants/{tenant_id}/
    'logo_app'               => 'logo.png',
    'logo_email'             => 'logo_email.png',
    'favicon'                => 'favicon.ico',
    'fondo_login'            => 'fondo_login.jpg',

    // Colores CSS (se inyectan como variables CSS en login.php y head.php)
    'color_primary'          => '#0ea5e9',
    'color_secondary'        => '#14b8a6',

    // ── Firma de IA en el login (v16.729) ──────────────────────────────
    // 🔴 Solo en los dominios con marca ISORGA. En un tenant de marca
    //    blanca, poner la mascota y el nombre ISORGI delata al proveedor
    //    del software — es la misma razón por la que los correos con
    //    credenciales usan isorga_mail_credenciales() y no la plantilla
    //    con cabecera de ISORGA (CLAUDE.md §28.1).
    // 🔴 En el FALLBACK va a false, y es deliberado: `_base.php` lo usa
    //    cualquier dominio que NO tenga fichero propio. Si no sabemos de
    //    quién es la marca, no se firma. Falla cerrado.
    'firma_ia'               => false,

    // ── Contacto y soporte ─────────────────────────────────────────────
    'email_soporte'          => 'soporte@isorga.com',
    'email_remitente'        => 'noreply@isorga.com',
    'email_remitente_nombre' => 'ISORGA',
    'telefono'               => '',

    // ── Datos fiscales / legales ───────────────────────────────────────
    'razon_social'           => 'ISORGA',
    'cif'                    => '',
    'direccion'              => '',

    // ── Base de datos ──────────────────────────────────────────────────
    // null = usar credenciales del .env global
    // Rellenar solo si este tenant tiene BD propia
    'db_host'                => null,
    'db_name'                => null,
    'db_user'                => null,
    'db_pass'                => null,
];
