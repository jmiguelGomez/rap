<?php
/**
 * Tenant: ISORGA Maroc — dominio nacional (operado por SIM Consulting)
 * Dominio: isorga.ma
 * tenant_id / distribuidor.id = 6 (SIM Consulting)
 *
 * Este archivo es un ALIAS técnico que enruta isorga.ma al distribuidor 6,
 * gemelo de ma.isorga.com. El dominio canónico del distribuidor 6 sigue
 * siendo sim-consulting.net. Aquí aplicamos branding ISORGA para el mercado marroquí.
 */
return [
    // ── Identidad ──────────────────────────────────────────────────────
    'tenant_id'              => 6,
    'nombre'                 => 'MAROC - ISORGA',
    'dominio'                => 'isorga.ma',

    // ── Idioma y locale ────────────────────────────────────────────────
    'idioma_defecto'         => 'fr',
    'idiomas_activos'        => ['fr', 'es', 'en'],
    'timezone'               => 'Africa/Casablanca',
    'locale'                 => 'fr_MA',

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
    'firma_ia'               => true,

    // ── Contacto y soporte ─────────────────────────────────────────────
    'email_soporte'          => 'soporte@isorga.com',
    'email_remitente'        => 'noreply@isorga.com',
    'email_remitente_nombre' => 'ISORGA',
    'telefono'               => '',

    // ── Datos fiscales / legales ───────────────────────────────────────
    'razon_social'           => 'SIM Consulting',
    'cif'                    => '',
    'direccion'              => '',

    // ── Base de datos ──────────────────────────────────────────────────
    'db_host'                => null,
    'db_name'                => null,
    'db_user'                => null,
    'db_pass'                => null,
];
