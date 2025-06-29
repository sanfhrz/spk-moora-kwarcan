-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 28 Jun 2025 pada 15.21
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `spk_kwarcan`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `nama_lengkap`, `email`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@spkkwarcan.com', '2025-06-28 12:30:44', '2025-06-28 12:30:44');

-- --------------------------------------------------------

--
-- Struktur dari tabel `hasil_moora`
--

CREATE TABLE `hasil_moora` (
  `id` int(11) NOT NULL,
  `kwarcan_id` int(11) NOT NULL,
  `nilai_optimasi` decimal(10,6) NOT NULL,
  `ranking` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kriteria`
--

CREATE TABLE `kriteria` (
  `id` int(11) NOT NULL,
  `kode_kriteria` varchar(10) NOT NULL,
  `nama_kriteria` varchar(100) NOT NULL,
  `bobot` decimal(5,3) NOT NULL,
  `jenis` enum('benefit','cost') NOT NULL DEFAULT 'benefit',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kriteria`
--

INSERT INTO `kriteria` (`id`, `kode_kriteria`, `nama_kriteria`, `bobot`, `jenis`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'C1', 'Kepemimpinan', 0.250, 'benefit', 'Kemampuan memimpin dan mengarahkan organisasi', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(2, 'C2', 'Kursus Pembinaan Tingkat Lanjut', 0.200, 'benefit', 'Anggota aktif yang telah mengikuti kursus pembinaan pembina tingkat lanjut', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(3, 'C3', 'Visi dan Misi', 0.150, 'benefit', 'Memiliki visi dan misi yang jelas untuk kemajuan organisasi', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(4, 'C4', 'Pengalaman Organisasi', 0.200, 'benefit', 'Pengalaman dalam mengelola dan mengembangkan organisasi', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(5, 'C5', 'Loyalitas Gerakan Pramuka', 0.200, 'benefit', 'Loyalitas dan dedikasi terhadap gerakan Pramuka', '2025-06-28 12:30:45', '2025-06-28 12:30:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kwarcan`
--

CREATE TABLE `kwarcan` (
  `id` int(11) NOT NULL,
  `kode_kwarcan` varchar(10) NOT NULL,
  `nama_kwarcan` varchar(100) NOT NULL,
  `daerah` varchar(100) NOT NULL,
  `kontak` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kwarcan`
--

INSERT INTO `kwarcan` (`id`, `kode_kwarcan`, `nama_kwarcan`, `daerah`, `kontak`, `keterangan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'A1', 'Bujur Tampubolon, S.Pd., MM', 'Bandar Pasir Mandoge', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(2, 'A2', 'Sudar Maji, S.Pd', 'Bandar Pulau', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(3, 'A3', 'Ahmad Gunawan, S.Pd', 'Aek Songsongan', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(4, 'A4', 'Nong Hilman Sinaga, S.Pd.I', 'Aek Kuasan', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(5, 'A5', 'Ilyan, S.Pd', 'Aek Ledong', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(6, 'A6', 'Agustina, M.Si', 'Pulau Rakyat', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(7, 'A7', 'Hendra Sipayung', 'Tinggi Raja', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(8, 'A8', 'AKP. Defta Sitepu, S. H', 'Buntu Pane', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(9, 'A9', 'Sueb', 'Setia Janji', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(10, 'A10', 'Marsidi', 'Meranti', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(11, 'A11', 'Budianto, S.Pd', 'Rawang Panca Arga', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(12, 'A12', 'Yeni Mariana Manurung, S.Pd', 'Air Joman', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(13, 'A13', 'Muhammad Yakub, S.Pd., M.Si', 'Silau Laut', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(14, 'A14', 'Dedi Irawan', 'Sei Kepayang', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(15, 'A15', 'Yusup Sitorus', 'Sei Kepayang Barat', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(16, 'A16', 'Agus Sitirjo', 'Sei Kepayang Timur', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(17, 'A17', 'Suparman, S.Pd', 'Tanjung Balai', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(18, 'A18', 'Daya Ikhsan Manurung', 'Simpang Empat', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(19, 'A19', 'Ery Asmuni Margolang', 'Teluk Dalam', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(20, 'A20', 'Selamat Raharjo', 'Air Batu', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(21, 'A21', 'Susanto, S.Pd', 'Sei Dadap', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(22, 'A22', 'Sumisno, S.pd', 'Rahuning', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(23, 'A23', 'Agus Ramanda', 'Kisaran Timur', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(24, 'A24', 'Sori Tua Muhammad Faisal', 'Kisaran Barat', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(25, 'A25', 'Roaidah', 'Pulau Bandring', '-', 'Calon Ketua Kwarcab Kabupaten Asahan', 'aktif', '2025-06-28 12:30:45', '2025-06-28 12:30:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian`
--

CREATE TABLE `penilaian` (
  `id` int(11) NOT NULL,
  `kwarcan_id` int(11) NOT NULL,
  `kriteria_id` int(11) NOT NULL,
  `nilai` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penilaian`
--

INSERT INTO `penilaian` (`id`, `kwarcan_id`, `kriteria_id`, `nilai`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(2, 1, 2, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(3, 1, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(4, 1, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(5, 1, 5, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(6, 2, 1, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(7, 2, 2, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(8, 2, 3, 50.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(9, 2, 4, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(10, 2, 5, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(11, 3, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(12, 3, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(13, 3, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(14, 3, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(15, 3, 5, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(16, 4, 1, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(17, 4, 2, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(18, 4, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(19, 4, 4, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(20, 4, 5, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(21, 5, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(22, 5, 2, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(23, 5, 3, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(24, 5, 4, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(25, 5, 5, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(26, 6, 1, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(27, 6, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(28, 6, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(29, 6, 4, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(30, 6, 5, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(31, 7, 1, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(32, 7, 2, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(33, 7, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(34, 7, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(35, 7, 5, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(36, 8, 1, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(37, 8, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(38, 8, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(39, 8, 4, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(40, 8, 5, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(41, 9, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(42, 9, 2, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(43, 9, 3, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(44, 9, 4, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(45, 9, 5, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(46, 10, 1, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(47, 10, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(48, 10, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(49, 10, 4, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(50, 10, 5, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(51, 11, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(52, 11, 2, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(53, 11, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(54, 11, 4, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(55, 11, 5, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(56, 12, 1, 50.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(57, 12, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(58, 12, 3, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(59, 12, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(60, 12, 5, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(61, 13, 1, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(62, 13, 2, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(63, 13, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(64, 13, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(65, 13, 5, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(66, 14, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(67, 14, 2, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(68, 14, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(69, 14, 4, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(70, 14, 5, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(71, 15, 1, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(72, 15, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(73, 15, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(74, 15, 4, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(75, 15, 5, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(76, 16, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(77, 16, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(78, 16, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(79, 16, 4, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(80, 16, 5, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(81, 17, 1, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(82, 17, 2, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(83, 17, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(84, 17, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(85, 17, 5, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(86, 18, 1, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(87, 18, 2, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(88, 18, 3, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(89, 18, 4, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(90, 18, 5, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(91, 19, 1, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(92, 19, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(93, 19, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(94, 19, 4, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(95, 19, 5, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(96, 20, 1, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(97, 20, 2, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(98, 20, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(99, 20, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(100, 20, 5, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(101, 21, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(102, 21, 2, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(103, 21, 3, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(104, 21, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(105, 21, 5, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(106, 22, 1, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(107, 22, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(108, 22, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(109, 22, 4, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(110, 22, 5, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(111, 23, 1, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(112, 23, 2, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(113, 23, 3, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(114, 23, 4, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(115, 23, 5, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(116, 24, 1, 40.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(117, 24, 2, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(118, 24, 3, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(119, 24, 4, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(120, 24, 5, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(121, 25, 1, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(122, 25, 2, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(123, 25, 3, 30.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(124, 25, 4, 20.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45'),
(125, 25, 5, 10.00, '2025-06-28 12:30:45', '2025-06-28 12:30:45');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_penilaian_lengkap`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_penilaian_lengkap` (
`kwarcan_id` int(11)
,`kode_kwarcan` varchar(10)
,`nama_kwarcan` varchar(100)
,`daerah` varchar(100)
,`kriteria_id` int(11)
,`kode_kriteria` varchar(10)
,`nama_kriteria` varchar(100)
,`bobot` decimal(5,3)
,`nilai` decimal(5,2)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_ranking_kwarcan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_ranking_kwarcan` (
`id` int(11)
,`kode_kwarcan` varchar(10)
,`nama_kwarcan` varchar(100)
,`daerah` varchar(100)
,`rata_nilai` decimal(9,6)
,`jumlah_penilaian` bigint(21)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `view_penilaian_lengkap`
--
DROP TABLE IF EXISTS `view_penilaian_lengkap`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_penilaian_lengkap`  AS SELECT `k`.`id` AS `kwarcan_id`, `k`.`kode_kwarcan` AS `kode_kwarcan`, `k`.`nama_kwarcan` AS `nama_kwarcan`, `k`.`daerah` AS `daerah`, `kr`.`id` AS `kriteria_id`, `kr`.`kode_kriteria` AS `kode_kriteria`, `kr`.`nama_kriteria` AS `nama_kriteria`, `kr`.`bobot` AS `bobot`, `p`.`nilai` AS `nilai` FROM ((`kwarcan` `k` join `kriteria` `kr`) left join `penilaian` `p` on(`k`.`id` = `p`.`kwarcan_id` and `kr`.`id` = `p`.`kriteria_id`)) ORDER BY `k`.`kode_kwarcan` ASC, `kr`.`kode_kriteria` ASC ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_ranking_kwarcan`
--
DROP TABLE IF EXISTS `view_ranking_kwarcan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_ranking_kwarcan`  AS SELECT `k`.`id` AS `id`, `k`.`kode_kwarcan` AS `kode_kwarcan`, `k`.`nama_kwarcan` AS `nama_kwarcan`, `k`.`daerah` AS `daerah`, avg(`p`.`nilai`) AS `rata_nilai`, count(`p`.`nilai`) AS `jumlah_penilaian` FROM (`kwarcan` `k` left join `penilaian` `p` on(`k`.`id` = `p`.`kwarcan_id`)) GROUP BY `k`.`id` ORDER BY avg(`p`.`nilai`) DESC ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `hasil_moora`
--
ALTER TABLE `hasil_moora`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kwarcan_id` (`kwarcan_id`);

--
-- Indeks untuk tabel `kriteria`
--
ALTER TABLE `kriteria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_kriteria` (`kode_kriteria`);

--
-- Indeks untuk tabel `kwarcan`
--
ALTER TABLE `kwarcan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_kwarcan` (`kode_kwarcan`);

--
-- Indeks untuk tabel `penilaian`
--
ALTER TABLE `penilaian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_penilaian` (`kwarcan_id`,`kriteria_id`),
  ADD KEY `kriteria_id` (`kriteria_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `hasil_moora`
--
ALTER TABLE `hasil_moora`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kriteria`
--
ALTER TABLE `kriteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `kwarcan`
--
ALTER TABLE `kwarcan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT untuk tabel `penilaian`
--
ALTER TABLE `penilaian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `hasil_moora`
--
ALTER TABLE `hasil_moora`
  ADD CONSTRAINT `hasil_moora_ibfk_1` FOREIGN KEY (`kwarcan_id`) REFERENCES `kwarcan` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian`
--
ALTER TABLE `penilaian`
  ADD CONSTRAINT `penilaian_ibfk_1` FOREIGN KEY (`kwarcan_id`) REFERENCES `kwarcan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penilaian_ibfk_2` FOREIGN KEY (`kriteria_id`) REFERENCES `kriteria` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
