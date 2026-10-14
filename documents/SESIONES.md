# Diario de Sesiones — U.E. Amelia Ríos

## Día 1 (6 oct) — Login + Sesiones
- Conexión PDO a amelia_rios.
- Login con password_verify.
- Sesiones PHP.

## Día 2 (7 oct) — RBAC
- 4 roles cargados en sesión.
- Funciones hasRole(), can(), requirePermission().

## Día 3 (8 oct) — Dashboard HTML por rol
- Panel diferenciado para Admin, Docente, Representante, Estudiante.

## Día 4 (9 oct) — CRUD Usuarios (crear + listar)
- Listado con tabla HTML.
- Formulario de creación.
- Validaciones de duplicados.

## Día 5 (10 oct) — CRUD Usuarios (editar + eliminar + seguridad)
- Editar usuario con reasignación de rol.
- Soft delete (activo = 0).
- Verificación global de sesión.

## Día 6 (11 oct) — Pruebas RBAC
- Probados los 4 roles.
- Confirmado acceso denegado por URL.

## Día 7 (12 oct) — Reconstrucción + separación modular
- Restaurados archivos tras error con GitHub Desktop.
- index.php separado en módulos (app/auth.php, app/usuarios.php).
- Cambio de Intelephense a PHP IntelliSense.

## Día 8 (13 oct) — CRUD Grados + Secciones
- app/academico.php creado.
- CRUD Grados y Secciones funcional.
- Permisos academico.* asignados al Admin.

## Día 9 (14 oct) — CRUD Materias + Cursos
- CRUD Materias agregado.
- CRUD Cursos agregado.
- Validaciones referenciales (no eliminar con dependencias).

## Día 10 (15 oct) — CRUD Estudiantes
- app/estudiantes.php creado.
- CRUD completo con transacciones.
- Permisos estudiante.* y representante.* creados.
- Tarjeta Estudiantes en el dashboard.
- Creado Sofía Martínez como prueba.