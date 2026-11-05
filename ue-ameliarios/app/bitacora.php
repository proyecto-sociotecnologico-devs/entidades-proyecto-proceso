<?php
/**
 * app/bitacora.php (o auditoria.php)
 * Módulo de Auditoría del Sistema (TEST-06: Auditoría Activa).
 * Cumple estrictamente con el esquema 'auditoria_logs' del Instructivo UNERMB.
 */

require_once __DIR__ . '/layout.php';

// ==========================================
// /bitacora (GET) — Listado con filtros, paginación y exportación
// ==========================================
if ($uri === '/bitacora' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/bitacora', 'VER'); // ✅ RBAC JERÁRQUICO

    // --- Filtros ---
    $f_usuario  = $_GET['id_usuario'] ?? '';
    $f_accion   = trim($_GET['accion'] ?? '');
    $f_desde    = $_GET['desde'] ?? '';
    $f_hasta    = $_GET['hasta'] ?? '';

    $where  = [];
    $params = [];

    if ($f_usuario !== '') {
        $where[] = 'a.usuario_id = :usuario_id';
        $params['usuario_id'] = (int)$f_usuario;
    }
    if ($f_accion !== '') {
        $where[] = '(a.accion LIKE :accion OR a.tabla_afectada LIKE :accion)';
        $params['accion'] = '%' . $f_accion . '%';
    }
    if ($f_desde !== '') {
        $where[] = 'a.fecha_hora >= :desde';
        $params['desde'] = $f_desde . ' 00:00:00';
    }
    if ($f_hasta !== '') {
        $where[] = 'a.fecha_hora <= :hasta';
        $params['hasta'] = $f_hasta . ' 23:59:59';
    }

    // Armamos la parte de la consulta que es común para conteo y listado
    // ✅ ESQUEMA ESTRICTO DEL INSTRUCTIVO: tabla 'auditoria_logs'
    $sql_base = "
        FROM auditoria_logs a
        LEFT JOIN usuario u ON a.usuario_id = u.id_usuario
        LEFT JOIN persona p ON u.id_persona = p.id_persona
    ";
    if ($where) {
        $sql_base .= ' WHERE ' . implode(' AND ', $where);
    }

    // --- Lógica para Exportar a CSV (Descarga TODO sin paginar) ---
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        // ✅ TEST-04: Validar permiso de exportación
        if (!can('/bitacora', 'EXPORTAR')) {
            die("Acceso denegado: No tiene permiso para exportar la bitácora.");
        }

        $sql_export = "SELECT a.id, a.fecha_hora, a.accion, a.tabla_afectada, a.registro_id, u.username, p.nombres, p.apellidos " . $sql_base . " ORDER BY a.fecha_hora DESC";
        $stmt = db()->prepare($sql_export);
        $stmt->execute($params);
        $datos_export = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=auditoria_' . date('Y-m-d_H-i') . '.csv');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

        // Encabezados alineados con el instructivo
        fputcsv($output, ['ID Log', 'Fecha y Hora', 'Usuario (Sistema)', 'Nombre Completo', 'Acción', 'Tabla Afectada', 'ID Registro'], ';');

        foreach ($datos_export as $d) {
            $nombre_completo = trim(($d['apellidos'] ?? '') . ' ' . ($d['nombres'] ?? ''));
            fputcsv($output, [
                $d['id'],
                $d['fecha_hora'],
                $d['username'] ?? 'Sistema/Automático',
                $nombre_completo ?: 'N/A',
                $d['accion'],
                $d['tabla_afectada'],
                $d['registro_id'] ?? 'N/A'
            ], ';');
        }
        fclose($output);
        exit; 
    }

    // --- Lógica de Paginación ---
    $por_pagina = 20;
    $pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina_actual < 1) $pagina_actual = 1;
    $inicio = ($pagina_actual - 1) * $por_pagina;

    // Contamos el total de registros con los filtros actuales
    $sql_count = "SELECT COUNT(*) " . $sql_base;
    $stmt_count = db()->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = (int) $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $por_pagina);

    // Consulta principal paginada
    $sql = "SELECT a.id, a.fecha_hora, a.accion, a.tabla_afectada, a.registro_id, u.username, p.nombres, p.apellidos " . $sql_base . " ORDER BY a.fecha_hora DESC LIMIT $por_pagina OFFSET $inicio";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll();

    // Usuarios para el filtro select
    $usuarios = db()->query("
        SELECT u.id_usuario, u.username, p.nombres, p.apellidos
        FROM usuario u
        LEFT JOIN persona p ON u.id_persona = p.id_persona
        ORDER BY p.apellidos ASC, p.nombres ASC
    ")->fetchAll();

    renderHeader('Auditoría del Sistema');
    ?>

    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="/ue-ameliarios/public/index.php/bitacora">
            <div style="display: grid; grid-template-columns: 1.5fr 1.5fr 1fr 1fr auto; gap: 10px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #4a5568;">Usuario</label>
                    <select name="id_usuario" style="width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id_usuario'] ?>" <?= ($f_usuario == $u['id_usuario']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username'] . ' — ' . ($u['apellidos'] ?? '') . ' ' . ($u['nombres'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #4a5568;">Acción / Tabla</label>
                    <input type="text" name="accion" placeholder="🔍 Ej: CREAR, estudiante..." value="<?= htmlspecialchars($f_accion) ?>" style="width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #4a5568;">Desde</label>
                    <input type="date" name="desde" value="<?= htmlspecialchars($f_desde) ?>" style="width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #4a5568;">Hasta</label>
                    <input type="date" name="hasta" value="<?= htmlspecialchars($f_hasta) ?>" style="width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                </div>
                <button type="submit" style="padding: 10px 18px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; height: 38px;">Filtrar</button>
            </div>
            
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <a href="/ue-ameliarios/public/index.php/bitacora" style="font-size: 12px; padding: 6px 12px; color: var(--primary-color); border: 1px solid var(--primary-color); border-radius: 6px; text-decoration: none;">Limpiar filtros</a>
                
                <?php if (can('/bitacora', 'EXPORTAR')): // ✅ TEST-04 ?>
                    <?php 
                        $query_export = $_GET; 
                        $query_export['export'] = 'csv';
                        $url_export = '/ue-ameliarios/public/index.php/bitacora?' . http_build_query($query_export);
                    ?>
                    <a href="<?= htmlspecialchars($url_export) ?>" style="font-size: 12px; padding: 6px 12px; background: #10b981; color: white; border: 1px solid #10b981; border-radius: 6px; text-decoration: none; font-weight: bold;">⬇️ Exportar a Excel (CSV)</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <?php if (!$registros): ?>
            <div style="padding: 40px; text-align: center; color: #888;">No hay registros de auditoría que coincidan con los filtros.</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: var(--primary-color); color: white;">
                        <th style="padding: 10px 12px; text-align: left; font-weight: normal;">Fecha y Hora</th>
                        <th style="padding: 10px 12px; text-align: left; font-weight: normal;">Usuario</th>
                        <th style="padding: 10px 12px; text-align: left; font-weight: normal;">Acción</th>
                        <th style="padding: 10px 12px; text-align: left; font-weight: normal;">Tabla Afectada</th>
                        <th style="padding: 10px 12px; text-align: left; font-weight: normal;">ID Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 10px 12px; white-space: nowrap;"><?= htmlspecialchars($r['fecha_hora']) ?></td>
                            <td style="padding: 10px 12px;">
                                <?php if ($r['username']): ?>
                                    <strong><?= htmlspecialchars($r['username']) ?></strong><br>
                                    <small style="color:#888;"><?= htmlspecialchars(($r['apellidos'] ?? '') . ' ' . ($r['nombres'] ?? '')) ?></small>
                                <?php else: ?>
                                    <em style="color:#999;">Sistema / Background</em>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 12px;">
                                <span style="display: inline-block; background: #e7f3ff; color: var(--primary-color); padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                                    <?= htmlspecialchars($r['accion']) ?>
                                </span>
                            </td>
                            <td style="padding: 10px 12px; font-family: monospace; font-size: 12px;"><?= htmlspecialchars($r['tabla_afectada']) ?></td>
                            <td style="padding: 10px 12px; font-weight: bold; color: #666;"><?= htmlspecialchars($r['registro_id'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Controles de Paginación -->
    <?php if ($total_paginas > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; margin-bottom: 30px;">
            
            <!-- Botón Anterior -->
            <?php if ($pagina_actual > 1): ?>
                <?php $query_ant = array_merge($_GET, ['pagina' => $pagina_actual - 1]); ?>
                <a href="?<?= http_build_query($query_ant) ?>" style="padding: 8px 16px; background: white; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: var(--primary-color); font-weight: bold; font-size: 14px;">&laquo; Anterior</a>
            <?php else: ?>
                <span style="padding: 8px 16px; background: #f9f9f9; border: 1px solid #eee; border-radius: 6px; color: #ccc; font-size: 14px;">&laquo; Anterior</span>
            <?php endif; ?>

            <!-- Números de Página -->
            <?php 
            $rango = 2; 
            for ($i = 1; $i <= $total_paginas; $i++) {
                if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - $rango && $i <= $pagina_actual + $rango)) {
                    $query_pag = array_merge($_GET, ['pagina' => $i]);
                    $active_style = ($i == $pagina_actual) ? 'background: var(--primary-color); color: white; border-color: var(--primary-color);' : 'background: white; color: var(--primary-color); border-color: #ddd;';
                    echo '<a href="?' . http_build_query($query_pag) . '" style="padding: 8px 14px; border: 1px solid; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; ' . $active_style . '">' . $i . '</a>';
                } elseif ($i == $pagina_actual - $rango - 1 || $i == $pagina_actual + $rango + 1) {
                    echo '<span style="padding: 8px; color: #888;">...</span>';
                }
            }
            ?>

            <!-- Botón Siguiente -->
            <?php if ($pagina_actual < $total_paginas): ?>
                <?php $query_sig = array_merge($_GET, ['pagina' => $pagina_actual + 1]); ?>
                <a href="?<?= http_build_query($query_sig) ?>" style="padding: 8px 16px; background: white; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: var(--primary-color); font-weight: bold; font-size: 14px;">Siguiente &raquo;</a>
            <?php else: ?>
                <span style="padding: 8px 16px; background: #f9f9f9; border: 1px solid #eee; border-radius: 6px; color: #ccc; font-size: 14px;">Siguiente &raquo;</span>
            <?php endif; ?>

        </div>
    <?php endif; ?>

    <?php
    renderFooter();
    exit;
}