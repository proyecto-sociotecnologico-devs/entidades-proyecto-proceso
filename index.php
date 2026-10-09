<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Configuración
$env = parse_ini_file(__DIR__ . '/../.env');

// ==========================================
// CONEXIÓN PDO
// ==========================================
function db() {
    global $env;
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ==========================================
// HELPERS DE RBAC
// ==========================================
function hasRole(string $rol): bool
{
    if (!isset($_SESSION['user']['roles'])) return false;
    return in_array($rol, $_SESSION['user']['roles'], true);
}

function can(string $permiso): bool
{
    if (!isset($_SESSION['user']['permisos'])) return false;
    return in_array($permiso, $_SESSION['user']['permisos'], true);
}

function hasAnyRole(array $roles): bool
{
    foreach ($roles as $rol) {
        if (hasRole($rol)) return true;
    }
    return false;
}

function primaryRole(): ?string
{
    return $_SESSION['user']['roles'][0] ?? null;
}

function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: /ue-ameliarios/public/index.php/login');
        exit;
    }
}

function requirePermission(string $permiso): void
{
    if (!can($permiso)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Acceso Denegado</title></head>';
        echo '<body style="font-family:Arial;text-align:center;padding:50px;">';
        echo '<h1>🚫 Acceso Denegado</h1>';
        echo '<p>No tienes permisos para acceder a esta sección.</p>';
        echo '<a href="/ue-ameliarios/public/index.php/dashboard">Volver al panel</a>';
        echo '</body></html>';
        exit;
    }
}

// ==========================================
// URI
// ==========================================
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/ue-ameliarios/public', '', $uri);
$uri = str_replace('/index.php', '', $uri);
$uri = rtrim($uri, '/') ?: '/';

// ==========================================
// EXTRAER ID DE LA RUTA
// ==========================================
$routeParams = [];
if (preg_match('#^/usuarios/editar/(\d+)$#', $uri, $m)) {
    $routeParams['id'] = (int)$m[1];
    $uri = '/usuarios/editar';
} elseif (preg_match('#^/usuarios/eliminar/(\d+)$#', $uri, $m)) {
    $routeParams['id'] = (int)$m[1];
    $uri = '/usuarios/eliminar';
}

// ==========================================
// VERIFICACIÓN: usuario activo (si hay sesión)
// ==========================================
if (isset($_SESSION['user']['id_usuario']) && $uri !== '/logout') {
    $stmt = db()->prepare("SELECT activo FROM usuario WHERE id_usuario = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user']['id_usuario']]);
    $u = $stmt->fetch();

    if (!$u || !$u['activo']) {
        session_destroy();
        header('Location: /ue-ameliarios/public/index.php/login?error=1');
        exit;
    }
}

// ==========================================
// /test
// ==========================================
if ($uri === '/test') {
    header('Content-Type: application/json; charset=utf-8');
    $stmt = db()->query("SELECT COUNT(*) AS total FROM rol");
    echo json_encode([
        'status' => 'OK',
        'msg'    => 'Conexión exitosa',
        'roles'  => $stmt->fetch()['total'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

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

// ==========================================
// /logout
// ==========================================
if ($uri === '/logout') {
    session_destroy();
    header('Location: /ue-ameliarios/public/index.php/login');
    exit;
}

// ==========================================
// / (raíz)
// ==========================================
if ($uri === '/') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'OK',
        'msg'    => 'API del sistema de la U.E. Amelia Ríos',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 404
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ERROR',
    'msg'    => 'Ruta no encontrada: ' . $uri,
], JSON_UNESCAPED_UNICODE);