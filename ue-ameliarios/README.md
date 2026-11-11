# 🏫 Sistema de Gestión de Información Administrativa – U.E. Amelia Ríos

**Caso de Estudio:** Control de Asistencia y Gestión Académica  
**Desarrollador:** [Xavier Hernández]  
**Institución:** Universidad Nacional Experimental Rafael María Baralt (UNERMB) – Núcleo Maracaibo  
**Estado del Proyecto:** Versión Beta Funcional (Aprobado con calificación máxima 20/20)

---

## 📌 Descripción del Proyecto

Sistema web desarrollado para centralizar y automatizar la gestión de información administrativa y académica de la Unidad Educativa Amelia Ríos. El núcleo del sistema resuelve la problemática del control manual de asistencias, ofreciendo un flujo digital completo desde el registro de la falta hasta la justificación por parte del representante y su aprobación por la institución.

## 🚀 Características Principales

- **🔐 Modelo de Seguridad RBAC Jerárquico:** Control de acceso basado en roles (Administrador, Docente, Representante, Estudiante) con granularidad a nivel de Módulo → Vista → Permiso (VER, CREAR, EDITAR, ELIMINAR).
- **✅ Control de Asistencias:** Registro diario por curso y materia, con validación de fechas y estados (Presente, Ausente, Retardo, Justificado).
- **📝 Flujo de Justificaciones:** Los representantes pueden enviar justificativos que, al ser aprobados, actualizan automáticamente el estado de la asistencia mediante integridad referencial.
- **📊 Dashboard Analítico:** Panel principal con estadísticas en tiempo real y gráficos de asistencia (Chart.js).
- **📋 Auditoría y Trazabilidad:** Registro de acciones críticas (`auditoria_logs`) con marca de tiempo, usuario, tabla afectada y dirección IP.
- **🛡️ Integridad de Datos:** Uso de transacciones ACID (`BEGIN`, `COMMIT`, `ROLLBACK`) y consultas preparadas (PDO) para prevenir inyección SQL y garantizar la consistencia.

## 🛠️ Stack Tecnológico

- **Backend:** PHP 8.x (Programación orientada a objetos, consultas preparadas PDO).
- **Base de Datos:** MySQL / InnoDB (36 tablas normalizadas hasta 3FN).
- **Entorno de Desarrollo:** Laragon (Windows), Visual Studio Code.
- **Pruebas de API:** Postman.
- **Frontend:** HTML5, CSS3, JavaScript (Chart.js para visualización de datos).

## 🗄️ Highlights de la Base de Datos

- **Entidad Central `persona`:** Diseño normalizado que evita la redundancia de datos personales, relacionándose con `usuario`, `empleado`, `estudiante` y `representante`.
- **Gestión Académica:** Estructura relacional sólida entre `periodo_academico`, `grado`, `seccion`, `curso`, `materia` e `inscripcion`.
- **Soft Delete:** Implementado en el módulo de inscripciones (cambio de estado a "Retirada" sin eliminación física del registro).

## ⚙️ Instalación y Ejecución Local

1. Clonar el repositorio en la carpeta `www` de **Laragon**.
2. Importar el archivo `.sql` de la base de datos `amelia_rios` desde MySQL Workbench o phpMyAdmin.
3. Configurar las credenciales de conexión en el archivo `app/db.php`.
4. Iniciar los servicios de Apache y MySQL en Laragon.
5. Acceder al sistema mediante: `http://localhost/ue-ameliarios/public/index.php/login`

*(Credenciales de prueba disponibles bajo solicitud).*

## 📂 Estructura del Proyecto

```text
ue-ameliarios/
├── public/
│   └── index.php          # Front Controller (Enrutamiento)
└── app/
    ├── db.php             # Conexión a base de datos (PDO Singleton)
    ├── auth.php           # Lógica de autenticación y dashboard
    ├── layout.php         # Plantilla unificada y menú dinámico
    ├── helpers.php        # Funciones de utilidad y validación RBAC
    ├── asistencia.php     # Módulo de control de asistencia
    ├── justificaciones.php# Módulo de flujo de justificaciones
    ├── roles.php          # Gestión de matriz de permisos
    └── modulos.php        # Administración de módulos y vistas