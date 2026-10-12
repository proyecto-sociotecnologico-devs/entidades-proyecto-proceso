<?php
/**
 * public/index.php
 * Punto de entrada de la aplicación.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// ==========================================
// CARGA DE MÓDULOS GLOBALES
// ==========================================
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

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
// RUTAS → MÓDULOS
// ==========================================

// Autenticación (login, logout, dashboard)
if (in_array($uri, ['/login', '/logout', '/dashboard'], true)) {
    require __DIR__ . '/../app/auth.php';
    exit;
}

// Usuarios (CRUD)
if (str_starts_with($uri, '/usuarios')) {
    require __DIR__ . '/../app/usuarios.php';
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

// ==========================================
// 404
// ==========================================
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ERROR',
    'msg'    => 'Ruta no encontrada: ' . $uri,
], JSON_UNESCAPED_UNICODE);