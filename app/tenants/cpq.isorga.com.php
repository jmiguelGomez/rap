<?php
/**
 * Tenant: CPQ
 * Dominio: cpq.isorga.com
 * tenant_id / distribuidor.id = 3
 */
return [
    // ── Identidad ──────────────────────────────────────────────────────
    'tenant_id'              => 3,
    'nombre'                 => 'CPQ',
    'dominio'                => 'cpq.isorga.com',

    // ── Idioma y locale ────────────────────────────────────────────────
    'idioma_defecto'         => 'es',
    'idiomas_activos'        => ['es', 'en', 'ca', 'fr'],
    'timezone'               => 'Europe/Madrid',
    'locale'                 => 'es_ES',

    // ── Branding ───────────────────────────────────────────────────────
    'logo_app'               => 'logo.png',
    'logo_email'             => 'logo_email.png',
    'favicon'                => 'favicon.ico',
    'fondo_login'            => 'fondo_login.jpg',
    'color_primary'          => '#0ea5e9',
    'color_secondary'        => '#14b8a6',

    // ── Firma de IA en el login (v16.729) ──────────────────────────────
    // 🔴 Solo en los dominios con marca ISORGA. En un tenant de marca
    //    blanca, poner la mascota y el nombre ISORGI delata al proveedor
    //    del software — es la misma razón por la que los correos con
    //    credenciales usan isorga_mail_credenciales() y no la plantilla
    //    con cabecera de ISORGA (CLAUDE.md §28.1).
    'firma_ia'               => false,

    // ── Contacto y soporte ─────────────────────────────────────────────
    'email_soporte'          => 'soporte@isorga.com',
    'email_remitente'        => 'noreply@isorga.com',
    'email_remitente_nombre' => 'CPQ',
    'telefono'               => '',

    // ── Datos fiscales / legales ───────────────────────────────────────
    'razon_social'           => 'CPQ',
    'cif'                    => '',
    'direccion'              => '',

    // ── Base de datos ──────────────────────────────────────────────────
    'db_host'                => null,
    'db_name'                => null,
    'db_user'                => null,
    'db_pass'                => null,
];