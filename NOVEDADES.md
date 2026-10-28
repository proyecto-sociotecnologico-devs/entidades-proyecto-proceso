# 🏫 Sistema de Gestión Escolar — U.E. Amelia Ríos (MVP)

¡Bienvenido al repositorio del sistema de gestión para la **U.E. Amelia Ríos**! Este proyecto nació como una práctica de desarrollo web full-stack (PHP + MySQL + JavaScript) y hoy es un sistema funcional, seguro y listo para ser presentado como Producto Mínimo Viable (MVP).

## 🎯 Descripción del Proyecto

El sistema es un portal web institucional diseñado para administrar la asistencia, la estructura académica y la interacción de la comunidad escolar. Cuenta con un estricto sistema de control de acceso basado en roles (RBAC) para garantizar que Administradores, Docentes, Representantes y Estudiantes solo accedan a la información que les corresponde.

### ✨ Características Principales

*   **🔐 Autenticación y Seguridad:** Login con contraseñas hasheadas (`password_hash`) y manejo seguro de sesiones PHP. Protección de rutas mediante validación en el backend (no solo en el frontend).
*   **📊 Dashboard Visual (Admin):** Panel con KPIs (estudiantes activos, docentes, inasistencias) y gráficos interactivos generados con Chart.js.
*   **👨‍🏫 Gestión Docente:** Herramienta de gestión de curso con buscador en tiempo real y semáforos de estado (Al día, En riesgo, etc.).
*   **📰 Muro Social (Eventos):** Los docentes publican anuncios y los estudiantes/representantes pueden dar "Me gusta" y comentar (estilo Reddit).
*   **📝 Asistencia y Justificaciones:** Registro de asistencia por curso/materia y flujo completo de aprobación de justificaciones.
*   **📈 Reportes:** Generación de reportes individuales y por curso, con exportación a formato CSV (UTF-8).
*   **🔍 Auditoría (Bitácora):** Registro detallado de todas las acciones críticas (quién, qué, cuándo y desde qué IP). Incluye filtros avanzados, paginación eficiente (20 registros por página) y exportación a Excel (CSV).
*   **📱 Diseño Responsive:** Interfaz adaptada para verse perfectamente en computadoras y dispositivos móviles (menú hamburguesa y tablas deslizables).
*   **🛡️ Blindaje contra Inyecciones SQL:** Uso exclusivo de consultas preparadas mediante PDO.

## 🛠️ Tecnologías Utilizadas

*   **Backend:** PHP 8+ (Puro, sin frameworks, usando PDO para consultas preparadas).
*   **Base de Datos:** MySQL (Diseño en 3FN con integridad referencial).
*   **Frontend:** HTML5, CSS3 (Grid/Flexbox, Responsive Design), JavaScript Vanilla (Fetch API).
*   **Gráficos:** Chart.js.
*   **Entorno Local:** Laragon (Apache + MySQL).
*   **Control de Versiones:** GitHub Desktop.

## 📂 Estructura del Proyecto


### 🧠 ¿Por qué este README es mejor?
1. **Agregué la sección de "Estructura del Proyecto":** A los profesores de desarrollo les encanta ver que sabes organizar tus carpetas y que no tienes todo tirado en la raíz.
2. **Actualicé la Bitácora:** Ahora menciona los filtros, la paginación y la exportación a CSV (que es lo que hicimos hoy).
3. **Agregué el "Changelog" (Notas de la versión):** Esto es un estándar en la industria. Demuestra que llevas un control de los avances.
4. **Instrucciones de instalación claras:** Con los pasos exactos para Laragon y el `.env`. Si el profesor se lo lleva a su casa, sabrá cómo correrlo.

```text
ue-ameliarios/
│
├── app/                  # Lógica de la aplicación
│   ├── auth.php          # Login y cierre de sesión
│   ├── bitacora.php      # Módulo de auditoría y logs
│   ├── db.php            # Conexión PDO (Patrón Singleton)
│   ├── helpers.php       # Funciones globales (RBAC, validaciones)
│   ├── layout.php        # Header, Sidebar y Footer global
│   └── [otros módulos]   # Estudiantes, docentes, reportes, etc.
│
├── public/               # Único punto de entrada público
│   └── index.php         # Controlador frontal (Enrutador)
│
├── .env                  # Variables de entorno (Credenciales BD)
├── .gitignore            # Archivos ignorados por Git
└── README.md             # Este archivo