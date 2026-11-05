<?php
/**
 * app/roles.php
 * Gestión de Roles con Matriz de Permisos RBAC Jerárquico (3 Niveles).
 * CORREGIDO: Enrutamiento robusto con strpos y soporte para $routeParams['id'].
 */
require_once __DIR__ . '/layout.php';

// ==========================================
// /roles (GET) — Listado
// ==========================================
if (strpos($uri, 'roles') !== false && strpos($uri, 'roles/editar') === false && strpos($uri, 'roles/crear') === false && strpos($uri, 'roles/eliminar') === false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { 
        header('Location: /ue-ameliarios/public/index.php/dashboard?error=Acceso+denegado'); exit; 
    }

    $roles = db()->query("SELECT id_rol, nombre, descripcion, es_sistema, fecha_creacion FROM rol ORDER BY nombre ASC")->fetchAll();

    renderHeader('Gestión de Roles y Permisos');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Roles del Sistema</h2>
        <a href="/ue-ameliarios/public/index.php/roles/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nuevo Rol</a>
    </div>

    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">ID</th>
                <th style="padding: 12px; text-align: left;">Nombre del Rol</th>
                <th style="padding: 12px; text-align: left;">Descripción</th>
                <th style="padding: 12px; text-align: left;">Tipo</th>
                <th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?= $r['id_rol'] ?></td>
                    <td style="padding: 12px;"><strong><?= htmlspecialchars($r['nombre']) ?></strong></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($r['descripcion'] ?? '-') ?></td>
                    <td style="padding: 12px;">
                        <?= $r['es_sistema'] ? '<span style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Sistema</span>' : '<span style="background: #d1fae5; color: #065f46; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Personalizado</span>' ?>
                    </td>
                    <td style="padding: 12px;">
                        <a href="/ue-ameliarios/public/index.php/roles/editar/<?= $r['id_rol'] ?>" style="color: #2c5aa0; margin-right: 10px; font-weight: 600;">⚙️ Editar / Permisos</a>
                        <?php if ($r['nombre'] !== 'Administrador' && !$r['es_sistema']): ?>
                            <a href="/ue-ameliarios/public/index.php/roles/eliminar/<?= $r['id_rol'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar este rol? Se revocarán todos sus permisos.')">Eliminar</a>
                        <?php else: ?>
                            <span style="color: #9ca3af; font-size: 12px;">(Protegido)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /roles/crear (GET y POST)
// ==========================================
if (strpos($uri, 'roles/crear') !== false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if (empty($nombre)) {
            header('Location: /ue-ameliarios/public/index.php/roles/crear?error=El+nombre+es+obligatorio'); exit;
        }

        try {
            db()->beginTransaction();
            db()->prepare("INSERT INTO rol (nombre, descripcion) VALUES (:n, :d)")
                ->execute(['n' => $nombre, 'd' => $descripcion]);
            $id_rol = db()->lastInsertId();
            
            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_ROL', 'rol', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id_rol]);
            
            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/roles?exito=Rol+creado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/roles/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    renderHeader('Crear Nuevo Rol');
    ?>
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <h2>Crear Nuevo Rol</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        
        <form method="POST" action="/ue-ameliarios/public/index.php/roles/crear">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; font-size: 14px; display: block; margin-bottom: 5px;">Nombre del Rol *</label>
                <input type="text" name="nombre" placeholder="Ej: Coordinador Académico" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; font-size: 14px; display: block; margin-bottom: 5px;">Descripción</label>
                <textarea name="descripcion" rows="3" placeholder="Describa las funciones de este rol..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Crear Rol</button>
                <a href="/ue-ameliarios/public/index.php/roles" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /roles/editar (GET y POST) - Matriz de Permisos RBAC 3 Niveles
// ==========================================
if (strpos($uri, 'roles/editar') !== false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }

    // Extracción robusta del ID: Prioriza $routeParams, luego busca en la URL
    $id = (int)($routeParams['id'] ?? 0);
    if ($id === 0) {
        $parts = explode('/', trim($uri, '/'));
        foreach ($parts as $part) {
            if (is_numeric($part)) { $id = (int)$part; break; }
        }
    }
    
    if ($id === 0) die("Error: ID no válido. URL detectada: " . htmlspecialchars($uri));

    $stmt = db()->prepare("SELECT id_rol, nombre, descripcion, es_sistema FROM rol WHERE id_rol = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $rol = $stmt->fetch();
    if (!$rol) die("Error: Rol no encontrado.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $modulos_habilitados = $_POST['modulos'] ?? []; 
        $permisos_vistas = $_POST['permisos'] ?? []; 

        if (empty($nombre)) die("Error: El nombre es obligatorio.");

        try {
            db()->beginTransaction();
            
            db()->prepare("UPDATE rol SET nombre = :n, descripcion = :d WHERE id_rol = :id")
                ->execute(['n' => $nombre, 'd' => $descripcion, 'id' => $id]);
            
            db()->prepare("DELETE FROM r_modulo_rol WHERE rol_id = :id")->execute(['id' => $id]);
            
            $stmtModRol = db()->prepare("INSERT INTO r_modulo_rol (rol_id, modulo_id, habilitado) VALUES (:rol_id, :modulo_id, 1)");
            $stmtVistaPerm = db()->prepare("INSERT INTO vistas_permiso (r_modulo_rol_id, vista_id, permiso_id, permitido) VALUES (:r_modulo_rol_id, :vista_id, :permiso_id, 1)");
            
            foreach ($modulos_habilitados as $modulo_id) {
                $stmtModRol->execute(['rol_id' => $id, 'modulo_id' => (int)$modulo_id]);
                $new_r_modulo_rol_id = db()->lastInsertId();
                
                if (isset($permisos_vistas[$modulo_id])) {
                    foreach ($permisos_vistas[$modulo_id] as $vista_id => $permisos_array) {
                        foreach ($permisos_array as $permiso_id) {
                            $stmtVistaPerm->execute([
                                'r_modulo_rol_id' => $new_r_modulo_rol_id,
                                'vista_id' => (int)$vista_id,
                                'permiso_id' => (int)$permiso_id
                            ]);
                        }
                    }
                }
            }
            
            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_ROL', 'rol', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id]);
            
            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/roles?exito=Rol+y+permisos+actualizados+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    $modulos = db()->query("SELECT id, nombre_modulo, icono, orden FROM modulo ORDER BY orden ASC, nombre_modulo ASC")->fetchAll();
    $permisos_disponibles = db()->query("SELECT id, nombre_permiso FROM permisos ORDER BY id ASC")->fetchAll();
    
    $modulos_habilitados = [];
    $stmtMod = db()->prepare("SELECT id, modulo_id FROM r_modulo_rol WHERE rol_id = :id");
    $stmtMod->execute(['id' => $id]);
    foreach ($stmtMod->fetchAll() as $rmr) {
        $modulos_habilitados[$rmr['modulo_id']] = $rmr['id']; 
    }
    
    $permisos_actuales = [];
    if (!empty($modulos_habilitados)) {
        $r_modulo_rol_ids = array_values($modulos_habilitados);
        $placeholders = implode(',', array_fill(0, count($r_modulo_rol_ids), '?'));
        $stmtVP = db()->prepare("SELECT r_modulo_rol_id, vista_id, permiso_id FROM vistas_permiso WHERE r_modulo_rol_id IN ($placeholders)");
        $stmtVP->execute($r_modulo_rol_ids);
        foreach ($stmtVP->fetchAll() as $vp) {
            $permisos_actuales[$vp['r_modulo_rol_id']][$vp['vista_id']][] = $vp['permiso_id'];
        }
    }

    renderHeader('Editar Rol y Permisos');
    ?>
    <div class="card" style="max-width: 1000px; margin: 0 auto;">
        <h2>Editar Rol: <?= htmlspecialchars($rol['nombre']) ?></h2>
        
        <form method="POST" action="/ue-ameliarios/public/index.php/roles/editar/<?= $id ?>">
            <h3 style="color: var(--primary-color); margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Datos del Rol</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                <div><label style="font-weight: bold; font-size: 14px;">Nombre *</label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($rol['nombre']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Descripción</label>
                    <input type="text" name="descripcion" value="<?= htmlspecialchars($rol['descripcion'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>

            <h3 style="color: var(--primary-color); margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">🔐 Matriz de Permisos (RBAC 3 Niveles)</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <strong>Paso 1:</strong> Seleccione los módulos a los que este rol tendrá acceso.<br>
                <strong>Paso 2:</strong> Para cada módulo, seleccione las vistas y los permisos específicos.
            </p>
            
            <div style="max-height: 600px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; background: #f8fafc;">
                <?php foreach ($modulos as $mod): 
                    $r_modulo_rol_id = $modulos_habilitados[$mod['id']] ?? null;
                ?>
                    <div style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 6px; border-left: 4px solid var(--primary-color);">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <input type="checkbox" name="modulos[]" value="<?= $mod['id'] ?>" <?= $r_modulo_rol_id ? 'checked' : '' ?> style="transform: scale(1.3);" id="mod_<?= $mod['id'] ?>">
                            <label for="mod_<?= $mod['id'] ?>" style="font-weight: bold; font-size: 15px; color: #1e3a8a; cursor: pointer; flex: 1;">
                                <?= htmlspecialchars($mod['icono'] ?? '📁') ?> <?= htmlspecialchars($mod['nombre_modulo']) ?>
                            </label>
                        </div>
                        
                        <?php if ($r_modulo_rol_id): 
                            $vistas = db()->query("SELECT id, nombre_vista, ruta_acceso FROM vistas ORDER BY nombre_vista ASC")->fetchAll();
                        ?>
                            <div style="margin-left: 30px; padding: 10px; background: #f1f5f9; border-radius: 4px;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                    <thead>
                                        <tr style="background: #e2e8f0; color: #475569;">
                                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #cbd5e1;">Vista</th>
                                            <?php foreach ($permisos_disponibles as $p): ?>
                                                <th style="padding: 8px; text-align: center; border-bottom: 1px solid #cbd5e1; width: 80px;"><?= htmlspecialchars($p['nombre_permiso']) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vistas as $v): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 8px; font-weight: 500;">
                                                <?= htmlspecialchars($v['nombre_vista']) ?>
                                                <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($v['ruta_acceso']) ?></div>
                                            </td>
                                            <?php foreach ($permisos_disponibles as $p): 
                                                $checked = in_array($p['id'], $permisos_actuales[$r_modulo_rol_id][$v['id']] ?? []) ? 'checked' : '';
                                            ?>
                                                <td style="padding: 8px; text-align: center;">
                                                    <input type="checkbox" name="permisos[<?= $mod['id'] ?>][<?= $v['id'] ?>][]" value="<?= $p['id'] ?>" <?= $checked ?> style="transform: scale(1.2);">
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="margin-left: 30px; font-size: 13px; color: #94a3b8; font-style: italic;">Active este módulo para configurar permisos específicos.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Cambios</button>
                <a href="/ue-ameliarios/public/index.php/roles" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /roles/eliminar (GET)
// ==========================================
if (strpos($uri, 'roles/eliminar') !== false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }
    
    $id = (int)($routeParams['id'] ?? 0);
    if ($id === 0) {
        $parts = explode('/', trim($uri, '/'));
        foreach ($parts as $part) { if (is_numeric($part)) { $id = (int)$part; break; } }
    }
    
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT nombre, es_sistema FROM rol WHERE id_rol = :id");
    $stmt->execute(['id' => $id]);
    $rol = $stmt->fetch();
    
    if (!$rol) die("Error: Rol no encontrado.");
    if ($rol['nombre'] === 'Administrador' || $rol['es_sistema']) die("Error: No se puede eliminar roles del sistema.");

    $stmt = db()->prepare("SELECT COUNT(*) as total FROM usuario_rol WHERE id_rol = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) {
        die("Error: No se puede eliminar. Hay usuarios asignados a este rol. Reasígnelos primero.");
    }

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM rol WHERE id_rol = :id")->execute(['id' => $id]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_ROL', 'rol', :r, NOW())")
            ->execute(['u' => $uid, 'r' => $id]);
        
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/roles?exito=Rol+eliminado+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error: " . $e->getMessage());
    }
}