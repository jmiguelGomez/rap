<?php

/**
 * Tenant: SIM-Consulting (Francia)
 * Dominio: sim-consulting.net  (y cualquier subdominio sin www)
 * tenant_id / distribuidor.id = 6
 */
return [
    // ── Identidad ──────────────────────────────────────────────────────
    'tenant_id'              => 6,
    'nombre'                 => 'SIM-Consulting',
    'dominio'                => 'sim-consulting.net',

    // ── Idioma y locale ────────────────────────────────────────────────
    'idioma_defecto'         => 'fr',
    'idiomas_activos'        => ['fr', 'en', 'es'],
    'timezone'               => 'Europe/Paris',
    'locale'                 => 'fr_FR',

    // ── Branding ───────────────────────────────────────────────────────
    // Los assets van en assets/tenants/6/
    // Mientras no existan, el código hace fallback a assets/tenants/1/
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
    'email_soporte'          => 'support@sim-consulting.net',
    'email_remitente'        => 'noreply@sim-consulting.net',
    'email_remitente_nombre' => 'SIM-Consulting',
    'telefono'               => '',

    // ── Datos fiscales / legales ───────────────────────────────────────
    'razon_social'           => 'SIM-Consulting',
    'cif'                    => '',   // SIRET / SIREN francés
    'direccion'              => '',

    // ── Base de datos ──────────────────────────────────────────────────
    'db_host'                => null,  // BD compartida con España
    'db_name'                => null,
    'db_user'                => null,
    'db_pass'                => null,
];
