<?php
/**
 * app/usuarios.php
 * CRUD de usuarios.
 */

/** @noinspection PhpUndefinedFunctionInspection */

// ==========================================
// /usuarios (GET) — Listado
// ==========================================
if ($uri === '/usuarios' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('usuario.ver');

    $usuarios = db()->query("
        SELECT u.id_usuario, u.username, u.correo, u.activo,
               p.cedula, p.nombres, p.apellidos, p.telefono
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        ORDER BY u.id_usuario ASC
    ")->fetchAll();

    $user = $_SESSION['user'];
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Usuarios — U.E. Amelia Ríos</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; }
            .header { background: #2c5aa0; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
            .header h1 { font-size: 20px; }
            .header a { color: white; text-decoration: none; margin-left: 15px; padding: 5px 10px; background: rgba(255,255,255,0.2); border-radius: 4px; }
            .header a:hover { background: rgba(255,255,255,0.3); }
            .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
            .page-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .page-title h2 { color: #333; }
            .btn { display: inline-block; padding: 10px 20px; background: #2c5aa0; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; border: none; cursor: pointer; }
            .btn:hover { background: #1e3d6f; }
            table { width: 100%; background: white; border-collapse: collapse; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
            th { background: #2c5aa0; color: white; font-weight: normal; }
            tr:hover { background: #f8f9fa; }
            .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
            .badge-active { background: #d4edda; color: #155724; }
            .badge-inactive { background: #f8d7da; color: #721c24; }
            .actions a { color: #2c5aa0; text-decoration: none; margin-right: 10px; font-size: 13px; }
            .actions a:hover { text-decoration: underline; }
            .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
            .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>U.E. Amelia Ríos — Usuarios</h1>
            <div>
                <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>
                <a href="/ue-ameliarios/public/index.php/dashboard">Panel</a>
                <a href="/ue-ameliarios/public/index.php/logout">Salir</a>
            </div>
        </div>

        <div class="container">
            <div class="page-title">
                <h2>Gestión de Usuarios</h2>
                <a href="/ue-ameliarios/public/index.php/usuarios/crear" class="btn">+ Nuevo Usuario</a>
            </div>

            <?php if (isset($_GET['exito'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['exito']) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cédula</th>
                        <th>Nombres</th>
                        <th>Apellidos</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= $u['id_usuario'] ?></td>
                            <td><?= htmlspecialchars($u['cedula']) ?></td>
                            <td><?= htmlspecialchars($u['nombres']) ?></td>
                            <td><?= htmlspecialchars($u['apellidos']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['correo']) ?></td>
                            <td>
                                <span class="badge <?= $u['activo'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="/ue-ameliarios/public/index.php/usuarios/editar/<?= $u['id_usuario'] ?>">Editar</a>
                                <a href="/ue-ameliarios/public/index.php/usuarios/eliminar/<?= $u['id_usuario'] ?>" onclick="return confirm('¿Estás seguro de eliminar a <?= htmlspecialchars($u['nombres'] . ' ' . $u['apellidos']) ?>?')">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// /usuarios/crear (GET) — Formulario
// ==========================================
if ($uri === '/usuarios/crear' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('usuario.crear');

    $user = $_SESSION['user'];
    $roles = db()->query("SELECT id_rol, nombre FROM rol ORDER BY id_rol")->fetchAll();

    $error = $_GET['error'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Nuevo Usuario — U.E. Amelia Ríos</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; }
            .header { background: #2c5aa0; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
            .header h1 { font-size: 20px; }
            .header a { color: white; text-decoration: none; margin-left: 15px; padding: 5px 10px; background: rgba(255,255,255,0.2); border-radius: 4px; }
            .header a:hover { background: rgba(255,255,255,0.3); }
            .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
            .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .card h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; color: #333; font-size: 14px; font-weight: bold; }
            input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
            input:focus, select:focus { outline: none; border-color: #2c5aa0; }
            .row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .btn { padding: 10px 20px; background: #2c5aa0; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
            .btn:hover { background: #1e3d6f; }
            .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; }
            .btn-secondary:hover { background: #545b62; }
            .actions { margin-top: 20px; display: flex; gap: 10px; }
            .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
            .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>U.E. Amelia Ríos — Nuevo Usuario</h1>
            <div>
                <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>
                <a href="/ue-ameliarios/public/index.php/usuarios">Usuarios</a>
                <a href="/ue-ameliarios/public/index.php/dashboard">Panel</a>
                <a href="/ue-ameliarios/public/index.php/logout">Salir</a>
            </div>
        </div>

        <div class="container">
            <div class="card">
                <h2>Crear Nuevo Usuario</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/ue-ameliarios/public/index.php/usuarios/crear">
                    <h3 style="margin-bottom:15px;color:#2c5aa0;font-size:16px;">Datos Personales</h3>

                    <div class="row">
                        <div class="form-group">
                            <label>Cédula *</label>
                            <input type="text" name="cedula" placeholder="V-12345678" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" placeholder="0414-1234567">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label>Nombres *</label>
                            <input type="text" name="nombres" placeholder="Juan" required>
                        </div>
                        <div class="form-group">
                            <label>Apellidos *</label>
                            <input type="text" name="apellidos" placeholder="Pérez" required>
                        </div>
                    </div>

                    <h3 style="margin:20px 0 15px;color:#2c5aa0;font-size:16px;">Datos de Acceso</h3>

                    <div class="row">
                        <div class="form-group">
                            <label>Usuario *</label>
                            <input type="text" name="username" placeholder="jperez" required>
                        </div>
                        <div class="form-group">
                            <label>Correo *</label>
                            <input type="email" name="correo" placeholder="jperez@ameliarios.edu.ve" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contraseña *</label>
                        <input type="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label>Rol *</label>
                        <select name="id_rol" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">Crear Usuario</button>
                        <a href="/ue-ameliarios/public/index.php/usuarios" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// /usuarios/crear (POST) — Procesar creación
// ==========================================
if ($uri === '/usuarios/crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requirePermission('usuario.crear');

    $cedula    = trim($_POST['cedula'] ?? '');
    $nombres   = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $password  = $_POST['password'] ?? '';
    $id_rol    = (int)($_POST['id_rol'] ?? 0);

    if (empty($cedula) || empty($nombres) || empty($apellidos) ||
        empty($username) || empty($correo) || empty($password) || $id_rol === 0) {
        header('Location: /ue-ameliarios/public/index.php/usuarios/crear?error=' . urlencode('Todos los campos con * son obligatorios'));
        exit;
    }

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM persona WHERE cedula = :c");
    $stmt->execute(['c' => $cedula]);
    if ($stmt->fetch()['total'] > 0) {
        header('Location: /ue-ameliarios/public/index.php/usuarios/crear?error=' . urlencode('La cédula ya está registrada'));
        exit;
    }

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM usuario WHERE username = :u OR correo = :c");
    $stmt->execute(['u' => $username, 'c' => $correo]);
    if ($stmt->fetch()['total'] > 0) {
        header('Location: /ue-ameliarios/public/index.php/usuarios/crear?error=' . urlencode('El usuario o correo ya están registrados'));
        exit;
    }

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM rol WHERE id_rol = :r");
    $stmt->execute(['r' => $id_rol]);
    if ($stmt->fetch()['total'] === 0) {
        header('Location: /ue-ameliarios/public/index.php/usuarios/crear?error=' . urlencode('El rol seleccionado no existe'));
        exit;
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("
            INSERT INTO persona (cedula, nombres, apellidos, telefono, estado)
            VALUES (:cedula, :nombres, :apellidos, :telefono, 'Activo')
        ");
        $stmt->execute([
            'cedula'    => $cedula,
            'nombres'   => $nombres,
            'apellidos' => $apellidos,
            'telefono'  => $telefono ?: null,
        ]);
        $id_persona = db()->lastInsertId();

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = db()->prepare("
            INSERT INTO usuario (id_persona, username, correo, password_hash, activo)
            VALUES (:id_persona, :username, :correo, :password_hash, 1)
        ");
        $stmt->execute([
            'id_persona'    => $id_persona,
            'username'      => $username,
            'correo'        => $correo,
            'password_hash' => $passwordHash,
        ]);
        $id_usuario = db()->lastInsertId();

        $stmt = db()->prepare("
            INSERT INTO usuario_rol (id_usuario, id_rol, es_principal, asignado_por)
            VALUES (:id_usuario, :id_rol, 1, :asignado_por)
        ");
        $stmt->execute([
            'id_usuario'   => $id_usuario,
            'id_rol'       => $id_rol,
            'asignado_por' => $_SESSION['user']['id_usuario'],
        ]);

        $stmt = db()->prepare("
            INSERT INTO bitacora (id_usuario, accion, tabla_afectada, id_registro, detalle, ip)
            VALUES (:id_usuario, 'crear_usuario', 'usuario', :id_registro, :detalle, :ip)
        ");
        $stmt->execute([
            'id_usuario'  => $_SESSION['user']['id_usuario'],
            'id_registro' => $id_usuario,
            'detalle'     => "Usuario creado: {$username}",
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        db()->commit();

        header('Location: /ue-ameliarios/public/index.php/usuarios?exito=' . urlencode('Usuario creado exitosamente'));
        exit;
    } catch (Exception $e) {
        db()->rollBack();
        header('Location: /ue-ameliarios/public/index.php/usuarios/crear?error=' . urlencode('Error al crear usuario: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// /usuarios/editar (GET) — Formulario de edición
// ==========================================
if ($uri === '/usuarios/editar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('usuario.editar');

    $id = $routeParams['id'] ?? 0;

    $stmt = db()->prepare("
        SELECT u.id_usuario, u.username, u.correo, u.activo,
               p.id_persona, p.cedula, p.nombres, p.apellidos, p.telefono
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        WHERE u.id_usuario = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        header('Location: /ue-ameliarios/public/index.php/usuarios?error=' . urlencode('Usuario no encontrado'));
        exit;
    }

    $roles = db()->query("SELECT id_rol, nombre FROM rol ORDER BY id_rol")->fetchAll();

    $stmt = db()->prepare("SELECT id_rol FROM usuario_rol WHERE id_usuario = :id AND es_principal = 1 LIMIT 1");
    $stmt->execute(['id' => $id]);
    $rolActual = $stmt->fetch()['id_rol'] ?? 0;

    $user = $_SESSION['user'];
    $error = $_GET['error'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Editar Usuario — U.E. Amelia Ríos</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; }
            .header { background: #2c5aa0; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
            .header h1 { font-size: 20px; }
            .header a { color: white; text-decoration: none; margin-left: 15px; padding: 5px 10px; background: rgba(255,255,255,0.2); border-radius: 4px; }
            .header a:hover { background: rgba(255,255,255,0.3); }
            .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
            .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .card h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; color: #333; font-size: 14px; font-weight: bold; }
            input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
            input:focus, select:focus { outline: none; border-color: #2c5aa0; }
            .row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .btn { padding: 10px 20px; background: #2c5aa0; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
            .btn:hover { background: #1e3d6f; }
            .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; }
            .btn-secondary:hover { background: #545b62; }
            .actions { margin-top: 20px; display: flex; gap: 10px; }
            .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
            .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .hint { font-size: 12px; color: #777; margin-top: 3px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>U.E. Amelia Ríos — Editar Usuario</h1>
            <div>
                <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>
                <a href="/ue-ameliarios/public/index.php/usuarios">Usuarios</a>
                <a href="/ue-ameliarios/public/index.php/dashboard">Panel</a>
                <a href="/ue-ameliarios/public/index.php/logout">Salir</a>
            </div>
        </div>

        <div class="container">
            <div class="card">
                <h2>Editar Usuario: <?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/ue-ameliarios/public/index.php/usuarios/editar/<?= $usuario['id_usuario'] ?>">
                    <h3 style="margin-bottom:15px;color:#2c5aa0;font-size:16px;">Datos Personales</h3>

                    <div class="row">
                        <div class="form-group">
                            <label>Cédula *</label>
                            <input type="text" name="cedula" value="<?= htmlspecialchars($usuario['cedula']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label>Nombres *</label>
                            <input type="text" name="nombres" value="<?= htmlspecialchars($usuario['nombres']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Apellidos *</label>
                            <input type="text" name="apellidos" value="<?= htmlspecialchars($usuario['apellidos']) ?>" required>
                        </div>
                    </div>

                    <h3 style="margin:20px 0 15px;color:#2c5aa0;font-size:16px;">Datos de Acceso</h3>

                    <div class="row">
                        <div class="form-group">
                            <label>Usuario *</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($usuario['username']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Correo *</label>
                            <input type="email" name="correo" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="password" placeholder="Dejar vacío para no cambiar" minlength="6">
                        <div class="hint">Si dejas este campo vacío, la contraseña actual se mantiene.</div>
                    </div>

                    <div class="form-group">
                        <label>Rol *</label>
                        <select name="id_rol" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>" <?= ($r['id_rol'] == $rolActual) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">Guardar Cambios</button>
                        <a href="/ue-ameliarios/public/index.php/usuarios" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// /usuarios/editar (POST) — Procesar actualización
// ==========================================
if ($uri === '/usuarios/editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requirePermission('usuario.editar');

    $id = $routeParams['id'] ?? 0;

    $stmt = db()->prepare("SELECT id_usuario, id_persona FROM usuario WHERE id_usuario = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $usuarioActual = $stmt->fetch();

    if (!$usuarioActual) {
        header('Location: /ue-ameliarios/public/index.php/usuarios?error=' . urlencode('Usuario no encontrado'));
        exit;
    }

    $cedula    = trim($_POST['cedula'] ?? '');
    $nombres   = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $password  = $_POST['password'] ?? '';
    $id_rol    = (int)($_POST['id_rol'] ?? 0);

    if (empty($cedula) || empty($nombres) || empty($apellidos) ||
        empty($username) || empty($correo) || $id_rol === 0) {
        header("Location: /ue-ameliarios/public/index.php/usuarios/editar/{$id}?error=" . urlencode('Todos los campos con * son obligatorios'));
        exit;
    }

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM persona WHERE cedula = :c AND id_persona != :idp");
    $stmt->execute(['c' => $cedula, 'idp' => $usuarioActual['id_persona']]);
    if ($stmt->fetch()['total'] > 0) {
        header("Location: /ue-ameliarios/public/index.php/usuarios/editar/{$id}?error=" . urlencode('La cédula ya está registrada en otro usuario'));
        exit;
    }

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM usuario WHERE (username = :u OR correo = :c) AND id_usuario != :id");
    $stmt->execute(['u' => $username, 'c' => $correo, 'id' => $id]);
    if ($stmt->fetch()['total'] > 0) {
        header("Location: /ue-ameliarios/public/index.php/usuarios/editar/{$id}?error=" . urlencode('El usuario o correo ya están registrados en otro usuario'));
        exit;
    }

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM rol WHERE id_rol = :r");
    $stmt->execute(['r' => $id_rol]);
    if ($stmt->fetch()['total'] === 0) {
        header("Location: /ue-ameliarios/public/index.php/usuarios/editar/{$id}?error=" . urlencode('El rol seleccionado no existe'));
        exit;
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("
            UPDATE persona
            SET cedula = :cedula, nombres = :nombres, apellidos = :apellidos, telefono = :telefono
            WHERE id_persona = :id_persona
        ");
        $stmt->execute([
            'cedula'     => $cedula,
            'nombres'    => $nombres,
            'apellidos'  => $apellidos,
            'telefono'   => $telefono ?: null,
            'id_persona' => $usuarioActual['id_persona'],
        ]);

        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = db()->prepare("
                UPDATE usuario
                SET username = :username, correo = :correo, password_hash = :password_hash
                WHERE id_usuario = :id
            ");
            $stmt->execute([
                'username'      => $username,
                'correo'        => $correo,
                'password_hash' => $passwordHash,
                'id'            => $id,
            ]);
        } else {
            $stmt = db()->prepare("
                UPDATE usuario
                SET username = :username, correo = :correo
                WHERE id_usuario = :id
            ");
            $stmt->execute([
                'username' => $username,
                'correo'   => $correo,
                'id'       => $id,
            ]);
        }

        db()->prepare("DELETE FROM usuario_rol WHERE id_usuario = :id")->execute(['id' => $id]);

        $stmt = db()->prepare("
            INSERT INTO usuario_rol (id_usuario, id_rol, es_principal, asignado_por)
            VALUES (:id_usuario, :id_rol, 1, :asignado_por)
        ");
        $stmt->execute([
            'id_usuario'   => $id,
            'id_rol'       => $id_rol,
            'asignado_por' => $_SESSION['user']['id_usuario'],
        ]);

        $stmt = db()->prepare("
            INSERT INTO bitacora (id_usuario, accion, tabla_afectada, id_registro, detalle, ip)
            VALUES (:id_usuario, 'editar_usuario', 'usuario', :id_registro, :detalle, :ip)
        ");
        $stmt->execute([
            'id_usuario'  => $_SESSION['user']['id_usuario'],
            'id_registro' => $id,
            'detalle'     => "Usuario editado: {$username}",
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        db()->commit();

        header('Location: /ue-ameliarios/public/index.php/usuarios?exito=' . urlencode('Usuario actualizado exitosamente'));
        exit;
    } catch (Exception $e) {
        db()->rollBack();
        header("Location: /ue-ameliarios/public/index.php/usuarios/editar/{$id}?error=" . urlencode('Error al actualizar: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// /usuarios/eliminar (GET) — Soft delete
// ==========================================
if ($uri === '/usuarios/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('usuario.eliminar');

    $id = $routeParams['id'] ?? 0;

    if ($id === (int)$_SESSION['user']['id_usuario']) {
        header('Location: /ue-ameliarios/public/index.php/usuarios?error=' . urlencode('No puedes eliminarte a ti mismo'));
        exit;
    }

    $stmt = db()->prepare("
        SELECT u.id_usuario, u.username, u.activo,
               p.nombres, p.apellidos
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        WHERE u.id_usuario = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        header('Location: /ue-ameliarios/public/index.php/usuarios?error=' . urlencode('Usuario no encontrado'));
        exit;
    }

    if (!$usuario['activo']) {
        header('Location: /ue-ameliarios/public/index.php/usuarios?error=' . urlencode('El usuario ya está inactivo'));
        exit;
    }

    $stmt = db()->prepare("
        SELECT COUNT(*) AS total
        FROM usuario_rol ur
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = :id AND r.nombre = 'Administrador'
    ");
    $stmt->execute(['id' => $id]);
    $esAdmin = (int)$stmt->fetch()['total'] > 0;

    $stmt = db()->prepare("
        SELECT COUNT(*) AS total
        FROM usuario u
        INNER JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE r.nombre = 'Administrador'
          AND u.activo = 1
          AND u.id_usuario != :id
    ");
    $stmt->execute(['id' => $id]);
    $otrosAdmins = (int)$stmt->fetch()['total'];

    if ($esAdmin && $otrosAdmins === 0) {
        header('Location: /ue-ameliarios/public/index.php/usuarios?error=' . urlencode('No puedes eliminar al último Administrador activo'));
        exit;
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("UPDATE usuario SET activo = 0 WHERE id_usuario = :id");
        $stmt->execute(['id' => $id]);

        $stmt = db()->prepare("
            INSERT INTO bitacora (id_usuario, accion, tabla_afectada, id_registro, detalle, ip)
            VALUES (:id_usuario, 'eliminar_usuario', 'usuario', :id_registro, :detalle, :ip)
        ");
        $stmt->execute([
            'id_usuario'  => $_SESSION['user']['id_usuario'],
            'id_registro' => $id,
            'detalle'     => "Usuario desactivado: {$usuario['username']}",
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        db()->commit();

        header('Location: /ue-ameliarios/public/index.php/usuarios?exito=' . urlencode('Usuario desactivado exitosamente'));
        exit;
    } catch (Exception $e) {
        db()->rollBack();
        header('Location: /ue-ameliarios/public/index.php/usuarios?error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
        exit;
    }
}