<?php
/**
 * app/auth.php
 * Login, logout y dashboard.
 */

/** @noinspection PhpUndefinedFunctionInspection */

// ==========================================
// /login (GET)
// ==========================================
if ($uri === '/login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Login — U.E. Amelia Ríos</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 320px; }
            h2 { text-align: center; color: #333; margin-top: 0; }
            input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
            button { width: 100%; padding: 10px; background: #2c5aa0; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #1e3d6f; }
            .error { color: red; text-align: center; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="login">
            <h2>Iniciar Sesión</h2>
            <?php if (isset($_GET['error'])): ?>
                <p class="error">Usuario o contraseña incorrectos</p>
            <?php endif; ?>
            <form method="POST" action="/ue-ameliarios/public/index.php/login">
                <input type="text" name="username" placeholder="Usuario" required autofocus>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Entrar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// /login (POST)
// ==========================================
if ($uri === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("
        SELECT u.id_usuario, u.username, u.correo, u.password_hash,
               p.nombres, p.apellidos
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        WHERE u.username = :u AND u.activo = 1
        LIMIT 1
    ");
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: /ue-ameliarios/public/index.php/login?error=1');
        exit;
    }

    $stmt = db()->prepare("
        SELECT r.nombre, ur.es_principal
        FROM usuario_rol ur
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = :id
        ORDER BY ur.es_principal DESC
    ");
    $stmt->execute(['id' => $user['id_usuario']]);
    $roles = $stmt->fetchAll();

    $stmt = db()->prepare("
        SELECT DISTINCT p.codigo
        FROM usuario_rol ur
        INNER JOIN rol_permiso rp ON ur.id_rol = rp.id_rol
        INNER JOIN permiso p ON rp.id_permiso = p.id_permiso
        WHERE ur.id_usuario = :id
    ");
    $stmt->execute(['id' => $user['id_usuario']]);
    $permisos = array_column($stmt->fetchAll(), 'codigo');

    $_SESSION['user'] = [
        'id_usuario' => $user['id_usuario'],
        'username'   => $user['username'],
        'correo'     => $user['correo'],
        'nombres'    => $user['nombres'],
        'apellidos'  => $user['apellidos'],
        'roles'      => array_column($roles, 'nombre'),
        'permisos'   => $permisos,
    ];

    header('Location: /ue-ameliarios/public/index.php/dashboard');
    exit;
}

// ==========================================
// /logout
// ==========================================
if ($uri === '/logout') {
    session_destroy();
    header('Location: /ue-ameliarios/public/index.php/login');
    exit;
}

// ==========================================
// /dashboard (HTML)
// ==========================================
if ($uri === '/dashboard') {
    requireLogin();

    $user = $_SESSION['user'];
    $rolPrincipal = primaryRole();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Dashboard — U.E. Amelia Ríos</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; }
            .header { background: #2c5aa0; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
            .header h1 { font-size: 20px; }
            .header .user-info { font-size: 14px; }
            .header a { color: white; text-decoration: none; margin-left: 15px; padding: 5px 10px; background: rgba(255,255,255,0.2); border-radius: 4px; }
            .header a:hover { background: rgba(255,255,255,0.3); }
            .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
            .welcome { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .welcome h2 { color: #333; margin-bottom: 5px; }
            .welcome p { color: #666; margin: 4px 0; }
            .badge { display: inline-block; background: #2c5aa0; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; margin-right: 5px; }
            .menu { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; }
            .menu-item { background: white; padding: 20px; border-radius: 8px; text-decoration: none; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; }
            .menu-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
            .menu-item .icon { font-size: 28px; margin-bottom: 10px; }
            .menu-item .title { font-weight: bold; font-size: 16px; }
            .menu-item .desc { font-size: 13px; color: #777; margin-top: 5px; }
            .section-title { color: #2c5aa0; margin: 25px 0 15px; font-size: 18px; border-bottom: 2px solid #2c5aa0; padding-bottom: 5px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>U.E. Amelia Ríos — Panel</h1>
            <div class="user-info">
                <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>
                <a href="/ue-ameliarios/public/index.php/logout">Salir</a>
            </div>
        </div>

        <div class="container">
            <div class="welcome">
                <h2>Bienvenido, <?= htmlspecialchars($user['nombres']) ?> 👋</h2>
                <p>Rol principal: <strong><?= htmlspecialchars($rolPrincipal) ?></strong></p>
                <p>
                    <?php foreach ($user['roles'] as $r): ?>
                        <span class="badge"><?= htmlspecialchars($r) ?></span>
                    <?php endforeach; ?>
                </p>
            </div>

            <?php if (hasRole('Administrador')): ?>
                <h3 class="section-title">Administración</h3>
                <div class="menu">
                    <a href="/ue-ameliarios/public/index.php/usuarios" class="menu-item">
                        <div class="icon">👥</div>
                        <div class="title">Usuarios</div>
                        <div class="desc">Gestionar usuarios</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">📚</div>
                        <div class="title">Académico</div>
                        <div class="desc">Grados, secciones, materias</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">📅</div>
                        <div class="title">Eventos</div>
                        <div class="desc">Eventos institucionales</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">📊</div>
                        <div class="title">Reportes</div>
                        <div class="desc">Generar reportes</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">📋</div>
                        <div class="title">Bitácora</div>
                        <div class="desc">Registro de acciones</div>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (hasRole('Docente')): ?>
                <h3 class="section-title">Docencia</h3>
                <div class="menu">
                    <a href="#" class="menu-item">
                        <div class="icon">✅</div>
                        <div class="title">Registrar Asistencia</div>
                        <div class="desc">Registrar asistencia del día</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">📊</div>
                        <div class="title">Mis Reportes</div>
                        <div class="desc">Reportes de mis cursos</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">📅</div>
                        <div class="title">Mis Eventos</div>
                        <div class="desc">Ver eventos asignados</div>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (hasRole('Representante')): ?>
                <h3 class="section-title">Mis Representados</h3>
                <div class="menu">
                    <a href="#" class="menu-item">
                        <div class="icon">👶</div>
                        <div class="title">Mis Estudiantes</div>
                        <div class="desc">Ver mis representados</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">✅</div>
                        <div class="title">Asistencia</div>
                        <div class="desc">Consultar asistencia</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">📝</div>
                        <div class="title">Justificar Falta</div>
                        <div class="desc">Enviar justificación</div>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (hasRole('Estudiante')): ?>
                <h3 class="section-title">Mi Información</h3>
                <div class="menu">
                    <a href="#" class="menu-item">
                        <div class="icon">✅</div>
                        <div class="title">Mi Asistencia</div>
                        <div class="desc">Ver mi asistencia</div>
                    </a>
                    <a href="#" class="menu-item">
                        <div class="icon">👤</div>
                        <div class="title">Mi Perfil</div>
                        <div class="desc">Mis datos personales</div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}