SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_usuario (
  id_tipo int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(50) NOT NULL,
  descripcion varchar(200) DEFAULT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_cliente (
  id_tipo_cliente int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(50) NOT NULL,
  descripcion varchar(200) DEFAULT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id_tipo_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_equipo (
  id_tipo int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(50) NOT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_servicio (
  id_tipo_servicio int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  descripcion varchar(200) DEFAULT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id_tipo_servicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sucursales (
  id_sucursal int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  direccion varchar(200) NOT NULL,
  telefono varchar(20) NOT NULL,
  representante varchar(200) NOT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS empresa (
  id_empresa int(11) NOT NULL AUTO_INCREMENT,
  nombre_empresa varchar(100) NOT NULL,
  slogan varchar(1000) NOT NULL,
  leyenda1 varchar(1000) NOT NULL,
  leyenda2 varchar(1000) NOT NULL,
  iva decimal(5,2) NOT NULL DEFAULT 0.15,
  PRIMARY KEY (id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario int(11) NOT NULL AUTO_INCREMENT,
  nombre_completo varchar(100) NOT NULL,
  usuario varchar(50) NOT NULL,
  password varchar(255) NOT NULL,
  id_tipo int(11) DEFAULT NULL,
  id_sucursal int(11) DEFAULT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario (usuario),
  KEY fk_usuarios_tipo (id_tipo),
  KEY fk_usuarios_sucursal (id_sucursal),
  CONSTRAINT usuarios_ibfk_1 FOREIGN KEY (id_tipo) REFERENCES tipos_usuario (id_tipo),
  CONSTRAINT usuarios_ibfk_2 FOREIGN KEY (id_sucursal) REFERENCES sucursales (id_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clientes (
  id_cliente int(11) NOT NULL AUTO_INCREMENT,
  nombre_apellido varchar(100) NOT NULL,
  identificacion varchar(20) NOT NULL,
  direccion varchar(200) NOT NULL,
  telefono varchar(20) NOT NULL,
  empresa varchar(100) NOT NULL,
  id_tipo_cliente int(11) DEFAULT NULL,
  estado tinyint(1) DEFAULT 1,
  email varchar(100) DEFAULT 'N/A',
  PRIMARY KEY (id_cliente),
  UNIQUE KEY uq_identificacion (identificacion),
  KEY fk_clientes_tipo (id_tipo_cliente),
  CONSTRAINT clientes_ibfk_1 FOREIGN KEY (id_tipo_cliente) REFERENCES tipos_cliente (id_tipo_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS equipos (
  id_equipo int(11) NOT NULL AUTO_INCREMENT,
  numero_serial varchar(50) NOT NULL,
  marca varchar(50) NOT NULL,
  modelo varchar(50) NOT NULL,
  id_cliente int(11) DEFAULT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id_equipo),
  KEY fk_equipos_cliente (id_cliente),
  CONSTRAINT equipos_ibfk_1 FOREIGN KEY (id_cliente) REFERENCES clientes (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ordenes_trabajo (
  id_orden int(11) NOT NULL AUTO_INCREMENT,
  codigo varchar(50) NOT NULL,
  id_cliente int(11) NOT NULL,
  id_equipo int(11) NOT NULL,
  descripcion_problema text DEFAULT NULL,
  estado enum('Pendiente','En Proceso','Completado','Entregado') DEFAULT 'Pendiente',
  id_usuario_registro int(11) NOT NULL,
  tecnico_responsable_id int(11) NOT NULL,
  id_sucursal int(11) NOT NULL,
  fecha_ingreso datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id_orden),
  UNIQUE KEY uq_codigo (codigo),
  KEY fk_ordenes_cliente (id_cliente),
  KEY fk_ordenes_equipo (id_equipo),
  KEY fk_ordenes_usuario (id_usuario_registro),
  KEY fk_ordenes_sucursal (id_sucursal),
  CONSTRAINT ordenes_trabajo_ibfk_1 FOREIGN KEY (id_cliente) REFERENCES clientes (id_cliente),
  CONSTRAINT ordenes_trabajo_ibfk_2 FOREIGN KEY (id_equipo) REFERENCES equipos (id_equipo),
  CONSTRAINT ordenes_trabajo_ibfk_3 FOREIGN KEY (id_usuario_registro) REFERENCES usuarios (id_usuario),
  CONSTRAINT ordenes_trabajo_ibfk_4 FOREIGN KEY (id_sucursal) REFERENCES sucursales (id_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orden_equipos (
  id_orden_equipo int(11) NOT NULL AUTO_INCREMENT,
  id_orden int(11) NOT NULL,
  id_equipo int(11) NOT NULL,
  observaciones_falla_equipo text DEFAULT NULL,
  fecha_registro timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id_orden_equipo),
  KEY fk_oe_orden (id_orden),
  KEY fk_oe_equipo (id_equipo),
  CONSTRAINT orden_equipos_ibfk_1 FOREIGN KEY (id_orden) REFERENCES ordenes_trabajo (id_orden),
  CONSTRAINT orden_equipos_ibfk_2 FOREIGN KEY (id_equipo) REFERENCES equipos (id_equipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS seguimientos_orden (
  id_seguimiento int(11) NOT NULL AUTO_INCREMENT,
  id_orden int(11) NOT NULL,
  id_tecnico int(11) NOT NULL,
  tipo_servicio varchar(100) NOT NULL,
  procedimiento text NOT NULL,
  valor_cobrar decimal(10,2) NOT NULL,
  fecha_registro datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id_seguimiento),
  KEY fk_seg_orden (id_orden),
  KEY fk_seg_tecnico (id_tecnico),
  CONSTRAINT seguimientos_orden_ibfk_1 FOREIGN KEY (id_orden) REFERENCES ordenes_trabajo (id_orden),
  CONSTRAINT seguimientos_orden_ibfk_2 FOREIGN KEY (id_tecnico) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tipos_usuario (id_tipo, nombre, descripcion, estado) VALUES
(1, 'Administrador', 'Control total del sistema', 1),
(2, 'Técnico', 'Personal técnico de mantenimiento', 1),
(3, 'Recepcionista', 'Personal de atención al cliente', 1);

INSERT IGNORE INTO sucursales (id_sucursal, nombre, direccion, telefono, representante, estado) VALUES
(1, 'Matriz', 'Dirección Principal', '000-0000', 'Administrador', 1);

INSERT IGNORE INTO tipos_cliente (id_tipo_cliente, nombre, descripcion, estado) VALUES
(1, 'Corporativo', 'Empresas y organizaciones', 1),
(2, 'Individual', 'Clientes particulares', 1);

INSERT IGNORE INTO tipos_equipo (id_tipo, nombre, estado) VALUES
(1, 'Laptop', 1),
(2, 'Desktop', 1),
(3, 'Impresora', 1),
(4, 'Monitor', 1),
(5, 'Otro', 1);

INSERT IGNORE INTO tipos_servicio (id_tipo_servicio, nombre, descripcion, estado) VALUES
(1, 'Mantenimiento Preventivo', 'Limpieza y revisión general', 1),
(2, 'Reparación Hardware', 'Reparación de componentes', 1),
(3, 'Actualización Software', 'Instalación y actualización de programas', 1),
(4, 'Diagnóstico', 'Diagnóstico de fallas', 1),
(5, 'Reparación', 'Reparación general', 1),
(6, 'Actualización', 'Actualización de componentes', 1),
(7, 'Otro', 'Otros servicios', 1);

INSERT IGNORE INTO usuarios (id_usuario, nombre_completo, usuario, password, id_tipo, id_sucursal, estado) VALUES
(1, 'Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 1);
