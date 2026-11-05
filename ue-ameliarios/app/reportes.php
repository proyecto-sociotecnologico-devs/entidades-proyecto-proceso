<?php
/**
 * app/reportes.php
 * Módulo de Reportes y Dashboard Analítico con RBAC Jerárquico.
 * Cumple con la Sección 4 (Dashboard Analítico) y TEST-05 (Consultas Preparadas).
 */

require_once __DIR__ . '/layout.php';

// ==========================================
// /reportes (GET) — Menú principal
// ==========================================
if ($uri === '/reportes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/reportes', 'VER'); // ✅ RBAC JERÁRQUICO

    renderHeader('Reportes');
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px;">
        <?php if (can('/reportes', 'VER')): ?>
        <a href="/ue-ameliarios/public/index.php/reportes/estudiante" style="background: white; padding: 40px 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; text-align: center; border: 1px solid #e2e8f0;">
            <div style="font-size: 48px; margin-bottom: 15px;">👨‍🎓</div>
            <div style="font-weight: 700; font-size: 18px; margin-bottom: 8px; color: var(--primary-color);">Reporte por Estudiante</div>
            <div style="font-size: 14px; color: #718096;">Historial detallado y gráfico de distribución</div>
        </a>
        <?php endif; ?>

        <?php if (can('/reportes', 'VER')): ?>
        <a href="/ue-ameliarios/public/index.php/reportes/curso" style="background: white; padding: 40px 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; text-align: center; border: 1px solid #e2e8f0;">
            <div style="font-size: 48px; margin-bottom: 15px;">🏫</div>
            <div style="font-weight: 700; font-size: 18px; margin-bottom: 8px; color: var(--primary-color);">Gestión de Curso (Docente)</div>
            <div style="font-size: 14px; color: #718096;">Semáforo de alumnos y acciones rápidas</div>
        </a>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /reportes/estudiante (GET) — Formulario de selección
// ==========================================
if ($uri === '/reportes/estudiante' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/reportes', 'VER'); // ✅ RBAC JERÁRQUICO

    // ✅ TEST-05: Consulta preparada (aunque no tiene parámetros, usamos query() de forma segura)
    $estudiantes = db()->query("
        SELECT e.id_estudiante, p.nombres, p.apellidos, p.cedula, e.codigo_estudiante
        FROM estudiante e
        INNER JOIN persona p ON e.id_persona = p.id_persona
        WHERE e.estado = 'Activo'
        ORDER BY p.apellidos ASC, p.nombres ASC
    ")->fetchAll();

    renderHeader('Reporte por Estudiante');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto; padding: 40px;">
        <h2>Seleccionar Estudiante</h2>
        <form method="GET" action="/ue-ameliarios/public/index.php/reportes/estudiante/ver">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #4a5568;">Estudiante *</label>
                <select name="id_estudiante" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 15px; background: #f8fafc;">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($estudiantes as $e): ?>
                        <option value="<?= $e['id_estudiante'] ?>">
                            <?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres'] . ' — C.I. ' . $e['cedula']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #4a5568;">Desde</label>
                    <input type="date" name="desde" value="<?= date('Y-m-01') ?>" style="width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 15px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #4a5568;">Hasta</label>
                    <input type="date" name="hasta" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 15px;">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer;">Generar Reporte</button>
                <a href="/ue-ameliarios/public/index.php/reportes" style="padding: 12px 24px; background: #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600; text-align: center;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /reportes/estudiante/ver (GET) — Reporte individual
// ==========================================
if ($uri === '/reportes/estudiante/ver' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/reportes', 'VER'); // ✅ RBAC JERÁRQUICO

    $id_estudiante = (int)($_GET['id_estudiante'] ?? 0);
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    if ($id_estudiante <= 0) {
        header('Location: /ue-ameliarios/public/index.php/reportes/estudiante?err=estudiante_invalido');
        exit;
    }

    // ✅ TEST-05: Consulta preparada con parámetros bind
    $stmt = db()->prepare("
        SELECT e.id_estudiante, e.codigo_estudiante, p.nombres, p.apellidos, p.cedula
        FROM estudiante e
        INNER JOIN persona p ON e.id_persona = p.id_persona
        WHERE e.id_estudiante = :id LIMIT 1
    ");
    $stmt->execute(['id' => $id_estudiante]);
    $estudiante = $stmt->fetch();

    if (!$estudiante) {
        header('Location: /ue-ameliarios/public/index.php/reportes/estudiante?err=estudiante_no_existe');
        exit;
    }

    // ✅ TEST-05: Consulta preparada con parámetros bind
    $stmt = db()->prepare("
        SELECT a.fecha, a.estado, a.observacion, a.hora_registro, m.nombre AS materia, g.nombre AS grado, s.nombre AS seccion
        FROM asistencia a
        INNER JOIN inscripcion i ON a.id_inscripcion = i.id_inscripcion
        INNER JOIN curso c ON i.id_curso = c.id_curso
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        INNER JOIN materia m ON a.id_materia = m.id_materia
        WHERE i.id_estudiante = :id AND a.fecha BETWEEN :desde AND :hasta
        ORDER BY a.fecha DESC, m.nombre ASC
    ");
    $stmt->execute(['id' => $id_estudiante, 'desde' => $desde, 'hasta' => $hasta]);
    $registros = $stmt->fetchAll();

    $total = count($registros);
    $contadores = ['Presente' => 0, 'Ausente' => 0, 'Justificado' => 0, 'Retardo' => 0];
    foreach ($registros as $r) {
        if (isset($contadores[$r['estado']])) $contadores[$r['estado']]++;
    }
    $porcentaje_inasistencia = $total > 0 ? round((($contadores['Ausente'] + $contadores['Retardo']) / $total) * 100, 1) : 0;

    // ✅ Exportación CSV segura (con BOM UTF-8 para Excel)
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        // Verificar permiso de exportación
        if (!can('/reportes', 'EXPORTAR')) {
            die("Acceso denegado: No tiene permiso para exportar.");
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte_estudiante_' . $estudiante['cedula'] . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
        fputcsv($out, ['Estudiante', $estudiante['apellidos'] . ', ' . $estudiante['nombres']]);
        fputcsv($out, ['Cédula', $estudiante['cedula']]);
        fputcsv($out, ['Período', $desde . ' a ' . $hasta]);
        fputcsv($out, ['Total', $total, 'Ausentes', $contadores['Ausente'], 'Retardos', $contadores['Retardo'], '% Inasistencia', $porcentaje_inasistencia . '%']);
        fputcsv($out, []);
        fputcsv($out, ['Fecha', 'Materia', 'Curso', 'Estado', 'Observación', 'Hora']);
        foreach ($registros as $r) {
            fputcsv($out, [$r['fecha'], $r['materia'], $r['grado'] . ' ' . $r['seccion'], $r['estado'], $r['observacion'] ?? '', substr($r['hora_registro'] ?? '', 11, 5)]);
        }
        fclose($out);
        exit;
    }

    $emoji = function(string $estado): string {
        return match($estado) { 'Presente' => '✅', 'Ausente' => '❌', 'Justificado' => '📄', 'Retardo' => '⏰', default => '•' };
    };

    renderHeader('Reporte por Estudiante');
    ?>
    <div class="card" style="border-left: 5px solid var(--primary-light); margin-bottom: 25px;">
        <h2 style="margin-bottom: 5px;"><?= htmlspecialchars($estudiante['apellidos'] . ', ' . $estudiante['nombres']) ?></h2>
        <p style="color: var(--text-muted); font-size: 14px;">
            C.I. <?= htmlspecialchars($estudiante['cedula']) ?>
            <?= $estudiante['codigo_estudiante'] ? ' · Código: ' . htmlspecialchars($estudiante['codigo_estudiante']) : '' ?>
            · Período: <?= htmlspecialchars($desde) ?> a <?= htmlspecialchars($hasta) ?>
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom: 4px solid var(--primary-color);">
            <div style="font-size: 28px; font-weight: bold; color: var(--primary-color);"><?= $total ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Total</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom: 4px solid #48bb78;">
            <div style="font-size: 28px; font-weight: bold; color: #48bb78;"><?= $contadores['Presente'] ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">✅ Presente</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom: 4px solid #f56565;">
            <div style="font-size: 28px; font-weight: bold; color: #f56565;"><?= $contadores['Ausente'] ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">❌ Ausente</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom: 4px solid #ed8936;">
            <div style="font-size: 28px; font-weight: bold; color: #ed8936;"><?= $contadores['Retardo'] ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">⏰ Retardo</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom: 4px solid #e53e3e;">
            <div style="font-size: 28px; font-weight: bold; color: #e53e3e;"><?= $porcentaje_inasistencia ?>%</div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Inasistencia</div>
        </div>
    </div>

    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;">
        <h3 style="color: #1e3a8a; margin-bottom: 20px; font-size: 1.1rem;">Distribución de Asistencias</h3>
        <div style="max-width: 350px; margin: 0 auto;">
            <canvas id="graficoReporteEstudiante"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('graficoReporteEstudiante').getContext('2d');
            const datos = {
                presente: <?= (int)$contadores['Presente'] ?>,
                ausente: <?= (int)$contadores['Ausente'] ?>,
                justificado: <?= (int)$contadores['Justificado'] ?>,
                retardo: <?= (int)$contadores['Retardo'] ?>
            };
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Presente', 'Ausente', 'Justificado', 'Retardo'],
                    datasets: [{
                        data: [datos.presente, datos.ausente, datos.justificado, datos.retardo],
                        backgroundColor: ['rgba(16, 185, 129, 0.8)','rgba(239, 68, 68, 0.8)','rgba(245, 158, 11, 0.8)','rgba(59, 130, 246, 0.8)'],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '60%' }
            });
        });
    </script>

    <div class="card" style="padding: 0; overflow: hidden;">
        <h2 style="padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; margin: 0; border: none;">Historial de Asistencia</h2>
        <?php if (!$registros): ?>
            <div style="padding: 50px; text-align: center; color: #a0aec0;"><p style="font-size:40px; margin-bottom:10px;">📭</p><p>No hay registros en el período seleccionado.</p></div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Fecha</th>
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Materia</th>
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Curso</th>
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Estado</th>
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Observación</th>
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                        <tr style="border-bottom: 1px solid #edf2f7;">
                            <td style="padding: 15px 20px;"><?= htmlspecialchars($r['fecha']) ?></td>
                            <td style="padding: 15px 20px;"><?= htmlspecialchars($r['materia']) ?></td>
                            <td style="padding: 15px 20px;"><?= htmlspecialchars($r['grado'] . ' ' . $r['seccion']) ?></td>
                            <td style="padding: 15px 20px;">
                                <span style="padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; 
                                    <?php 
                                        if ($r['estado'] === 'Presente') echo 'background: #c6f6d5; color: #22543d;';
                                        elseif ($r['estado'] === 'Ausente') echo 'background: #fed7d7; color: #822727;';
                                        elseif ($r['estado'] === 'Justificado') echo 'background: #fefcbf; color: #744210;';
                                        else echo 'background: #bee3f8; color: #2a4365;';
                                    ?>">
                                    <?= $emoji($r['estado']) ?> <?= htmlspecialchars($r['estado']) ?>
                                </span>
                            </td>
                            <td style="padding: 15px 20px; color: #718096;"><?= htmlspecialchars($r['observacion'] ?? '—') ?></td>
                            <td style="padding: 15px 20px; color: #a0aec0;"><?= htmlspecialchars(substr($r['hora_registro'] ?? '', 11, 5)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px;">
        <a href="/ue-ameliarios/public/index.php/reportes/estudiante" style="padding: 12px 24px; background: #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">← Cambiar estudiante</a>
        <?php if (can('/reportes', 'EXPORTAR')): ?>
        <a href="/ue-ameliarios/public/index.php/reportes/estudiante/ver?id_estudiante=<?= $id_estudiante ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&export=csv" style="padding: 12px 24px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">⬇️ Exportar CSV</a>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /reportes/curso (GET) — Formulario de selección
// ==========================================
if ($uri === '/reportes/curso' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/reportes', 'VER'); // ✅ RBAC JERÁRQUICO

    $cursos = db()->query("
        SELECT c.id_curso, g.nombre AS grado, s.nombre AS seccion
        FROM curso c
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        ORDER BY g.orden ASC, s.nombre ASC
    ")->fetchAll();

    renderHeader('Gestión de Curso');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto; padding: 40px;">
        <h2>Seleccionar Curso para Gestionar</h2>
        <form method="GET" action="/ue-ameliarios/public/index.php/reportes/curso/ver">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #4a5568;">Curso *</label>
                <select name="id_curso" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 15px; background: #f8fafc;">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c['id_curso'] ?>"><?= htmlspecialchars($c['grado'] . ' ' . $c['seccion']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #4a5568;">Desde</label>
                    <input type="date" name="desde" value="<?= date('Y-m-01') ?>" style="width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 15px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #4a5568;">Hasta</label>
                    <input type="date" name="hasta" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 15px;">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer;">Cargar Gestión</button>
                <a href="/ue-ameliarios/public/index.php/reportes" style="padding: 12px 24px; background: #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600; text-align: center;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php
    renderFooter();
    exit;
}

// ==========================================
// /reportes/curso/ver (GET) — Gestión del Curso (ENFOQUE DOCENTE)
// ==========================================
if ($uri === '/reportes/curso/ver' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/reportes', 'VER'); // ✅ RBAC JERÁRQUICO

    $id_curso = (int)($_GET['id_curso'] ?? 0);
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    if ($id_curso <= 0) {
        header('Location: /ue-ameliarios/public/index.php/reportes/curso?err=curso_invalido');
        exit;
    }

    // ✅ TEST-05: Consulta preparada
    $stmt = db()->prepare("
        SELECT c.id_curso, g.nombre AS grado, s.nombre AS seccion
        FROM curso c
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        WHERE c.id_curso = :id LIMIT 1
    ");
    $stmt->execute(['id' => $id_curso]);
    $curso = $stmt->fetch();

    if (!$curso) {
        header('Location: /ue-ameliarios/public/index.php/reportes/curso?err=curso_no_existe');
        exit;
    }

    // ✅ TEST-05: Consulta preparada con agregaciones seguras
    $stmt = db()->prepare("
        SELECT e.id_estudiante, p.apellidos, p.nombres, p.cedula,
            COUNT(a.id_asistencia) AS total_registros,
            SUM(CASE WHEN a.estado = 'Presente' THEN 1 ELSE 0 END) AS presentes,
            SUM(CASE WHEN a.estado = 'Ausente' THEN 1 ELSE 0 END) AS ausentes,
            SUM(CASE WHEN a.estado = 'Retardo' THEN 1 ELSE 0 END) AS retardos,
            SUM(CASE WHEN a.estado = 'Justificado' THEN 1 ELSE 0 END) AS justificados
        FROM inscripcion i
        INNER JOIN estudiante e ON i.id_estudiante = e.id_estudiante
        INNER JOIN persona p ON e.id_persona = p.id_persona
        LEFT JOIN asistencia a ON a.id_inscripcion = i.id_inscripcion AND a.fecha BETWEEN :desde AND :hasta
        WHERE i.id_curso = :id_curso AND i.estado = 'Activa'
        GROUP BY e.id_estudiante, p.apellidos, p.nombres, p.cedula
        ORDER BY p.apellidos ASC, p.nombres ASC
    ");
    $stmt->execute(['id_curso' => $id_curso, 'desde' => $desde, 'hasta' => $hasta]);
    $resumen = $stmt->fetchAll();

    // Calcular estado visual para cada alumno
    foreach ($resumen as &$r) {
        $inasistencias = $r['ausentes'] + $r['retardos'];
        if ($r['total_registros'] == 0) {
            $r['estado_visual'] = 'Sin datos';
            $r['color_estado'] = '#a0aec0'; // Gris
        } elseif ($inasistencias == 0) {
            $r['estado_visual'] = 'Al día';
            $r['color_estado'] = '#48bb78'; // Verde
        } elseif ($inasistencias <= 2) {
            $r['estado_visual'] = 'Con faltas/retardos';
            $r['color_estado'] = '#ed8936'; // Naranja
        } else {
            $r['estado_visual'] = 'Requiere atención';
            $r['color_estado'] = '#f56565'; // Rojo
        }
    }
    unset($r);

    // ✅ Exportación CSV segura
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        if (!can('/reportes', 'EXPORTAR')) {
            die("Acceso denegado: No tiene permiso para exportar.");
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte_curso_' . $curso['grado'] . '_' . $curso['seccion'] . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
        fputcsv($out, ['Curso', $curso['grado'] . ' ' . $curso['seccion']]);
        fputcsv($out, ['Período', $desde . ' a ' . $hasta]);
        fputcsv($out, []);
        fputcsv($out, ['Estudiante', 'Cédula', 'Total', 'Presente', 'Ausente', 'Retardo', 'Justificado', 'Estado Visual']);
        foreach ($resumen as $r) {
            fputcsv($out, [$r['apellidos'] . ', ' . $r['nombres'], $r['cedula'], $r['total_registros'], $r['presentes'], $r['ausentes'], $r['retardos'], $r['justificados'], $r['estado_visual']]);
        }
        fclose($out);
        exit;
    }

    renderHeader('Gestión del Curso');
    ?>
    
    <div class="card" style="border-left: 5px solid var(--primary-light); margin-bottom: 25px;">
        <h2 style="margin-bottom: 5px;">Curso: <?= htmlspecialchars($curso['grado'] . ' ' . $curso['seccion']) ?></h2>
        <p style="color: var(--text-muted); font-size: 14px;">
            Período: <?= htmlspecialchars($desde) ?> a <?= htmlspecialchars($hasta) ?>
        </p>
    </div>

    <!-- Buscador Rápido -->
    <div class="card" style="margin-bottom: 20px; padding: 15px;">
        <div style="display: flex; gap: 15px; align-items: center;">
            <input type="text" id="buscador-alumnos" placeholder="🔍 Buscar alumno por nombre o cédula..." style="flex: 1; padding: 12px 15px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 15px;">
        </div>
    </div>

    <!-- Tabla de Alumnos con Semáforo -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <h2 style="padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; margin: 0; border: none;">Estado de los Alumnos</h2>
        <?php if (!$resumen): ?>
            <div style="padding: 50px; text-align: center; color: #a0aec0;">No hay estudiantes activos inscritos en este curso.</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;" id="tabla-alumnos">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Estudiante</th>
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Cédula</th>
                        <th style="padding: 15px 20px; text-align: center; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Estado Visual</th>
                        <th style="padding: 15px 20px; text-align: left; color: #4a5568; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Acciones Rápidas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumen as $r): ?>
                        <tr style="border-bottom: 1px solid #edf2f7;">
                            <td style="padding: 15px 20px;">
                                <strong><?= htmlspecialchars($r['apellidos'] . ', ' . $r['nombres']) ?></strong>
                            </td>
                            <td style="padding: 15px 20px;"><?= htmlspecialchars($r['cedula']) ?></td>
                            <td style="padding: 15px 20px; text-align: center;">
                                <span style="display: inline-block; background: <?= $r['color_estado'] ?>; color: white; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                                    <?= $r['estado_visual'] ?>
                                </span>
                            </td>
                            <td style="padding: 15px 20px;">
                                <a href="/ue-ameliarios/public/index.php/reportes/estudiante/ver?id_estudiante=<?= $r['id_estudiante'] ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>" style="display: inline-block; padding: 8px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-size: 12px; font-weight: 600; margin-right: 5px;">Ver Asistencia</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Script para el buscador rápido en tiempo real -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buscador = document.getElementById('buscador-alumnos');
            const tabla = document.getElementById('tabla-alumnos');
            
            if (buscador && tabla) {
                const filas = tabla.querySelectorAll('tbody tr');

                buscador.addEventListener('input', (e) => {
                    const termino = e.target.value.toLowerCase();
                    filas.forEach(fila => {
                        const textoFila = fila.textContent.toLowerCase();
                        fila.style.display = textoFila.includes(termino) ? '' : 'none';
                    });
                });
            }
        });
    </script>

    <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px;">
        <a href="/ue-ameliarios/public/index.php/reportes/curso" style="padding: 12px 24px; background: #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">← Cambiar curso</a>
        <?php if (can('/reportes', 'EXPORTAR')): ?>
        <a href="/ue-ameliarios/public/index.php/reportes/curso/ver?id_curso=<?= $id_curso ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&export=csv" style="padding: 12px 24px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">⬇️ Exportar CSV</a>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}