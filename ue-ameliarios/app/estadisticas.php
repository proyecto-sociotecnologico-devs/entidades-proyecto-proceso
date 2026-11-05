<?php
// app/estadisticas.php
// Endpoint JSON para el Dashboard Analítico (Sección 4 del Instructivo)

// 1. Iniciar sesión para validar que solo usuarios autenticados puedan ver los datos
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// 2. Cargar conexión
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = db();
    $stats = [];

    // 1. Total de estudiantes activos
    $stmt = $pdo->query("SELECT COUNT(*) FROM estudiante WHERE estado = 'Activo'");
    $stats['total_estudiantes'] = (int)$stmt->fetchColumn();

    // 2. Total de docentes (usamos LIKE por si el rol se llama 'Docente' o 'Docentes')
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id_usuario) 
        FROM usuario u
        INNER JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE r.nombre LIKE '%Docente%' AND u.activo = 1
    ");
    $stats['total_docentes'] = (int)$stmt->fetchColumn();

    // 3. Asistencias registradas hoy
    $stmt = $pdo->query("SELECT COUNT(*) FROM asistencia WHERE fecha = CURDATE()");
    $stats['asistencias_hoy'] = (int)$stmt->fetchColumn();

    // 4. Justificaciones pendientes
    $stmt = $pdo->query("SELECT COUNT(*) FROM justificacion WHERE estado = 'Pendiente'");
    $stats['justificaciones_pendientes'] = (int)$stmt->fetchColumn();

    // 5. Datos para el gráfico: Asistencias por estado (últimos 30 días)
    $stmt = $pdo->query("
        SELECT estado, COUNT(*) as total 
        FROM asistencia 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY estado
    ");
    $stats['asistencias_por_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Devolver JSON limpio
    echo json_encode($stats);

} catch (PDOException $e) {
    http_response_code(500);
    // En producción no mostramos el error exacto, pero para desarrollo es útil
    echo json_encode(["error" => "Error en la base de datos"]);
}