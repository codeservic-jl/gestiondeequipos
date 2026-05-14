-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-06-2025 a las 09:48:15
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `ingresoequiposcd`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `nombre_apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identificacion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `empresa` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_tipo_cliente` int(11) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `email` varchar(100) DEFAULT 'N/A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `nombre_apellido`, `identificacion`, `direccion`, `telefono`, `empresa`, `id_tipo_cliente`, `estado`, `email`) VALUES
(1, 'Juan Pérez', '12345678', 'Calle Cliente 789', '555-1111', 'Empresa ABC', 1, 1, 'N/A'),
(2, 'María García', '87654321', 'Av. Cliente 456', '555-2222', 'Personal', 2, 1, 'asas@gmail.com'),
(4, 'Adrian Valarezo', '0952336452', 'sur de gye', '09182928128', 'Salud de todos', NULL, 1, 'sal@gmail.com'),
(7, 'Jean Castro', '0984554877', 'Floresta', '09182918999', 'NA', 1, 0, 'N/A'),
(8, 'Luis Par', '0940588566', 'Floresta', '0918282728', 'codeservic', 1, 0, 'j@gmail.com'),
(9, 'Pablo Fernandez', '0911225544', 'floresta', '09877844551212', 'P&P', 2, 1, 'lncamu18@gmail.com'),
(10, 'Paul Pérez', '098745678', 'Calle Cliente 789', '555-1111', 'Empresa ABC', 1, 1, 'N/A'),
(11, 'Rubén Per García', '0911654321', 'Av. Cliente we456', '555-2222', 'Personal', 2, 0, 'N/A'),
(12, 'Luisa Fabi Valarezo', '098566588', 'centro de gye', '09182928128', '', NULL, 1, 'N/A'),
(13, 'Derian Salazar', '09565554877', 'Floresta', '09182918999', 'NA', 1, 0, 'email@gmail.com'),
(14, 'Jorge P Par', '0933456566', 'Floresta', '0918282728', 'codeservic', 1, 1, 'N/A'),
(15, 'Ricard Lopez', '092225544', 'floresta', '09877844551212', 'P&P', 2, 1, 'lncamu18@gmail.com'),
(16, 'Joffre Castro', '09855563256', 'Centro-sur ', '0998785563', 'Codeservic', 2, 1, 'jnt@hotmail.com'),
(17, 'Fabiola PAsantes', '0985665454', 'floresta', '0988788454', 'FAB S.A', 1, 1, 'fabisagmail.com'),
(18, 'Paul Guaranda', '0911223341', 'santa martha', '0998810900', 'Soto098', 2, 1, 'pdefr@gmail.com'),
(19, 'Laura Peralta', '0918199818', 'direccion', '01911818177', 'Personal', 1, 1, 'email@gmail.com'),
(20, 'Jean Pier Hinojoza', '09509595929', 'dieccion', '09877716777', 'p', 1, 1, 'email@gmail.com'),
(21, 'María Lozano', '023665987', 'fertisa', '09181819188', 'personal', 1, 1, 'ter@gmail.com'),
(22, 'Ambar Alarcon', '098875487', 'fertisa', '0918811565', 'per', 1, 1, 'email@gmail.com'),
(23, 'paola ka', '09555055555', 'direcj mon', '0909909888', 'personal', 1, 1, 'lncamu18@gmail.com'),
(24, 'luis alberto suares torres', '15548788899', 'floresta 2', '0928899111', 'los hermanos c', NULL, 1, 'luis@gmail.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `id_equipo` int(11) NOT NULL,
  `numero_serial` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `marca` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `modelo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `equipos`
--

INSERT INTO `equipos` (`id_equipo`, `numero_serial`, `marca`, `modelo`, `id_cliente`, `estado`) VALUES
(1, 'LAP001', 'HP', 'Pavilion', 1, 1),
(2, 'LAP002', 'Dell', 'Latitude', 1, 1),
(3, 'LAP003', 'Lenovo', 'ThinkPad', 2, 0),
(6, '23FRE990L', 'Dell', 'latitude 5000', 4, 0),
(7, 'LAP2309', 'apple', 'mac', 7, 0),
(8, 'sd', 'apple', 'mac', 7, 0),
(9, 'ewewe', 'apple', 'macbook', 8, 1),
(10, '23WEX12', 'apple', 'mac', 9, 1),
(11, '54KSOS00EEE2', 'Acer', 'E5-471', 16, 1),
(12, '11PEIUW', 'Asus', 'Vivobook', 16, 1),
(13, '09ER456', 'asus', 'se23-er', 17, 1),
(14, '90ER451', 'asus', 'model34', 16, 1),
(15, 'jshs34', 'asus', 'molde2323', 1, 1),
(16, '09err3', 'mac', 'macbook', 17, 1),
(17, '344RTTY0', 'mac', 'macbook pro', 15, 1),
(18, 'oi233', 'mac', 'macbook', 15, 1),
(19, '23REDD31', 'dell', 'latitude15', 12, 1),
(20, 'PRUEBA', 'PRUEBA', 'PRUEBA', 10, 1),
(21, '223FER90', 'dell', 'latitude', 2, 0),
(22, '43ERT', 'dell', 'latitude', 8, 1),
(23, 'er223', 'dell', 'dell model', 9, 1),
(24, '12ser', 'dell', 'modelo prueba', 12, 1),
(25, '45YR2', 'dell prueba', 'modelo dell', 2, 0),
(26, '89upo', 'pr dell', 'model pr', 1, 1),
(27, '90jjnb', 'prueba dell', 'model dell', 4, 1),
(28, '10OPQ0', 'HP', 'pavilon', 18, 1),
(29, '109DER-23', 'Dell', 'latitude', 19, 1),
(30, '23TGH700', 'dell', 'latitude 14', 19, 1),
(31, '290UUI8', 'mac', 'macbook', 20, 1),
(32, '09IOIKKK', 'mac', 'macbook pro', 20, 1),
(33, '98YYU-0', 'mac', 'macbook1', 21, 1),
(34, '9015GT-q', 'dell', 'latitude 15', 21, 1),
(35, '09iio-q', 'mac', 'macbook 1', 22, 1),
(36, '09UI123', 'mac', 'macbook2', 22, 1),
(37, '1999ER44-qw', 'marca', 'modelo900000-777', 7, 0),
(89, 'kkkk', 'x', 'mkm', 23, 1),
(96, 'serie x1', 'marca x', 'modelo x', 16, 1),
(97, 'serie x1', 'marca x', 'modelo x', 16, 1),
(98, 'serie x2', 'marca x1', 'modelo x1', 16, 1),
(99, 'serie x2', 'marca x1', 'modelo x1', 16, 1),
(100, 'serie xl', 'marca x l', 'modelo x l', 16, 1),
(101, 'serie xl', 'marca x l', 'modelo x l', 16, 1),
(102, 'seriexj', 'marca xj', 'modelo xj', 19, 1),
(103, 'seriexj', 'marca xj', 'modelo xj', 19, 1),
(104, 'serie xf', 'marca xf', 'modelo xf', 12, 1),
(105, 'serie xf1', 'marca xf1', 'modelo xf1', 12, 1),
(106, 'serie xf', 'marca xf', 'modelo xf', 12, 1),
(107, 'serie xf1', 'marca xf1', 'modelo xf1', 12, 1),
(108, 'asas11', 'sa', 'asas', 4, 1),
(109, '234SERT', 'mers', 'sef', 17, 1),
(110, '234SERT', 'mers', 'sef', 17, 1),
(119, 'seriedell0', 'dell', 'model0', 24, 1),
(120, 'seriehp1', 'hp', 'modelhp', 24, 1),
(121, 'seriedell0', 'dell', 'model0', 24, 1),
(122, 'seriehp1', 'hp', 'modelhp', 24, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_trabajo`
--

CREATE TABLE `ordenes_trabajo` (
  `id_orden` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `descripcion_problema` text DEFAULT NULL,
  `estado` enum('Pendiente','En Proceso','Completado','Entregado') DEFAULT 'Pendiente',
  `id_usuario_registro` int(11) NOT NULL,
  `tecnico_responsable_id` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `fecha_ingreso` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `ordenes_trabajo`
--

INSERT INTO `ordenes_trabajo` (`id_orden`, `codigo`, `id_cliente`, `id_equipo`, `descripcion_problema`, `estado`, `id_usuario_registro`, `tecnico_responsable_id`, `id_sucursal`, `fecha_ingreso`) VALUES
(2, 'RGE00001LAP001', 1, 1, 'prueba', 'Pendiente', 1, 4, 1, '2025-04-22 23:11:29'),
(3, 'RGE00003LAP001', 1, 1, 'problema', 'Pendiente', 1, 4, 1, '2025-04-22 23:17:54'),
(5, 'RGE0000423FRE990L', 4, 6, 'no enciende y la batería no funciona', 'Pendiente', 1, 4, 1, '2025-04-22 23:26:28'),
(6, 'RGE00006LAP2309', 7, 7, 'equipo no enciende', '', 1, 4, 1, '2025-04-24 21:52:40'),
(7, 'RGE00007sd', 7, 8, '', '', 1, 4, 1, '2025-04-24 23:44:55'),
(8, 'RGE00008ewewe', 8, 9, '', 'Pendiente', 1, 4, 1, '2025-04-24 23:46:32'),
(9, 'RGE0000923WEX12', 9, 10, 'Prueba', 'Entregado', 1, 4, 1, '2025-04-28 20:37:30'),
(10, 'RGE0001054KSOS00EEE2', 16, 11, 'La batería falla Equipo 1 \r\nCambio de pantalla Equipo 2', 'Completado', 1, 4, 1, '2025-04-29 22:05:20'),
(11, 'RGE0001109ER456', 17, 13, 'cliente que llega en el local del sur', 'Pendiente', 1, 4, 1, '2025-04-30 21:54:05'),
(12, 'RGE0001290ER451', 16, 14, 'preuba', 'Pendiente', 1, 4, 1, '2025-04-30 22:16:33'),
(13, 'RGE00013jshs34', 1, 15, 'prueba', 'Pendiente', 1, 4, 1, '2025-04-30 22:23:47'),
(14, 'RGE0001409err3', 17, 16, 'preueba', 'Pendiente', 1, 4, 1, '2025-04-30 22:27:15'),
(15, 'RGE00015344RTTY0', 15, 17, 'prueba', 'Pendiente', 1, 4, 1, '2025-04-30 22:32:47'),
(16, 'RGE00016oi233', 15, 18, 'prueba', 'Pendiente', 1, 4, 1, '2025-04-30 22:38:32'),
(17, 'RGE0001723REDD31', 12, 19, 'disco duro, bateria, pantalla funcionan', 'Entregado', 1, 4, 1, '2025-05-03 18:15:07'),
(18, 'RGE00018PRUEBA', 10, 20, 'prueb', 'Pendiente', 1, 4, 1, '2025-05-03 18:38:07'),
(19, 'RGE00019223FER90', 2, 21, 'prueba', '', 1, 4, 1, '2025-05-03 19:07:28'),
(20, 'RGE0002043ERT', 8, 22, 'prueba', 'Pendiente', 1, 4, 1, '2025-05-05 22:10:31'),
(21, 'RGE00021er223', 9, 23, 'prueba', 'Pendiente', 1, 4, 1, '2025-05-05 22:12:46'),
(22, 'RGE0002212ser', 12, 24, 'prueba', 'Pendiente', 1, 4, 1, '2025-05-05 22:26:20'),
(23, 'RGE0002345YR2', 2, 25, 'prueba', '', 1, 4, 1, '2025-05-06 12:25:20'),
(24, 'RGE0002445YR2', 2, 25, 'pr de ot', '', 1, 4, 1, '2025-05-06 13:40:44'),
(25, 'RGE0002589upo', 1, 26, 'pr', 'En Proceso', 1, 4, 1, '2025-05-06 13:47:17'),
(26, 'RGE0002690jjnb', 4, 27, 'pr', 'Pendiente', 1, 4, 1, '2025-05-06 14:01:35'),
(27, 'RGE0002710OPQ0', 18, 28, 'Equipo llega con batería y con cargador', 'Pendiente', 1, 4, 1, '2025-05-06 15:09:57'),
(28, 'RGE00028LAP003', 2, 3, 'prueba', '', 5, 4, 3, '2025-05-06 20:57:07'),
(29, 'RGE0002923WEX12', 9, 10, 'equipo viene con bateria y con cargador original', 'Pendiente', 5, 4, 3, '2025-05-06 21:34:22'),
(30, 'RGE00030109DER-23', 19, 29, 'prueba, equipos tinen sus baterias en buen estado y traen cargadoresriginales', 'Pendiente', 5, 4, 3, '2025-05-06 21:58:33'),
(31, 'RGE00031290UUI8', 20, 31, 'equipos estan completos', 'Pendiente', 5, 4, 3, '2025-05-06 22:05:10'),
(32, 'RGE0003298YYU-0', 21, 33, 'equipos llegan completos (cargadores y baterias)', 'Pendiente', 5, 4, 3, '2025-05-06 22:16:56'),
(33, 'RGE0003309iio-q', 22, 35, 'equipos vienen completos', 'Entregado', 5, 4, 3, '2025-05-06 22:31:30'),
(34, 'RGE0003423REDD31', 12, 19, 'prueba de viernes', 'Pendiente', 5, 4, 3, '2025-05-09 11:56:07'),
(35, 'RGE0003590jjnb', 4, 27, 'equipo viene con la pantalla rota', 'En Proceso', 1, 4, 1, '2025-05-15 20:49:42'),
(45, 'RGE00036LAP001', 1, 1, 'xxxdde', 'Pendiente', 1, 4, 1, '2025-05-19 13:52:48'),
(63, 'RGE00046kkkk', 23, 89, 'kmkm', 'Pendiente', 1, 4, 1, '2025-05-19 23:50:58'),
(64, 'RGE00064LAP002', 1, 2, 'prueba equipo existente cliente existente', 'Pendiente', 1, 4, 1, '2025-05-20 18:27:56'),
(68, 'RGE00065serie x1', 16, 96, 'prueba equipo nuevo x', 'Entregado', 1, 4, 1, '2025-05-20 20:09:58'),
(69, 'RGE00069serie x2', 16, 98, 'prueba de equipo nuevo y cliente existente', 'Pendiente', 1, 4, 1, '2025-05-20 20:13:05'),
(70, 'RGE00070serie xl', 16, 100, 'prueba cliente existente equipo nuevo', 'En Proceso', 1, 4, 1, '2025-05-20 20:31:01'),
(71, 'RGE00071seriexj', 19, 102, 'prueba', 'En Proceso', 1, 4, 1, '2025-05-20 21:13:47'),
(72, 'RGE00072serie xf', 12, 104, 'prueba varios equipos', 'En Proceso', 1, 4, 1, '2025-05-20 21:15:24'),
(73, 'RGE00073234SERT', 17, 109, 'prrueba', 'Pendiente', 1, 4, 1, '2025-06-14 01:36:55'),
(74, 'RGE00074seriedell0', 24, 119, 'prueba', 'Pendiente', 1, 4, 1, '2025-06-14 02:30:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_equipos`
--

CREATE TABLE `orden_equipos` (
  `id_orden_equipo` int(11) NOT NULL,
  `id_orden` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `observaciones_falla_equipo` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `orden_equipos`
--

INSERT INTO `orden_equipos` (`id_orden_equipo`, `id_orden`, `id_equipo`, `observaciones_falla_equipo`, `fecha_registro`) VALUES
(1, 2, 1, NULL, '2025-05-01 01:10:45'),
(2, 3, 1, NULL, '2025-05-01 01:10:45'),
(3, 5, 6, NULL, '2025-05-01 01:10:45'),
(4, 6, 7, NULL, '2025-05-01 01:10:45'),
(5, 7, 8, NULL, '2025-05-01 01:10:45'),
(6, 8, 9, NULL, '2025-05-01 01:10:45'),
(7, 9, 10, NULL, '2025-05-01 01:10:45'),
(8, 10, 11, NULL, '2025-05-01 01:10:45'),
(16, 10, 12, 'prueba eq16 kei uie njndjdnjfnjndf oeuennj nudey i oenjdnjd yuer', '2025-05-01 01:10:45'),
(17, 11, 13, 'prueba -- ', '2025-05-01 02:54:05'),
(18, 12, 14, '', '2025-05-01 03:16:33'),
(19, 13, 15, '', '2025-05-01 03:23:47'),
(20, 14, 16, '', '2025-05-01 03:27:15'),
(21, 15, 17, '', '2025-05-01 03:32:47'),
(22, 16, 18, '', '2025-05-01 03:38:32'),
(23, 17, 19, '', '2025-05-03 23:15:07'),
(24, 18, 20, '', '2025-05-03 23:38:07'),
(25, 19, 21, '', '2025-05-04 00:07:28'),
(26, 20, 22, '', '2025-05-06 03:10:31'),
(27, 21, 23, '', '2025-05-06 03:12:46'),
(28, 22, 24, '', '2025-05-06 03:26:20'),
(29, 23, 25, '', '2025-05-06 17:25:20'),
(30, 24, 25, 'pr de dño', '2025-05-06 18:40:44'),
(31, 25, 26, '', '2025-05-06 18:47:17'),
(32, 26, 27, 'daño equipo nuevo', '2025-05-06 19:01:35'),
(33, 27, 28, '', '2025-05-06 20:09:57'),
(34, 28, 3, '', '2025-05-07 01:57:07'),
(35, 29, 10, 'equipo no enciende y suena', '2025-05-07 02:34:22'),
(36, 30, 29, '', '2025-05-07 02:58:33'),
(37, 30, 30, '', '2025-05-07 02:58:33'),
(38, 31, 31, '', '2025-05-07 03:05:10'),
(39, 31, 32, '', '2025-05-07 03:05:10'),
(40, 32, 33, '', '2025-05-07 03:16:56'),
(41, 32, 34, '', '2025-05-07 03:16:56'),
(42, 33, 35, 'no enciende', '2025-05-07 03:31:30'),
(43, 33, 36, 'enciende pero no da video', '2025-05-07 03:31:30'),
(44, 34, 19, 'no enciende', '2025-05-09 16:56:07'),
(45, 35, 27, 'equipo no enciende y no trae el cargador', '2025-05-16 01:49:42'),
(62, 45, 1, 'xxxop', '2025-05-19 18:52:48'),
(80, 63, 89, 'por', '2025-05-20 04:50:58'),
(81, 64, 2, 'asas', '2025-05-20 23:27:56'),
(82, 68, 97, 'prueba', '2025-05-21 01:09:58'),
(83, 69, 99, 'equipo 1x prueba', '2025-05-21 01:13:05'),
(84, 70, 101, 'prueba xl', '2025-05-21 01:31:01'),
(85, 71, 103, 'prueba xj', '2025-05-21 02:13:47'),
(86, 72, 106, 'equipo xf', '2025-05-21 02:15:24'),
(87, 72, 107, 'equipo xf1', '2025-05-21 02:15:24'),
(88, 73, 110, 'prueba', '2025-06-14 06:36:55'),
(89, 74, 121, 'observ', '2025-06-14 07:30:07'),
(90, 74, 122, 'prueba1', '2025-06-14 07:30:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguimientos_orden`
--

CREATE TABLE `seguimientos_orden` (
  `id_seguimiento` int(11) NOT NULL,
  `id_orden` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `tipo_servicio` varchar(100) NOT NULL,
  `procedimiento` text NOT NULL,
  `valor_cobrar` decimal(10,2) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `seguimientos_orden`
--

INSERT INTO `seguimientos_orden` (`id_seguimiento`, `id_orden`, `id_tecnico`, `tipo_servicio`, `procedimiento`, `valor_cobrar`, `fecha_registro`) VALUES
(1, 10, 1, 'Mantenimiento Preventivo', 'Se realiza el cambio de pasta termina', 25.00, '2025-04-30 19:38:48'),
(2, 9, 1, 'Reparación', 'Se realizó el arreglo', 12.00, '2025-04-30 19:41:46'),
(3, 9, 1, 'Reparación', 'se realiza el proceso completo', 15.00, '2025-04-30 19:42:31'),
(4, 9, 1, 'Reparación', 'prueba', 12.00, '2025-04-30 19:49:12'),
(5, 17, 1, 'Mantenimiento Preventivo', 'ref', 21.00, '2025-05-06 14:51:13'),
(6, 28, 5, 'Mantenimiento Preventivo', 'prueba de un procedimiento realizado', 0.00, '2025-05-06 20:58:11'),
(7, 33, 5, 'Mantenimiento Preventivo', 'limpieza equipos', 15.00, '2025-05-09 11:24:37'),
(8, 35, 1, 'Diagnóstico', 'Se revisará la pantalla en otro equipo', 0.00, '2025-05-15 22:04:27'),
(9, 25, 1, 'Actualización', 'xxx koel', 12.00, '2025-05-18 22:34:41'),
(10, 70, 1, 'Otro', 'prueba de seguimiento', 0.00, '2025-05-20 21:23:02'),
(11, 68, 5, 'Reparación', 'prueba para jnt', 20.00, '2025-05-20 22:29:01'),
(12, 71, 1, 'Otro', 'asa', 2.00, '2025-06-14 01:43:49'),
(13, 72, 5, 'Actualización', 'sas', 2.00, '2025-06-14 02:22:12'),
(14, 72, 4, 'Diagnóstico', 'qwqw', 2.00, '2025-06-14 02:22:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursales`
--

CREATE TABLE `sucursales` (
  `id_sucursal` int(11) NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `representante` varchar(200) NOT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `sucursales`
--

INSERT INTO `sucursales` (`id_sucursal`, `nombre`, `direccion`, `telefono`, `representante`, `estado`) VALUES
(1, 'Matriz', 'Floresta 1Mz 25 Villa 20', '555-0001', 'JORGE MERELO MIELES', 1),
(2, 'Sucursal Norte', 'C.C. Alban Borja puerta 6 local 63', '555-0002', 'JORGE MERELO MIELES', 1),
(3, 'Centro sur', 'Frente a la estatal', '019191-223', 'JORGE MERELO MIELES', 0),
(4, 'po', 'mkk', '676', '', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_cliente`
--

CREATE TABLE `tipos_cliente` (
  `id_tipo_cliente` int(11) NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tipos_cliente`
--

INSERT INTO `tipos_cliente` (`id_tipo_cliente`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Corporativo', 'Empresas y organizaciones', 1),
(2, 'Individual', 'Clientes particulares', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_equipo`
--

CREATE TABLE `tipos_equipo` (
  `id_tipo` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tipos_equipo`
--

INSERT INTO `tipos_equipo` (`id_tipo`, `nombre`, `estado`) VALUES
(1, 'Laptop', 1),
(2, 'Desktop', 1),
(3, 'Impresora', 1),
(4, 'Monitor', 1),
(5, 'Otro', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_servicio`
--

CREATE TABLE `tipos_servicio` (
  `id_tipo_servicio` int(11) NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tipos_servicio`
--

INSERT INTO `tipos_servicio` (`id_tipo_servicio`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Mantenimiento Preventivo', 'Limpieza y revisión general', 1),
(2, 'Reparación Hardware', 'Reparación de componentes', 1),
(3, 'Actualización Software', 'Instalación y actualización de programas', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_usuario`
--

CREATE TABLE `tipos_usuario` (
  `id_tipo` int(11) NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tipos_usuario`
--

INSERT INTO `tipos_usuario` (`id_tipo`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Administrador', 'Control total del sistema', 1),
(2, 'Técnico', 'Personal técnico de mantenimiento', 1),
(3, 'Recepcionista', 'Personal de atención al cliente', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre_completo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_tipo` int(11) DEFAULT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;



CREATE TABLE `ingresoequiposcd`.`empresa` (`id_empresa` INT NOT NULL AUTO_INCREMENT , `nombre_empresa` VARCHAR(100) NOT NULL ,
 `slogan` VARCHAR(1000) NOT NULL , `leyenda1` VARCHAR(1000) NOT NULL , `leyenda2` VARCHAR(1000) NOT NULL , `iva` DECIMAL(5,2) NOT NULL DEFAULT 0.15 , PRIMARY KEY (`id_empresa`)) ENGINE = InnoDB;



--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_completo`, `usuario`, `password`, `id_tipo`, `id_sucursal`, `estado`) VALUES
(1, 'Noemí CaMu', 'admin', '$2y$10$oTAyjgLT1I/x6/ok7efLWeEU/LmUuB/U9W8uugZau6.f7wGMrkMU.', 1, 1, 1),
(2, 'Técnico Prueba', 'tecnico1', '$2y$10$uWx7kWSJPOFi0DpZHMIFpOO0AOZZDPeNFKRCShWK6LqpvACx3jACK', 2, 2, 0),
(3, 'Recepcionista Prueba', 'recep1', '$2y$10$HY.vJLLRmj8JGFtBRJXC8.9N0UKXRmJc4kFZL2s.C9hDx/MggDOiS', 3, 1, 0),
(4, 'Leo', 'leoadmin', '$2y$10$lx3BAfhidXSTV.oiuZHlwO6gia1h7t26c3fNoT5RUDu1zf.GYOYuq', 2, 2, 1),
(5, 'Jona', 'jnt', '$2y$10$a8..Nf4dEePoDR8j/B03cuhzXpdLYqrmayX0UR2V4QloTI7fVTyPC', 2, 1, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `identificacion` (`identificacion`),
  ADD KEY `id_tipo_cliente` (`id_tipo_cliente`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id_equipo`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `ordenes_trabajo`
--
ALTER TABLE `ordenes_trabajo`
  ADD PRIMARY KEY (`id_orden`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_equipo` (`id_equipo`),
  ADD KEY `id_usuario_registro` (`id_usuario_registro`),
  ADD KEY `id_sucursal` (`id_sucursal`);

--
-- Indices de la tabla `orden_equipos`
--
ALTER TABLE `orden_equipos`
  ADD PRIMARY KEY (`id_orden_equipo`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_equipo` (`id_equipo`);

--
-- Indices de la tabla `seguimientos_orden`
--
ALTER TABLE `seguimientos_orden`
  ADD PRIMARY KEY (`id_seguimiento`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_tecnico` (`id_tecnico`);

--
-- Indices de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id_sucursal`);

--
-- Indices de la tabla `tipos_cliente`
--
ALTER TABLE `tipos_cliente`
  ADD PRIMARY KEY (`id_tipo_cliente`);

--
-- Indices de la tabla `tipos_equipo`
--
ALTER TABLE `tipos_equipo`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `tipos_servicio`
--
ALTER TABLE `tipos_servicio`
  ADD PRIMARY KEY (`id_tipo_servicio`);

--
-- Indices de la tabla `tipos_usuario`
--
ALTER TABLE `tipos_usuario`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `id_tipo` (`id_tipo`),
  ADD KEY `id_sucursal` (`id_sucursal`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT de la tabla `ordenes_trabajo`
--
ALTER TABLE `ordenes_trabajo`
  MODIFY `id_orden` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT de la tabla `orden_equipos`
--
ALTER TABLE `orden_equipos`
  MODIFY `id_orden_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT de la tabla `seguimientos_orden`
--
ALTER TABLE `seguimientos_orden`
  MODIFY `id_seguimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id_sucursal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tipos_cliente`
--
ALTER TABLE `tipos_cliente`
  MODIFY `id_tipo_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tipos_equipo`
--
ALTER TABLE `tipos_equipo`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `tipos_servicio`
--
ALTER TABLE `tipos_servicio`
  MODIFY `id_tipo_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tipos_usuario`
--
ALTER TABLE `tipos_usuario`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`id_tipo_cliente`) REFERENCES `tipos_cliente` (`id_tipo_cliente`);

--
-- Filtros para la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD CONSTRAINT `equipos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`);

--
-- Filtros para la tabla `ordenes_trabajo`
--
ALTER TABLE `ordenes_trabajo`
  ADD CONSTRAINT `ordenes_trabajo_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `ordenes_trabajo_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`),
  ADD CONSTRAINT `ordenes_trabajo_ibfk_3` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `ordenes_trabajo_ibfk_4` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`);

--
-- Filtros para la tabla `orden_equipos`
--
ALTER TABLE `orden_equipos`
  ADD CONSTRAINT `orden_equipos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_trabajo` (`id_orden`),
  ADD CONSTRAINT `orden_equipos_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`);

--
-- Filtros para la tabla `seguimientos_orden`
--
ALTER TABLE `seguimientos_orden`
  ADD CONSTRAINT `seguimientos_orden_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_trabajo` (`id_orden`),
  ADD CONSTRAINT `seguimientos_orden_ibfk_2` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_tipo`) REFERENCES `tipos_usuario` (`id_tipo`),
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
