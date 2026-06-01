<?php
declare(strict_types=1);

require __DIR__ . '/pengaturan/konfigurasi.php';
require __DIR__ . '/pengaturan/fungsi_umum.php';
require __DIR__ . '/pengaturan/fungsi_aplikasi.php';

require __DIR__ . '/tampilan/dashboard.php';
require __DIR__ . '/tampilan/mata_pelajaran.php';
require __DIR__ . '/tampilan/tugas.php';
require __DIR__ . '/tampilan/penilaian.php';

initialize_database();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$page = $_GET['page'] ?? '';

require __DIR__ . '/proses/akun.php';
require __DIR__ . '/proses/guru.php';
require __DIR__ . '/proses/siswa.php';
require __DIR__ . '/proses/penilaian.php';
require __DIR__ . '/proses/unduh.php';
require __DIR__ . '/arah_halaman/halaman_awal.php';
require __DIR__ . '/arah_halaman/halaman_pengguna.php';
