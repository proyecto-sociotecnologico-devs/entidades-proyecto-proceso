# Estructura del Proyecto

## Carpetas

- `app/` → Módulos de código PHP.
  - `auth.php` → Login, logout, dashboard.
  - `db.php` → Conexión PDO.
  - `helpers.php` → Funciones RBAC y utilidades.
  - `usuarios.php` → CRUD Usuarios.
  - `academico.php` → CRUD Grados, Secciones, Materias, Cursos.
  - `estudiantes.php` → CRUD Estudiantes.
- `public/` → Carpeta pública (accesible desde el navegador).
  - `index.php` → Punto de entrada (solo rutas).
  - `.htaccess` → Reescritura de URLs.
- `docs/` → Documentación.
  - `SESIONES.md` → Diario de sesiones.
  - `ESTRUCTURA.md` → Este archivo.
- `.env` → Variables de entorno (credenciales BD).

## Acceso

- URL base: `http://localhost/ue-ameliarios/public/index.php/`
- Login: `http://localhost/ue-ameliarios/public/index.php/login`

## Módulos funcionales

- ✅ Login + Sesiones.
- ✅ RBAC (4 roles, 30+ permisos).
- ✅ CRUD Usuarios.
- ✅ CRUD Grados.
- ✅ CRUD Secciones.
- ✅ CRUD Materias.
- ✅ CRUD Cursos.
- ✅ CRUD Estudiantes.
- ⏳ CRUD Representantes.
- ⏳ Inscripciones.
- ⏳ Asistencia (Semana 3).
- ⏳ Eventos (Semana 4).
- ⏳ Encuestas (Semana 4).

## Estado general

- **MVP:** ~65%.
- **Semana 1:** COMPLETA.
- **Semana 2:** en progreso.
- **Fecha límite interna:** 2 de noviembre.
- **Entrega final:** 11 de noviembre.