-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 25 Sep 2025 pada 12.07
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
-- Struktur dari tabel `wood_engineered`
--

CREATE TABLE `wood_engineered` (
  `wood_engineered_id` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `wood_engineered_code` varchar(15) NOT NULL,
  `wood_engineered_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_engineered_lpb`
--

CREATE TABLE `wood_engineered_lpb` (
  `wood_engineered_lpb_id` int(11) NOT NULL,
  `wood_engineered_lpb_number` varchar(255) NOT NULL,
  `wood_engineered_lpb_date` date NOT NULL,
  `supplier_code` varchar(255) NOT NULL,
  `user_fullname` varchar(255) NOT NULL,
  `wood_engineered_lpb_description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_engineered_lpb_detail`
--

CREATE TABLE `wood_engineered_lpb_detail` (
  `wood_engineered_lpb_detail_id` int(11) NOT NULL,
  `wood_engineered_lpb_id` int(11) NOT NULL,
  `wood_engineered_code` varchar(255) NOT NULL,
  `wood_engineered_lpb_detail_qty` decimal(10,2) NOT NULL,
  `wood_engineered_lpb_detail_po_number` varchar(255) NOT NULL,
  `wood_engineered_lpb_description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_engineered_pbp`
--

CREATE TABLE `wood_engineered_pbp` (
  `wood_engineered_id` int(11) NOT NULL,
  `wood_engineered_number` varchar(255) NOT NULL,
  `wood_engineered_date` date NOT NULL,
  `wood_engineered_time` time NOT NULL,
  `wood_engineered_code` varchar(255) NOT NULL,
  `wood_engineered_qty` decimal(10,2) NOT NULL,
  `so_number` varchar(255) NOT NULL,
  `product_code` varchar(255) NOT NULL,
  `employee_nik` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_engineered_return`
--

CREATE TABLE `wood_engineered_return` (
  `wood_engineered_return_id` int(11) NOT NULL,
  `wood_engineered_return_number` varchar(30) NOT NULL,
  `wood_engineered_pbp_number` varchar(30) NOT NULL,
  `so_number` varchar(50) NOT NULL,
  `product_code` varchar(20) NOT NULL,
  `wood_engineered_return_date` date NOT NULL,
  `wood_engineered_return_time` time NOT NULL,
  `wood_engineered_code` varchar(30) NOT NULL,
  `employee_nik` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_pallet`
--

CREATE TABLE `wood_pallet` (
  `wood_pallet_id` int(11) NOT NULL,
  `wood_pallet_code` varchar(255) DEFAULT NULL COMMENT 'Kode Pallet',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `wood_pallet`
--

INSERT INTO `wood_pallet` (`wood_pallet_id`, `wood_pallet_code`, `created_at`) VALUES
(1, '2509-0001', '2025-09-22 06:41:16'),
(2, '2509-0002', '2025-09-22 06:41:16'),
(3, '2509-0003', '2025-09-22 06:41:16'),
(4, '2509-0004', '2025-09-22 06:41:16'),
(5, '2509-0005', '2025-09-22 06:41:16'),
(6, '2509-0006', '2025-09-22 03:52:07'),
(7, '2509-0007', '2025-09-22 03:52:07'),
(8, '2509-0008', '2025-09-22 03:52:07'),
(9, '2509-0009', '2025-09-22 03:52:07'),
(10, '2509-0010', '2025-09-22 03:52:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_solid`
--

CREATE TABLE `wood_solid` (
  `wood_solid_id` int(11) NOT NULL,
  `wood_solid_barcode` varchar(255) DEFAULT NULL,
  `wood_solid_status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `wood_solid`
--

INSERT INTO `wood_solid` (`wood_solid_id`, `wood_solid_barcode`, `wood_solid_status`, `created_at`) VALUES
(1, '092523-0001', NULL, '2025-09-23 09:57:39'),
(2, '092523-0002', NULL, '2025-09-23 09:57:39'),
(3, '092523-0003', NULL, '2025-09-23 09:57:39'),
(4, '092523-0004', NULL, '2025-09-23 09:57:39'),
(5, '092523-0005', NULL, '2025-09-23 09:57:39'),
(6, '092523-0006', NULL, '2025-09-23 09:57:39'),
(7, '092523-0007', NULL, '2025-09-23 09:57:39'),
(8, '092523-0008', NULL, '2025-09-23 09:57:39'),
(9, '092523-0009', NULL, '2025-09-23 09:57:39'),
(10, '092523-0010', NULL, '2025-09-23 09:57:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_solid_dtg`
--

CREATE TABLE `wood_solid_dtg` (
  `wood_solid_dtg_id` int(11) NOT NULL,
  `wood_solid_group` varchar(20) NOT NULL,
  `wood_solid_dtg_date` date NOT NULL,
  `wood_solid_dtg_time` time NOT NULL,
  `wood_solid_barcode` varchar(30) NOT NULL,
  `operator_fullname` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `wood_solid_dtg`
--

INSERT INTO `wood_solid_dtg` (`wood_solid_dtg_id`, `wood_solid_group`, `wood_solid_dtg_date`, `wood_solid_dtg_time`, `wood_solid_barcode`, `operator_fullname`) VALUES
(1, '2211-0176', '2025-09-23', '15:56:17', '112224-0151', 'Tajuddin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_solid_grade`
--

CREATE TABLE `wood_solid_grade` (
  `wood_solid_grade_id` int(11) NOT NULL,
  `wood_solid_group` varchar(10) NOT NULL,
  `wood_solid_grade_date` date NOT NULL,
  `wood_solid_grade_time` time NOT NULL,
  `wood_name` varchar(30) NOT NULL,
  `wood_solid_barcode` varchar(20) NOT NULL,
  `wood_solid_height` varchar(50) NOT NULL,
  `wood_solid_width` varchar(50) NOT NULL,
  `wood_solid_length` varchar(50) NOT NULL,
  `operator_fullname` varchar(20) NOT NULL,
  `location_name` varchar(50) NOT NULL,
  `wood_solid_grade_status` int(1) DEFAULT NULL,
  `wood_solid_grade_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `wood_solid_grade`
--

INSERT INTO `wood_solid_grade` (`wood_solid_grade_id`, `wood_solid_group`, `wood_solid_grade_date`, `wood_solid_grade_time`, `wood_name`, `wood_solid_barcode`, `wood_solid_height`, `wood_solid_width`, `wood_solid_length`, `operator_fullname`, `location_name`, `wood_solid_grade_status`, `wood_solid_grade_description`, `created_at`, `updated_at`) VALUES
(1, '2211-0176', '2025-09-23', '08:25:41', 'Jati', '112224-0151', '4', '20', '200', 'Muklis', 'Omega Mas', 1, NULL, '2025-09-23 01:28:20', '2025-09-25 05:14:46'),
(2, '2110-0031', '2025-09-24', '10:30:00', 'Mahoni', '1121-009441', '4', '19', '200', 'Muklis', 'Omega Mas', 1, NULL, '2025-09-23 03:30:39', '2025-09-25 06:13:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_solid_lpb`
--

CREATE TABLE `wood_solid_lpb` (
  `wood_solid_lpb_id` int(11) NOT NULL,
  `wood_solid_lpb_number` varchar(255) NOT NULL,
  `wood_solid_lpb_po` varchar(255) NOT NULL,
  `wood_solid_lpb_date_invoice` date NOT NULL,
  `wood_name` varchar(255) NOT NULL,
  `supplier_name` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `wood_solid_lpb`
--

INSERT INTO `wood_solid_lpb` (`wood_solid_lpb_id`, `wood_solid_lpb_number`, `wood_solid_lpb_po`, `wood_solid_lpb_date_invoice`, `wood_name`, `supplier_name`, `created_at`, `updated_at`) VALUES
(1, 'LPB202509-0001', 'PO#20250925-0001', '2025-09-25', 'Mahoni', 'Amori-Bali', '2025-09-25 05:05:56', '2025-09-25 05:05:56'),
(2, 'LPB202509-0002', 'PO#20250925-0002', '2025-09-25', 'Jati', 'Amori-Bali', '2025-09-25 05:08:37', '2025-09-25 05:08:37'),
(3, 'LPB202509-0003', 'PO#20250925-0001', '2025-09-25', 'Mahoni', 'Amori-Bali', '2025-09-25 05:09:20', '2025-09-25 05:09:20'),
(4, 'LPB202509-0004', 'PO#20250925-0002', '2025-09-25', 'Jati', 'Amori-Bali', '2025-09-25 05:14:46', '2025-09-25 05:14:46'),
(5, 'LPB202509-0006', 'PO#20250925-0002', '2025-09-25', 'Mahoni', 'Amartha Indotama CV (IDR)', '2025-09-25 06:13:16', '2025-09-25 06:13:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_solid_lpb_detail`
--

CREATE TABLE `wood_solid_lpb_detail` (
  `wood_solid_lpb_detail_id` int(11) NOT NULL,
  `wood_solid_lpb_id` int(11) NOT NULL,
  `wood_solid_group` varchar(255) NOT NULL,
  `wood_name` varchar(255) NOT NULL,
  `wood_solid_barcode` varchar(255) NOT NULL,
  `wood_solid_height` varchar(50) NOT NULL,
  `wood_solid_width` varchar(50) NOT NULL,
  `wood_solid_length` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `wood_solid_lpb_detail`
--

INSERT INTO `wood_solid_lpb_detail` (`wood_solid_lpb_detail_id`, `wood_solid_lpb_id`, `wood_solid_group`, `wood_name`, `wood_solid_barcode`, `wood_solid_height`, `wood_solid_width`, `wood_solid_length`, `created_at`, `updated_at`) VALUES
(1, 4, '2211-0176', 'Jati', '112224-0151', '4', '20', '200', '2025-09-25 05:14:46', '2025-09-25 05:14:46'),
(2, 5, '2110-0031', 'Mahoni', '1121-009441', '4', '19', '200', '2025-09-25 06:13:16', '2025-09-25 06:13:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_solid_pbp`
--

CREATE TABLE `wood_solid_pbp` (
  `wood_solid_pbp_id` int(11) NOT NULL,
  `wood_solid_pbp_number` varchar(255) NOT NULL,
  `so_number` varchar(255) NOT NULL,
  `product_code` varchar(255) NOT NULL,
  `wood_solid_pbp_date` date NOT NULL,
  `wood_solid_pbp_time` time NOT NULL,
  `wood_solid_barcode` varchar(255) NOT NULL,
  `employee_nik` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `wood_solid_return`
--

CREATE TABLE `wood_solid_return` (
  `wood_solid_return_id` int(11) NOT NULL,
  `wood_solid_return_number` varchar(30) NOT NULL,
  `wood_solid_pbp_number` varchar(30) NOT NULL,
  `so_number` varchar(50) NOT NULL,
  `product_code` varchar(20) NOT NULL,
  `wood_solid_return_date` date NOT NULL,
  `wood_solid_return_time` time NOT NULL,
  `wood_solid_barcode` varchar(20) NOT NULL,
  `employee_nik` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `wood_engineered`
--
ALTER TABLE `wood_engineered`
  ADD PRIMARY KEY (`wood_engineered_id`);

--
-- Indeks untuk tabel `wood_engineered_lpb`
--
ALTER TABLE `wood_engineered_lpb`
  ADD PRIMARY KEY (`wood_engineered_lpb_id`);

--
-- Indeks untuk tabel `wood_engineered_lpb_detail`
--
ALTER TABLE `wood_engineered_lpb_detail`
  ADD PRIMARY KEY (`wood_engineered_lpb_detail_id`),
  ADD KEY `wood_engineered_lpb_id` (`wood_engineered_lpb_id`);

--
-- Indeks untuk tabel `wood_engineered_pbp`
--
ALTER TABLE `wood_engineered_pbp`
  ADD PRIMARY KEY (`wood_engineered_id`);

--
-- Indeks untuk tabel `wood_engineered_return`
--
ALTER TABLE `wood_engineered_return`
  ADD PRIMARY KEY (`wood_engineered_return_id`);

--
-- Indeks untuk tabel `wood_pallet`
--
ALTER TABLE `wood_pallet`
  ADD PRIMARY KEY (`wood_pallet_id`);

--
-- Indeks untuk tabel `wood_solid`
--
ALTER TABLE `wood_solid`
  ADD PRIMARY KEY (`wood_solid_id`);

--
-- Indeks untuk tabel `wood_solid_dtg`
--
ALTER TABLE `wood_solid_dtg`
  ADD PRIMARY KEY (`wood_solid_dtg_id`);

--
-- Indeks untuk tabel `wood_solid_grade`
--
ALTER TABLE `wood_solid_grade`
  ADD PRIMARY KEY (`wood_solid_grade_id`);

--
-- Indeks untuk tabel `wood_solid_lpb`
--
ALTER TABLE `wood_solid_lpb`
  ADD PRIMARY KEY (`wood_solid_lpb_id`);

--
-- Indeks untuk tabel `wood_solid_lpb_detail`
--
ALTER TABLE `wood_solid_lpb_detail`
  ADD PRIMARY KEY (`wood_solid_lpb_detail_id`),
  ADD KEY `wood_solid_lpb_id` (`wood_solid_lpb_id`);

--
-- Indeks untuk tabel `wood_solid_pbp`
--
ALTER TABLE `wood_solid_pbp`
  ADD PRIMARY KEY (`wood_solid_pbp_id`);

--
-- Indeks untuk tabel `wood_solid_return`
--
ALTER TABLE `wood_solid_return`
  ADD PRIMARY KEY (`wood_solid_return_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `wood_engineered`
--
ALTER TABLE `wood_engineered`
  MODIFY `wood_engineered_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `wood_engineered_lpb`
--
ALTER TABLE `wood_engineered_lpb`
  MODIFY `wood_engineered_lpb_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `wood_engineered_lpb_detail`
--
ALTER TABLE `wood_engineered_lpb_detail`
  MODIFY `wood_engineered_lpb_detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `wood_engineered_pbp`
--
ALTER TABLE `wood_engineered_pbp`
  MODIFY `wood_engineered_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `wood_engineered_return`
--
ALTER TABLE `wood_engineered_return`
  MODIFY `wood_engineered_return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `wood_pallet`
--
ALTER TABLE `wood_pallet`
  MODIFY `wood_pallet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `wood_solid`
--
ALTER TABLE `wood_solid`
  MODIFY `wood_solid_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `wood_solid_dtg`
--
ALTER TABLE `wood_solid_dtg`
  MODIFY `wood_solid_dtg_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `wood_solid_grade`
--
ALTER TABLE `wood_solid_grade`
  MODIFY `wood_solid_grade_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `wood_solid_lpb`
--
ALTER TABLE `wood_solid_lpb`
  MODIFY `wood_solid_lpb_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `wood_solid_lpb_detail`
--
ALTER TABLE `wood_solid_lpb_detail`
  MODIFY `wood_solid_lpb_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `wood_solid_pbp`
--
ALTER TABLE `wood_solid_pbp`
  MODIFY `wood_solid_pbp_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `wood_solid_return`
--
ALTER TABLE `wood_solid_return`
  MODIFY `wood_solid_return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `wood_engineered_lpb_detail`
--
ALTER TABLE `wood_engineered_lpb_detail`
  ADD CONSTRAINT `wood_engineered_lpb_detail_ibfk_1` FOREIGN KEY (`wood_engineered_lpb_id`) REFERENCES `wood_engineered_lpb` (`wood_engineered_lpb_id`);

--
-- Ketidakleluasaan untuk tabel `wood_solid_lpb_detail`
--
ALTER TABLE `wood_solid_lpb_detail`
  ADD CONSTRAINT `wood_solid_lpb_detail_ibfk_1` FOREIGN KEY (`wood_solid_lpb_id`) REFERENCES `wood_solid_lpb` (`wood_solid_lpb_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
