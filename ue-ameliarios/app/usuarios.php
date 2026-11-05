<?php
/**
 * app/usuarios.php
 * CRUD de Usuarios con RBAC Jerárquico — Versión Corregida y a Prueba de Balas.
 */
require_once __DIR__ . '/layout.php';

// ==========================================
// /usuarios (GET) — Listado
// ==========================================
if ($uri === '/usuarios' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/usuarios', 'VER');

    $usuarios = db()->query("
        SELECT u.id_usuario, u.username, u.correo, u.activo,
               p.nombres, p.apellidos, p.cedula,
               GROUP_CONCAT(r.nombre SEPARATOR ', ') AS roles
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario
        LEFT JOIN rol r ON ur.id_rol = r.id_rol
        GROUP BY u.id_usuario, u.username, u.correo, u.activo, p.nombres, p.apellidos, p.cedula
        ORDER BY p.apellidos ASC, p.nombres ASC
    ")->fetchAll();

    renderHeader('Gestión de Usuarios');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Usuarios del Sistema</h2>
        <?php if (can('/usuarios', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/usuarios/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nuevo Usuario</a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">Usuario</th>
                <th style="padding: 12px; text-align: left;">Nombre</th>
                <th style="padding: 12px; text-align: left;">Cédula</th>
                <th style="padding: 12px; text-align: left;">Rol</th>
                <th style="padding: 12px; text-align: left;">Estado</th>
                <th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px; font-weight: bold;"><?= htmlspecialchars($u['username']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($u['apellidos'] . ', ' . $u['nombres']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($u['cedula']) ?></td>
                    <td style="padding: 12px;"><span style="background: #e7f3ff; color: var(--primary-color); padding: 3px 8px; border-radius: 10px; font-size: 12px;"><?= htmlspecialchars($u['roles'] ?? 'Sin rol') ?></span></td>
                    <td style="padding: 12px;"><?= $u['activo'] ? '<span style="color: #28a745; font-weight: bold;">● Activo</span>' : '<span style="color: #dc3545; font-weight: bold;">● Inactivo</span>' ?></td>
                    <td style="padding: 12px;">
                        <?php if (can('/usuarios', 'EDITAR')): ?>
                            <a href="/ue-ameliarios/public/index.php/usuarios/editar/<?= $u['id_usuario'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a>
                        <?php endif; ?>
                        <?php if (can('/usuarios', 'ELIMINAR')): ?>
                            <a href="/ue-ameliarios/public/index.php/usuarios/eliminar/<?= $u['id_usuario'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar este usuario?')">Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /usuarios/crear (GET y POST)
// ==========================================
if ($uri === '/usuarios/crear') {
    requireLogin(); requirePermission('/usuarios', 'CREAR');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_rol = (int)($_POST['id_rol'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 1);

        if (empty($nombres) || empty($apellidos) || empty($cedula) || empty($correo) || empty($username) || empty($password) || $id_rol <= 0) {
            header('Location: /ue-ameliarios/public/index.php/usuarios/crear?error=Todos+los+campos+son+obligatorios'); exit;
        }

        try {
            db()->beginTransaction();
            
            $stmt = db()->prepare("SELECT COUNT(*) AS n FROM persona WHERE cedula = :cedula");
            $stmt->execute(['cedula' => $cedula]);
            if ($stmt->fetch()['n'] > 0) throw new Exception("La cédula ya está registrada.");

            db()->prepare("INSERT INTO persona (nombres, apellidos, cedula) VALUES (:nombres, :apellidos, :cedula)")
                ->execute(['nombres' => $nombres, 'apellidos' => $apellidos, 'cedula' => $cedula]);
            $id_persona = db()->lastInsertId();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare("INSERT INTO usuario (username, password_hash, correo, id_persona, activo) VALUES (:username, :hash, :correo, :id_persona, :activo)")
                ->execute(['username' => $username, 'hash' => $hash, 'correo' => $correo, 'id_persona' => $id_persona, 'activo' => $activo]);
            $id_usuario = db()->lastInsertId();

            db()->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (:id_usuario, :id_rol)")
                ->execute(['id_usuario' => $id_usuario, 'id_rol' => $id_rol]);

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_USUARIO', 'usuario', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id_usuario]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/usuarios?exito=Usuario+creado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/usuarios/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    // GET: Mostrar formulario
    $roles = db()->query("SELECT id_rol, nombre FROM rol ORDER BY nombre ASC")->fetchAll();
    renderHeader('Crear Nuevo Usuario');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Crear Nuevo Usuario</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

        <form method="POST" action="/ue-ameliarios/public/index.php/usuarios/crear">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Nombres *</label>
                    <input type="text" name="nombres" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Apellidos *</label>
                    <input type="text" name="apellidos" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Cédula *</label>
                    <input type="text" name="cedula" required placeholder="V-12345678" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Correo *</label>
                    <input type="text" name="correo" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Nombre de Usuario *</label>
                    <input type="text" name="username" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Contraseña *</label>
                    <input type="password" name="password" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Rol *</label>
                    <select name="id_rol" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($roles as $r): ?><option value="<?= $r['id_rol'] ?>"><?= htmlspecialchars($r['nombre']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label style="font-weight: bold; font-size: 14px;">Estado</label>
                    <select name="activo" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="1" selected>Activo</option>
                        <option value="0">Inactivo</option>
                    </select></div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Usuario</button>
                <a href="/ue-ameliarios/public/index.php/usuarios" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /usuarios/editar (GET y POST)
// ==========================================
if ($uri === '/usuarios/editar') {
    requireLogin(); requirePermission('/usuarios', 'EDITAR');
    
    // ✅ CORRECCIÓN CLAVE: Usar $routeParams en lugar de preg_match
    $id_usuario = $routeParams['id'] ?? 0;
    if ($id_usuario === 0) die("Error: ID de usuario no válido.");

    $stmt = db()->prepare("
        SELECT u.id_usuario, u.username, u.correo, u.activo, p.id_persona, p.nombres, p.apellidos, p.cedula, ur.id_rol
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario
        WHERE u.id_usuario = :id LIMIT 1
    ");
    $stmt->execute(['id' => $id_usuario]);
    $usuario = $stmt->fetch();

    if (!$usuario) die("Error: Usuario no encontrado.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_rol = (int)($_POST['id_rol'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 1);

        try {
            db()->beginTransaction();

            $stmt = db()->prepare("SELECT COUNT(*) AS n FROM persona WHERE cedula = :cedula AND id_persona != :id_persona");
            $stmt->execute(['cedula' => $cedula, 'id_persona' => $usuario['id_persona']]);
            if ($stmt->fetch()['n'] > 0) throw new Exception("La cédula ya está registrada en otro usuario.");

            db()->prepare("UPDATE persona SET nombres = :nombres, apellidos = :apellidos, cedula = :cedula WHERE id_persona = :id_persona")
                ->execute(['nombres' => $nombres, 'apellidos' => $apellidos, 'cedula' => $cedula, 'id_persona' => $usuario['id_persona']]);

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare("UPDATE usuario SET username = :username, correo = :correo, activo = :activo, password_hash = :hash WHERE id_usuario = :id")
                    ->execute(['username' => $username, 'correo' => $correo, 'activo' => $activo, 'hash' => $hash, 'id' => $id_usuario]);
            } else {
                db()->prepare("UPDATE usuario SET username = :username, correo = :correo, activo = :activo WHERE id_usuario = :id")
                    ->execute(['username' => $username, 'correo' => $correo, 'activo' => $activo, 'id' => $id_usuario]);
            }

            db()->prepare("DELETE FROM usuario_rol WHERE id_usuario = :id")->execute(['id' => $id_usuario]);
            db()->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (:id_usuario, :id_rol)")
                ->execute(['id_usuario' => $id_usuario, 'id_rol' => $id_rol]);

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_USUARIO', 'usuario', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id_usuario]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/usuarios?exito=Usuario+actualizado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    // GET: Mostrar formulario
    $roles = db()->query("SELECT id_rol, nombre FROM rol ORDER BY nombre ASC")->fetchAll();
    renderHeader('Editar Usuario');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Editar Usuario</h2>
        <form method="POST" action="/ue-ameliarios/public/index.php/usuarios/editar/<?= $id_usuario ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Nombres *</label>
                    <input type="text" name="nombres" value="<?= htmlspecialchars($usuario['nombres']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Apellidos *</label>
                    <input type="text" name="apellidos" value="<?= htmlspecialchars($usuario['apellidos']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Cédula *</label>
                    <input type="text" name="cedula" value="<?= htmlspecialchars($usuario['cedula']) ?>" required readonly style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #e9ecef;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Correo *</label>
                    <input type="text" name="correo" value="<?= htmlspecialchars($usuario['correo']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Nombre de Usuario *</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($usuario['username']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Nueva Contraseña (opcional)</label>
                    <input type="password" name="password" placeholder="Dejar vacío para mantener" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Rol *</label>
                    <select name="id_rol" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id_rol'] ?>" <?= ($usuario['id_rol'] == $r['id_rol']) ? 'selected' : '' ?>><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label style="font-weight: bold; font-size: 14px;">Estado</label>
                    <select name="activo" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="1" <?= $usuario['activo'] ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= !$usuario['activo'] ? 'selected' : '' ?>>Inactivo</option>
                    </select></div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Actualizar Usuario</button>
                <a href="/ue-ameliarios/public/index.php/usuarios" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /usuarios/eliminar (GET)
// ==========================================
if ($uri === '/usuarios/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/usuarios', 'ELIMINAR');
    
    $id_usuario = $routeParams['id'] ?? 0;
    if ($id_usuario === 0) die("Error: ID no válido.");

    try {
        db()->beginTransaction();

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if ($id_usuario === $uid) throw new Exception("No puedes eliminar tu propio usuario.");

        $stmt = db()->prepare("SELECT username, id_persona FROM usuario WHERE id_usuario = :id");
        $stmt->execute(['id' => $id_usuario]);
        $user = $stmt->fetch();
        if (!$user) throw new Exception("Usuario no encontrado.");

        db()->prepare("DELETE FROM usuario_rol WHERE id_usuario = :id")->execute(['id' => $id_usuario]);
        db()->prepare("DELETE FROM usuario WHERE id_usuario = :id")->execute(['id' => $id_usuario]);

        $stmt = db()->prepare("SELECT COUNT(*) AS n FROM usuario WHERE id_persona = :id_persona");
        $stmt->execute(['id_persona' => $user['id_persona']]);
        if ($stmt->fetch()['n'] == 0) {
            db()->prepare("DELETE FROM persona WHERE id_persona = :id_persona")->execute(['id_persona' => $user['id_persona']]);
        }

        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_USUARIO', 'usuario', :r, NOW())")
            ->execute(['u' => $uid, 'r' => $id_usuario]);

        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/usuarios?exito=Usuario+eliminado+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}