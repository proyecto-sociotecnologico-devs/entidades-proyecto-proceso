<?php
/**
 * app/helpers.php
 * Funciones de RBAC y utilidades.
 */

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