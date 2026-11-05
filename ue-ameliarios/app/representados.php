<?php
/**
 * app/representados.php
 * Lista de estudiantes del Representante.
 */

require_once __DIR__ . '/layout.php';

if ($uri === '/representados' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();

    // 1. Buscar el id_representante del usuario logueado
    $stmt = db()->prepare("
        SELECT r.id_representante 
        FROM representante r
        INNER JOIN usuario u ON r.id_persona = u.id_persona
        WHERE u.id_usuario = :idu
    ");
    $stmt->execute(['idu' => $_SESSION['user']['id_usuario']]);
    $rep = $stmt->fetch();

    if (!$rep) {
        die("Error: El usuario actual no tiene un perfil de representante asignado.");
    }

    $id_representante = $rep['id_representante'];

    // 2. Obtener los estudiantes vinculados
    $stmt = db()->prepare("
        SELECT e.id_estudiante, e.codigo_estudiante, p.nombres, p.apellidos, p.cedula
        FROM representante_estudiante re
        INNER JOIN estudiante e ON re.id_estudiante = e.id_estudiante
        INNER JOIN persona p ON e.id_persona = p.id_persona
        WHERE re.id_representante = :idr AND e.estado = 'Activo'
        ORDER BY p.apellidos ASC, p.nombres ASC
    ");
    $stmt->execute(['idr' => $id_representante]);
    $estudiantes = $stmt->fetchAll();

    renderHeader('Mis Representados');
    ?>
    <?php if (!$estudiantes): ?>
        <div class="card" style="text-align: center; padding: 50px 20px; color: #888;">
            <p style="font-size:50px; margin-bottom:15px;">👨‍👩‍👧</p>
            <h3>No tienes estudiantes vinculados</h3>
            <p style="margin-top:10px; font-size:14px;">Contacta al administrador para vincular a tus representados.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px;">
            <?php foreach ($estudiantes as $e): ?>
                <div style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0; display: flex; flex-direction: column;">
                    <div style="background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); padding: 20px; color: white; display: flex; align-items: center; gap: 15px;">
                        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: bold; border: 2px solid rgba(255,255,255,0.5);">
                            <?= strtoupper(substr($e['nombres'], 0, 1) . substr($e['apellidos'], 0, 1)) ?>
                        </div>
                        <div>
                            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 2px;"><?= htmlspecialchars($e['nombres']) ?></h3>
                            <span style="font-size: 12px; opacity: 0.9; background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; display: inline-block;"><?= htmlspecialchars($e['codigo_estudiante']) ?></span>
                        </div>
                    </div>
                    <div style="padding: 20px; flex: 1;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">
                            <span style="color: #718096; font-weight: 500;">Apellidos:</span>
                            <span style="color: #2d3748; font-weight: 600;"><?= htmlspecialchars($e['apellidos']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">
                            <span style="color: #718096; font-weight: 500;">Cédula Escolar:</span>
                            <span style="color: #2d3748; font-weight: 600;"><?= htmlspecialchars($e['cedula']) ?></span>
                        </div>
                    </div>
                    <div style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center;">
                        <a href="/ue-ameliarios/public/index.php/reportes/estudiante/ver?id_estudiante=<?= $e['id_estudiante'] ?>" style="display: inline-block; width: 100%; padding: 10px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                            Ver Reporte de Asistencia
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    renderFooter();
    exit;
}