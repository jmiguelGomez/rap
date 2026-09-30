<?php
declare(strict_types=1);

/** Adaptadores cerrados. Configuración nunca contiene SQL, rutas ni callbacks. */
final class IsorgiContextoFuentes
{
    public const CAMPOS = [
        'centro' => ['nombre'],
        'cuestionario' => ['q_actividad', 'q_productos', 'q_proposito', 'q_valores',
            'q_emplazamientos', 'q_procesos_subcontratacion', 'q_recursos',
            'q_clientes_mercado', 'q_cadena_suministro', 'q_certificaciones'],
        'organizacion' => ['unidades', 'puestos', 'mision', 'funcion', 'responsabilidad', 'autoridad'],
    ];

    public static function filas(PDO $pdo, string $sql, array $params = []): array
    {
        $s = $pdo->prepare($sql);
        if (!$s || !$s->execute($params)) throw new RuntimeException('consulta_contexto');
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function hecho(string $clave, string $categoria, array $etiquetas,
        string $texto, string $permiso, string $localizador, int $objeto = 0): array
    {
        $texto = trim(strip_tags($texto));
        if (class_exists('Normalizer')) $texto = Normalizer::normalize($texto, Normalizer::FORM_C) ?: $texto;
        if (mb_strlen($texto) > 16000) throw new LengthException('fuente_demasiado_larga');
        return ['clave' => $clave, 'categoria' => $categoria, 'etiquetas' => $etiquetas,
            'contenido' => $texto, 'permiso' => $permiso, 'localizador' => $localizador, 'objeto_id' => $objeto];
    }

    public static function etiquetas(string $es, string $en, string $fr, string $ca): array
    {
        return compact('es', 'en', 'fr', 'ca');
    }

    public static function cargar(PDO $pdo, int $centro, string $codigo, array $campos): array
    {
        if ($centro <= 0 || !isset(self::CAMPOS[$codigo]) || array_diff($campos, self::CAMPOS[$codigo])) {
            throw new InvalidArgumentException('fuente_invalida');
        }
        $out = [];
        if ($codigo === 'centro' && in_array('nombre', $campos, true)) {
            $r = self::filas($pdo, 'SELECT centroNombre FROM centros WHERE centroId=? AND centroActivo=1', [$centro]);
            if (!$r) throw new DomainException('centro_inactivo');
            $out[] = self::hecho('perfil.nombre', 'perfil', self::etiquetas('Organización', 'Organization', 'Organisation', 'Organització'),
                $r[0]['centroNombre'], 'centro', 'centros.centroNombre');
        }
        if ($codigo === 'cuestionario') {
            $r = self::filas($pdo, 'SELECT respuestas FROM 12_contexto_cuestionario WHERE centro=?', [$centro]);
            $respuestas = $r ? json_decode($r[0]['respuestas'], true, 32, JSON_THROW_ON_ERROR) : [];
            $preguntas = self::filas($pdo, 'SELECT codigo,pregunta_es,pregunta_en,pregunta_fr,pregunta_ca FROM 12_contexto_preguntas WHERE activo=1 ORDER BY codigo');
            foreach ($preguntas as $p) {
                $c = $p['codigo'];
                if (!in_array($c, $campos, true)) continue;
                $valor = $respuestas[$c] ?? '';
                if (!is_string($valor)) throw new UnexpectedValueException('respuesta_invalida');
                if (trim($valor) === '') continue;
                $out[] = self::hecho('perfil.' . substr($c, 2), 'perfil',
                    self::etiquetas($p['pregunta_es'], $p['pregunta_en'], $p['pregunta_fr'], $p['pregunta_ca']),
                    $valor, 'cuestionario', '12_contexto_cuestionario.respuestas.' . $c);
            }
        }
        if ($codigo === 'organizacion') {
            if (in_array('unidades', $campos, true)) {
                $unidades = self::filas($pdo,
                    'SELECT u.id,u.nombre,p.nombre AS superior FROM 21_unidades u
                     LEFT JOIN 21_unidades p ON p.id=u.padre_id AND p.centro=u.centro AND p.activo=1
                     WHERE u.centro=? AND u.activo=1 ORDER BY u.id', [$centro]);
                foreach ($unidades as $u) {
                    $out[] = self::hecho('unidad.' . $u['id'], 'organizacion',
                        self::etiquetas('Unidad organizativa', 'Organizational unit', 'Unité organisationnelle', 'Unitat organitzativa'),
                        $u['nombre'] . ($u['superior'] ? ' → ' . $u['superior'] : ''), 'organizacion', '21_unidades:' . $u['id']);
                }
            }
            $puestos = self::filas($pdo,
                'SELECT p.id,p.txtPuesto,u.nombre AS unidad,s.txtPuesto AS superior
                 FROM puestos p LEFT JOIN 21_puestos_organizacion o ON o.puesto_id=p.id
                 LEFT JOIN 21_unidades u ON u.id=o.unidad_id AND u.centro=p.idCentro AND u.activo=1
                 LEFT JOIN puestos s ON s.id=o.puesto_superior_id AND s.idCentro=p.idCentro AND s.general=0
                 WHERE p.idCentro=? AND p.general=0 ORDER BY p.id', [$centro]);
            if (count($puestos) > 1000) throw new LengthException('demasiados_puestos');
            $nombres = array_column($puestos, 'txtPuesto', 'id');
            foreach ($puestos as $p) {
                $id = (int) $p['id'];
                if (in_array('puestos', $campos, true)) {
                    $out[] = self::hecho('puesto.' . $id, 'organizacion',
                        self::etiquetas('Puesto / unidad / superior', 'Position / unit / manager', 'Poste / unité / supérieur', 'Lloc / unitat / superior'),
                        implode(' | ', array_filter([$p['txtPuesto'], $p['unidad'], $p['superior']])),
                        'organizacion', 'puestos:' . $id, $id);
                }
            }
            // Solo revisiones vigentes: no aprender de propuestas de ISORGI sin publicar.
            $versiones = self::filas($pdo,
                "SELECT v.id,v.puesto_id,v.mision,v.version FROM 21_puesto_versiones v
                 JOIN puestos p ON p.id=v.puesto_id WHERE p.idCentro=? AND p.general=0 AND v.estado='vigente'
                 ORDER BY v.puesto_id,v.version", [$centro]);
            $itemsPorVersion = [];
            foreach (self::filas($pdo,
                "SELECT i.id,i.version_id,i.tipo,i.contenido FROM 21_puesto_version_items i
                 JOIN 21_puesto_versiones v ON v.id=i.version_id JOIN puestos p ON p.id=v.puesto_id
                 WHERE p.idCentro=? AND p.general=0 AND v.estado='vigente' ORDER BY i.version_id,i.tipo,i.orden,i.id",
                [$centro]) as $item) $itemsPorVersion[$item['version_id']][] = $item;
            foreach ($versiones as $v) {
                if (in_array('mision', $campos, true) && trim($v['mision'] ?? '') !== '') {
                    $out[] = self::hecho('puesto.' . $v['puesto_id'] . '.mision', 'organizacion',
                        array_map(static fn($t) => $t . ' · ' . $nombres[$v['puesto_id']],
                            self::etiquetas('Misión del puesto', 'Position purpose', 'Mission du poste', 'Missió del lloc')),
                        $v['mision'], 'organizacion', '21_puesto_versiones:' . $v['id'], (int) $v['puesto_id']);
                }
                foreach ($itemsPorVersion[$v['id']] ?? [] as $i) {
                    if (!in_array($i['tipo'], $campos, true)) continue;
                    $etiquetas = match ($i['tipo']) {
                        'funcion' => self::etiquetas('Funciones', 'Functions', 'Fonctions', 'Funcions'),
                        'responsabilidad' => self::etiquetas('Responsabilidades', 'Responsibilities', 'Responsabilités', 'Responsabilitats'),
                        'autoridad' => self::etiquetas('Autoridad', 'Authority', 'Autorité', 'Autoritat'),
                    };
                    $out[] = self::hecho('puesto.' . $v['puesto_id'] . '.' . $i['tipo'] . '.' . $i['id'], 'organizacion',
                        array_map(static fn($t) => $t . ' · ' . $nombres[$v['puesto_id']], $etiquetas),
                        $i['contenido'], 'organizacion', '21_puesto_version_items:' . $i['id'], (int) $v['puesto_id']);
                }
            }
        }
        if (count($out) > 5000) throw new LengthException('fuente_demasiado_grande');
        return $out;
    }
}
