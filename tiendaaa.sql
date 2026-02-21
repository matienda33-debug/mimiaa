-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-02-2026 a las 05:59:32
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
-- Base de datos: `tiendaaa`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ajitos_config`
--

CREATE TABLE `ajitos_config` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `valor` text DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ajitos_config`
--

INSERT INTO `ajitos_config` (`id`, `nombre`, `valor`, `descripcion`) VALUES
(1, 'nombre_tienda', 'MI&MI Store', 'Nombre principal de la tienda'),
(2, 'nombre_ajitos', 'Ajitos Kids', 'Nombre de la sección de bebés');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `dpi` varchar(13) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `puntos` int(11) DEFAULT 0,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamentos`
--

CREATE TABLE `departamentos` (
  `id_departamento` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `es_ajitos` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `departamentos`
--

INSERT INTO `departamentos` (`id_departamento`, `nombre`, `descripcion`, `imagen`, `activo`, `es_ajitos`) VALUES
(1, 'Ropa de Mujer', 'Moda femenina', 'mujer.jpg', 1, 0),
(2, 'Ropa de Hombre', 'Moda masculina', 'hombre.jpg', 1, 0),
(3, 'Ropa de Niños', 'Ropa infantil', 'ninos.jpg', 1, 0),
(4, 'Ropa de Bebé', 'Sección Ajitos Kids', 'bebe.jpg', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado_factura`
--

CREATE TABLE `estado_factura` (
  `id_estado` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estado_factura`
--

INSERT INTO `estado_factura` (`id_estado`, `nombre`, `descripcion`) VALUES
(1, 'pendiente', 'Factura pendiente de pago'),
(2, 'pagada', 'Factura pagada'),
(3, 'enviada', 'Productos enviados'),
(4, 'entregada', 'Productos entregados'),
(5, 'cancelada', 'Factura cancelada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_cabecera`
--

CREATE TABLE `factura_cabecera` (
  `id_factura` int(11) NOT NULL,
  `numero_factura` varchar(20) NOT NULL,
  `numero_orden` varchar(20) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `id_cliente` int(11) DEFAULT NULL,
  `nombre_cliente` varchar(200) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `referencia_envio` text DEFAULT NULL,
  `tipo_venta` enum('tienda','online','recoger') NOT NULL DEFAULT 'tienda',
  `id_usuario` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `puntos_usados` int(11) DEFAULT 0,
  `puntos_ganados` int(11) DEFAULT 0,
  `id_estado` int(11) DEFAULT 1,
  `llave_confirmacion` varchar(100) DEFAULT NULL,
  `fecha_expiracion` datetime DEFAULT NULL,
  `correo_enviado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_detalle`
--

CREATE TABLE `factura_detalle` (
  `id_detalle` int(11) NOT NULL,
  `id_factura` int(11) NOT NULL,
  `id_producto_variante` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `descuento_unitario` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_movimientos`
--

CREATE TABLE `inventario_movimientos` (
  `id_movimiento` int(11) NOT NULL,
  `id_producto_variante` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `ubicacion` enum('tienda','bodega') NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_movimiento` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario_movimientos`
--

INSERT INTO `inventario_movimientos` (`id_movimiento`, `id_producto_variante`, `tipo_movimiento`, `cantidad`, `ubicacion`, `motivo`, `id_usuario`, `fecha_movimiento`) VALUES
(1, 1, 'entrada', 5, 'tienda', 'Creación de variante', 1, '2026-02-05 22:29:13'),
(2, 1, 'entrada', 5, 'bodega', 'Creación de variante', 1, '2026-02-05 22:29:13'),
(3, 1, 'salida', 1, 'tienda', 'pedido', 1, '2026-02-05 22:30:03'),
(4, 1, 'entrada', 1, 'bodega', 'pedido', 1, '2026-02-05 22:30:03'),
(5, 2, 'entrada', 6, 'tienda', 'Creación de variante', 1, '2026-02-05 22:32:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marcas`
--

CREATE TABLE `marcas` (
  `id_marca` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `marcas`
--

INSERT INTO `marcas` (`id_marca`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'kalua', 'marca para mujer', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ofertas`
--

CREATE TABLE `ofertas` (
  `id_oferta` int(11) NOT NULL,
  `id_producto_raiz` int(11) NOT NULL,
  `porcentaje_descuento` decimal(5,2) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_raiz`
--

CREATE TABLE `productos_raiz` (
  `id_raiz` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_departamento` int(11) NOT NULL,
  `id_marca` int(11) DEFAULT NULL,
  `tipo_ropa` varchar(100) DEFAULT NULL,
  `precio_compra` decimal(10,2) DEFAULT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `etiqueta` enum('oferta','nuevo','reingreso') DEFAULT 'nuevo',
  `es_ajitos` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_raiz`
--

INSERT INTO `productos_raiz` (`id_raiz`, `codigo`, `nombre`, `descripcion`, `id_departamento`, `id_marca`, `tipo_ropa`, `precio_compra`, `precio_venta`, `etiqueta`, `es_ajitos`, `activo`, `fecha_creacion`) VALUES
(2, '123', 'pantalones', 'es una buena compra', 1, 1, 'versatil', 15.00, 30.00, 'oferta', 0, 1, '2026-02-05 00:43:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_raiz_fotos`
--

CREATE TABLE `productos_raiz_fotos` (
  `id_foto` int(11) NOT NULL,
  `id_producto_raiz` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `es_principal` tinyint(1) DEFAULT 0,
  `orden` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_raiz_fotos`
--

INSERT INTO `productos_raiz_fotos` (`id_foto`, `id_producto_raiz`, `nombre_archivo`, `es_principal`, `orden`) VALUES
(1, 2, '69843c26d3194_1770273830.jpg', 1, 0),
(2, 2, '69856df5ae9ea_1770352117.jpg', 0, 0),
(3, 2, '69856e0602fec_1770352134.jpeg', 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_variantes`
--

CREATE TABLE `productos_variantes` (
  `id_variante` int(11) NOT NULL,
  `id_producto_raiz` int(11) NOT NULL,
  `color` varchar(50) NOT NULL,
  `talla` varchar(20) NOT NULL,
  `stock_tienda` int(11) DEFAULT 0,
  `stock_bodega` int(11) DEFAULT 0,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_variantes`
--

INSERT INTO `productos_variantes` (`id_variante`, `id_producto_raiz`, `color`, `talla`, `stock_tienda`, `stock_bodega`, `precio_venta`, `sku`, `activo`) VALUES
(1, 2, 'Azul', 'XXL', 4, 6, 0.00, '123-AZU-XXL', 1),
(2, 2, 'Rojo', 'XXL', 6, 1, 0.00, '123-ROJ-XXL', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `permisos` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `permisos`) VALUES
(1, 'admin', 'Administrador total del sistema', '[\"usuarios\",\"productos\",\"ventas\",\"reportes\",\"configuracion\",\"inventario\",\"contabilidad\"]'),
(2, 'trabajador1', 'Trabajador nivel 1 - Ventas y consultas', '[\"ventas\",\"productos\",\"clientes\"]'),
(3, 'trabajador2', 'Trabajador nivel 2 - Ventas e inventario', '[\"ventas\",\"productos\",\"clientes\",\"inventario\"]'),
(4, 'cliente', 'Cliente externo', '[\"comprar\",\"ver_perfil\",\"ver_puntos\"]');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_ropa`
--

CREATE TABLE `tipos_ropa` (
  `id_tipo` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `dpi` varchar(13) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `ultimo_login` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `username`, `password`, `email`, `id_rol`, `nombre`, `apellido`, `dpi`, `telefono`, `fecha_registro`, `ultimo_login`, `activo`) VALUES
(1, 'admin', '$2y$10$ycxQtv5MsuLhOy2aUMcF1e8o2o3sr9q0GH9Lpb23br5JWn6JjFlOS', 'admin@tiendamm.com', 1, 'Administrador', 'Sistema', '1234567890123', NULL, '2026-02-04 23:59:27', '2026-02-05 22:21:00', 1),
(2, 'trabajador1', '$2y$10$yAp2uqPvCJUzMWatWBbQm.E45W7HvTxqmhGbZZvBteiJNyHMr/Yv.', 'trab1@tiendamm.com', 2, 'Juan', 'Pérez', '2345678901234', NULL, '2026-02-04 23:59:27', NULL, 1),
(3, 'trabajador2', '$2y$10$iST4hRwC28bmU/1UAFnLSO3XLhK/J67kU3coID4Oyr7mxfJ//WHYC', 'admin@tiendxxamm.com', 1, 'wagner esau', 'choc', '3035416870110', '58101902', '2026-02-04 23:59:27', NULL, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `ajitos_config`
--
ALTER TABLE `ajitos_config`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `dpi` (`dpi`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id_departamento`);

--
-- Indices de la tabla `estado_factura`
--
ALTER TABLE `estado_factura`
  ADD PRIMARY KEY (`id_estado`);

--
-- Indices de la tabla `factura_cabecera`
--
ALTER TABLE `factura_cabecera`
  ADD PRIMARY KEY (`id_factura`),
  ADD UNIQUE KEY `numero_factura` (`numero_factura`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_estado` (`id_estado`);

--
-- Indices de la tabla `factura_detalle`
--
ALTER TABLE `factura_detalle`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_factura` (`id_factura`),
  ADD KEY `id_producto_variante` (`id_producto_variante`);

--
-- Indices de la tabla `inventario_movimientos`
--
ALTER TABLE `inventario_movimientos`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `id_producto_variante` (`id_producto_variante`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `marcas`
--
ALTER TABLE `marcas`
  ADD PRIMARY KEY (`id_marca`);

--
-- Indices de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  ADD PRIMARY KEY (`id_oferta`),
  ADD KEY `id_producto_raiz` (`id_producto_raiz`);

--
-- Indices de la tabla `productos_raiz`
--
ALTER TABLE `productos_raiz`
  ADD PRIMARY KEY (`id_raiz`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `id_departamento` (`id_departamento`),
  ADD KEY `id_marca` (`id_marca`);

--
-- Indices de la tabla `productos_raiz_fotos`
--
ALTER TABLE `productos_raiz_fotos`
  ADD PRIMARY KEY (`id_foto`),
  ADD KEY `id_producto_raiz` (`id_producto_raiz`);

--
-- Indices de la tabla `productos_variantes`
--
ALTER TABLE `productos_variantes`
  ADD PRIMARY KEY (`id_variante`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `id_producto_raiz` (`id_producto_raiz`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `tipos_ropa`
--
ALTER TABLE `tipos_ropa`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `ajitos_config`
--
ALTER TABLE `ajitos_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `estado_factura`
--
ALTER TABLE `estado_factura`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `factura_cabecera`
--
ALTER TABLE `factura_cabecera`
  MODIFY `id_factura` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `factura_detalle`
--
ALTER TABLE `factura_detalle`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventario_movimientos`
--
ALTER TABLE `inventario_movimientos`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `marcas`
--
ALTER TABLE `marcas`
  MODIFY `id_marca` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  MODIFY `id_oferta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos_raiz`
--
ALTER TABLE `productos_raiz`
  MODIFY `id_raiz` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `productos_raiz_fotos`
--
ALTER TABLE `productos_raiz_fotos`
  MODIFY `id_foto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `productos_variantes`
--
ALTER TABLE `productos_variantes`
  MODIFY `id_variante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tipos_ropa`
--
ALTER TABLE `tipos_ropa`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `factura_cabecera`
--
ALTER TABLE `factura_cabecera`
  ADD CONSTRAINT `FK_factura_cabecera_clientes` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_factura_cabecera_estado_factura` FOREIGN KEY (`id_estado`) REFERENCES `estado_factura` (`id_estado`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_factura_cabecera_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `factura_detalle`
--
ALTER TABLE `factura_detalle`
  ADD CONSTRAINT `FK_factura_detalle_factura_cabecera` FOREIGN KEY (`id_factura`) REFERENCES `factura_cabecera` (`id_factura`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_factura_detalle_productos_variantes` FOREIGN KEY (`id_producto_variante`) REFERENCES `productos_variantes` (`id_variante`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `inventario_movimientos`
--
ALTER TABLE `inventario_movimientos`
  ADD CONSTRAINT `FK_inventario_movimientos_productos_variantes` FOREIGN KEY (`id_producto_variante`) REFERENCES `productos_variantes` (`id_variante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_inventario_movimientos_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `ofertas`
--
ALTER TABLE `ofertas`
  ADD CONSTRAINT `FK_ofertas_productos_raiz` FOREIGN KEY (`id_producto_raiz`) REFERENCES `productos_raiz` (`id_raiz`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos_raiz`
--
ALTER TABLE `productos_raiz`
  ADD CONSTRAINT `FK_productos_raiz_departamentos` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id_departamento`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_productos_raiz_marcas` FOREIGN KEY (`id_marca`) REFERENCES `marcas` (`id_marca`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos_raiz_fotos`
--
ALTER TABLE `productos_raiz_fotos`
  ADD CONSTRAINT `FK_productos_raiz_fotos_productos_raiz` FOREIGN KEY (`id_producto_raiz`) REFERENCES `productos_raiz` (`id_raiz`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos_variantes`
--
ALTER TABLE `productos_variantes`
  ADD CONSTRAINT `FK_productos_variantes_productos_raiz` FOREIGN KEY (`id_producto_raiz`) REFERENCES `productos_raiz` (`id_raiz`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `FK_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
