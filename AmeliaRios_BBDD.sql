-- 1. Aqui se cedio a la configuracion de seguridad
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET sql_notes = 0;

-- 2. Recreacion de la Base de Datos, ya que el Profesor nos dio una mejor sugerencia
DROP DATABASE IF EXISTS AmeliaRios; 
CREATE DATABASE AmeliaRios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE AmeliaRios;

-- 3. Tablas Independientes
CREATE TABLE Empresa (
    id_empresa INT PRIMARY KEY AUTO_INCREMENT,
    nombre_institucion VARCHAR(150) NOT NULL,
    rif VARCHAR(20) UNIQUE NOT NULL,
    logo_path VARCHAR(255)
);

CREATE TABLE Cargo (
    id_cargo INT PRIMARY KEY AUTO_INCREMENT,
    nombre_cargo VARCHAR(100) NOT NULL
);

CREATE TABLE Rol (
    id_rol INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(60) UNIQUE NOT NULL,
    descripcion TEXT
);

CREATE TABLE Modulo (
    id_modulo INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    area_institucional VARCHAR(100),
    estado TINYINT(1) DEFAULT 1,
    url_destino VARCHAR(255)
);

CREATE TABLE Permisos (
    id_permiso INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
);

CREATE TABLE Periodo (
    id_periodo INT PRIMARY KEY AUTO_INCREMENT,
    nombre_periodo VARCHAR(50) NOT NULL,
    estado BOOLEAN DEFAULT TRUE
);

-- 4. Tablas dependientes
CREATE TABLE Personal (
    cedula INT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    id_cargo INT,
    estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    CONSTRAINT fk_pers_cargo FOREIGN KEY (id_cargo) REFERENCES Cargo(id_cargo)
);

CREATE TABLE Usuario (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    cedula_personal INT UNIQUE NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    CONSTRAINT fk_user_personal FOREIGN KEY (cedula_personal) REFERENCES Personal(cedula));

-- 5. Muy importante aqui ver las tablas de detalle y relacion
CREATE TABLE Direccion (
    id_direccion INT PRIMARY KEY AUTO_INCREMENT,
    calle VARCHAR(100),
    avenida VARCHAR(100),
    sector VARCHAR(100),
    nro_casa VARCHAR(20),
    estado_provincia VARCHAR(100),
    id_usuario INT,
    CONSTRAINT fk_dir_usuario FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Paginas (
    id_pagina INT PRIMARY KEY AUTO_INCREMENT,
    id_modulo INT,
    nombre_pagina VARCHAR(100),
    url VARCHAR(255),
    icono VARCHAR(50),
    es_visible BOOLEAN DEFAULT TRUE,
    estado TINYINT(1) DEFAULT 1, 
    CONSTRAINT fk_pag_modulo FOREIGN KEY (id_modulo) REFERENCES Modulo(id_modulo)
);

CREATE TABLE RM_Per (
    id_rmp INT PRIMARY KEY AUTO_INCREMENT,
    id_rol INT, 
    id_modulo INT,
    id_permiso INT,
    estado TINYINT(1) DEFAULT 1,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rmp_rol FOREIGN KEY (id_rol) REFERENCES Rol(id_rol),
    CONSTRAINT fk_rmp_mod FOREIGN KEY (id_modulo) REFERENCES Modulo(id_modulo),
    CONSTRAINT fk_rmp_per FOREIGN KEY (id_permiso) REFERENCES Permisos(id_permiso),
    UNIQUE KEY unique_permiso_rol (id_rol, id_modulo, id_permiso)
);

CREATE TABLE Post (
    id_post INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(150),
    contenido TEXT,
    id_usuario INT,
    id_periodo INT,
    id_empresa INT,
    fecha_pub TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_post_user FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    CONSTRAINT fk_post_peri FOREIGN KEY (id_periodo) REFERENCES Periodo(id_periodo),
    CONSTRAINT fk_post_empr FOREIGN KEY (id_empresa) REFERENCES Empresa(id_empresa)
);

CREATE TABLE Asistencia (
    id_asistencia INT PRIMARY KEY AUTO_INCREMENT,
    cedula_personal INT,
    fecha DATE DEFAULT (CURRENT_DATE), -- Esto es clave porque se usa una expresion o funcion
    hora_entrada TIME,
    hora_salida TIME,
    observacion TEXT,
    CONSTRAINT fk_asist_pers FOREIGN KEY (cedula_personal) REFERENCES Personal(cedula)
);

CREATE TABLE usuario_rol (
    id_usuario INT,
    id_rol INT,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT,
    es_principal BOOLEAN DEFAULT FALSE,
    fecha_expiracion DATETIME,
    PRIMARY KEY (id_usuario, id_rol),
    CONSTRAINT fk_ur_usuario FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    CONSTRAINT fk_ur_rol FOREIGN KEY (id_rol) REFERENCES Rol(id_rol)
);

-- 6. Aqui implementamos datos muy importantes
INSERT INTO Empresa (nombre_institucion, rif, logo_path) VALUES ('Unidad Educativa Amelia Rios', 'J-12345678-9', 'assets/img/logo.png');
INSERT INTO Cargo (nombre_cargo) VALUES ('Directivo'), ('Docente'), ('Administrativo'), ('Obrero');
INSERT INTO Rol (nombre, descripcion) VALUES ('SuperAdmin', 'Control total'), ('Administrador', 'Gestion'), ('Consultor', 'Solo lectura');
INSERT INTO Modulo (nombre, area_institucional, url_destino) VALUES ('Seguridad', 'Sistemas', 'seguridad.php'), ('Personal', 'RRHH', 'personal.php');
INSERT INTO Permisos (nombre, descripcion) VALUES ('CREAR', 'Anadir'), ('LEER', 'Ver'), ('ACTUALIZAR', 'Editar'), ('ELIMINAR', 'Borrar');
INSERT INTO Periodo (nombre_periodo, estado) VALUES ('2025-2026', 1);
INSERT INTO Personal (cedula, nombres, apellidos, id_cargo) VALUES (20111222, 'Manuel', 'Pena', 1);
INSERT INTO Usuario (cedula_personal, correo, password_hash) VALUES (20111222, 'admin@ameliarios.edu.ve', 'hash123');
INSERT INTO usuario_rol (id_usuario, id_rol, es_principal) VALUES (1, 1, TRUE);
INSERT INTO RM_Per (id_rol, id_modulo, id_permiso) VALUES (1, 1, 1), (1, 1, 2), (1, 2, 2);

-- 7. En esta instancia se oriento hacia el restablecimiento de seguridad y las uniones de las tablas
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

SHOW TABLES;

SELECT 
    P.nombres AS 'Nombre Personal',
    C.nombre_cargo AS 'Cargo Institucional',
    U.correo AS 'Acceso Usuario',
    R.nombre AS 'Rol Asignado'
FROM Personal P
JOIN Cargo C ON P.id_cargo = C.id_cargo
JOIN Usuario U ON P.cedula = U.cedula_personal
JOIN usuario_rol UR ON U.id_usuario = UR.id_usuario
JOIN Rol R ON UR.id_rol = R.id_rol
WHERE UR.es_principal = TRUE;