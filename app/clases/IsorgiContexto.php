<?php
declare(strict_types=1);
require_once __DIR__ . '/IsorgiContextoFuentes.php';

/** Memoria persistente. PDO de Conectar; ninguna llamada a IA en fase 1. */
final class IsorgiContexto
{
    public function __construct(private PDO $pdo) {}

    public static function accesoGeneral(int $usuario, int $centro): bool
    {
        // Apertura general: el llamador conserva permisos y centro de sesión.
        return $usuario > 0 && $centro > 0;
    }

    public static function json(mixed $valor): string
    {
        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function filas(string $sql, array $params = []): array
    {
        return IsorgiContextoFuentes::filas($this->pdo, $sql, $params);
    }

    private function ejecutar(string $sql, array $params = []): int
    {
        $s = $this->pdo->prepare($sql);
        if (!$s || !$s->execute($params)) throw new RuntimeException('escritura_contexto');
        return $s->rowCount();
    }

    public function disponible(): bool
    {
        $r = $this->filas("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME IN ('isorgi_contexto_reglas','isorgi_contexto_fuentes','isorgi_contexto_hechos',
            'isorgi_contexto_evidencias','isorgi_contexto_eventos','isorgi_contexto_trabajos')");
        return (int) $r[0]['n'] === 6;
    }

    public static function validarConfig(array $config): array
    {
        if (!is_bool($config['habilitado'] ?? null) || !is_array($config['fuentes'] ?? null)
            || array_diff(array_keys($config), ['habilitado','fuentes'])
            || array_diff(array_keys($config['fuentes']), array_keys(IsorgiContextoFuentes::CAMPOS))) {
            throw new InvalidArgumentException('config_invalida');
        }
        $out = ['habilitado' => $config['habilitado'], 'fuentes' => []];
        foreach (IsorgiContextoFuentes::CAMPOS as $codigo => $permitidos) {
            $r = $config['fuentes'][$codigo] ?? null;
            if (!is_array($r) || !is_bool($r['activo'] ?? null) || !is_int($r['prioridad'] ?? null)
                || $r['prioridad'] < 1 || $r['prioridad'] > 999 || !is_array($r['campos'] ?? null)
                || array_diff(array_keys($r), ['activo','prioridad','campos'])) throw new InvalidArgumentException('regla_invalida');
            foreach ($r['campos'] as $c) {
                if (!is_string($c) || !in_array($c, $permitidos, true)) throw new InvalidArgumentException('campo_invalido');
            }
            $r['campos'] = array_values(array_intersect($permitidos, $r['campos']));
            if ($r['activo'] && !$r['campos']) throw new InvalidArgumentException('regla_vacia');
            $out['fuentes'][$codigo] = $r;
        }
        return $out;
    }

    public function reglas(): array
    {
        $r = $this->filas('SELECT version,config,creado_en FROM isorgi_contexto_reglas ORDER BY version DESC LIMIT 1');
        if (!$r) throw new RuntimeException('sin_reglas');
        return ['version' => (int) $r[0]['version'], 'config' => self::validarConfig(json_decode($r[0]['config'], true, 32, JSON_THROW_ON_ERROR)),
            'creado_en' => $r[0]['creado_en']];
    }

    private function bloquear(string $nombre): void
    {
        $r = $this->filas('SELECT GET_LOCK(?,0) AS ok', [$nombre]);
        if ((int) $r[0]['ok'] !== 1) throw new RuntimeException('ocupado');
    }

    private function liberar(string $nombre): void
    {
        $this->filas('SELECT RELEASE_LOCK(?)', [$nombre]);
    }

    public function publicarReglas(array $config, int $esperada, int $usuario): int
    {
        if ($usuario !== 7) throw new DomainException('sin_acceso');
        $config = self::validarConfig($config);
        $this->bloquear('isorgi_contexto_reglas');
        try {
            $actual = $this->reglas();
            if ($actual['version'] !== $esperada) throw new DomainException('edicion_concurrente');
            $version = $esperada + 1;
            $this->ejecutar('INSERT INTO isorgi_contexto_reglas(version,config,creado_por) VALUES(?,?,?)',
                [$version, self::json($config), $usuario]);
            return $version;
        } finally { $this->liberar('isorgi_contexto_reglas'); }
    }

    private function centroActivo(int $centro): void
    {
        if ($centro <= 0 || !$this->filas('SELECT centroId FROM centros WHERE centroId=? AND centroActivo=1', [$centro])) {
            throw new DomainException('centro_inactivo');
        }
    }

    private function recoger(int $centro, array $reglas, ?array $permisos = null): array
    {
        $out = [];
        foreach ($reglas['config']['fuentes'] as $codigo => $r) {
            if ($permisos !== null && empty($permisos[$codigo])) continue;
            $hechos = $r['activo'] ? IsorgiContextoFuentes::cargar($this->pdo, $centro, $codigo, $r['campos']) : [];
            $out[$codigo] = ['hechos' => $hechos, 'huella' => hash('sha256', self::json([$r, $hechos]))];
        }
        return $out;
    }

    public function evento(int $centro, string $tipo, ?int $usuario, array $datos): int
    {
        $this->ejecutar('INSERT INTO isorgi_contexto_eventos(centro,tipo,usuario_id,datos) VALUES(?,?,?,?)',
            [$centro, $tipo, $usuario, self::json($datos)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function historial(int $centro): array
    {
        $this->centroActivo($centro);
        return $this->filas('SELECT id,tipo,usuario_id,datos,creado_en FROM isorgi_contexto_eventos WHERE centro=? ORDER BY id DESC LIMIT 50', [$centro]);
    }

    private function insertarHecho(int $fuente, array $h, int $prioridad, string $huella, int $version): int
    {
        $this->ejecutar('INSERT INTO isorgi_contexto_hechos(fuente_id,clave,categoria,etiquetas,contenido,permiso,objeto_id,prioridad)
            VALUES(?,?,?,?,?,?,?,?)', [$fuente, $h['clave'], $h['categoria'], self::json($h['etiquetas']),
            $h['contenido'], $h['permiso'], $h['objeto_id'], $prioridad]);
        $id = (int) $this->pdo->lastInsertId();
        $this->ejecutar('INSERT INTO isorgi_contexto_evidencias(hecho_id,localizador,huella,version_reglas) VALUES(?,?,?,?)',
            [$id, $h['localizador'], $huella, $version]);
        return $id;
    }

    /** Reconciliación por centro activo, sin LLM. --dry-run no escribe. */
    public function actualizar(int $centro, bool $dryRun = true): array
    {
        $this->centroActivo($centro);
        $reglas = $this->reglas();
        if ($dryRun) {
            $fuentes = $this->recoger($centro, $reglas);
            return ['centro' => $centro, 'version_reglas' => $reglas['version'], 'dry_run' => true,
                'fuentes' => array_map(static fn($f) => count($f['hechos']), $fuentes)];
        }
        $lock = 'isorgi_contexto_' . $centro;
        $this->bloquear($lock);
        try {
            $this->ejecutar("INSERT INTO isorgi_contexto_trabajos(centro,estado,intentos) VALUES(?,'procesando',1)
                ON DUPLICATE KEY UPDATE estado='procesando',intentos=intentos+1,error_codigo=NULL", [$centro]);
            if (!$this->pdo->beginTransaction()) throw new RuntimeException('transaccion_contexto');
            $fuentes = $this->recoger($centro, $reglas);
            $cambios = 0;
            foreach ($fuentes as $codigo => $f) {
                $existente = $this->filas('SELECT id,huella FROM isorgi_contexto_fuentes WHERE centro=? AND codigo=? AND referencia=?',
                    [$centro, $codigo, 'centro']);
                if ($existente && hash_equals($existente[0]['huella'], $f['huella'])) {
                    $this->ejecutar('UPDATE isorgi_contexto_fuentes SET revisado_en=NOW(),version_reglas=? WHERE id=? AND centro=?',
                        [$reglas['version'], $existente[0]['id'], $centro]);
                    continue;
                }
                if ($existente) {
                    $fuente = (int) $existente[0]['id'];
                    $this->ejecutar('UPDATE isorgi_contexto_hechos h JOIN isorgi_contexto_fuentes f ON f.id=h.fuente_id
                        SET h.vigente=0 WHERE f.id=? AND f.centro=? AND h.vigente=1', [$fuente, $centro]);
                    $this->ejecutar('UPDATE isorgi_contexto_fuentes SET huella=?,version_reglas=?,revisado_en=NOW() WHERE id=? AND centro=?',
                        [$f['huella'], $reglas['version'], $fuente, $centro]);
                } else {
                    $this->ejecutar('INSERT INTO isorgi_contexto_fuentes(centro,codigo,referencia,huella,version_reglas) VALUES(?,?,?,?,?)',
                        [$centro, $codigo, 'centro', $f['huella'], $reglas['version']]);
                    $fuente = (int) $this->pdo->lastInsertId();
                }
                foreach ($f['hechos'] as $h) $this->insertarHecho($fuente, $h, $reglas['config']['fuentes'][$codigo]['prioridad'], $f['huella'], $reglas['version']);
                $this->evento($centro, 'fuente_actualizada', null, ['fuente_id' => $fuente, 'huella' => $f['huella'], 'version_reglas' => $reglas['version'], 'hechos' => count($f['hechos'])]);
                $cambios++;
            }
            $this->ejecutar("UPDATE isorgi_contexto_trabajos SET estado='ok',version_reglas=?,revision=revision+?,
                intentos=0,revisado_en=NOW(),completado_en=NOW(),error_codigo=NULL WHERE centro=?",
                [$reglas['version'], $cambios > 0 ? 1 : 0, $centro]);
            if (!$this->pdo->commit()) throw new RuntimeException('commit_contexto');
            return ['centro' => $centro, 'cambios' => $cambios, 'version_reglas' => $reglas['version']];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->ejecutar("UPDATE isorgi_contexto_trabajos SET estado='error',revisado_en=NOW(),error_codigo='actualizacion_fallida' WHERE centro=?", [$centro]);
            throw $e;
        } finally { $this->liberar($lock); }
    }

    /** Pura: filtra ANTES de resolver conflictos; nunca usa datos no autorizados. */
    public static function resolver(array $hechos, array $permisos): array
    {
        $grupos = [];
        foreach ($hechos as $h) {
            if (empty($permisos[$h['permiso']]) || empty($h['vigente'])) continue;
            $grupos[$h['clave']][] = $h;
        }
        $aceptados = []; $conflictos = [];
        ksort($grupos);
        foreach ($grupos as $clave => $filas) {
            usort($filas, static fn($a, $b) => ((int) $b['prioridad'] <=> (int) $a['prioridad']) ?: ((int) $a['id'] <=> (int) $b['id']));
            $primero = $filas[0]; $empate = false;
            foreach (array_slice($filas, 1) as $otro) {
                if (trim($otro['contenido']) === trim($primero['contenido'])) continue;
                $esEmpate = (int) $otro['prioridad'] === (int) $primero['prioridad'];
                $empate = $empate || $esEmpate;
                $conflictos[] = ['clave' => $clave, 'ids' => [(int) $primero['id'], (int) $otro['id']], 'empate' => $esEmpate];
            }
            if (!$empate) $aceptados[] = $primero;
        }
        return ['hechos' => $aceptados, 'conflictos' => $conflictos];
    }

    public function leer(int $centro, array $permisos): array
    {
        $this->centroActivo($centro);
        if (!$this->disponible()) return ['estado' => 'sin_esquema', 'hechos' => [], 'conflictos' => []];
        $reglas = $this->reglas();
        $trabajo = $this->filas('SELECT * FROM isorgi_contexto_trabajos WHERE centro=?', [$centro])[0] ?? null;
        $vivas = $this->recoger($centro, $reglas, $permisos);
        $ambitos = array_values(array_filter(array_keys(IsorgiContextoFuentes::CAMPOS), static fn($c) => !empty($permisos[$c])));
        $placeholders = implode(',', array_fill(0, count($ambitos), '?'));
        $filas = $ambitos ? $this->filas('SELECT h.*,f.codigo,f.huella AS huella_actual,e.huella,e.localizador,e.version_reglas
            FROM isorgi_contexto_hechos h JOIN isorgi_contexto_fuentes f ON f.id=h.fuente_id
            JOIN isorgi_contexto_evidencias e ON e.hecho_id=h.id WHERE f.centro=? AND h.vigente=1
            AND h.permiso IN (' . $placeholders . ') ORDER BY h.id', array_merge([$centro], $ambitos)) : [];
        $validos = []; $pendientes = [];
        $guardadas = $this->filas('SELECT codigo,huella FROM isorgi_contexto_fuentes WHERE centro=? AND referencia=?', [$centro, 'centro']);
        $hashes = array_column($guardadas, 'huella', 'codigo');
        foreach ($vivas as $codigo => $f) {
            if (!isset($hashes[$codigo]) || !hash_equals($hashes[$codigo], $f['huella'])) $pendientes[] = $codigo;
        }
        foreach ($filas as $h) {
            if (empty($permisos[$h['permiso']])) continue;
            if ($h['codigo'] !== 'manual' && (!isset($vivas[$h['codigo']])
                || !hash_equals($h['huella'], $vivas[$h['codigo']]['huella']))) continue;
            $h['etiquetas'] = json_decode($h['etiquetas'], true, 16, JSON_THROW_ON_ERROR);
            $validos[] = $h;
        }
        $resultado = self::resolver($validos, $permisos);
        $resultado += ['estado' => $pendientes ? 'pendiente' : ($resultado['hechos'] ? 'ok' : 'vacio'),
            'habilitado' => $reglas['config']['habilitado'], 'version_reglas' => $reglas['version'],
            'revision' => (int) ($trabajo['revision'] ?? 0), 'revisado_en' => $trabajo['completado_en'] ?? null,
            'trabajo' => $trabajo, 'pendientes' => $pendientes, 'evidencias' => $validos];
        return $resultado;
    }

    /** Corrección explícita, optimistic locking por id vigente; historial inmutable. */
    public function corregir(int $centro, int $usuario, string $clave, string $etiqueta, string $texto,
        string $permiso, string $motivo, int $esperado, bool $retirar = false): int
    {
        $this->centroActivo($centro);
        if ($usuario !== 7 || !preg_match('/^[a-z][a-z0-9_.]{1,119}$/D', $clave)
            || !in_array($permiso, ['centro','cuestionario','organizacion'], true)
            || trim($motivo) === '' || mb_strlen($motivo) > 1000 || mb_strlen($etiqueta) > 250
            || (!$retirar && (trim($etiqueta) === '' || trim($texto) === ''))) throw new InvalidArgumentException('correccion_invalida');
        $lock = 'isorgi_contexto_' . $centro;
        $this->bloquear($lock);
        try {
            if (!$this->pdo->beginTransaction()) throw new RuntimeException('transaccion_contexto');
            $reglas = $this->reglas();
            $anteriores = $this->filas('SELECT h.id,h.permiso,h.objeto_id,h.categoria,h.vigente,f.codigo,f.id AS fuente_id
                FROM isorgi_contexto_hechos h JOIN isorgi_contexto_fuentes f ON f.id=h.fuente_id
                WHERE f.centro=? AND h.clave=? ORDER BY h.id', [$centro, $clave]);
            if (!$anteriores && !str_starts_with($clave, 'perfil.')) throw new DomainException('clave_manual_invalida');
            $manual = null; $objeto = 0; $categoria = 'perfil';
            foreach ($anteriores as $a) {
                if ($a['permiso'] !== $permiso) throw new DomainException('permiso_fuente');
                $objeto = (int) $a['objeto_id']; $categoria = $a['categoria'];
                if ($a['codigo'] === 'manual' && (int) $a['vigente'] === 1) $manual = $a;
            }
            if ((int) ($manual['id'] ?? 0) !== $esperado) throw new DomainException('edicion_concurrente');
            if ($retirar && !$manual) throw new DomainException('sin_correccion');
            $f = $this->filas('SELECT id FROM isorgi_contexto_fuentes WHERE centro=? AND codigo=? AND referencia=?', [$centro, 'manual', $clave]);
            $huella = hash('sha256', self::json([$clave, $texto, $motivo, $esperado]));
            if (!$f) {
                $this->ejecutar('INSERT INTO isorgi_contexto_fuentes(centro,codigo,referencia,huella,version_reglas) VALUES(?,?,?,?,?)',
                    [$centro, 'manual', $clave, $huella, $reglas['version']]);
                $fuente = (int) $this->pdo->lastInsertId();
            } else {
                $fuente = (int) $f[0]['id'];
                $this->ejecutar('UPDATE isorgi_contexto_fuentes SET huella=?,version_reglas=?,revisado_en=NOW() WHERE id=? AND centro=?',
                    [$huella, $reglas['version'], $fuente, $centro]);
            }
            if ($manual) $this->ejecutar('UPDATE isorgi_contexto_hechos h JOIN isorgi_contexto_fuentes f ON f.id=h.fuente_id
                SET h.vigente=0 WHERE h.id=? AND f.centro=? AND h.vigente=1', [$esperado, $centro]);
            $id = 0;
            if (!$retirar) {
                $h = IsorgiContextoFuentes::hecho($clave, $categoria,
                    array_fill_keys(['es','en','fr','ca'], trim($etiqueta)), $texto, $permiso, 'soporte', $objeto);
                $id = $this->insertarHecho($fuente, $h, 1000, $huella, $reglas['version']);
            }
            $this->evento($centro, $retirar ? 'correccion_retirada' : 'correccion', $usuario,
                ['hecho_id' => $id, 'anterior_id' => $esperado, 'clave' => $clave, 'motivo' => trim($motivo)]);
            $this->ejecutar('UPDATE isorgi_contexto_trabajos SET revision=revision+1 WHERE centro=?', [$centro]);
            if (!$this->pdo->commit()) throw new RuntimeException('commit_contexto');
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } finally { $this->liberar($lock); }
    }

    public static function permisosSesion(): array
    {
        require_once __DIR__ . '/../../rrhh/rap_lib.php';
        return ['centro' => (int) ($_SESSION['centro']['modulo0'] ?? 0) >= 1,
            'cuestionario' => (int) ($_SESSION['centro']['modulo12'] ?? 0) >= 1,
            'organizacion' => m21_rap_nivel_modulo() >= 1 && m21_rap_nivel_area('puestos', true) >= 1];
    }

    /** Perfil siempre; para puestos añade estructura y datos del puesto seleccionado. */
    public static function seleccionar(array $hechos, int $objeto, int $limite = 14000): array
    {
        usort($hechos, static fn($a, $b) => ($a['categoria'] === 'perfil' ? 0 : ((int) $a['objeto_id'] === $objeto ? 1 : 2))
            <=> ($b['categoria'] === 'perfil' ? 0 : ((int) $b['objeto_id'] === $objeto ? 1 : 2)));
        $out = []; $usados = 0; $omitidos = 0;
        foreach ($hechos as $h) {
            if ($h['categoria'] !== 'perfil' && (int) $h['objeto_id'] > 0 && (int) $h['objeto_id'] !== $objeto) continue;
            $n = mb_strlen(self::json($h));
            if ($usados + $n > $limite) { $omitidos++; continue; }
            $out[] = $h; $usados += $n;
        }
        return ['hechos' => $out, 'omitidos_por_limite' => $omitidos];
    }

    public static function paraFormulario(string $operacion, int $objeto, int $centro, int $usuario): ?array
    {
        if (!self::accesoGeneral($usuario, $centro)) return null;
        if ($centro !== (int) ($_SESSION['centro']['NoCentro'] ?? 0)
            || $usuario !== (int) ($_SESSION['user']['NoUsuario'] ?? 0)
            || ($_SESSION['user']['autenticado'] ?? false) !== true) throw new DomainException('sesion_contexto');
        $servicio = new self(Conectar::conexion());
        if (!$servicio->disponible() || !$servicio->reglas()['config']['habilitado']) return null;
        $m = $servicio->leer($centro, self::permisosSesion());
        if ($m['estado'] === 'pendiente' || ($m['trabajo']['estado'] ?? '') !== 'ok') throw new RuntimeException('contexto_pendiente');
        $seleccion = self::seleccionar($m['hechos'], $objeto);
        $ids = array_map(static fn($h) => (int) $h['id'], $seleccion['hechos']);
        $traza = ['operacion' => $operacion, 'objeto_id' => $objeto, 'hechos_ids' => $ids,
            'version_reglas' => $m['version_reglas'], 'revision' => $m['revision'],
            'omitidos_por_limite' => $seleccion['omitidos_por_limite']];
        $evento = $servicio->evento($centro, 'uso_formulario', $usuario, $traza);
        return ['evento_id' => $evento, 'estado' => $m['estado'], 'version_reglas' => $m['version_reglas'], 'revision' => $m['revision'],
            'hechos' => array_map(static fn($h) => ['id' => (int) $h['id'], 'clave' => $h['clave'],
                'dato' => $h['contenido'], 'origen' => $h['codigo'], 'referencia' => $h['localizador']], $seleccion['hechos']),
            'conflictos' => $m['conflictos'], 'omitidos_por_limite' => $seleccion['omitidos_por_limite']];
    }
}
