<?php
/**
 * app/asistencia.php
 * Gestión de asistencia académica con RBAC Jerárquico.
 *
 * Rutas:
 *   GET  /asistencia              → Menú
 *   GET  /asistencia/registrar    → Formulario selección
 *   POST /asistencia/registrar    → Lista de estudiantes
 *   POST /asistencia/guardar      → Guardar asistencia
 *   GET  /asistencia/consultar    → Consultar con filtros (+ export CSV)
 */

require_once __DIR__ . '/layout.php';

// ==========================================
// /asistencia (GET) — Menú principal
// ==========================================
if ($uri === '/asistencia' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/asistencia', 'VER'); // ✅ CORREGIDO: Formato jerárquico

    renderHeader('Gestión de Asistencia');
    ?>
    <div class="card">
        <h2>Gestión de Asistencia</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php if (can('/asistencia', 'CREAR')): ?> <!-- ✅ CORREGIDO -->
                <a href="/ue-ameliarios/public/index.php/asistencia/registrar" style="background: white; padding: 30px 20px; border-radius: 8px; text-decoration: none; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; text-align: center; border: 1px solid #e5e7eb;">
                    <div style="font-size: 42px; margin-bottom: 12px;">✅</div>
                    <div style="font-weight: bold; font-size: 17px; margin-bottom: 5px;">Registrar Asistencia</div>
                    <div style="font-size: 13px; color: #777;">Registrar la asistencia del día</div>
                </a>
            <?php endif; ?>

            <a href="/ue-ameliarios/public/index.php/asistencia/consultar" style="background: white; padding: 30px 20px; border-radius: 8px; text-decoration: none; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; text-align: center; border: 1px solid #e5e7eb;">
                <div style="font-size: 42px; margin-bottom: 12px;">📊</div>
                <div style="font-weight: bold; font-size: 17px; margin-bottom: 5px;">Consultar Asistencia</div>
                <div style="font-size: 13px; color: #777;">Ver registros por curso y materia</div>
            </a>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /asistencia/registrar (GET) — Formulario
// ==========================================
if ($uri === '/asistencia/registrar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/asistencia', 'CREAR'); // ✅ CORREGIDO

    $cursos = db()->query("
        SELECT c.id_curso, g.nombre AS grado, s.nombre AS seccion, pa.nombre AS periodo
        FROM curso c
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        INNER JOIN periodo_academico pa ON c.id_periodo = pa.id_periodo
        ORDER BY pa.id_periodo DESC, g.orden ASC, s.nombre ASC
    ")->fetchAll();

    $materias = db()->query("SELECT id_materia, nombre FROM materia ORDER BY nombre ASC")->fetchAll();

    renderHeader('Registrar Asistencia');
    ?>
    <div class="card">
        <h2>Registrar Asistencia</h2>

        <?php if (isset($_GET['ok'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                ✅ Asistencia guardada correctamente (<?= (int)$_GET['ok'] ?> registros).
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                ⚠️ No se pudo procesar: <strong><?= htmlspecialchars($_GET['err']) ?></strong>
            </div>
        <?php endif; ?>

        <div style="background: #e7f3ff; border-left: 4px solid var(--primary-color); padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px;">
            <strong>Paso 1:</strong> Selecciona el curso, la materia y la fecha.<br>
            <strong>Paso 2:</strong> Marca el estado de cada estudiante.<br>
            <strong>Paso 3:</strong> Guarda los cambios.
        </div>

        <form method="POST" action="/ue-ameliarios/public/index.php/asistencia/registrar">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Curso *</label>
                    <select name="id_curso" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= $c['id_curso'] ?>">
                                <?= htmlspecialchars($c['grado'] . ' ' . $c['seccion']) ?> — <?= htmlspecialchars($c['periodo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Materia *</label>
                    <select name="id_materia" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= $m['id_materia'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Fecha *</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-size: 15px; cursor: pointer;">Continuar</button>
                <a href="/ue-ameliarios/public/index.php/asistencia" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 15px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /asistencia/registrar (POST) — Lista de estudiantes
// ==========================================
if ($uri === '/asistencia/registrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requirePermission('/asistencia', 'CREAR'); // ✅ CORREGIDO

    $id_curso   = (int)($_POST['id_curso']   ?? 0);
    $id_materia = (int)($_POST['id_materia'] ?? 0);
    $fecha      = $_POST['fecha']            ?? '';

    if ($id_curso <= 0 || $id_materia <= 0 || $fecha === '') {
        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?err=datos_incompletos');
        exit;
    }

    $hoy = date('Y-m-d');
    if ($fecha > $hoy) {
        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?err=fecha_futura');
        exit;
    }
    $limite = date('Y-m-d', strtotime('-7 days'));
    if ($fecha < $limite) {
        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?err=fecha_muy_antigua');
        exit;
    }

    $chk = db()->prepare("SELECT COUNT(*) AS n FROM curso WHERE id_curso = :c");
    $chk->execute(['c' => $id_curso]);
    if ((int)$chk->fetch()['n'] === 0) {
        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?err=curso_no_existe');
        exit;
    }

    $chk = db()->prepare("SELECT COUNT(*) AS n FROM materia WHERE id_materia = :m");
    $chk->execute(['m' => $id_materia]);
    if ((int)$chk->fetch()['n'] === 0) {
        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?err=materia_no_existe');
        exit;
    }

    $stmt = db()->prepare("
        SELECT c.id_curso, g.nombre AS grado, s.nombre AS seccion, m.nombre AS materia
        FROM curso c
        INNER JOIN grado g   ON c.id_grado   = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        INNER JOIN materia m ON m.id_materia = :m
        WHERE c.id_curso = :c
    ");
    $stmt->execute(['c' => $id_curso, 'm' => $id_materia]);
    $info = $stmt->fetch();

    $stmt = db()->prepare("
        SELECT i.id_inscripcion, i.id_estudiante,
               p.nombres, p.apellidos, p.cedula,
               e.codigo_estudiante,
               a.id_asistencia, a.estado AS estado_actual, a.observacion AS obs_actual
        FROM inscripcion i
        INNER JOIN estudiante e ON i.id_estudiante = e.id_estudiante
        INNER JOIN persona p    ON e.id_persona    = p.id_persona
        LEFT  JOIN asistencia a ON a.id_inscripcion = i.id_inscripcion
                                AND a.id_materia    = :m
                                AND a.fecha         = :f
        WHERE i.id_curso = :c
          AND i.estado = 'Activa'
        ORDER BY p.apellidos ASC, p.nombres ASC
    ");
    $stmt->execute(['c' => $id_curso, 'm' => $id_materia, 'f' => $fecha]);
    $estudiantes = $stmt->fetchAll();

    renderHeader('Tomar Asistencia');
    ?>
    <div class="card">
        <h2><?= htmlspecialchars($info['grado'] . ' ' . $info['seccion'] . ' — ' . $info['materia']) ?></h2>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Fecha: <strong><?= htmlspecialchars($fecha) ?></strong> · Estudiantes activos: <strong><?= count($estudiantes) ?></strong></p>

        <?php if (!$estudiantes): ?>
            <p style="text-align: center; color: #888; padding: 40px;">No hay estudiantes activos inscritos en este curso.</p>
        <?php else: ?>
            <div style="background: #e7f3ff; border-left: 4px solid var(--primary-color); padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; font-size: 14px;">
                Marca el estado de cada estudiante. Si ya existe un registro del día, se te muestra preseleccionado y podrás corregirlo.
            </div>

            <form method="POST" action="/ue-ameliarios/public/index.php/asistencia/guardar">
                <input type="hidden" name="id_curso"   value="<?= $id_curso ?>">
                <input type="hidden" name="id_materia" value="<?= $id_materia ?>">
                <input type="hidden" name="fecha"      value="<?= htmlspecialchars($fecha) ?>">

                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr style="background: var(--primary-color); color: white;">
                            <th style="padding: 10px; text-align: left; width: 50px;">#</th>
                            <th style="padding: 10px; text-align: left;">Estudiante</th>
                            <th style="padding: 10px; text-align: left; width: 360px;">Estado</th>
                            <th style="padding: 10px; text-align: left;">Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($estudiantes as $e): ?>
                            <?php $est = $e['estado_actual'] ?? 'Presente'; ?>
                            <tr style="<?= $e['id_asistencia'] ? 'background: #fff9e6;' : '' ?> border-bottom: 1px solid #eee;">
                                <td style="padding: 10px;"><?= $i++ ?></td>
                                <td style="padding: 10px;">
                                    <strong><?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']) ?></strong><br>
                                    <small style="color:#888;">C.I. <?= htmlspecialchars($e['cedula']) ?><?= $e['codigo_estudiante'] ? ' · ' . htmlspecialchars($e['codigo_estudiante']) : '' ?></small>
                                </td>
                                <td style="padding: 10px;">
                                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                        <label style="display: flex; align-items: center; gap: 4px; font-size: 13px; cursor: pointer;">
                                            <input type="radio" name="estado[<?= $e['id_inscripcion'] ?>]" value="Presente" <?= $est === 'Presente' ? 'checked' : '' ?>> ✅ Presente
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 4px; font-size: 13px; cursor: pointer;">
                                            <input type="radio" name="estado[<?= $e['id_inscripcion'] ?>]" value="Ausente" <?= $est === 'Ausente' ? 'checked' : '' ?>> ❌ Ausente
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 4px; font-size: 13px; cursor: pointer;">
                                            <input type="radio" name="estado[<?= $e['id_inscripcion'] ?>]" value="Justificado" <?= $est === 'Justificado' ? 'checked' : '' ?>> 📄 Justificado
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 4px; font-size: 13px; cursor: pointer;">
                                            <input type="radio" name="estado[<?= $e['id_inscripcion'] ?>]" value="Retardo" <?= $est === 'Retardo' ? 'checked' : '' ?>> ⏰ Retardo
                                        </label>
                                    </div>
                                </td>
                                <td style="padding: 10px;">
                                    <input type="text" name="observacion[<?= $e['id_inscripcion'] ?>]" value="<?= htmlspecialchars($e['obs_actual'] ?? '') ?>" placeholder="Opcional" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-size: 15px; cursor: pointer;">💾 Guardar Asistencia</button>
                    <a href="/ue-ameliarios/public/index.php/asistencia" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 15px;">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /asistencia/guardar (POST) — Guardado con trazabilidad
// ==========================================
if ($uri === '/asistencia/guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requirePermission('/asistencia', 'CREAR'); // ✅ CORREGIDO

    $id_curso      = (int)($_POST['id_curso']   ?? 0);
    $id_materia    = (int)($_POST['id_materia'] ?? 0);
    $fecha         = $_POST['fecha']            ?? '';
    $estados       = $_POST['estado']           ?? [];
    $observaciones = $_POST['observacion']      ?? [];

    $hoy = date('Y-m-d');
    if ($id_curso <= 0 || $id_materia <= 0 || $fecha === '' || $fecha > $hoy || empty($estados)) {
        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?err=datos_invalidos');
        exit;
    }

    $validos = ['Presente', 'Ausente', 'Justificado', 'Retardo'];
    
    // ✅ CORREGIDO: Usamos 'id' en lugar de 'id_usuario' para coincidir con helpers.php
    $id_usuario = (int)($_SESSION['user']['id'] ?? 0);

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $sql = "
            INSERT INTO asistencia
                (id_inscripcion, id_materia, fecha, estado, observacion, registrado_por, hora_registro)
            VALUES
                (:ins, :mat, :fec, :est, :obs, :usr, NOW())
            ON DUPLICATE KEY UPDATE
                estado         = VALUES(estado),
                observacion    = VALUES(observacion),
                registrado_por = VALUES(registrado_por),
                hora_registro  = NOW()
        ";
        $stmt = $pdo->prepare($sql);

        $guardados = 0;
        foreach ($estados as $id_inscripcion => $estado) {
            $id_inscripcion = (int)$id_inscripcion;
            if ($id_inscripcion <= 0 || !in_array($estado, $validos, true)) {
                continue;
            }
            $obs = trim((string)($observaciones[$id_inscripcion] ?? ''));
            $stmt->execute([
                'ins' => $id_inscripcion,
                'mat' => $id_materia,
                'fec' => $fecha,
                'est' => $estado,
                'obs' => $obs !== '' ? $obs : null,
                'usr' => $id_usuario ?: null,
            ]);
            $guardados++;
        }

        $pdo->commit();

        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?ok=' . $guardados . '&id_curso=' . $id_curso . '&id_materia=' . $id_materia . '&fecha=' . urlencode($fecha));
        exit;

    } catch (Throwable $ex) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Para depuración, puedes descomentar la siguiente línea si falla:
        // error_log($ex->getMessage());
        header('Location: /ue-ameliarios/public/index.php/asistencia/registrar?err=guardar_fallido');
        exit;
    }
}

// ==========================================
// /asistencia/consultar (GET) — Listado simple y agrupado
// ==========================================
if ($uri === '/asistencia/consultar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/asistencia', 'VER'); // ✅ CORREGIDO

    $f_id_curso   = $_GET['id_curso']   ?? '';
    $f_id_materia = $_GET['id_materia'] ?? '';
    $f_desde      = $_GET['desde']      ?? '';
    $f_hasta      = $_GET['hasta']      ?? '';
    $f_buscar     = trim($_GET['q']     ?? '');

    $where  = [];
    $params = [];

    if ($f_id_curso !== '') {
        $where[] = 'i.id_curso = :id_curso';
        $params['id_curso'] = (int)$f_id_curso;
    }
    if ($f_id_materia !== '') {
        $where[] = 'a.id_materia = :id_materia';
        $params['id_materia'] = (int)$f_id_materia;
    }
    if ($f_desde !== '') {
        $where[] = 'a.fecha >= :desde';
        $params['desde'] = $f_desde;
    }
    if ($f_hasta !== '') {
        $where[] = 'a.fecha <= :hasta';
        $params['hasta'] = $f_hasta;
    }
    if ($f_buscar !== '') {
        $where[] = '(p.nombres LIKE :q OR p.apellidos LIKE :q OR p.cedula LIKE :q)';
        $params['q'] = '%' . $f_buscar . '%';
    }

    $sql = "
        SELECT a.fecha, a.estado, a.hora_registro,
               p.nombres, p.apellidos, p.cedula,
               g.nombre AS grado, s.nombre AS seccion,
               m.nombre AS materia
        FROM asistencia a
        INNER JOIN inscripcion i ON a.id_inscripcion = i.id_inscripcion
        INNER JOIN estudiante e  ON i.id_estudiante  = e.id_estudiante
        INNER JOIN persona p     ON e.id_persona     = p.id_persona
        INNER JOIN curso c       ON i.id_curso       = c.id_curso
        INNER JOIN grado g       ON c.id_grado       = g.id_grado
        INNER JOIN seccion s     ON c.id_seccion     = s.id_seccion
        INNER JOIN materia m     ON a.id_materia     = m.id_materia
    ";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY a.fecha DESC, c.id_curso ASC, p.apellidos ASC LIMIT 500';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll();

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="asistencia_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
        fputcsv($out, ['Fecha', 'Estudiante', 'Cédula', 'Curso', 'Materia', 'Estado', 'Hora']);
        foreach ($registros as $r) {
            fputcsv($out, [
                $r['fecha'],
                $r['apellidos'] . ', ' . $r['nombres'],
                $r['cedula'],
                $r['grado'] . ' ' . $r['seccion'],
                $r['materia'],
                $r['estado'],
                substr($r['hora_registro'] ?? '', 11, 5),
            ]);
        }
        fclose($out);
        exit;
    }

    $contadores = ['Presente' => 0, 'Ausente' => 0, 'Justificado' => 0, 'Retardo' => 0];
    foreach ($registros as $r) {
        if (isset($contadores[$r['estado']])) {
            $contadores[$r['estado']]++;
        }
    }

    $agrupado = [];
    foreach ($registros as $r) {
        $key = $r['fecha'] . '|' . $r['grado'] . ' ' . $r['seccion'];
        if (!isset($agrupado[$key])) {
            $agrupado[$key] = [
                'fecha'    => $r['fecha'],
                'curso'    => $r['grado'] . ' ' . $r['seccion'],
                'materia'  => $r['materia'],
                'alumnos'  => [],
            ];
        }
        $agrupado[$key]['alumnos'][] = $r;
    }

    $cursos = db()->query("
        SELECT c.id_curso, g.nombre AS grado, s.nombre AS seccion
        FROM curso c
        INNER JOIN grado g   ON c.id_grado   = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        ORDER BY g.orden ASC, s.nombre ASC
    ")->fetchAll();

    $materias = db()->query("SELECT id_materia, nombre FROM materia ORDER BY nombre ASC")->fetchAll();

    $emoji = function(string $estado): string {
        return match($estado) {
            'Presente'    => '✅',
            'Ausente'     => '❌',
            'Justificado' => '📄',
            'Retardo'     => '⏰',
            default       => '•',
        };
    };

    renderHeader('Consultar Asistencia');
    ?>
    <div class="card" style="margin-bottom: 15px;">
        <form method="GET" action="/ue-ameliarios/public/index.php/asistencia/consultar">
            <div style="margin-bottom: 12px;">
                <input type="text" name="q" placeholder="🔍 Buscar estudiante por nombre o cédula..." value="<?= htmlspecialchars($f_buscar) ?>" style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 10px;">
                <select name="id_curso" style="padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                    <option value="">Todos los cursos</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c['id_curso'] ?>" <?= ($f_id_curso == $c['id_curso']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['grado'] . ' ' . $c['seccion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="id_materia" style="padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                    <option value="">Todas las materias</option>
                    <?php foreach ($materias as $m): ?>
                        <option value="<?= $m['id_materia'] ?>" <?= ($f_id_materia == $m['id_materia']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="desde" value="<?= htmlspecialchars($f_desde) ?>" style="padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                <input type="date" name="hasta" value="<?= htmlspecialchars($f_hasta) ?>" style="padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                <button type="submit" style="padding: 10px 18px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">Filtrar</button>
            </div>
            <div style="margin-top:10px;">
                <a href="/ue-ameliarios/public/index.php/asistencia/consultar" style="font-size:13px; padding: 7px 14px; color: var(--primary-color); border: 1px solid var(--primary-color); border-radius: 6px; text-decoration: none;">Limpiar filtros</a>
            </div>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;">
        <div style="background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); text-align: center;">
            <div style="font-size: 28px; font-weight: bold; color: #28a745;"><?= $contadores['Presente'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">✅ Presentes</div>
        </div>
        <div style="background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); text-align: center;">
            <div style="font-size: 28px; font-weight: bold; color: #dc3545;"><?= $contadores['Ausente'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">❌ Ausentes</div>
        </div>
        <div style="background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); text-align: center;">
            <div style="font-size: 28px; font-weight: bold; color: #ffc107;"><?= $contadores['Justificado'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">📄 Justificados</div>
        </div>
        <div style="background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); text-align: center;">
            <div style="font-size: 28px; font-weight: bold; color: #17a2b8;"><?= $contadores['Retardo'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">⏰ Retardos</div>
        </div>
    </div>

    <?php if (!$agrupado): ?>
        <div style="background: white; padding: 50px 20px; border-radius: 8px; text-align: center; color: #888;">
            <p style="font-size:40px;margin-bottom:10px;">📭</p>
            <p>No hay registros que coincidan con los filtros.</p>
        </div>
    <?php else: ?>
        <?php foreach ($agrupado as $grupo): ?>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); margin-bottom: 15px; overflow: hidden;">
                <div style="padding: 12px 20px; background: #f8f9fb; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                    <span style="font-weight: bold; color: var(--primary-color); font-size: 15px;">📅 <?= htmlspecialchars($grupo['fecha']) ?></span>
                    <span style="font-size: 13px; color: #666;">🎓 <?= htmlspecialchars($grupo['curso']) ?> · <?= htmlspecialchars($grupo['materia']) ?> · <?= count($grupo['alumnos']) ?> estudiantes</span>
                </div>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <?php foreach ($grupo['alumnos'] as $a): ?>
                        <li style="padding: 10px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 12px; font-size: 14px;">
                            <span style="font-size: 18px; width: 24px; text-align: center;"><?= $emoji($a['estado']) ?></span>
                            <span style="flex: 1; font-weight: 500;"><?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?></span>
                            <span style="color: #888; font-size: 13px;"><?= htmlspecialchars($a['cedula']) ?></span>
                            <span style="color: #aaa; font-size: 12px;"><?= htmlspecialchars(substr($a['hora_registro'] ?? '', 11, 5)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
            <?php
                $qs = $_GET;
                $qs['export'] = 'csv';
            ?>
            <a href="/ue-ameliarios/public/index.php/asistencia/consultar?<?= htmlspecialchars(http_build_query($qs)) ?>" style="padding: 10px 18px; background: transparent; color: var(--primary-color); border: 1px solid var(--primary-color); border-radius: 6px; text-decoration: none; font-size: 14px;">⬇️ Exportar CSV</a>
        </div>
    <?php endif; ?>
    <?php
    renderFooter();
    exit;
}