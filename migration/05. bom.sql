-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 26 Sep 2025 pada 16.01
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
-- Struktur dari tabel `bom`
--

CREATE TABLE `bom` (
  `bom_id` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `product_reference` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_img` varchar(100) NOT NULL,
  `bom_desc` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bom_component`
--

CREATE TABLE `bom_component` (
  `bom_component_id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `bom_component_number` varchar(30) NOT NULL,
  `bom_component_name` varchar(100) NOT NULL,
  `bom_component_bahan` varchar(15) NOT NULL,
  `bom_component_kayu` varchar(20) NOT NULL,
  `bom_component_kayu_detail` varchar(50) NOT NULL,
  `bom_component_jenis` varchar(1) NOT NULL,
  `bom_component_qty` int(11) NOT NULL,
  `bom_component_panjang` int(11) NOT NULL,
  `bom_component_lebar` int(11) NOT NULL,
  `bom_component_tebal` int(11) NOT NULL,
  `bom_component_description` varchar(100) NOT NULL,
  `bom_component_group` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `bom_component`
--

INSERT INTO `bom_component` (`bom_component_id`, `bom_id`, `bom_component_number`, `bom_component_name`, `bom_component_bahan`, `bom_component_kayu`, `bom_component_kayu_detail`, `bom_component_jenis`, `bom_component_qty`, `bom_component_panjang`, `bom_component_lebar`, `bom_component_tebal`, `bom_component_description`, `bom_component_group`) VALUES
(1, 0, '20345-0001', 'CAB Body', 'Body', 'Body', 'Body', '', 0, 0, 0, 0, '', 'Body'),
(2, 0, '20345-0002', 'Corner', 'Kayu', 'Kayu', 'Mindi', 'D', 8, 164, 72, 20, '', 'Body'),
(3, 0, '20345-0003', 'Dudukan Sink knn kr', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 400, 211, 20, 'Cowakan untuk sink', 'Body'),
(4, 0, '20345-0004', 'Kaki mk blk smp kr', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 880, 50, 50, '', 'Body'),
(5, 0, '20345-0005', 'Kaki mk knn', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 880, 50, 25, '', 'Body'),
(6, 0, '20345-0006', 'Klit Palang Dudukan Sink knn kr', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 400, 30, 20, '', 'Body'),
(7, 0, '20345-0007', 'Klit Palang Panel smp kr', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 350, 20, 13, '', 'Body'),
(8, 0, '20345-0008', 'Klit Tiang Panel smp knn', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 880, 20, 20, '', 'Body'),
(9, 0, '20345-0009', 'Palang blk ats bwh', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 820, 50, 25, 'Pen 20mm 1 sisi', 'Body'),
(10, 0, '20345-0010', 'Palang mk ats bwh', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 840, 50, 25, 'Pen 20mm 2 sisi', 'Body'),
(11, 0, '20345-0011', 'Palang smp kr', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 390, 50, 25, '', 'Body'),
(12, 0, '20345-0012', 'Panel Tutup blk', 'Plywood', 'Plywood', 'Ply semi meranti', '1', 1, 820, 700, 9, '', 'Body'),
(13, 0, '20345-0013', 'Panel smp knn', 'Plywood', 'Plywood', 'Ply semi meranti', '1', 1, 880, 425, 18, '', 'Body'),
(14, 0, '20345-0014', 'Panel smp kr dlm', 'Plywood', 'Plywood', 'Ply semi meranti', '1', 1, 780, 380, 12, '', 'Body'),
(15, 0, '20345-0015', 'Panel smp kr luar', 'Plywood', 'Plywood', 'Ply semi meranti 1 sisi mindi', '1', 1, 700, 370, 12, '', 'Body'),
(16, 0, '20345-0016', 'Support Palang Panel smp kr', 'Kayu', 'Kayu', 'Mindi', 'D', 3, 350, 40, 21, '', 'Body'),
(17, 0, '20345-0017', 'CAB Drawer 1', 'Drawer 1', 'Drawer 1', 'Drawer 1', '', 0, 0, 0, 0, '', 'Drawer 1'),
(18, 0, '20345-0018', 'Alas drawer', 'Plywood', 'Plywood', 'Ply semi meranti 1 sisi mindi', '1', 1, 768, 350, 12, '', 'Drawer 1'),
(19, 0, '20345-0019', 'Panel mk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 796, 224, 25, 'Cowakan handle bwh', 'Drawer 1'),
(20, 0, '20345-0020', 'Tutup blk smp drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 137, 77, 15, '', 'Drawer 1'),
(21, 0, '20345-0021', 'Tutup blk tgh drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 514, 77, 15, '', 'Drawer 1'),
(22, 0, '20345-0022', 'Tutup mk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 758, 77, 15, '', 'Drawer 1'),
(23, 0, '20345-0023', 'Tutup smp dlm', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 175, 77, 15, '', 'Drawer 1'),
(24, 0, '20345-0024', 'Tutup smp luar drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 370, 77, 15, '', 'Drawer 1'),
(25, 0, '20345-0025', 'CAB Drawer 2', 'Drawer 2', 'Drawer 2', 'Drawer 2', '', 0, 0, 0, 0, '', 'Drawer 2'),
(26, 0, '20345-0026', 'Alas drawer', 'Plywood', 'Plywood', 'Ply semi meranti 1 sisi mindi', '1', 1, 768, 350, 12, '', 'Drawer 2'),
(27, 0, '20345-0027', 'Panel mk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 796, 224, 25, 'Cowakan handle ats bwh', 'Drawer 2'),
(28, 0, '20345-0028', 'Support drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 340, 25, 12, '', 'Drawer 2'),
(29, 0, '20345-0029', 'Tutup blk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 758, 174, 15, '', 'Drawer 2'),
(30, 0, '20345-0030', 'Tutup mk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 758, 174, 15, '', 'Drawer 2'),
(31, 0, '20345-0031', 'Tutup smp drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 370, 174, 15, '', 'Drawer 2'),
(32, 0, '20345-0032', 'CAB Drawer 3', 'Drawer 3', 'Drawer 3', 'Drawer 3', '', 0, 0, 0, 0, '', 'Drawer 3'),
(33, 0, '20345-0033', 'Alas drawer', 'Plywood', 'Plywood', 'Ply semi meranti 1 sisi mindi', '1', 1, 768, 350, 12, '', 'Drawer 3'),
(34, 0, '20345-0034', 'Panel mk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 796, 224, 25, 'Cowakan handle ats', 'Drawer 3'),
(35, 0, '20345-0035', 'Support drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 340, 25, 12, '', 'Drawer 3'),
(36, 0, '20345-0036', 'Tutup blk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 758, 174, 15, '', 'Drawer 3'),
(37, 0, '20345-0037', 'Tutup mk drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 1, 758, 174, 15, '', 'Drawer 3'),
(38, 0, '20345-0038', 'Tutup smp drawer', 'Kayu', 'Kayu', 'Mindi', 'D', 2, 370, 174, 15, '', 'Drawer 3');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bom_material`
--

CREATE TABLE `bom_material` (
  `bom_material_id` int(11) NOT NULL,
  `hardware_code` varchar(20) NOT NULL,
  `bom_material_qty` int(11) NOT NULL,
  `bom_material_desc` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `bom`
--
ALTER TABLE `bom`
  ADD PRIMARY KEY (`bom_id`);

--
-- Indeks untuk tabel `bom_component`
--
ALTER TABLE `bom_component`
  ADD PRIMARY KEY (`bom_component_id`);

--
-- Indeks untuk tabel `bom_material`
--
ALTER TABLE `bom_material`
  ADD PRIMARY KEY (`bom_material_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `bom`
--
ALTER TABLE `bom`
  MODIFY `bom_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `bom_component`
--
ALTER TABLE `bom_component`
  MODIFY `bom_component_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT untuk tabel `bom_material`
--
ALTER TABLE `bom_material`
  MODIFY `bom_material_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
