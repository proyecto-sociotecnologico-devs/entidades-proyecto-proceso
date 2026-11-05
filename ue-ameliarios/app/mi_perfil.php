<?php
/**
 * app/mi_perfil.php
 * Perfil del Estudiante.
 */

require_once __DIR__ . '/layout.php';

if ($uri === '/mi_perfil' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();

    // 1. Obtener id_estudiante, datos personales y correo
    $stmt = db()->prepare("
        SELECT e.id_estudiante, p.nombres, p.apellidos, p.cedula, p.fecha_nacimiento, u.correo
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        LEFT JOIN estudiante e ON p.id_persona = e.id_persona
        WHERE u.id_usuario = :idu
    ");
    $stmt->execute(['idu' => $_SESSION['user']['id_usuario']]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        die("Error: No se encontraron datos para este usuario.");
    }

    // 2. Buscar curso y sección (si está inscrito)
    $curso = null;
    if ($perfil['id_estudiante']) {
        $stmt = db()->prepare("
            SELECT g.nombre AS grado, s.nombre AS seccion, pa.nombre AS periodo
            FROM inscripcion i
            INNER JOIN curso c ON i.id_curso = c.id_curso
            INNER JOIN grado g ON c.id_grado = g.id_grado
            INNER JOIN seccion s ON c.id_seccion = s.id_seccion
            INNER JOIN periodo_academico pa ON c.id_periodo = pa.id_periodo
            WHERE i.id_estudiante = :ide AND i.estado = 'Activa'
            LIMIT 1
        ");
        $stmt->execute(['ide' => $perfil['id_estudiante']]);
        $curso = $stmt->fetch();
    }

    // 3. Buscar representante (si existe)
    $representante = null;
    if ($perfil['id_estudiante']) {
        $stmt = db()->prepare("
            SELECT p.nombres, p.apellidos, p.cedula, p.telefono
            FROM representante r
            INNER JOIN representante_estudiante re ON r.id_representante = re.id_representante
            INNER JOIN persona p ON r.id_persona = p.id_persona
            WHERE re.id_estudiante = :ide
            LIMIT 1
        ");
        $stmt->execute(['ide' => $perfil['id_estudiante']]);
        $representante = $stmt->fetch();
    }

    // Iniciales para el avatar
    $iniciales = strtoupper(substr($perfil['nombres'], 0, 1) . substr($perfil['apellidos'], 0, 1));

    // Renderizar la cabecera del layout
    renderHeader('Mi Perfil');
    ?>

    <div class="card" style="display: flex; align-items: center; gap: 25px; border-left: 5px solid var(--primary-color);">
        <div style="width: 80px; height: 80px; background: #e5e7eb; color: var(--primary-color); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 32px; font-weight: bold; flex-shrink: 0;">
            <?= $iniciales ?>
        </div>
        <div>
            <h2 style="border: none; padding: 0; margin-bottom: 5px;"><?= htmlspecialchars($perfil['nombres'] . ' ' . $perfil['apellidos']) ?></h2>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 2px;">C.I. <?= htmlspecialchars($perfil['cedula']) ?></p>
            <?php if ($curso): ?>
                <p style="color: var(--text-muted); font-size: 14px;"><?= htmlspecialchars($curso['grado'] . ' ' . $curso['seccion']) ?> — <?= htmlspecialchars($curso['periodo']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Datos Personales -->
    <div class="card">
        <h2>👤 Datos Personales</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Nombres</span>
                <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($perfil['nombres']) ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Apellidos</span>
                <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($perfil['apellidos']) ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Fecha de Nacimiento</span>
                <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($perfil['fecha_nacimiento'] ?? 'No registrada') ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Correo Electrónico</span>
                <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($perfil['correo'] ?? 'No registrado') ?></span>
            </div>
        </div>
    </div>

    <!-- Información Académica -->
    <div class="card">
        <h2>🎓 Información Académica</h2>
        <?php if ($curso): ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Grado / Curso</span>
                    <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($curso['grado']) ?></span>
                </div>
                <div>
                    <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Sección</span>
                    <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($curso['seccion']) ?></span>
                </div>
                <div style="grid-column: 1 / -1;">
                    <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Período Académico</span>
                    <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($curso['periodo']) ?></span>
                </div>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 20px;">No tienes un curso activo asignado.</p>
        <?php endif; ?>
    </div>

    <!-- Datos del Representante -->
    <div class="card">
        <h2>👨‍👩‍👧 Mi Representante</h2>
        <?php if ($representante): ?>
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: #e5e7eb; color: var(--primary-color); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; font-weight: bold;">
                    <?= strtoupper(substr($representante['nombres'], 0, 1) . substr($representante['apellidos'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($representante['apellidos'] . ', ' . $representante['nombres']) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">C.I. <?= htmlspecialchars($representante['cedula']) ?></div>
                </div>
            </div>
            <div>
                <span style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Teléfono de Contacto</span>
                <span style="font-size: 16px; font-weight: 500;"><?= htmlspecialchars($representante['telefono'] ?? 'No registrado') ?></span>
            </div>
            <?php if (!empty($representante['telefono'])): ?>
                <a href="https://wa.me/58<?= preg_replace('/[^0-9]/', '', $representante['telefono']) ?>" target="_blank" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #25d366; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    Contactar por WhatsApp
                </a>
            <?php endif; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 20px;">No hay representante asignado.</p>
        <?php endif; ?>
    </div>

    <?php
    // Renderizar el pie del layout
    renderFooter();
    exit;
}