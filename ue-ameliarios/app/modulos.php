<?php
/**
 * app/modulos.php
 * Gestión de Módulos y Vistas (Niveles 1 y 2 del RBAC).
 * CORREGIDO: Estructura if/elseif estricta para evitar conflictos entre pestañas y acciones.
 */
require_once __DIR__ . '/layout.php';

$tab = $_GET['tab'] ?? 'modulos';

// ==========================================
// 1. ACCIONES DE VISTAS (Crear, Editar, Eliminar)
// ==========================================

if (strpos($uri, 'modulos/vistas/crear') !== false || strpos($uri, 'modulos/vistas/editar') !== false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }

    $is_crear = strpos($uri, 'modulos/vistas/crear') !== false;
    $id = (int)($routeParams['id'] ?? 0);
    
    if (!$is_crear && $id === 0) {
        $parts = explode('/', trim($uri, '/'));
        foreach ($parts as $part) { if (is_numeric($part)) { $id = (int)$part; break; } }
    }

    $vista = null;
    $titulo = $is_crear ? "Crear Nueva Vista" : "Editar Vista";
    $accion_url = $is_crear ? "/ue-ameliarios/public/index.php/modulos/vistas/crear" : "/ue-ameliarios/public/index.php/modulos/vistas/editar/$id";

    if ($id > 0) {
        $stmt = db()->prepare("SELECT * FROM vistas WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $vista = $stmt->fetch();
        if (!$vista) die("Vista no encontrada.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre_vista'] ?? '');
        $ruta = trim($_POST['ruta_acceso'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if (empty($nombre) || empty($ruta)) die("Error: Nombre y Ruta son obligatorios.");

        try {
            db()->beginTransaction();
            if ($id > 0) {
                db()->prepare("UPDATE vistas SET nombre_vista=:n, ruta_acceso=:r, descripcion=:d WHERE id=:id")
                    ->execute(['n'=>$nombre, 'r'=>$ruta, 'd'=>$descripcion, 'id'=>$id]);
            } else {
                db()->prepare("INSERT INTO vistas (nombre_vista, ruta_acceso, descripcion) VALUES (:n, :r, :d)")
                    ->execute(['n'=>$nombre, 'r'=>$ruta, 'd'=>$descripcion]);
                $id = db()->lastInsertId();
            }

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            $accion_log = $is_crear ? 'CREAR_VISTA' : 'EDITAR_VISTA';
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, :a, 'vistas', :r, NOW())")
                ->execute(['u' => $uid, 'a' => $accion_log, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/modulos?tab=vistas&exito=Vista+guardada+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error (posible ruta duplicada): " . $e->getMessage());
        }
    }

    renderHeader($titulo);
    ?>
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <h2><?= $titulo ?></h2>
        <form method="POST" action="<?= $accion_url ?>">
            <div style="margin-bottom: 15px;"><label style="font-weight:bold">Nombre de la Vista *</label>
                <input type="text" name="nombre_vista" value="<?= htmlspecialchars($vista['nombre_vista'] ?? '') ?>" required placeholder="Ej: Lista de Estudiantes" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight:bold">Ruta de Acceso *</label>
                <input type="text" name="ruta_acceso" value="<?= htmlspecialchars($vista['ruta_acceso'] ?? '') ?>" required placeholder="Ej: /estudiantes" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-family:monospace;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight:bold">Descripción</label>
                <input type="text" name="descripcion" value="<?= htmlspecialchars($vista['descripcion'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
            <div style="display:flex; gap:10px;">
                <button type="submit" style="padding:10px 20px; background:var(--primary-color); color:white; border:none; border-radius:4px; cursor:pointer;">💾 Guardar</button>
                <a href="/ue-ameliarios/public/index.php/modulos?tab=vistas" style="padding:10px 20px; background:#6c757d; color:white; text-decoration:none; border-radius:4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

elseif (strpos($uri, 'modulos/vistas/eliminar') !== false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }
    
    $id = (int)($routeParams['id'] ?? 0);
    if ($id === 0) {
        $parts = explode('/', trim($uri, '/'));
        foreach ($parts as $part) { if (is_numeric($part)) { $id = (int)$part; break; } }
    }

    $stmt = db()->prepare("SELECT COUNT(*) as total FROM vistas_permiso WHERE vista_id = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) {
        if (!isset($_GET['confirmar'])) {
            die("⚠️ Esta vista tiene permisos asignados. Se eliminarán en cascada. <br><br><a href='$uri&confirmar=1' style='padding:8px 15px; background:#e74c3c; color:white; text-decoration:none; border-radius:4px;'>Confirmar Eliminación</a> <a href='/ue-ameliarios/public/index.php/modulos?tab=vistas' style='padding:8px 15px; background:#6c757d; color:white; text-decoration:none; border-radius:4px; margin-left:10px;'>Cancelar</a>");
        }
    }

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM vistas WHERE id = :id")->execute(['id' => $id]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_VISTA', 'vistas', :r, NOW())")
            ->execute(['u' => $uid, 'r' => $id]);
            
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/modulos?tab=vistas&exito=Vista+eliminada'); exit;
    } catch (Exception $e) { 
        db()->rollBack();
        die("Error: " . $e->getMessage()); 
    }
}

// ==========================================
// 2. ACCIONES DE MÓDULOS (Crear, Editar, Eliminar)
// ==========================================

elseif (strpos($uri, 'modulos/crear') !== false || strpos($uri, 'modulos/editar') !== false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }

    $is_crear = strpos($uri, 'modulos/crear') !== false;
    $id = (int)($routeParams['id'] ?? 0);
    
    if (!$is_crear && $id === 0) {
        $parts = explode('/', trim($uri, '/'));
        foreach ($parts as $part) { if (is_numeric($part)) { $id = (int)$part; break; } }
    }

    $modulo = null;
    $titulo = $is_crear ? "Crear Nuevo Módulo" : "Editar Módulo";
    $accion_url = $is_crear ? "/ue-ameliarios/public/index.php/modulos/crear" : "/ue-ameliarios/public/index.php/modulos/editar/$id";

    if ($id > 0) {
        $stmt = db()->prepare("SELECT * FROM modulo WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $modulo = $stmt->fetch();
        if (!$modulo) die("Módulo no encontrado.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre_modulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $icono = trim($_POST['icono'] ?? '📁');
        $orden = (int)($_POST['orden'] ?? 1);

        if (empty($nombre)) die("Error: El nombre del módulo es obligatorio.");

        try {
            db()->beginTransaction();
            if ($id > 0) {
                db()->prepare("UPDATE modulo SET nombre_modulo=:n, descripcion=:d, icono=:i, orden=:o WHERE id=:id")
                    ->execute(['n'=>$nombre, 'd'=>$descripcion, 'i'=>$icono, 'o'=>$orden, 'id'=>$id]);
            } else {
                db()->prepare("INSERT INTO modulo (nombre_modulo, descripcion, icono, orden) VALUES (:n, :d, :i, :o)")
                    ->execute(['n'=>$nombre, 'd'=>$descripcion, 'i'=>$icono, 'o'=>$orden]);
                $id = db()->lastInsertId();
            }

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            $accion_log = $is_crear ? 'CREAR_MODULO' : 'EDITAR_MODULO';
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, :a, 'modulo', :r, NOW())")
                ->execute(['u' => $uid, 'a' => $accion_log, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/modulos?tab=modulos&exito=Modulo+guardado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error: " . $e->getMessage());
        }
    }

    renderHeader($titulo);
    ?>
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <h2><?= $titulo ?></h2>
        <form method="POST" action="<?= $accion_url ?>">
            <div style="margin-bottom: 15px;"><label style="font-weight:bold">Nombre del Módulo *</label>
                <input type="text" name="nombre_modulo" value="<?= htmlspecialchars($modulo['nombre_modulo'] ?? '') ?>" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight:bold">Descripción</label>
                <input type="text" name="descripcion" value="<?= htmlspecialchars($modulo['descripcion'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-weight:bold">Icono (Emoji)</label>
                    <input type="text" name="icono" value="<?= htmlspecialchars($modulo['icono'] ?? '📁') ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
                <div><label style="font-weight:bold">Orden</label>
                    <input type="number" name="orden" value="<?= $modulo['orden'] ?? 1 ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="submit" style="padding:10px 20px; background:var(--primary-color); color:white; border:none; border-radius:4px; cursor:pointer;">💾 Guardar</button>
                <a href="/ue-ameliarios/public/index.php/modulos?tab=modulos" style="padding:10px 20px; background:#6c757d; color:white; text-decoration:none; border-radius:4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

elseif (strpos($uri, 'modulos/eliminar') !== false) {
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }
    
    $id = (int)($routeParams['id'] ?? 0);
    if ($id === 0) {
        $parts = explode('/', trim($uri, '/'));
        foreach ($parts as $part) { if (is_numeric($part)) { $id = (int)$part; break; } }
    }
    
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM r_modulo_rol WHERE modulo_id = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) die("Error: No se puede eliminar. Este módulo está asignado a roles. Elimine las asignaciones primero.");

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM modulo WHERE id = :id")->execute(['id' => $id]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_MODULO', 'modulo', :r, NOW())")
            ->execute(['u' => $uid, 'r' => $id]);
            
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/modulos?tab=modulos&exito=Modulo+eliminado'); exit;
    } catch (Exception $e) { 
        db()->rollBack();
        die("Error: " . $e->getMessage()); 
    }
}

// ==========================================
// 3. LISTADO DE PESTAÑAS (Vistas o Módulos)
// ==========================================

elseif (strpos($uri, 'modulos') !== false && $tab === 'vistas') {
    // --- LISTAR VISTAS ---
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }

    $vistas = db()->query("SELECT * FROM vistas ORDER BY nombre_vista ASC")->fetchAll();

    renderHeader('Gestión de Vistas');
    ?>
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;">Configuración de Seguridad RBAC</h2>
            <a href="/ue-ameliarios/public/index.php/modulos/vistas/crear" style="padding: 8px 15px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">+ Nueva Vista</a>
        </div>
        <div style="display: flex; gap: 10px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px;">
            <a href="/ue-ameliarios/public/index.php/modulos?tab=modulos" style="padding: 10px 20px; text-decoration: none; color: #666;">Módulos</a>
            <a href="/ue-ameliarios/public/index.php/modulos?tab=vistas" style="padding: 10px 20px; text-decoration: none; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); font-weight: bold; margin-bottom: -2px;">Vistas</a>
        </div>

        <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: #f8fafc; color: #475569;">
                <th style="padding: 10px; text-align: left;">ID</th>
                <th style="padding: 10px; text-align: left;">Nombre de la Vista</th>
                <th style="padding: 10px; text-align: left;">Ruta de Acceso</th>
                <th style="padding: 10px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($vistas as $v): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;"><?= $v['id'] ?></td>
                    <td style="padding: 10px;"><strong><?= htmlspecialchars($v['nombre_vista']) ?></strong><br><small style="color:#666"><?= htmlspecialchars($v['descripcion'] ?? '') ?></small></td>
                    <td style="padding: 10px; font-family: monospace; font-size: 12px; background: #f1f5f9; border-radius: 4px;"><?= htmlspecialchars($v['ruta_acceso']) ?></td>
                    <td style="padding: 10px;">
                        <a href="/ue-ameliarios/public/index.php/modulos/vistas/editar/<?= $v['id'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a>
                        <a href="/ue-ameliarios/public/index.php/modulos/vistas/eliminar/<?= $v['id'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar vista? Se eliminarán los permisos asociados.')">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

else {
    // --- LISTAR MÓDULOS (Default) ---
    requireLogin();
    if (!function_exists('hasRole') || !hasRole('Administrador')) { header('Location: /ue-ameliarios/public/index.php/dashboard'); exit; }

    $modulos = db()->query("SELECT * FROM modulo ORDER BY orden ASC, nombre_modulo ASC")->fetchAll();

    renderHeader('Gestión de Módulos y Vistas');
    ?>
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;">Configuración de Seguridad RBAC</h2>
            <a href="/ue-ameliarios/public/index.php/modulos/crear" style="padding: 8px 15px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">+ Nuevo Módulo</a>
        </div>
        <div style="display: flex; gap: 10px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px;">
            <a href="/ue-ameliarios/public/index.php/modulos?tab=modulos" style="padding: 10px 20px; text-decoration: none; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); font-weight: bold; margin-bottom: -2px;">Módulos</a>
            <a href="/ue-ameliarios/public/index.php/modulos?tab=vistas" style="padding: 10px 20px; text-decoration: none; color: #666;">Vistas</a>
        </div>

        <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: #f8fafc; color: #475569;">
                <th style="padding: 10px; text-align: left;">Orden</th>
                <th style="padding: 10px; text-align: left;">Nombre del Módulo</th>
                <th style="padding: 10px; text-align: left;">Icono</th>
                <th style="padding: 10px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($modulos as $m): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;"><?= $m['orden'] ?></td>
                    <td style="padding: 10px;"><strong><?= htmlspecialchars($m['nombre_modulo']) ?></strong><br><small style="color:#666"><?= htmlspecialchars($m['descripcion'] ?? '') ?></small></td>
                    <td style="padding: 10px; font-size: 20px;"><?= htmlspecialchars($m['icono'] ?? '📁') ?></td>
                    <td style="padding: 10px;">
                        <a href="/ue-ameliarios/public/index.php/modulos/editar/<?= $m['id'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a>
                        <a href="/ue-ameliarios/public/index.php/modulos/eliminar/<?= $m['id'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar módulo? Se perderán las asignaciones de roles.')">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }