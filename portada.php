<?php
// Portada pública de rap.isorga.com. Los textos salen de isorga-net/scripts/satelites/rap.php (satelite_crear.php).
$landing = array (
  'nombre' => 'Envases y RAP',
  'etiqueta' => 'Responsabilidad ampliada del productor de envases',
  'descripcion' => 'Software de ISORGA para la responsabilidad ampliada del productor de envases (RD 1055/2022, Ley 7/2022 y Reglamento (UE) 2025/40): inventario de envases por material y peso, lo puesto en el mercado y la declaración anual.',
  'ceja' => 'RAPisorga · envases, materiales y declaración',
  'titular' => 'Sabes qué envases pones en el mercado.',
  'titular_acento' => 'Ahora también cuánto pesan.',
  'intro' => 'El RD 1055/2022 pide al productor saber, por material y por peso, cada envase que pone en el mercado y declararlo cada año. RAPisorga lo lleva desde la ficha del envase hasta la declaración, con la trazabilidad que pide el SCRAP.',
  'chips' => 
  array (
    0 => 
    array (
      0 => 'bi bi-box-seam',
      1 => 'Inventario de envases',
    ),
    1 => 
    array (
      0 => 'bi bi-layers',
      1 => 'Materiales y pesos por unidad',
    ),
    2 => 
    array (
      0 => 'bi bi-graph-up',
      1 => 'Puesto en el mercado por periodo',
    ),
    3 => 
    array (
      0 => 'bi bi-file-earmark-text',
      1 => 'Declaración anual',
    ),
  ),
  'seccion_ceja' => 'Qué es',
  'seccion_titulo' => 'La declaración de envases sale de lo que ya vendes.',
  'seccion_texto' => 'RAPisorga parte de tus envases —primarios, secundarios y terciarios— con su composición por material y su peso, y los cruza con lo que pones en el mercado en cada periodo. La declaración anual y los informes por material salen de ahí, sin rehacer hojas de cálculo.',
  'pilares' => 
  array (
    0 => 
    array (
      0 => 'Envases',
      1 => 'Cada envase con sus componentes, materiales y pesos.',
    ),
    1 => 
    array (
      0 => 'Mercado',
      1 => 'Unidades puestas en el mercado por periodo y cliente.',
    ),
    2 => 
    array (
      0 => 'Declaración',
      1 => 'Totales por material listos para el SCRAP y la declaración anual.',
    ),
  ),
  'flujo_titulo' => 'Del envase a la declaración.',
  'flujo_intro' => 'Se describe el envase una vez. Cada periodo solo se registran las unidades.',
  'pasos' => 
  array (
    0 => 
    array (
      0 => 'Describe',
      1 => 'Componentes, materiales y pesos de cada envase.',
    ),
    1 => 
    array (
      0 => 'Registra',
      1 => 'Unidades puestas en el mercado por periodo.',
    ),
    2 => 
    array (
      0 => 'Declara',
      1 => 'Totales por material y la declaración anual.',
    ),
  ),
  'marca' => 'RAPisorga',
  'url' => 'https://rap.isorga.com/',
);
require __DIR__ . '/app/portada_plantilla.php';
