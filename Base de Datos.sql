-- Creación de la Base de Datos para el Sistema de Control de Transmetro
CREATE DATABASE IF NOT EXISTS transmetro_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transmetro_db;

-- 1. Tabla: Linea
CREATE TABLE IF NOT EXISTS linea (
id_linea INT AUTO_INCREMENT PRIMARY KEY,
nombre_linea VARCHAR(100) NOT NULL,
distancia_total DECIMAL(10, 2) DEFAULT 0.00
) ENGINE=InnoDB;

-- 2. Tabla: Estacion
CREATE TABLE IF NOT EXISTS estacion (
id_estacion INT AUTO_INCREMENT PRIMARY KEY,
nombre_estacion VARCHAR(100) NOT NULL,
capacidad_maxima INT NOT NULL,
ip_pc_local VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- 3. Tabla: Ruta_Secuencia (Intermedia N:M)
CREATE TABLE IF NOT EXISTS ruta_secuencia (
id_linea INT,
id_estacion INT,
orden_secuencial INT NOT NULL,
distancia_siguiente DECIMAL(10, 2) NOT NULL,
PRIMARY KEY (id_linea, id_estacion),
FOREIGN KEY (id_linea) REFERENCES linea(id_linea) ON DELETE CASCADE,
FOREIGN KEY (id_estacion) REFERENCES estacion(id_estacion) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Tabla: Acceso (Relación 1:N con Estacion)
CREATE TABLE IF NOT EXISTS acceso (
id_acceso INT AUTO_INCREMENT PRIMARY KEY,
nombre_acceso VARCHAR(100) NOT NULL,
id_estacion INT NOT NULL,
FOREIGN KEY (id_estacion) REFERENCES estacion(id_estacion) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Tabla: Guardia
CREATE TABLE IF NOT EXISTS guardia (
id_guardia INT AUTO_INCREMENT PRIMARY KEY,
nombre_completo VARCHAR(150) NOT NULL,
dpi VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 6. Tabla: Asignacion_Seguridad
CREATE TABLE IF NOT EXISTS asignacion_seguridad (
id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
id_acceso INT NOT NULL,
id_guardia INT NOT NULL,
fecha_turno DATE NOT NULL,
estado_activo BOOLEAN DEFAULT TRUE,
FOREIGN KEY (id_acceso) REFERENCES acceso(id_acceso) ON DELETE CASCADE,
FOREIGN KEY (id_guardia) REFERENCES guardia(id_guardia) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Tabla: Parqueo
CREATE TABLE IF NOT EXISTS parqueo (
id_parqueo INT AUTO_INCREMENT PRIMARY KEY,
codigo_espacio VARCHAR(50) NOT NULL,
id_estacion INT NOT NULL,
FOREIGN KEY (id_estacion) REFERENCES estacion(id_estacion) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Tabla: Piloto
CREATE TABLE IF NOT EXISTS piloto (
id_piloto INT AUTO_INCREMENT PRIMARY KEY,
nombre_completo VARCHAR(150) NOT NULL,
historial_educativo TEXT NOT NULL,
direccion_residencia VARCHAR(200) NOT NULL,
telefono_contacto VARCHAR(20) NOT NULL
) ENGINE=InnoDB;

-- 9. Tabla: Bus
CREATE TABLE IF NOT EXISTS bus (
id_bus INT AUTO_INCREMENT PRIMARY KEY,
numero_unidad VARCHAR(50) NOT NULL UNIQUE,
placa VARCHAR(20) NOT NULL UNIQUE,
capacidad_pasajeros INT NOT NULL,
id_linea INT,
id_parqueo INT NOT NULL,
id_piloto INT,
FOREIGN KEY (id_linea) REFERENCES linea(id_linea) ON DELETE SET NULL,
FOREIGN KEY (id_parqueo) REFERENCES parqueo(id_parqueo) ON DELETE RESTRICT,
FOREIGN KEY (id_piloto) REFERENCES piloto(id_piloto) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 10. Tabla: Transaccion_Alerta
CREATE TABLE IF NOT EXISTS transaccion_alerta (
id_alerta INT AUTO_INCREMENT PRIMARY KEY,
tipo_alerta VARCHAR(50) NOT NULL, -- 'SATURACION_50', 'BAJA_CARGA_25'
id_estacion INT NOT NULL,
id_bus INT NULL,
fecha_hora DATETIME NOT NULL,
sincronizado_central BOOLEAN DEFAULT TRUE,
FOREIGN KEY (id_estacion) REFERENCES estacion(id_estacion) ON DELETE CASCADE,
FOREIGN KEY (id_bus) REFERENCES bus(id_bus) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- Datos Iniciales de Prueba
-- ==========================================

-- Insertar Estaciones
INSERT INTO estacion (id_estacion, nombre_estacion, capacidad_maxima, ip_pc_local) VALUES
(1, 'Plaza Barrios', 500, '192.168.10.15'),
(2, 'El Trébol', 750, '192.168.10.16'),
(3, 'Exposición', 300, '192.168.10.17'),
(4, 'Estación Central', 800, '192.168.10.18'),
(5, 'Terminal Pasajes', 450, '192.168.10.19');

-- Insertar Accesos por Estación (REQ-0002)
INSERT INTO acceso (id_acceso, nombre_acceso, id_estacion) VALUES
(1, 'Acceso Norte (Rampas)', 1),
(2, 'Acceso Sur (Peatonal)', 1),
(3, 'Acceso Pasarela Norte', 2),
(4, 'Acceso Rampa Sótano', 2);

-- Insertar Guardias
INSERT INTO guardia (id_guardia, nombre_completo, dpi) VALUES
(1, 'Marco Vinicio López', '2901847190101'),
(2, 'Sergio Estuardo Pineda', '3104829100101'),
(3, 'Walter Alonzo', '1892049180101'),
(4, 'Edwin Javier Cruz', '2490182900101');

-- Insertar Asignaciones de Seguridad Iniciales (REQ-0006)
INSERT INTO asignacion_seguridad (id_acceso, id_guardia, fecha_turno, estado_activo) VALUES
(1, 1, CURDATE(), 1),
(2, 2, CURDATE(), 1),
(3, 3, CURDATE(), 1);

-- Insertar Parqueos
INSERT INTO parqueo (id_parqueo, codigo_espacio, id_estacion) VALUES
(1, 'PB-PARQ-01', 1),
(2, 'PB-PARQ-02', 1),
(3, 'ET-PARQ-01', 2),
(4, 'EC-PARQ-01', 4);

-- Insertar Pilotos (REQ-0005)
INSERT INTO piloto (id_piloto, nombre_completo, historial_educativo, direccion_residencia, telefono_contacto) VALUES
(1, 'Carlos Mendoza Contreras', 'Bachiller Industrial y Perito', 'Zona 12, Ciudad de Guatemala', '+502 5521 8930'),
(2, 'Josué Girón Alvarado', 'Diversificado Completo', 'Villa Nueva, Guatemala', '+502 4432 1098'),
(3, 'Ramiro Portillo Solis', 'Técnico en Mecánica Diésel', 'Mixco, Guatemala', '+502 3312 9044');

-- Insertar Líneas Básicas (REQ-0001)
INSERT INTO linea (id_linea, nombre_linea, distancia_total) VALUES
(1, 'Línea 12 - Troncal Centro', 12.50),
(2, 'Línea 13', 8.20);

-- Insertar Secuencias de Ruta (REQ-0001)
INSERT INTO ruta_secuencia (id_linea, id_estacion, orden_secuencial, distancia_siguiente) VALUES
(1, 1, 1, 1.20),
(1, 2, 2, 2.50),
(1, 4, 3, 0.00);

-- Insertar Buses Asociados (REQ-0003, REQ-0004)
INSERT INTO bus (id_bus, numero_unidad, placa, capacidad_pasajeros, id_linea, id_parqueo, id_piloto) VALUES
(1, 'TRM-042', 'U-91823', 80, 1, 1, 1),
(2, 'TRM-108', 'U-10294', 80, 1, 2, 2);