README – Backend del Sistema de Asistencia (Version Beta)
Descripcion del Proyecto
Este backend corresponde al modulo de Control de Asistencia del sistema AMELIARIOS.
Incluye endpoints para registrar entrada/salida y consultar la asistencia del dia, listos para ser consumidos por el Front-End.
Funciona sin internet usando Laragon/XAMPP/WAMP.

Tecnologias Utilizadas
PHP 8+

MySQL (MariaDB)

Laragon

Postman

Arquitectura REST

Estructura del Proyecto

AMELIARIOS/
│
├── api/
│   ├── auth_middleware.php
│   ├── consultar_asistencia_hoy.php
│   ├── login.php
│   ├── obtener_eventos.php
│   ├── registrar_asistencias.php
│   └── test.php
│
├── dashboard.php
├── db.php
├── ejemplo_consumo.js
├── hash.php
├── index.php
└── README.md

Base de Datos

Tabla: Personal
cedula

nombres

apellidos

Tabla: Asistencia

id_asistencia INT PK AI
cedula_personal INT FK
fecha DATE DEFAULT CURRENT_DATE
hora_entrada TIME
hora_salida TIME
observacion TEXT
UNIQUE (cedula_personal, fecha)

Tabla: usuario
id_usuario

cedula_personal (FK)

Seguridad (RBAC)
El middleware valida permisos por modulo y accion:

verificarPermiso($pdo, $id_usuario, "Asistencia", "registrar");

Endpoints Disponibles
Registrar Asistencia
URL: http://localhost/AMELIARIOS/api/registrar_asistencia.php

Body JSON: 
{
  "id_evento": 1
}

Respuestas:

Entrada: { "ok": true, "estado": "entrada" }

Salida: { "ok": true, "estado": "salida" }

Tercera marca: { "ok": false, "message": "Ya registraste entrada y salida hoy" }

Consultar Asistencia del Dia
URL: http://localhost/AMELIARIOS/api/consultar_asistencia_hoy.php

Ejemplo de respuesta:

{
  "ok": true,
  "asistencia_hoy": [
    {
      "cedula": 20111222,
      "nombre": "Manuel",
      "apellido": "Pena",
      "estado": "salida",
      "hora_entrada": "17:23:33",
      "hora_salida": "17:25:40",
      "observacion": "Salida registrada"
    }
  ]
}

Como probar el Backend:

Registrar asistencia
Metodo: POST
URL: http://localhost/AMELIARIOS/api/registrar_asistencia.php

Consultar asistencia del dia
Metodo: GET
URL: http://localhost/AMELIARIOS/api/consultar_asistencia_hoy.php

Consumir la API desde el Front-End:

fetch("http://localhost/AMELIARIOS/api/consultar_asistencia_hoy.php")
  .then(res => res.json())
  .then(data => console.log(data));

Instalacion para el Front-End
Instalar Laragon/XAMPP

Copiar la carpeta ameliarios dentro de /www

Importar ameliarios.sql

Abrir: http://localhost/AMELIARIOS/

Estado del Proyecto
Version Beta – Funcional

Registro de asistencia

Consulta del dia

Seguridad RBAC

Base de datos estable

API lista para el Front-End

Funciona sin internet

Notas Finales
Compatible con:

React

Vue

Angular

Svelte

Vanilla JS