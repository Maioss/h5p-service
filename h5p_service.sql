-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-11-2025 a las 16:54:17
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `h5p_service`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_contents`
--

CREATE TABLE `h5p_contents` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `library_id` int(10) UNSIGNED NOT NULL,
  `parameters` longtext NOT NULL,
  `filtered` longtext NOT NULL,
  `slug` varchar(127) NOT NULL,
  `embed_type` varchar(127) NOT NULL,
  `disable` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `content_type` varchar(127) DEFAULT NULL,
  `authors` longtext DEFAULT NULL,
  `source` varchar(2083) DEFAULT NULL,
  `year_from` int(10) UNSIGNED DEFAULT NULL,
  `year_to` int(10) UNSIGNED DEFAULT NULL,
  `license` varchar(32) DEFAULT NULL,
  `license_version` varchar(10) DEFAULT NULL,
  `license_extras` longtext DEFAULT NULL,
  `author_comments` longtext DEFAULT NULL,
  `changes` longtext DEFAULT NULL,
  `default_language` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_contents_libraries`
--

CREATE TABLE `h5p_contents_libraries` (
  `content_id` int(10) UNSIGNED NOT NULL,
  `library_id` int(10) UNSIGNED NOT NULL,
  `dependency_type` varchar(31) NOT NULL,
  `weight` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `drop_css` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_contents_tags`
--

CREATE TABLE `h5p_contents_tags` (
  `content_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_contents_user_data`
--

CREATE TABLE `h5p_contents_user_data` (
  `content_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `sub_content_id` int(10) UNSIGNED NOT NULL,
  `data_id` varchar(127) NOT NULL,
  `data` longtext NOT NULL,
  `preload` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `invalidate` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_counters`
--

CREATE TABLE `h5p_counters` (
  `type` varchar(63) NOT NULL,
  `library_name` varchar(127) NOT NULL,
  `library_version` varchar(31) NOT NULL,
  `num` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_events`
--

CREATE TABLE `h5p_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL,
  `type` varchar(63) NOT NULL,
  `sub_type` varchar(63) NOT NULL,
  `content_id` int(10) UNSIGNED NOT NULL,
  `content_title` varchar(255) NOT NULL,
  `library_name` varchar(127) NOT NULL,
  `library_version` varchar(31) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_libraries`
--

CREATE TABLE `h5p_libraries` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name` varchar(127) NOT NULL,
  `title` varchar(255) NOT NULL,
  `major_version` int(10) UNSIGNED NOT NULL,
  `minor_version` int(10) UNSIGNED NOT NULL,
  `patch_version` int(10) UNSIGNED NOT NULL,
  `runnable` int(10) UNSIGNED NOT NULL,
  `restricted` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `fullscreen` int(10) UNSIGNED NOT NULL,
  `embed_types` varchar(255) NOT NULL,
  `preloaded_js` text DEFAULT NULL,
  `preloaded_css` text DEFAULT NULL,
  `drop_library_css` text DEFAULT NULL,
  `semantics` text NOT NULL,
  `tutorial_url` varchar(1023) NOT NULL,
  `has_icon` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `metadata_settings` text DEFAULT NULL,
  `add_to` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_libraries_cachedassets`
--

CREATE TABLE `h5p_libraries_cachedassets` (
  `library_id` int(10) UNSIGNED NOT NULL,
  `hash` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_libraries_hub_cache`
--

CREATE TABLE `h5p_libraries_hub_cache` (
  `id` int(10) UNSIGNED NOT NULL,
  `machine_name` varchar(127) NOT NULL,
  `major_version` int(10) UNSIGNED NOT NULL,
  `minor_version` int(10) UNSIGNED NOT NULL,
  `patch_version` int(10) UNSIGNED NOT NULL,
  `h5p_major_version` int(10) UNSIGNED DEFAULT NULL,
  `h5p_minor_version` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `description` text NOT NULL,
  `icon` varchar(511) NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL,
  `updated_at` int(10) UNSIGNED NOT NULL,
  `is_recommended` int(10) UNSIGNED NOT NULL,
  `popularity` int(10) UNSIGNED NOT NULL,
  `screenshots` text DEFAULT NULL,
  `license` text DEFAULT NULL,
  `example` varchar(511) NOT NULL,
  `tutorial` varchar(511) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `categories` text DEFAULT NULL,
  `owner` varchar(511) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_libraries_languages`
--

CREATE TABLE `h5p_libraries_languages` (
  `library_id` int(10) UNSIGNED NOT NULL,
  `language_code` varchar(31) NOT NULL,
  `translation` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_libraries_libraries`
--

CREATE TABLE `h5p_libraries_libraries` (
  `library_id` int(10) UNSIGNED NOT NULL,
  `required_library_id` int(10) UNSIGNED NOT NULL,
  `dependency_type` varchar(31) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_options`
--

CREATE TABLE `h5p_options` (
  `name` varchar(255) NOT NULL,
  `value` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_results`
--

CREATE TABLE `h5p_results` (
  `id` int(10) UNSIGNED NOT NULL,
  `content_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `score` int(10) UNSIGNED NOT NULL,
  `max_score` int(10) UNSIGNED NOT NULL,
  `opened` int(10) UNSIGNED NOT NULL,
  `finished` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_tags`
--

CREATE TABLE `h5p_tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(31) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `h5p_tmpfiles`
--

CREATE TABLE `h5p_tmpfiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `h5p_contents`
--
ALTER TABLE `h5p_contents`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `h5p_contents_libraries`
--
ALTER TABLE `h5p_contents_libraries`
  ADD PRIMARY KEY (`content_id`,`library_id`,`dependency_type`);

--
-- Indices de la tabla `h5p_contents_tags`
--
ALTER TABLE `h5p_contents_tags`
  ADD PRIMARY KEY (`content_id`,`tag_id`);

--
-- Indices de la tabla `h5p_contents_user_data`
--
ALTER TABLE `h5p_contents_user_data`
  ADD PRIMARY KEY (`content_id`,`user_id`,`sub_content_id`,`data_id`);

--
-- Indices de la tabla `h5p_counters`
--
ALTER TABLE `h5p_counters`
  ADD PRIMARY KEY (`type`,`library_name`,`library_version`);

--
-- Indices de la tabla `h5p_events`
--
ALTER TABLE `h5p_events`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `h5p_libraries`
--
ALTER TABLE `h5p_libraries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name_version` (`name`,`major_version`,`minor_version`,`patch_version`),
  ADD KEY `runnable` (`runnable`);

--
-- Indices de la tabla `h5p_libraries_cachedassets`
--
ALTER TABLE `h5p_libraries_cachedassets`
  ADD PRIMARY KEY (`library_id`,`hash`);

--
-- Indices de la tabla `h5p_libraries_hub_cache`
--
ALTER TABLE `h5p_libraries_hub_cache`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name_version` (`machine_name`,`major_version`,`minor_version`,`patch_version`);

--
-- Indices de la tabla `h5p_libraries_languages`
--
ALTER TABLE `h5p_libraries_languages`
  ADD PRIMARY KEY (`library_id`,`language_code`);

--
-- Indices de la tabla `h5p_libraries_libraries`
--
ALTER TABLE `h5p_libraries_libraries`
  ADD PRIMARY KEY (`library_id`,`required_library_id`);

--
-- Indices de la tabla `h5p_options`
--
ALTER TABLE `h5p_options`
  ADD PRIMARY KEY (`name`);

--
-- Indices de la tabla `h5p_results`
--
ALTER TABLE `h5p_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `content_user` (`content_id`,`user_id`);

--
-- Indices de la tabla `h5p_tags`
--
ALTER TABLE `h5p_tags`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `h5p_tmpfiles`
--
ALTER TABLE `h5p_tmpfiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `h5p_contents`
--
ALTER TABLE `h5p_contents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `h5p_events`
--
ALTER TABLE `h5p_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `h5p_libraries`
--
ALTER TABLE `h5p_libraries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `h5p_libraries_hub_cache`
--
ALTER TABLE `h5p_libraries_hub_cache`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `h5p_results`
--
ALTER TABLE `h5p_results`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `h5p_tags`
--
ALTER TABLE `h5p_tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `h5p_tmpfiles`
--
ALTER TABLE `h5p_tmpfiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
