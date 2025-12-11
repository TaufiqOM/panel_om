-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 25 Sep 2025 pada 12.00
-- Versi server: 10.4.19-MariaDB
-- Versi PHP: 7.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `siomas_odoo`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `hardware`
--

CREATE TABLE `hardware` (
  `hardware_id` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `hardware_code` varchar(15) NOT NULL,
  `hardware_name` varchar(50) NOT NULL,
  `hardware_uom` varchar(5) NOT NULL,
  `is_active` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur dari tabel `hardware_pbp`
--

CREATE TABLE `hardware_pbp` (
  `hardware_pbp_id` int(11) NOT NULL,
  `hardware_pbp_number` varchar(30) NOT NULL,
  `hardware_pbp_date` date NOT NULL,
  `hardware_pbp_time` time NOT NULL,
  `hardware_code` varchar(20) NOT NULL,
  `hardware_name` varchar(100) DEFAULT NULL,
  `hardware_uom` varchar(10) NOT NULL,
  `hardware_pbp_qty` float NOT NULL,
  `so_number` varchar(50) NOT NULL,
  `product_code` varchar(20) NOT NULL,
  `picking_id` int(11) DEFAULT NULL,
  `picking_name` varchar(100) DEFAULT NULL,
  `picking_name_after_validate` varchar(100) DEFAULT NULL,
  `so_id` int(11) DEFAULT NULL,
  `so_name` varchar(100) DEFAULT NULL,
  `employee_nik` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Indeks untuk tabel `hardware`
--
ALTER TABLE `hardware_pbp`
  ADD PRIMARY KEY (`hardware_pbp_id`),
  ADD KEY `idx_picking_id` (`picking_id`),
  ADD KEY `idx_so_id` (`so_id`);

--
-- Indeks untuk tabel `hardware_pbp`
--
ALTER TABLE `hardware_pbp`
  MODIFY `hardware_pbp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `hardware`
--
ALTER TABLE `hardware`
  MODIFY `hardware_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `hardware_pbp`
--
ALTER TABLE `hardware_pbp`
  MODIFY `hardware_pbp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
