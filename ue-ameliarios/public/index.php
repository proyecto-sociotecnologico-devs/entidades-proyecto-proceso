<?php
/**
 * public/index.php
 * Punto de entrada de la aplicación (Front Controller).
 */

// ✅ 1. SEGURIDAD (TEST-05): Desactivar muestra de errores en entorno de producción/defensa
ini_set('display_errors', 0); 
error_reporting(0); 

session_start();

// ==========================================
// CARGA DE MÓDULOS GLOBALES
// ==========================================
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// ==========================================
// PROCESAMIENTO DE URI
// ==========================================
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/ue-ameliarios/public', '', $uri);
$uri = str_replace('/index.php', '', $uri);
$uri = rtrim($uri, '/') ?: '/';

// ==========================================
// EXTRACCIÓN DE PARÁMETROS (ID)
// ==========================================
$routeParams = [];

$extractId = function($path) use (&$uri, &$routeParams) {
    if (preg_match('#^' . preg_quote($path, '#') . '/(\d+)$#', $uri, $m)) {
        $routeParams['id'] = (int)$m[1];
        $uri = $path; 
    }
};

$extractId('/usuarios/editar');
$extractId('/usuarios/eliminar');
$extractId('/academico/grados/editar');
$extractId('/academico/grados/eliminar');
$extractId('/academico/secciones/editar');
$extractId('/academico/secciones/eliminar');
$extractId('/academico/materias/editar');
$extractId('/academico/materias/eliminar');
$extractId('/academico/cursos/editar');
$extractId('/academico/cursos/eliminar');
$extractId('/estudiantes/editar');
$extractId('/estudiantes/eliminar');
$extractId('/representantes/editar');
$extractId('/representantes/eliminar');
$extractId('/inscripciones/eliminar');
$extractId('/justificaciones/editar');
$extractId('/justificaciones/eliminar');
$extractId('/roles/editar');
$extractId('/roles/eliminar');
$extractId('/modulos/editar');
$extractId('/modulos/eliminar');

// ==========================================
// VERIFICACIÓN DE SESIÓN Y USUARIO ACTIVO (TEST-03)
// ==========================================
$public_routes = ['/login', '/logout', '/test', '/'];
if (isset($_SESSION['user']['id']) && !in_array($uri, $public_routes, true)) {
    $stmt = db()->prepare("SELECT activo FROM usuario WHERE id_usuario = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user']['id']]);
    $u = $stmt->fetch();

    if (!$u || !$u['activo']) {
        session_destroy();
        header('Location: /ue-ameliarios/public/index.php/login?error=1');
        exit;
    }
}

// ==========================================
// RUTA DE PRUEBA (/test)
// ==========================================
if ($uri === '/test') {
    header('Content-Type: application/json; charset=utf-8');
    $stmt = db()->query("SELECT COUNT(*) AS total FROM rol");
    echo json_encode([
        'status' => 'OK',
        'msg'    => 'Conexión exitosa a la BD',
        'roles'  => $stmt->fetch()['total'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ==========================================
// ENRUTAMIENTO A MÓDULOS
// ==========================================

if (in_array($uri, ['/login', '/logout', '/dashboard'], true)) {
    require __DIR__ . '/../app/auth.php';
    exit;
}

$modulos = [
    '/usuarios'       => 'usuarios.php',
    '/academico'      => 'academico.php',
    '/estudiantes'    => 'estudiantes.php',
    '/representantes' => 'representantes.php',
    '/inscripciones'  => 'inscripciones.php',
    '/asistencia'     => 'asistencia.php',
    '/reportes'       => 'reportes.php',
    '/bitacora'       => 'bitacora.php',
    '/eventos'        => 'eventos.php',
    '/representados'  => 'representados.php',
    '/justificaciones'=> 'justificaciones.php',
    '/roles'          => 'roles.php',       // ✅ NUEVO
    '/modulos'        => 'modulos.php',     // ✅ NUEVO
];

foreach ($modulos as $ruta => $archivo) {
    if (str_starts_with($uri, $ruta)) {
        require __DIR__ . '/../app/' . $archivo;
        exit;
    }
}

// ==========================================
// RUTA RAÍZ (/)
// ==========================================
if ($uri === '/') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'OK',
        'msg'    => 'API del Sistema de Gestión U.E. Amelia Ríos',
        'docs'   => 'Use /test para verificar la conexión a la base de datos.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ==========================================
// 404 NOT FOUND
// ==========================================
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ERROR',
    'msg'    => 'Ruta no encontrada: ' . $uri,
], JSON_UNESCAPED_UNICODE);