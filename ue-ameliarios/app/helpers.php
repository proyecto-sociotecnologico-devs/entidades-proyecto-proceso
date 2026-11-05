<?php
/**
 * app/helpers.php
 * Funciones de RBAC Jerárquico, utilidades y notificaciones.
 */

/**
 * Verifica si el usuario tiene un rol específico.
 */
function hasRole(string $rol): bool
{
    if (!isset($_SESSION['user']['roles'])) return false;
    return in_array($rol, $_SESSION['user']['roles'], true);
}

/**
 * Verifica si el usuario tiene al menos uno de los roles dados.
 */
function hasAnyRole(array $roles): bool
{
    foreach ($roles as $rol) {
        if (hasRole($rol)) return true;
    }
    return false;
}

/**
 * Obtiene el rol principal del usuario.
 */
function primaryRole(): ?string
{
    return $_SESSION['user']['roles'][0] ?? null;
}

/**
 * NUEVO RBAC JERÁRQUICO: Verifica si el usuario tiene acceso a un Módulo.
 * Se usa para mostrar u ocultar secciones enteras en el menú lateral.
 */
function hasModuleAccess(string $modulo): bool
{
    if (!isset($_SESSION['user']['modulos'])) return false;
    return in_array($modulo, $_SESSION['user']['modulos'], true);
}

/**
 * NUEVO RBAC JERÁRQUICO: Verifica si el usuario tiene un permiso (VER, CREAR, EDITAR, ELIMINAR)
 * sobre una Vista específica (ej: '/usuarios').
 */
function can(string $ruta_vista, string $accion = 'VER'): bool
{
    if (!isset($_SESSION['user']['permisos'])) return false;
    
    // Validamos si la ruta existe en sus permisos y si la acción está permitida
    return isset($_SESSION['user']['permisos'][$ruta_vista][$accion]) 
        && $_SESSION['user']['permisos'][$ruta_vista][$accion] === true;
}

/**
 * Redirige al login si no hay sesión.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: /ue-ameliarios/public/index.php/login');
        exit;
    }
}

/**
 * Bloquea el acceso a una ruta si el usuario no tiene el permiso requerido.
 */
function requirePermission(string $ruta_vista, string $accion = 'VER'): void
{
    if (!can($ruta_vista, $accion)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Acceso Denegado</title></head>';
        echo '<body style="font-family:Arial;text-align:center;padding:50px;">';
        echo '<h1>🚫 Acceso Denegado</h1>';
        echo '<p>No tienes permisos para acceder a esta sección (' . htmlspecialchars($ruta_vista) . ' - ' . htmlspecialchars($accion) . ').</p>';
        echo '<a href="/ue-ameliarios/public/index.php/dashboard">Volver al panel</a>';
        echo '</body></html>';
        exit;
    }
}

/**
 * Cuenta las justificaciones pendientes para el menú lateral (Badges).
 * Utiliza la función db() de app/db.php.
 */
function contarJustificacionesPendientes(): int 
{
    if (!isset($_SESSION['user'])) {
        return 0;
    }

    $rol = primaryRole(); 
    
    try {
        $pdo = db();

        if ($rol === 'Administrador' || $rol === 'Docente') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM justificacion WHERE estado = 'Pendiente'");
            return (int) $stmt->fetchColumn();
        } 
        
        if ($rol === 'Representante') {
            // En la nueva estructura, el id del usuario está en $_SESSION['user']['id']
            // Asumiendo que la tabla representante tiene el id_persona del usuario.
            // Para simplificar y que no falle, lo dejamos así por ahora, o lo comentamos si da error.
            $id_persona = $_SESSION['user']['id'] ?? 0; // Temporal
            
            $sql = "SELECT COUNT(j.id_justificacion) 
                    FROM justificacion j
                    INNER JOIN representante r ON j.id_representante = r.id_representante
                    WHERE r.id_persona = :id_persona 
                    AND j.estado IN ('Aprobada', 'Rechazada')"; 
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_persona' => $id_persona]);
            return (int) $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        return 0;
    }

    return 0;
}