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

/**
 * Verifica si el usuario actual tiene un rol específico.
 */
function hasRole(string $rol): bool
{
    if (!isset($_SESSION['user']['roles'])) {
        return false;
    }
    return in_array($rol, $_SESSION['user']['roles'], true);
}

/**
 * Verifica si el usuario actual tiene un permiso específico.
 */
function can(string $permiso): bool
{
    if (!isset($_SESSION['user']['permisos'])) {
        return false;
    }
    return in_array($permiso, $_SESSION['user']['permisos'], true);
}

/**
 * Verifica si el usuario tiene ALGUNO de los roles indicados.
 */
function hasAnyRole(array $roles): bool
{
    foreach ($roles as $rol) {
        if (hasRole($rol)) {
            return true;
        }
    }
    return false;
}

/**
 * Devuelve el rol principal del usuario (el primero de la lista).
 */
function primaryRole(): ?string
{
    return $_SESSION['user']['roles'][0] ?? null;
}

// ==========================================
// URI
// ==========================================
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/ue-ameliarios/public', '', $uri);
$uri = str_replace('/index.php', '', $uri);
$uri = rtrim($uri, '/') ?: '/';

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
// /login (GET) — Formulario
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
// /login (POST) — Validar + Cargar roles y permisos
// ==========================================
if ($uri === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Buscar usuario con datos de persona
    $stmt = db()->prepare("
        SELECT u.id_usuario, u.username, u.correo, u.password_hash, u.activo,
               p.nombres, p.apellidos
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        WHERE u.username = :u
        LIMIT 1
    ");
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: /ue-ameliarios/public/index.php/login?error=1');
        exit;
    }

    // 2. Cargar roles
    $stmt = db()->prepare("
        SELECT r.nombre, ur.es_principal
        FROM usuario_rol ur
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = :id
        ORDER BY ur.es_principal DESC
    ");
    $stmt->execute(['id' => $user['id_usuario']]);
    $roles = $stmt->fetchAll();

    // 3. Cargar permisos
    $stmt = db()->prepare("
        SELECT DISTINCT p.codigo
        FROM usuario_rol ur
        INNER JOIN rol_permiso rp ON ur.id_rol = rp.id_rol
        INNER JOIN permiso p ON rp.id_permiso = p.id_permiso
        WHERE ur.id_usuario = :id
    ");
    $stmt->execute(['id' => $user['id_usuario']]);
    $permisos = array_column($stmt->fetchAll(), 'codigo');

    // 4. Guardar en sesión
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
// /dashboard
// ==========================================
if ($uri === '/dashboard') {
    if (!isset($_SESSION['user'])) {
        header('Location: /ue-ameliarios/public/index.php/login');
        exit;
    }

    $user = $_SESSION['user'];
    $rolPrincipal = primaryRole();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'         => 'OK',
        'msg'            => 'Bienvenido ' . $user['nombres'] . ' ' . $user['apellidos'],
        'rol_principal'  => $rolPrincipal,
        'roles'          => $user['roles'],
        'total_permisos' => count($user['permisos']),

        // Verificaciones de ejemplo
        'puede_ver_usuarios'   => can('usuario.ver'),
        'puede_crear_usuarios' => can('usuario.crear'),
        'puede_ver_asistencia' => can('asistencia.ver'),
        'es_administrador'     => hasRole('Administrador'),
        'es_docente'           => hasRole('Docente'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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