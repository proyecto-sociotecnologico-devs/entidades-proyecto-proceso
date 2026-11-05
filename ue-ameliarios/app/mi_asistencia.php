<?php
/**
 * app/mi_asistencia.php
 * Historial de asistencia para el Estudiante.
 */

require_once __DIR__ . '/layout.php';

if ($uri === '/mi_asistencia' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();

    // Buscar el id_estudiante usando el id_usuario de la sesión
    $stmt = db()->prepare("
        SELECT e.id_estudiante, p.nombres, p.apellidos, p.cedula, e.codigo_estudiante
        FROM estudiante e
        INNER JOIN persona p ON e.id_persona = p.id_persona
        INNER JOIN usuario u ON p.id_persona = u.id_persona
        WHERE u.id_usuario = :idu
        LIMIT 1
    ");
    $stmt->execute(['idu' => $_SESSION['user']['id_usuario']]);
    $estudiante = $stmt->fetch();

    // Si no existe como estudiante, mostramos un mensaje amable
    if (!$estudiante) {
        renderHeader('Mi Asistencia');
        ?>
        <div class="card" style="text-align: center; padding: 40px;">
            <h2>Mi Asistencia</h2>
            <p style="color: #666; margin: 15px 0;">Hola, <strong><?= htmlspecialchars($_SESSION['user']['nombres'] . ' ' . $_SESSION['user']['apellidos']) ?></strong>.</p>
            <p style="color: #666; margin-bottom: 20px;">Aún no tienes un perfil de estudiante asignado con registros de asistencia.</p>
            <p style="color: #666; margin-bottom: 20px;">Contacta al administrador para que vincule tu perfil.</p>
            <a href="/ue-ameliarios/public/index.php/dashboard" style="display: inline-block; padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px;">Volver al Panel</a>
        </div>
        <?php
        renderFooter();
        exit;
    }

    $id_estudiante = $estudiante['id_estudiante'];

    // Consultar asistencia
    $stmt = db()->prepare("
        SELECT a.fecha, a.estado, a.observacion, a.hora_registro,
               m.nombre AS materia, g.nombre AS grado, s.nombre AS seccion
        FROM asistencia a
        INNER JOIN inscripcion i ON a.id_inscripcion = i.id_inscripcion
        INNER JOIN curso c ON i.id_curso = c.id_curso
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        INNER JOIN materia m ON a.id_materia = m.id_materia
        WHERE i.id_estudiante = :ide
        ORDER BY a.fecha DESC
        LIMIT 100
    ");
    $stmt->execute(['ide' => $id_estudiante]);
    $registros = $stmt->fetchAll();

    // Contadores
    $contadores = ['Presente' => 0, 'Ausente' => 0, 'Justificado' => 0, 'Retardo' => 0];
    foreach ($registros as $r) {
        if (isset($contadores[$r['estado']])) {
            $contadores[$r['estado']]++;
        }
    }

    renderHeader('Mi Asistencia');
    ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>Historial de Asistencia</h2>
        <p style="color: #666; margin-top: 5px;">Estudiante: <strong><?= htmlspecialchars($estudiante['apellidos'] . ', ' . $estudiante['nombres']) ?></strong> (<?= htmlspecialchars($estudiante['codigo_estudiante']) ?>)</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.08); border-bottom: 4px solid #28a745;">
            <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?= $contadores['Presente'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">✅ Presente</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.08); border-bottom: 4px solid #dc3545;">
            <div style="font-size: 24px; font-weight: bold; color: #dc3545;"><?= $contadores['Ausente'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">❌ Ausente</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.08); border-bottom: 4px solid #ffc107;">
            <div style="font-size: 24px; font-weight: bold; color: #ffc107;"><?= $contadores['Justificado'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">📄 Justificado</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.08); border-bottom: 4px solid #17a2b8;">
            <div style="font-size: 24px; font-weight: bold; color: #17a2b8;"><?= $contadores['Retardo'] ?></div>
            <div style="font-size: 12px; color: #777; text-transform: uppercase;">⏰ Retardo</div>
        </div>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <?php if (!$registros): ?>
            <div style="padding: 40px; text-align: center; color: #888;">No hay registros de asistencia aún.</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: var(--primary-color); color: white;">
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Fecha</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Materia</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Curso</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Estado</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: normal;">Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($r['fecha']) ?></td>
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($r['materia']) ?></td>
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($r['grado'] . ' ' . $r['seccion']) ?></td>
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($r['estado']) ?></td>
                            <td style="padding: 12px 15px;"><?= htmlspecialchars($r['observacion'] ?? '—') ?></td>
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