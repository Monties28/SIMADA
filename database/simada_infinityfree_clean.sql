-- SIMADA Backend Database untuk InfinityFree
-- Import file ini melalui phpMyAdmin pada database yang sudah Anda buat.
-- Akun awal: username admin, password admin123. Ganti password setelah login.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `attendance_records`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `document_types`;
DROP TABLE IF EXISTS `document_categories`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `nip` char(18) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `jabatan` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `nip` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(150) NOT NULL,
  `slug_kategori` varchar(180) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_categories_slug` (`slug_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int(10) UNSIGNED NOT NULL,
  `nama_jenis` varchar(180) NOT NULL,
  `slug_jenis` varchar(220) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_types_category_slug` (`category_id`,`slug_jenis`),
  KEY `idx_document_types_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `stored_file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_extension` varchar(10) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `file_hash` char(64) DEFAULT NULL,
  `ocr_text` mediumtext DEFAULT NULL,
  `ocr_status` varchar(30) NOT NULL DEFAULT 'not_processed',
  `ocr_engine` varchar(80) DEFAULT NULL,
  `ocr_processed_at` datetime DEFAULT NULL,
  `kategori_dokumen` varchar(150) NOT NULL,
  `jenis_dokumen` varchar(180) NOT NULL,
  `jenis_dokumen_lainnya` varchar(255) DEFAULT NULL,
  `tanggal_dokumen` date NOT NULL,
  `periode_bulan` char(7) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `catatan_admin` varchar(255) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revision_count` int(11) DEFAULT 0,
  `last_revision_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_documents_user_id` (`user_id`),
  KEY `idx_documents_file_hash` (`file_hash`),
  KEY `idx_documents_ocr_status` (`ocr_status`),
  CONSTRAINT `fk_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `target_role` varchar(20) DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `message` text NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_notifications_role_read` (`target_role`,`is_read`),
  KEY `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance_records` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `employee_name` varchar(150) NOT NULL,
  `periode_bulan` char(7) NOT NULL,
  `hadir` int(11) NOT NULL DEFAULT 0,
  `izin` int(11) NOT NULL DEFAULT 0,
  `sakit` int(11) NOT NULL DEFAULT 0,
  `alpha` int(11) NOT NULL DEFAULT 0,
  `terlambat` int(11) NOT NULL DEFAULT 0,
  `cluster_label` varchar(60) DEFAULT NULL,
  `cluster_score` decimal(8,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attendance_period` (`periode_bulan`),
  KEY `idx_attendance_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `nip`, `password`, `name`, `jabatan`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', NULL, '$2y$10$tS1QZffx9F0mySA.PZ/wz.VhoXnevpEzqIA2jzpLAn.GXtaB9/xcu', 'Administrator', 'Admin Sistem', 'admin', 1, NOW());

INSERT INTO `document_categories` (`id`, `nama_kategori`, `slug_kategori`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'DOKUMEN TEKNIS PRODUKSI & BUDIDAYA', 'dokumen_teknis_produksi_budidaya', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(2, 'DOKUMEN ADMINISTRASI & KESEKRETARIATAN', 'dokumen_administrasi_kesekretariatan', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(3, 'DOKUMEN KEUANGAN & ANGGARAN', 'dokumen_keuangan_anggaran', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(4, 'DOKUMEN PENGADAAN BARANG/JASA', 'dokumen_pengadaan_barang_jasa', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(5, 'DOKUMEN MANAJEMEN KINERJA', 'dokumen_manajemen_kinerja', 1, 50, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(6, 'DOKUMEN KEPEGAWAIAN & SDM', 'dokumen_kepegawaian_sdm', 1, 60, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(7, 'DOKUMEN ASET & LOGISTIK', 'dokumen_aset_logistik', 1, 70, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(8, 'DOKUMEN PERENCANAAN & EVALUASI', 'dokumen_perencanaan_evaluasi', 1, 80, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(9, 'DOKUMEN PEMASARAN & DISTRIBUSI', 'dokumen_pemasaran_distribusi', 1, 90, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(10, 'DOKUMEN TEKNIS PERAWATAN FASILITAS', 'dokumen_teknis_perawatan_fasilitas', 1, 100, '2026-04-11 14:43:17', '2026-04-11 14:43:17');

INSERT INTO `document_types` (`id`, `category_id`, `nama_jenis`, `slug_jenis`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Laporan Produksi/Pemijahan Ikan & Udang', 'laporan_produksi_pemijahan_ikan_udang', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(2, 1, 'Laporan Pemeliharaan Larva & Benih', 'laporan_pemeliharaan_larva_benih', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(3, 1, 'Laporan Pendederan Benih', 'laporan_pendederan_benih', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(4, 1, 'Laporan Pembesaran Ikan & Udang', 'laporan_pembesaran_ikan_udang', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(5, 1, 'Laporan Panen Benih dan Ikan Konsumsi', 'laporan_panen_benih_dan_ikan_konsumsi', 1, 50, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(6, 1, 'Laporan Pengelolaan Kualitas Air', 'laporan_pengelolaan_kualitas_air', 1, 60, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(7, 1, 'Laporan Pengapuran dan Pemupukan Kolam', 'laporan_pengapuran_dan_pemupukan_kolam', 1, 70, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(8, 1, 'Laporan Pemberian Pakan, Vitamin, dan Obat', 'laporan_pemberian_pakan_vitamin_dan_obat', 1, 80, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(9, 2, 'Laporan Arsip Surat Masuk dan Keluar', 'laporan_arsip_surat_masuk_dan_keluar', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(10, 2, 'Laporan Pengetikan dan Penomoran Surat', 'laporan_pengetikan_dan_penomoran_surat', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(11, 2, 'Surat Perintah Tugas (SPT)', 'surat_perintah_tugas_spt', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(12, 2, 'Surat Pengantar Resmi', 'surat_pengantar_resmi', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(13, 2, 'Nota Dinas dan Memorandum', 'nota_dinas_dan_memorandum', 1, 50, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(14, 3, 'Laporan Realisasi Anggaran (SIRA)', 'laporan_realisasi_anggaran_sira', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(15, 3, 'Laporan Rekapitulasi Belanja', 'laporan_rekapitulasi_belanja', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(16, 3, 'Laporan Penjualan Hasil Produksi', 'laporan_penjualan_hasil_produksi', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(17, 3, 'Rekapitulasi Pengajuan SPM', 'rekapitulasi_pengajuan_spm', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(18, 3, 'Laporan Barang Pakai Habis (BPH)', 'laporan_barang_pakai_habis_bph', 1, 50, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(19, 4, 'Dokumen Tender dan Pengadaan', 'dokumen_tender_dan_pengadaan', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(20, 4, 'Surat Pesanan Pembelian', 'surat_pesanan_pembelian', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(21, 4, 'Surat Perubahan RUP', 'surat_perubahan_rup', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(22, 4, 'Berita Acara Pemeriksaan Barang', 'berita_acara_pemeriksaan_barang', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(23, 4, 'Kontrak dan Perjanjian Kerja', 'kontrak_dan_perjanjian_kerja', 1, 50, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(24, 5, 'Laporan E-Kinerja Pegawai', 'laporan_e_kinerja_pegawai', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(25, 5, 'Laporan E-SAKIP Triwulanan', 'laporan_e_sakip_triwulanan', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(26, 5, 'Laporan Survei Kepuasan Masyarakat', 'laporan_survei_kepuasan_masyarakat', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(27, 5, 'Laporan Evaluasi Kinerja Instansi', 'laporan_evaluasi_kinerja_instansi', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(28, 6, 'Daftar Induk dan Database Pegawai', 'daftar_induk_dan_database_pegawai', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(29, 6, 'Laporan Rekapitulasi Absensi', 'laporan_rekapitulasi_absensi', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(30, 6, 'Laporan Perjalanan Dinas (PERDIN)', 'laporan_perjalanan_dinas_perdin', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(31, 6, 'Berkas Kepegawaian dan Penilaian Kinerja', 'berkas_kepegawaian_dan_penilaian_kinerja', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(32, 7, 'Laporan Inventarisasi Barang Milik Daerah (BMD)', 'laporan_inventarisasi_barang_milik_daerah_bmd', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(33, 7, 'Laporan Pemeliharaan Sarana dan Prasarana', 'laporan_pemeliharaan_sarana_dan_prasarana', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(34, 7, 'Laporan Penggunaan Barang Inventaris', 'laporan_penggunaan_barang_inventaris', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(35, 7, 'Berita Acara Serah Terima Barang', 'berita_acara_serah_terima_barang', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(36, 8, 'Laporan Rencana Strategis (RENSTRA)', 'laporan_rencana_strategis_renstra', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(37, 8, 'Laporan Rencana Kerja Tahunan', 'laporan_rencana_kerja_tahunan', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(38, 8, 'Laporan Monitoring dan Evaluasi Program', 'laporan_monitoring_dan_evaluasi_program', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(39, 8, 'Laporan Capaian Kinerja Triwulanan/Tahunan', 'laporan_capaian_kinerja_triwulanan_tahunan', 1, 40, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(40, 9, 'Laporan Promosi dan Pemasaran', 'laporan_promosi_dan_pemasaran', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(41, 9, 'Laporan Penjualan dan Distribusi Benih', 'laporan_penjualan_dan_distribusi_benih', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(42, 9, 'Dokumentasi Kegiatan Pameran/Promosi', 'dokumentasi_kegiatan_pameran_promosi', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(43, 10, 'Laporan Pemeliharaan Listrik dan Genset', 'laporan_pemeliharaan_listrik_dan_genset', 1, 10, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(44, 10, 'Laporan Perawatan Alat dan Mesin Budidaya', 'laporan_perawatan_alat_dan_mesin_budidaya', 1, 20, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(45, 10, 'Laporan Kebersihan dan Perawatan Gedung', 'laporan_kebersihan_dan_perawatan_gedung', 1, 30, '2026-04-11 14:43:17', '2026-04-11 14:43:17'),
(46, 2, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(47, 7, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(48, 6, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(49, 3, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(50, 5, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(51, 9, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(52, 4, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(53, 8, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(54, 10, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06'),
(55, 1, 'Lainnya', 'lainnya', 1, 999, '2026-05-03 20:26:06', '2026-05-03 20:26:06');

ALTER TABLE `users` AUTO_INCREMENT = 2;
ALTER TABLE `document_categories` AUTO_INCREMENT = 11;
ALTER TABLE `document_types` AUTO_INCREMENT = 56;
ALTER TABLE `documents` AUTO_INCREMENT = 1;
ALTER TABLE `notifications` AUTO_INCREMENT = 1;
ALTER TABLE `attendance_records` AUTO_INCREMENT = 1;
