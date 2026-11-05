<?php
/**
 * app/justificaciones.php
 * Módulo de Justificaciones con RBAC Jerárquico y Auditoría.
 * Cumple con la Sección 2 y 3 del Instructivo UNERMB.
 */

require_once __DIR__ . '/layout.php';

// ==========================================
// /justificaciones (GET) — Menú principal
// ==========================================
if ($uri === '/justificaciones' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/justificaciones', 'VER'); // ✅ RBAC JERÁRQUICO
    
    $esRepresentante = hasRole('Representante');
    $esAdminDocente  = hasAnyRole(['Administrador', 'Docente']);

    renderHeader('Justificaciones');
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
        <?php if ($esRepresentante): ?>
            <?php if (can('/justificaciones', 'CREAR')): ?>
            <a href="/ue-ameliarios/public/index.php/justificaciones/crear" style="background: white; padding: 30px 20px; border-radius: 8px; text-decoration: none; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; border: 1px solid #e5e7eb;">
                <div style="font-size: 42px; margin-bottom: 12px;">📝</div>
                <div style="font-weight: bold; font-size: 17px; margin-bottom: 5px;">Crear Justificación</div>
                <div style="font-size: 13px; color: #777;">Justificar una falta de mi representado</div>
            </a>
            <?php endif; ?>
            
            <a href="/ue-ameliarios/public/index.php/justificaciones/mis" style="background: white; padding: 30px 20px; border-radius: 8px; text-decoration: none; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; border: 1px solid #e5e7eb;">
                <div style="font-size: 42px; margin-bottom: 12px;">📋</div>
                <div style="font-weight: bold; font-size: 17px; margin-bottom: 5px;">Mis Justificaciones</div>
                <div style="font-size: 13px; color: #777;">Ver el estado de mis solicitudes</div>
            </a>
        <?php endif; ?>

        <?php if ($esAdminDocente && can('/justificaciones', 'EDITAR')): ?>
            <a href="/ue-ameliarios/public/index.php/justificaciones/revisar" style="background: white; padding: 30px 20px; border-radius: 8px; text-decoration: none; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; border: 1px solid #e5e7eb;">
                <div style="font-size: 42px; margin-bottom: 12px;">🔍</div>
                <div style="font-weight: bold; font-size: 17px; margin-bottom: 5px;">Revisar Pendientes</div>
                <div style="font-size: 13px; color: #777;">Aprobar o rechazar justificaciones</div>
            </a>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /justificaciones/crear (GET) — Formulario Representante
// ==========================================
if ($uri === '/justificaciones/crear' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/justificaciones', 'CREAR'); // ✅ RBAC JERÁRQUICO

    $stmt = db()->prepare("
        SELECT r.id_representante 
        FROM representante r
        INNER JOIN usuario u ON r.id_persona = u.id_persona
        WHERE u.id_usuario = :idu
    ");
    // ✅ CORREGIDO: Usar $_SESSION['user']['id']
    $stmt->execute(['idu' => $_SESSION['user']['id']]);
    $rep = $stmt->fetch();

    if (!$rep) {
        die("Error: El usuario actual no tiene un perfil de representante asignado en la base de datos.");
    }
    $id_representante = $rep['id_representante'];

    $stmt = db()->prepare("
        SELECT e.id_estudiante, p.nombres, p.apellidos, e.codigo_estudiante
        FROM representante_estudiante re
        INNER JOIN estudiante e ON re.id_estudiante = e.id_estudiante
        INNER JOIN persona p ON e.id_persona = p.id_persona
        WHERE re.id_representante = :idr AND e.estado = 'Activo'
        ORDER BY p.apellidos ASC
    ");
    $stmt->execute(['idr' => $id_representante]);
    $estudiantes = $stmt->fetchAll();

    renderHeader('Crear Justificación');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto; padding: 30px;">
        <h2>Justificar Falta</h2>

        <?php if (isset($_GET['error'])): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; border: 1px solid #f5c6cb;">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/ue-ameliarios/public/index.php/justificaciones/crear">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Estudiante Representado *</label>
                <select name="id_estudiante" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($estudiantes as $e): ?>
                        <option value="<?= $e['id_estudiante'] ?>">
                            <?= htmlspecialchars($e['nombres'] . ' ' . $e['apellidos']) ?> (<?= htmlspecialchars($e['codigo_estudiante']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($estudiantes)): ?>
                    <p style="color:#777; font-size:12px; margin-top:5px;">No tienes estudiantes asignados.</p>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Fecha de la Falta *</label>
                <input type="date" name="fecha_justificacion" max="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Motivo de la Justificación *</label>
                <textarea name="motivo" rows="4" placeholder="Ej: Cita médica, reposo, problemas familiares..." required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-size: 15px; cursor: pointer;">Enviar Justificación</button>
                <a href="/ue-ameliarios/public/index.php/justificaciones" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 15px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /justificaciones/crear (POST) — Guardar
// ==========================================
if ($uri === '/justificaciones/crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requirePermission('/justificaciones', 'CREAR'); // ✅ RBAC JERÁRQUICO

    $id_estudiante = (int)($_POST['id_estudiante'] ?? 0);
    $fecha_falta   = $_POST['fecha_justificacion'] ?? '';
    $motivo        = trim($_POST['motivo'] ?? '');

    if ($id_estudiante <= 0 || empty($fecha_falta) || empty($motivo)) {
        header('Location: /ue-ameliarios/public/index.php/justificaciones/crear?error=' . urlencode('Todos los campos son obligatorios'));
        exit;
    }

    $stmt = db()->prepare("
        SELECT r.id_representante 
        FROM representante r
        INNER JOIN usuario u ON r.id_persona = u.id_persona
        WHERE u.id_usuario = :idu
    ");
    $stmt->execute(['idu' => $_SESSION['user']['id']]); // ✅ CORREGIDO
    $rep = $stmt->fetch();
    
    $id_representante = $rep['id_representante'] ?? 0;

    if ($id_representante === 0) {
        header('Location: /ue-ameliarios/public/index.php/justificaciones/crear?error=' . urlencode('Error de perfil de representante'));
        exit;
    }

    $stmt = db()->prepare("SELECT COUNT(*) as n FROM representante_estudiante WHERE id_representante = :idr AND id_estudiante = :ide");
    $stmt->execute(['idr' => $id_representante, 'ide' => $id_estudiante]);
    if ($stmt->fetch()['n'] == 0) {
        header('Location: /ue-ameliarios/public/index.php/justificaciones/crear?error=' . urlencode('No tienes permiso sobre este estudiante'));
        exit;
    }

    try {
        db()->beginTransaction(); // ✅ TRANSACCIÓN ACID (TEST-02)

        $stmt = db()->prepare("
            SELECT a.id_asistencia 
            FROM asistencia a
            INNER JOIN inscripcion i ON a.id_inscripcion = i.id_inscripcion
            WHERE i.id_estudiante = :ide AND a.fecha = :fecha
            LIMIT 1
        ");
        $stmt->execute(['ide' => $id_estudiante, 'fecha' => $fecha_falta]);
        $asistencia = $stmt->fetch();

        if (!$asistencia) {
            throw new Exception("No hay registro de asistencia para esta fecha. El docente debe registrarlo primero.");
        }

        $id_asistencia = $asistencia['id_asistencia'];

        $stmt = db()->prepare("
            INSERT INTO justificacion (id_asistencia, id_representante, motivo, estado, fecha_justificacion)
            VALUES (:ida, :idr, :motivo, 'Pendiente', :fecha_just)
        ");
        $stmt->execute([
            'ida'        => $id_asistencia,
            'idr'        => $id_representante,
            'motivo'     => $motivo,
            'fecha_just' => $fecha_falta
        ]);
        $id_justificacion = db()->lastInsertId();

        // ✅ AUDITORÍA CORREGIDA (Instructivo Sección 2 - TEST-06)
        $id_usuario_session = (int)($_SESSION['user']['id'] ?? 0);
        $stmt = db()->prepare("
            INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora)
            VALUES (:id_usuario, 'CREAR_JUSTIFICACION', 'justificacion', :id_registro, NOW())
        ");
        $stmt->execute([
            'id_usuario'  => $id_usuario_session,
            'id_registro' => $id_justificacion,
        ]);

        db()->commit();

        header('Location: /ue-ameliarios/public/index.php/justificaciones?exito=' . urlencode('Justificación enviada correctamente. Esperando revisión.'));
        exit;

    } catch (Exception $e) {
        db()->rollBack();
        header('Location: /ue-ameliarios/public/index.php/justificaciones/crear?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ==========================================
// /justificaciones/mis (GET) — Lista para el Representante
// ==========================================
if ($uri === '/justificaciones/mis' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/justificaciones', 'VER'); // ✅ RBAC JERÁRQUICO

    $stmt = db()->prepare("
        SELECT r.id_representante 
        FROM representante r
        INNER JOIN usuario u ON r.id_persona = u.id_persona
        WHERE u.id_usuario = :idu
    ");
    $stmt->execute(['idu' => $_SESSION['user']['id']]); // ✅ CORREGIDO
    $rep = $stmt->fetch();
    
    $id_representante = $rep['id_representante'] ?? 0;

    if ($id_representante === 0) {
        die("Error de perfil.");
    }

    $stmt = db()->prepare("
        SELECT j.id_justificacion, j.motivo, j.estado, j.fecha_justificacion,
               p.nombres AS est_nombres, p.apellidos AS est_apellidos
        FROM justificacion j
        INNER JOIN asistencia a ON j.id_asistencia = a.id_asistencia
        INNER JOIN inscripcion i ON a.id_inscripcion = i.id_inscripcion
        INNER JOIN estudiante e ON i.id_estudiante = e.id_estudiante
        INNER JOIN persona p ON e.id_persona = p.id_persona
        WHERE j.id_representante = :idr
        ORDER BY j.fecha_justificacion DESC
    ");
    $stmt->execute(['idr' => $id_representante]);
    $mis_justificaciones = $stmt->fetchAll();

    renderHeader('Mis Justificaciones');
    ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <h2 style="padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; margin: 0; border: none;">Historial de Solicitudes</h2>
        <?php if (empty($mis_justificaciones)): ?>
            <div style="padding: 40px; text-align: center; color: #888;">No has enviado ninguna justificación.</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: var(--primary-color); color: white;">
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Fecha Falta</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Estudiante</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Motivo</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mis_justificaciones as $j): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($j['fecha_justificacion']) ?></td>
                            <td style="padding: 12px 15px;"><strong><?= htmlspecialchars($j['est_apellidos'] . ', ' . $j['est_nombres']) ?></strong></td>
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($j['motivo']) ?></td>
                            <td style="padding: 12px 15px;">
                                <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; 
                                    <?php 
                                        if ($j['estado'] === 'Pendiente') echo 'background: #ffc107; color: #856404;';
                                        elseif ($j['estado'] === 'Aprobada') echo 'background: #d4edda; color: #155724;';
                                        else echo 'background: #f8d7da; color: #721c24;';
                                    ?>">
                                    <?= htmlspecialchars($j['estado']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /justificaciones/revisar (GET) — Lista para Admin/Docente
// ==========================================
if ($uri === '/justificaciones/revisar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/justificaciones', 'EDITAR'); // ✅ RBAC JERÁRQUICO (Mapeamos revisar a EDITAR)

    $stmt = db()->query("
        SELECT j.id_justificacion, j.motivo, j.estado, j.fecha_justificacion,
               p.nombres AS est_nombres, p.apellidos AS est_apellidos,
               pr.nombres AS rep_nombres, pr.apellidos AS rep_apellidos,
               a.fecha AS fecha_falta
        FROM justificacion j
        INNER JOIN asistencia a ON j.id_asistencia = a.id_asistencia
        INNER JOIN inscripcion i ON a.id_inscripcion = i.id_inscripcion
        INNER JOIN estudiante e ON i.id_estudiante = e.id_estudiante
        INNER JOIN persona p ON e.id_persona = p.id_persona
        INNER JOIN representante r ON j.id_representante = r.id_representante
        INNER JOIN persona pr ON r.id_persona = pr.id_persona
        ORDER BY j.estado ASC, j.fecha_justificacion DESC
    ");
    $justificaciones = $stmt->fetchAll();

    renderHeader('Revisar Justificaciones');
    ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <h2 style="padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; margin: 0; border: none;">Bandeja de Revisión</h2>
        <?php if (empty($justificaciones)): ?>
            <div style="padding: 40px; text-align: center; color: #888;">No hay justificaciones registradas.</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: var(--primary-color); color: white;">
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Fecha Falta</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Estudiante</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Representante</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Motivo</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Estado</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($justificaciones as $j): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($j['fecha_falta']) ?></td>
                            <td style="padding: 12px 15px;"><strong><?= htmlspecialchars($j['est_apellidos'] . ', ' . $j['est_nombres']) ?></strong></td>
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($j['rep_apellidos'] . ', ' . $j['rep_nombres']) ?></td>
                            <td style="padding: 12px 15px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($j['motivo']) ?>"><?= htmlspecialchars($j['motivo']) ?></td>
                            <td style="padding: 12px 15px;">
                                <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; 
                                    <?php 
                                        if ($j['estado'] === 'Pendiente') echo 'background: #ffc107; color: #856404;';
                                        elseif ($j['estado'] === 'Aprobada') echo 'background: #d4edda; color: #155724;';
                                        else echo 'background: #f8d7da; color: #721c24;';
                                    ?>">
                                    <?= htmlspecialchars($j['estado']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px 15px;">
                                <?php if ($j['estado'] === 'Pendiente'): ?>
                                    <a href="/ue-ameliarios/public/index.php/justificaciones/revisar/procesar?id=<?= $j['id_justificacion'] ?>&accion=aprobar" style="display: inline-block; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: #28a745; margin-right: 5px;">Aprobar</a>
                                    <a href="/ue-ameliarios/public/index.php/justificaciones/revisar/procesar?id=<?= $j['id_justificacion'] ?>&accion=rechazar" style="display: inline-block; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: #dc3545;">Rechazar</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /justificaciones/revisar/procesar (GET) — Acción
// ==========================================
if ($uri === '/justificaciones/revisar/procesar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/justificaciones', 'EDITAR'); // ✅ RBAC JERÁRQUICO

    $id_justificacion = (int)($_GET['id'] ?? 0);
    $accion = $_GET['accion'] ?? '';

    if ($id_justificacion <= 0 || !in_array($accion, ['aprobar', 'rechazar'])) {
        header('Location: /ue-ameliarios/public/index.php/justificaciones/revisar');
        exit;
    }

    try {
        db()->beginTransaction(); // ✅ TRANSACCIÓN ACID (TEST-02)

        $nuevo_estado = ($accion === 'aprobar') ? 'Aprobada' : 'Rechazada';
        $id_usuario_session = (int)($_SESSION['user']['id'] ?? 0); // ✅ CORREGIDO
        
        $stmt = db()->prepare("
            UPDATE justificacion 
            SET estado = :estado, revisado_por = :usr, fecha_revision = NOW()
            WHERE id_justificacion = :id AND estado = 'Pendiente'
        ");
        $stmt->execute([
            'estado' => $nuevo_estado,
            'usr'    => $id_usuario_session,
            'id'     => $id_justificacion
        ]);

        if ($accion === 'aprobar') {
            $stmt = db()->prepare("SELECT id_asistencia FROM justificacion WHERE id_justificacion = :id");
            $stmt->execute(['id' => $id_justificacion]);
            $id_asistencia = $stmt->fetch()['id_asistencia'];

            $stmt = db()->prepare("UPDATE asistencia SET estado = 'Justificado' WHERE id_asistencia = :ida");
            $stmt->execute(['ida' => $id_asistencia]);
        }

        // ✅ AUDITORÍA CORREGIDA (Instructivo Sección 2 - TEST-06)
        $accion_log = ($accion === 'aprobar') ? 'APROBAR_JUSTIFICACION' : 'RECHAZAR_JUSTIFICACION';
        $stmt = db()->prepare("
            INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora)
            VALUES (:id_usuario, :accion, 'justificacion', :id_registro, NOW())
        ");
        $stmt->execute([
            'id_usuario'  => $id_usuario_session,
            'accion'      => $accion_log,
            'id_registro' => $id_justificacion,
        ]);

        db()->commit();

        header('Location: /ue-ameliarios/public/index.php/justificaciones/revisar?exito=' . urlencode('Justificación ' . $nuevo_estado . ' correctamente.'));
        exit;

    } catch (Exception $e) {
        db()->rollBack();
        header('Location: /ue-ameliarios/public/index.php/justificaciones/revisar?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}