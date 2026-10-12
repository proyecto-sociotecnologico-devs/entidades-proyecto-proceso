<?php
/**
 * app/db.php
 * Conexión PDO a la base de datos (Singleton).
 */

// Cargar configuración del .env
$env = parse_ini_file(__DIR__ . '/../.env');

/**
 * Devuelve la instancia única de PDO.
 *
 * @return PDO
 */
function db(): PDO {
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