<?php
// ============================================================
// ISORGA v16 — GUÍA DE ESTILO GLOBAL
// ============================================================
// Este archivo centraliza las clases CSS y variables de diseño
// usadas en toda la plataforma.
//
// USO: include '../app/style_guide.php';
//      Luego usar las variables directamente en HTML/PHP.
// ============================================================


// ============================================================
// CABECERA DE TARJETAS (card-header)
// ============================================================
// Clase estándar para cabeceras de cards en toda la plataforma.
// Usa bg-primary al 10% de opacidad: discreta en modo claro,
// coherente en modo oscuro. El texto hereda text-body automáticamente.

$CARD_HEADER        = 'bg-primary bg-opacity-10 border-bottom p-3';

// Variantes de cabecera para contextos específicos:
$CARD_HEADER_SUCCESS = 'bg-success bg-opacity-10 border-bottom p-3';  // Completado, aprobado
$CARD_HEADER_WARNING = 'bg-warning bg-opacity-10 border-bottom p-3';  // Atención, pendiente
$CARD_HEADER_DANGER  = 'bg-danger  bg-opacity-10 border-bottom p-3';  // Error, rechazado
$CARD_HEADER_INFO    = 'bg-info    bg-opacity-10 border-bottom p-3';  // Información


// ============================================================
// TARJETA BASE (card)
// ============================================================

$CARD_BASE = 'card border-0 shadow-sm';        // Card estándar
$CARD_FULL = 'card border-0 shadow-sm h-100';  // Card altura completa


// ============================================================
// TABLA ESTÁNDAR
// ============================================================

$TABLE_BASE   = 'table table-hover mb-0';      // Tabla sin margen inferior
$TABLE_HEADER = 'table-light';                  // Cabecera de tabla


// ============================================================
// BOTONES
// ============================================================

$BTN_PRIMARY   = 'btn btn-primary';
$BTN_SECONDARY = 'btn btn-outline-secondary';
$BTN_DANGER    = 'btn btn-outline-secondary text-danger';
$BTN_SM        = 'btn btn-sm btn-outline-primary';

// Boton "Imprimir / PDF" — estilo canonico de la plataforma (outline neutro).
// Ref.: 13/listadoVigor.php. Incluye el espaciado estandar m-1.
// Uso: en un <a target="_blank" a un _pdf.php>, class="[echo BTN_IMPRIMIR]" + texto IMPRIMIR.
$BTN_IMPRIMIR  = 'btn btn-outline-secondary m-1';


// ============================================================
// BADGES / ESTADOS
// ============================================================

$BADGE_OK      = 'badge bg-success';
$BADGE_PENDING = 'badge bg-warning text-body';
$BADGE_KO      = 'badge bg-danger';
$BADGE_INFO    = 'badge bg-info';
$BADGE_MUTED   = 'badge bg-secondary';


// ============================================================
// NOTAS DE USO
// ============================================================
//
// CABECERA CARD (más usada):
//   <div class="card-header CARD_HEADER">
//     <h6 class="mb-0 text-primary">
//       <i class="fas fa-icon me-2"></i> Título
//     </h6>
//   </div>
//
// CARD COMPLETA:
//   <div class="<? // echo $CARD_BASE ">
//     <div class="card-header <?= $CARD_HEADER "> ... </div>
//     <div class="card-body p-0">
//       <table class="<?= $TABLE_BASE "> ... </table>
//     </div>
//   </div>
//
// ============================================================
