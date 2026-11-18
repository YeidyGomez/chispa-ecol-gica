-- =====================================================================
-- CHISPA ECOLÓGICA - Base de datos COMPLETA con los datos actuales
-- (usuarios, tips, retos, evidencias, publicaciones, comentarios y "me gusta")
--
-- Es la copia de la base anterior (envirospark_db) con el nombre nuevo.
-- Conserva los mismos ID, contraseñas (cifradas), puntos y publicaciones.
--
-- CÓMO IMPORTARLA (phpMyAdmin):
--   1) Abre http://localhost/phpmyadmin > pestaña "Importar".
--   2) Elige este archivo > "Importar". (Crea la base chispa_ecologica_db sola.)
--   3) Copia las carpetas uploads/ del proyecto viejo al nuevo (ya vienen incluidas).
--
-- Si después de esta copia se registraron usuarios nuevos en el sitio viejo,
-- usa mejor migrar_datos_envirospark.sql, que copia los datos más recientes.
-- =====================================================================

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-11-2026 a las 02:19:01
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
-- Base de datos: `chispa_ecologica_db`
--
CREATE DATABASE IF NOT EXISTS `chispa_ecologica_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chispa_ecologica_db`;

--
-- Base de datos de origen: `envirospark_db` (ahora `chispa_ecologica_db`)
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios`
--

CREATE TABLE `comentarios` (
  `id` int(11) NOT NULL,
  `publicacion_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `autor` varchar(30) NOT NULL,
  `texto` varchar(500) NOT NULL,
  `visitante` varchar(64) NOT NULL DEFAULT '',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `comentarios`
--

INSERT INTO `comentarios` (`id`, `publicacion_id`, `parent_id`, `autor`, `texto`, `visitante`, `fecha`, `estado`) VALUES
(1, 1, NULL, 'María José Higuita', 'Mi primera iniciativa de muchas', 'u2', '2026-11-09 18:51:12', 'aprobado'),
(2, 1, NULL, 'Hanna Uribe', 'Que chévere, me gusta la iniciativa', 'u3', '2026-11-09 18:55:29', 'aprobado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentario_likes`
--

CREATE TABLE `comentario_likes` (
  `comentario_id` int(11) NOT NULL,
  `visitante` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evidencias`
--

CREATE TABLE `evidencias` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `reto_id` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `imagen` varchar(255) NOT NULL,
  `puntos` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `evidencias`
--

INSERT INTO `evidencias` (`id`, `usuario_id`, `reto_id`, `descripcion`, `imagen`, `puntos`, `fecha`, `estado`) VALUES
(1, 2, 1, 'Hoy realicé una jornada de separación de residuos en mi hogar. Clasifiqué 4 botellas de plástico, 3 hojas de papel, 2 cajas de cartón y algunos residuos orgánicos, como cáscaras de frutas. Separé cada material en su respectivo recipiente para facilitar su reciclaje y aprovechamiento.', 'uploads/retos/35a6a8f0d50b2549.png', 50, '2026-11-09 18:46:13', 'aprobado'),
(3, 3, 4, 'Realicé el reto del apagón consciente desconectando los cargadores que no estaba utilizando, la regleta del centro de entretenimiento y el televisor. Además, aproveché la luz natural abriendo las cortinas para estudiar y realizar mis actividades durante el día sin encender las luces de las habitaciones.', 'uploads/retos/92d232b3e9ac155b.jpg', 40, '2026-11-09 20:24:13', 'aprobado'),
(4, 4, 1, 'Hoy clasifiqué los residuos de mi hogar separando el plástico, papel, cartón y vidrio de la materia orgánica, dejándolos limpios y secos para su reciclaje.', 'uploads/retos/672a438b7b36e8e0.jpg', 50, '2026-11-11 15:17:28', 'aprobado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicaciones`
--

CREATE TABLE `publicaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `autor` varchar(30) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `imagen` varchar(255) NOT NULL,
  `visitante` varchar(64) NOT NULL DEFAULT '',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `publicaciones`
--

INSERT INTO `publicaciones` (`id`, `usuario_id`, `autor`, `titulo`, `descripcion`, `imagen`, `visitante`, `fecha`, `estado`) VALUES
(1, 2, 'María José Higuita', 'Jornada para un planeta más verde', 'Hoy decidí contribuir al cuidado del medio ambiente realizando una pequeña jornada ecológica. Recogí residuos que encontré en mi entorno, separé los materiales reciclables y cuidé las plantas de mi comunidad.', 'uploads/comunidad/67008fb6a0e8691e.png', 'u2', '2026-11-09 18:47:20', 'aprobado'),
(2, 3, 'Hanna Uribe', 'Unidos por espacios verdes más limpios', 'Hoy decidí aportar al cuidado de mi comunidad mediante una jornada de limpieza y protección de las zonas verdes. Recogí residuos que se encontraban en el suelo, separé los materiales reciclables y contribuí a mantener limpio y agradable nuestro entorno.\r\nTambién quiero motivar a mis vecinos, amigos y familiares a cuidar los parques, evitar arrojar basura y proteger las plantas y los árboles.', 'uploads/comunidad/2af4bc4889da9a15.jpg', 'u3', '2026-11-09 19:03:58', 'aprobado'),
(3, 4, 'Daniel Giraldo', 'Pequeñas acciones', 'Cuidar el planeta comienza con nuestras decisiones diarias. Una excelente forma de ayudar al medio ambiente es reducir el uso de plásticos de un solo uso.\r\nPodemos utilizar bolsas de tela, botellas reutilizables y recipientes ecológicos en lugar de productos desechables. Estas pequeñas acciones ayudan a disminuir la contaminación, proteger los ecosistemas y cuidar nuestra biodiversidad.', 'uploads/comunidad/dd7b270d549b568e.jpg', 'u4', '2026-11-11 15:19:13', 'aprobado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicacion_likes`
--

CREATE TABLE `publicacion_likes` (
  `publicacion_id` int(11) NOT NULL,
  `visitante` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `publicacion_likes`
--

INSERT INTO `publicacion_likes` (`publicacion_id`, `visitante`) VALUES
(1, 'smkt8ordlmmtqrfhnm4dtnup46d'),
(1, 'u1'),
(1, 'u2'),
(1, 'u3'),
(1, 'u4'),
(2, 'smkt8ordlmmtqrfhnm4dtnup46d'),
(2, 'u1'),
(2, 'u3'),
(2, 'u4');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retos`
--

CREATE TABLE `retos` (
  `id` int(11) NOT NULL,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `puntos` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `retos`
--

INSERT INTO `retos` (`id`, `clave`, `nombre`, `puntos`) VALUES
(1, 'recicla', 'Recicla y gana', 50),
(2, 'agua', 'Ahorro de agua', 30),
(3, 'naturaleza', 'Misión naturaleza', 100),
(4, 'energia', 'Apagón consciente', 40),
(5, 'movilidad', 'Muévete sin carbono', 60),
(6, 'plasticos', 'Cero plásticos', 50);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tips`
--

CREATE TABLE `tips` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `autor` varchar(100) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `categoria` enum('hogar','energia','movilidad','agua') NOT NULL,
  `explicacion` text NOT NULL,
  `origen` enum('base','comunidad') NOT NULL DEFAULT 'comunidad',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tips`
--

INSERT INTO `tips` (`id`, `usuario_id`, `autor`, `titulo`, `categoria`, `explicacion`, `origen`, `fecha`, `estado`) VALUES
(1, NULL, NULL, 'Ahorra energía en casa', 'energia', 'Apaga las luces que no uses y desconecta los aparatos electrónicos. Usa bombillas LED y aprovecha la luz solar.', 'base', '2026-11-09 18:37:43', 'aprobado'),
(2, NULL, NULL, 'Planta árboles', 'hogar', 'Ayuda a mejorar la calidad del aire y crea hábitats para la fauna silvestre. Elige especies nativas.', 'base', '2026-11-09 18:37:43', 'aprobado'),
(3, NULL, NULL, 'Muévete sostenible', 'movilidad', 'Usa la bicicleta, camina o utiliza transporte público para evitar emisiones.', 'base', '2026-11-09 18:37:43', 'aprobado'),
(4, NULL, NULL, 'Cuida el agua', 'agua', 'Toma duchas cortas, cierra el grifo mientras te cepillas los dientes y repara fugas.', 'base', '2026-11-09 18:37:43', 'aprobado'),
(5, NULL, NULL, 'Grifos inteligentes', 'agua', 'Instalar aireadores en tus grifos reduce el caudal de agua consumido hasta en un 50% sin perder presión.', 'base', '2026-11-09 18:37:43', 'aprobado'),
(8, 2, 'María José Higuita', 'Reutiliza el agua', 'agua', 'Coloca un recipiente limpio en un lugar donde pueda recoger el agua de lluvia.\r\nTapa el recipiente para evitar que entren hojas, insectos o suciedad.\r\nGuarda el agua recolectada en un lugar seguro.\r\nUtilízala para regar las plantas, lavar patios o limpiar herramientas.\r\nEvita consumir esta agua o utilizarla para cocinar, ya que no está tratada para el consumo humano.', 'comunidad', '2026-11-09 18:45:19', 'aprobado'),
(9, 3, 'Hanna Uribe', 'Reduce la contaminación', 'movilidad', 'Organiza tus desplazamientos con familiares, amigos o compañeros que tengan un destino similar.\r\nAcuerden compartir un mismo vehículo en lugar de utilizar varios automóviles.\r\nEstablezcan puntos de encuentro seguros para recoger a los pasajeros.\r\nPlanifiquen los recorridos para evitar viajes innecesarios.\r\nCuando sea posible, combina esta práctica con caminar, usar bicicleta o transporte público.', 'comunidad', '2026-11-09 18:57:31', 'aprobado'),
(10, 4, 'Daniel Giraldo', 'DESCONECTA Y AHORRA', 'energia', 'Apaga los dispositivos electrónicos que no estés utilizando y desconecta sus cargadores para evitar el consumo innecesario de energía.', 'comunidad', '2026-11-11 15:15:14', 'aprobado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('admin','estudiante','externo') NOT NULL DEFAULT 'externo',
  `grado` tinyint(4) DEFAULT NULL,
  `politica_aceptada` datetime DEFAULT NULL,
  `politica_version` varchar(20) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `puntos` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_completo`, `correo`, `contrasena`, `rol`, `grado`, `politica_aceptada`, `politica_version`, `fecha_registro`, `puntos`) VALUES
(1, 'Yeidy', 'ieca.yeidygomez@gmail.com', '$2y$10$/axt7.h.IXZlXTBz3CYjueMIAEMA7HyWMZzs74mR2ZMV9YLo9m14u', 'admin', NULL, NULL, NULL, '2026-11-09 18:41:34', 0),
(2, 'María José Higuita', 'ieca.majohiguita@gmail.com', '$2y$10$HhsTJgIWTVYgaTQ8Dr2Hze9Ck6Rze5Ugb8B.1tc2q9RsSVoO3OoHK', 'externo', NULL, '2026-11-09 18:43:35', '2026-11-08', '2026-11-09 18:43:35', 50),
(3, 'Hanna Uribe', 'ieca.hannauribebetantacur@gmail.com', '$2y$10$QgffHkPfMy.QdI4Gqk5MQeGcGdS8eaSxSn5PFw6E5HhqanyGc417m', 'externo', NULL, '2026-11-09 18:54:38', '2026-11-08', '2026-11-09 18:54:38', 40),
(4, 'Daniel Giraldo', 'daniel.giraldogo@gmail.com', '$2y$10$l3Io5EVPE/1olpBTXf0O7eLOCzN00EjQLhDBuybeIstCqU9qw34jG', 'externo', NULL, '2026-11-11 15:13:01', '2026-11-08', '2026-11-11 15:13:01', 50);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `publicacion_id` (`publicacion_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_comentarios_estado` (`estado`);

--
-- Indices de la tabla `comentario_likes`
--
ALTER TABLE `comentario_likes`
  ADD PRIMARY KEY (`comentario_id`,`visitante`);

--
-- Indices de la tabla `evidencias`
--
ALTER TABLE `evidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `reto_id` (`reto_id`),
  ADD KEY `idx_evidencias_estado` (`estado`);

--
-- Indices de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_publicaciones_estado` (`estado`);

--
-- Indices de la tabla `publicacion_likes`
--
ALTER TABLE `publicacion_likes`
  ADD PRIMARY KEY (`publicacion_id`,`visitante`);

--
-- Indices de la tabla `retos`
--
ALTER TABLE `retos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `tips`
--
ALTER TABLE `tips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_tips_estado` (`estado`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `evidencias`
--
ALTER TABLE `evidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `retos`
--
ALTER TABLE `retos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tips`
--
ALTER TABLE `tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`publicacion_id`) REFERENCES `publicaciones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comentarios_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `comentarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `comentario_likes`
--
ALTER TABLE `comentario_likes`
  ADD CONSTRAINT `comentario_likes_ibfk_1` FOREIGN KEY (`comentario_id`) REFERENCES `comentarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `evidencias`
--
ALTER TABLE `evidencias`
  ADD CONSTRAINT `evidencias_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evidencias_ibfk_2` FOREIGN KEY (`reto_id`) REFERENCES `retos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD CONSTRAINT `publicaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `publicacion_likes`
--
ALTER TABLE `publicacion_likes`
  ADD CONSTRAINT `publicacion_likes_ibfk_1` FOREIGN KEY (`publicacion_id`) REFERENCES `publicaciones` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tips`
--
ALTER TABLE `tips`
  ADD CONSTRAINT `tips_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
