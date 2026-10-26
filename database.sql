-- ============================================================
-- Database: Sistem Presensi Wajah CNN - Website Admin & Super Admin
-- Cara pakai: buka http://localhost/phpmyadmin -> tab "Import" -> pilih file ini -> Go
-- ============================================================

CREATE DATABASE IF NOT EXISTS `presensi_wajah`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `presensi_wajah`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_log`, `presensi`, `data_wajah`, `mahasiswa`,
  `perangkat`, `konfigurasi`, `login_attempts`, `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Akun pengelola. Hanya dua peran yang dapat login: admin dan superadmin.
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','superadmin') NOT NULL DEFAULT 'admin',
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pencatatan percobaan login gagal (pembatasan brute force)
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `waktu` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_waktu` (`ip`,`waktu`),
  KEY `idx_user_waktu` (`username`,`waktu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pengguna/{id_pengguna} pada SRS
CREATE TABLE `mahasiswa` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nim` VARCHAR(30) NOT NULL,
  `nama` VARCHAR(120) NOT NULL,
  `kelas` VARCHAR(50) NOT NULL DEFAULT '',
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `persetujuan_wajah` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nim` (`nim`),
  KEY `idx_kelas` (`kelas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- data_wajah/{id_pengguna} pada SRS (metadata berkas foto registrasi)
CREATE TABLE `data_wajah` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mahasiswa_id` INT UNSIGNED NOT NULL,
  `nama_file` VARCHAR(100) NOT NULL,
  `ukuran` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mhs` (`mahasiswa_id`),
  CONSTRAINT `fk_wajah_mhs` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- presensi/{tanggal}/{sesi}/{id_pengguna} pada SRS: satu catatan per mahasiswa per sesi
CREATE TABLE `presensi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mahasiswa_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `sesi` VARCHAR(50) NOT NULL DEFAULT 'Sesi 1',
  `waktu` DATETIME NOT NULL,
  `confidence` DECIMAL(5,4) DEFAULT NULL,
  `status` ENUM('hadir','manual') NOT NULL DEFAULT 'hadir',
  `device_id` VARCHAR(50) DEFAULT NULL,
  `model_version` VARCHAR(50) DEFAULT NULL,
  `transaction_id` CHAR(36) NOT NULL,
  `keterangan` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transaksi` (`transaction_id`),
  UNIQUE KEY `uk_mhs_sesi` (`mahasiswa_id`,`tanggal`,`sesi`),
  KEY `idx_tanggal` (`tanggal`),
  CONSTRAINT `fk_presensi_mhs` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- perangkat/{device_id} pada SRS. Token disimpan sebagai hash SHA-256.
CREATE TABLE `perangkat` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` VARCHAR(50) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `lokasi` VARCHAR(150) NOT NULL DEFAULT '',
  `token_hash` CHAR(64) NOT NULL,
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `last_seen` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- konfigurasi/{key} pada SRS
CREATE TABLE `konfigurasi` (
  `kunci` VARCHAR(60) NOT NULL,
  `nilai` VARCHAR(255) NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- audit_log/{event_id} pada SRS
CREATE TABLE `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` INT UNSIGNED DEFAULT NULL,
  `actor_username` VARCHAR(50) DEFAULT NULL,
  `aksi` VARCHAR(80) NOT NULL,
  `target` VARCHAR(255) DEFAULT NULL,
  `hasil` VARCHAR(20) NOT NULL DEFAULT 'berhasil',
  `ip` VARCHAR(45) DEFAULT NULL,
  `waktu` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_waktu` (`waktu`),
  KEY `idx_aksi` (`aksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- DATA AWAL
-- Akun bawaan (WAJIB diganti setelah login pertama):
--   superadmin / SuperAdmin@123
--   admin      / Admin@12345
-- ------------------------------------------------------------
INSERT INTO `users` (`username`,`nama_lengkap`,`email`,`password_hash`,`role`,`status_aktif`) VALUES
('superadmin','Super Administrator','superadmin@example.com','$2y$10$TY/rlG7QO8i9kOQk2k7Ng.i8bIKYPL8XwG1EaxfIXRNmjAq86h64m','superadmin',1),
('admin','Administrator','admin@example.com','$2y$10$9D3FvfUqpKvle8qQZl9yNOU6pby7LeLaEYLaC7jiKuzEdG9k8i.Kq','admin',1);

INSERT INTO `konfigurasi` (`kunci`,`nilai`,`updated_by`) VALUES
('threshold','0.90','system'),
('zona_waktu','Asia/Makassar','system'),
('aturan_duplikasi','satu_per_sesi','system'),
('model_version','mobilenetv2-v1','system'),
('versi_konfigurasi','1','system');

INSERT INTO `mahasiswa` (`nim`,`nama`,`kelas`,`status_aktif`,`persetujuan_wajah`) VALUES
('2021001','Contoh Mahasiswa Satu','TI-A',1,0),
('2021002','Contoh Mahasiswa Dua','TI-A',1,0),
('2021003','Contoh Mahasiswa Tiga','TI-B',1,0);
