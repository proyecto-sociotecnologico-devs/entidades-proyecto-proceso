<?php
/**
 * app/inscripciones.php
 * Gestión de inscripciones (estudiante ↔ curso) con RBAC Jerárquico, Auditoría y Soft Delete.
 * Versión Corregida: Soluciona el problema de enrutamiento en "Retirar".
 */
require_once __DIR__ . '/layout.php';

// ==========================================
// /inscripciones (GET) — Listado
// ==========================================
if ($uri === '/inscripciones' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/inscripciones', 'VER');

    $inscripciones = db()->query("
        SELECT i.id_inscripcion, i.fecha_inscripcion, i.estado,
               p.nombres AS estudiante_nombres, p.apellidos AS estudiante_apellidos, p.cedula,
               e.codigo_estudiante,
               g.nombre AS grado, s.nombre AS seccion,
               pa.nombre AS periodo, c.aula
        FROM inscripcion i
        INNER JOIN estudiante e ON i.id_estudiante = e.id_estudiante
        INNER JOIN persona p ON e.id_persona = p.id_persona
        INNER JOIN curso c ON i.id_curso = c.id_curso
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        INNER JOIN periodo_academico pa ON c.id_periodo = pa.id_periodo
        ORDER BY pa.id_periodo DESC, g.orden ASC, s.nombre ASC, p.apellidos ASC
    ")->fetchAll();

    renderHeader('Gestión de Inscripciones');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Inscripciones del Sistema</h2>
        <?php if (can('/inscripciones', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/inscripciones/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nueva Inscripción</a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <?php if (!$inscripciones): ?>
            <div style="padding: 40px; text-align: center; color: #888;">No hay inscripciones registradas.</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: var(--primary-color); color: white;">
                        <th style="padding: 12px; text-align: left;">Código</th>
                        <th style="padding: 12px; text-align: left;">Estudiante</th>
                        <th style="padding: 12px; text-align: left;">Curso</th>
                        <th style="padding: 12px; text-align: left;">Período</th>
                        <th style="padding: 12px; text-align: left;">Estado</th>
                        <th style="padding: 12px; text-align: left;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscripciones as $i): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?= htmlspecialchars($i['codigo_estudiante']) ?></td>
                            <td style="padding: 12px;">
                                <strong><?= htmlspecialchars($i['estudiante_apellidos'] . ', ' . $i['estudiante_nombres']) ?></strong><br>
                                <small style="color: #888;">C.I. <?= htmlspecialchars($i['cedula']) ?></small>
                            </td>
                            <td style="padding: 12px;"><?= htmlspecialchars($i['grado'] . ' ' . $i['seccion']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($i['periodo']) ?></td>
                            <td style="padding: 12px;">
                                <span style="padding: 3px 10px; border-radius: 12px; font-size: 12px; 
                                    <?= $i['estado'] === 'Activa' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                                    <?= htmlspecialchars($i['estado']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <?php if ($i['estado'] === 'Activa' && can('/inscripciones', 'EDITAR')): ?>
                                    <a href="/ue-ameliarios/public/index.php/inscripciones/eliminar/<?= $i['id_inscripcion'] ?>" style="color: #e74c3c; text-decoration: none; font-size: 13px; font-weight: bold;" onclick="return confirm('¿Retirar la inscripción de <?= htmlspecialchars($i['estudiante_nombres']) ?>? Esta acción no la borra, solo cambia su estado a Retirada.')">Retirar</a>
                                <?php else: ?>
                                    <span style="color: #ccc; font-size: 13px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /inscripciones/crear (GET) — Formulario
// ==========================================
if ($uri === '/inscripciones/crear' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/inscripciones', 'CREAR');

    $estudiantes = db()->query("
        SELECT e.id_estudiante, e.codigo_estudiante, p.cedula, p.nombres, p.apellidos
        FROM estudiante e
        INNER JOIN persona p ON e.id_persona = p.id_persona
        WHERE e.estado = 'Activo'
        ORDER BY p.apellidos ASC
    ")->fetchAll();

    $cursos = db()->query("
        SELECT c.id_curso, c.aula, g.nombre AS grado, s.nombre AS seccion, pa.nombre AS periodo
        FROM curso c
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        INNER JOIN periodo_academico pa ON c.id_periodo = pa.id_periodo
        ORDER BY pa.id_periodo DESC, g.orden ASC, s.nombre ASC
    ")->fetchAll();

    renderHeader('Nueva Inscripción');
    ?>
    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <h2>Inscribir Estudiante en un Curso</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

        <form method="POST" action="/ue-ameliarios/public/index.php/inscripciones/crear">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Estudiante *</label>
                <select name="id_estudiante" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Seleccionar Estudiante --</option>
                    <?php foreach ($estudiantes as $e): ?>
                        <option value="<?= $e['id_estudiante'] ?>">
                            <?= htmlspecialchars($e['cedula'] . ' - ' . $e['apellidos'] . ', ' . $e['nombres'] . ' (' . $e['codigo_estudiante'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Curso *</label>
                <select name="id_curso" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Seleccionar Curso --</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c['id_curso'] ?>">
                            <?= htmlspecialchars($c['grado'] . ' ' . $c['seccion']) ?> — <?= htmlspecialchars($c['periodo']) ?> <?= $c['aula'] ? ' (' . htmlspecialchars($c['aula']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Fecha de Inscripción *</label>
                <input type="date" name="fecha_inscripcion" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Crear Inscripción</button>
                <a href="/ue-ameliarios/public/index.php/inscripciones" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /inscripciones/crear (POST)
// ==========================================
if ($uri === '/inscripciones/crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requirePermission('/inscripciones', 'CREAR');

    $id_estudiante = (int)($_POST['id_estudiante'] ?? 0);
    $id_curso = (int)($_POST['id_curso'] ?? 0);
    $fecha_inscripcion = trim($_POST['fecha_inscripcion'] ?? '');

    if ($id_estudiante === 0 || $id_curso === 0 || empty($fecha_inscripcion)) {
        header('Location: /ue-ameliarios/public/index.php/inscripciones/crear?error=Todos+los+campos+son+obligatorios'); exit;
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("SELECT COUNT(*) AS total FROM inscripcion WHERE id_estudiante = :e AND id_curso = :c AND estado = 'Activa'");
        $stmt->execute(['e' => $id_estudiante, 'c' => $id_curso]);
        if ($stmt->fetch()['total'] > 0) throw new Exception("El estudiante ya está inscrito activamente en ese curso.");

        db()->prepare("INSERT INTO inscripcion (id_estudiante, id_curso, fecha_inscripcion, estado) VALUES (:e, :c, :f, 'Activa')")
            ->execute(['e' => $id_estudiante, 'c' => $id_curso, 'f' => $fecha_inscripcion]);
        $id_inscripcion = db()->lastInsertId();

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_INSCRIPCION', 'inscripcion', :r, NOW())")
            ->execute(['u' => $uid, 'r' => $id_inscripcion]);

        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/inscripciones?exito=Inscripcion+creada+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        header('Location: /ue-ameliarios/public/index.php/inscripciones/crear?error=' . urlencode($e->getMessage())); exit;
    }
}

// ==========================================
// /inscripciones/eliminar (GET) — Soft delete (Retirar)
// ==========================================
if ($uri === '/inscripciones/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/inscripciones', 'EDITAR');

    // ✅ CORRECCIÓN CLAVE: Usar $routeParams en lugar de preg_match
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) {
        die("Error: ID de inscripción no válido.");
    }

    $stmt = db()->prepare("SELECT id_inscripcion, estado FROM inscripcion WHERE id_inscripcion = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $inscripcion = $stmt->fetch();

    if (!$inscripcion) {
        die("Error: Inscripción no encontrada.");
    }

    if ($inscripcion['estado'] !== 'Activa') {
        die("Error: La inscripción ya no está activa (ya fue retirada previamente).");
    }

    try {
        db()->beginTransaction();

        // Soft Delete: Cambiamos el estado en lugar de borrar el registro físicamente
        $stmt = db()->prepare("UPDATE inscripcion SET estado = 'Retirada' WHERE id_inscripcion = :id");
        $stmt->execute(['id' => $id]);

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'RETIRAR_INSCRIPCION', 'inscripcion', :r, NOW())")
            ->execute(['u' => $uid, 'r' => $id]);

        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/inscripciones?exito=Inscripcion+retirada+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al retirar la inscripción: " . $e->getMessage());
    }
}