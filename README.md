# TugasKita SMA

TugasKita adalah sistem pengumpulan dan penilaian tugas digital berbasis web untuk Sekolah Menengah Atas. Versi ini dibuat rapi untuk XAMPP dan aman dipush ke GitHub sebagai source code beserta database SQLite.

## Catatan Penting GitHub

GitHub biasa hanya menyimpan source code. GitHub Pages tidak menjalankan PHP dan SQLite, jadi aplikasi ini tidak bisa berjalan langsung sebagai website statis di GitHub Pages.

Cara yang benar:

1. Push folder ini ke GitHub sebagai repository.
2. Clone atau download repository ke komputer.
3. Jalankan melalui XAMPP/Apache lokal.

Database ikut dipush melalui file `database/tugaskita.sqlite`. Database awal sudah berisi daftar mata pelajaran, tetapi tidak berisi akun, materi, tugas, pengumpulan, atau nilai lama.

## Cara Menjalankan di XAMPP

1. Salin folder ini ke:

   ```text
   C:\xampp\htdocs\TugasKita-SMA
   ```

2. Buka XAMPP Control Panel.
3. Klik `Start` pada Apache.
4. Buka browser:

   ```text
   http://localhost/TugasKita-SMA/
   ```

## Akun Awal

Belum ada akun awal. Buat akun baru dari halaman `Daftar akun baru`. Siswa mengisi nomor induk siswa sendiri, sedangkan guru mengisi NIP serta memilih akses guru.

## Fitur

- Login dan daftar akun baru
- Nomor induk siswa diisi manual oleh siswa
- NIP untuk akun guru
- Guru memilih akses `Guru Admin` atau `Guru Pengampu` saat daftar
- Guru biasa dapat memilih satu atau beberapa jenjang pengampu
- Guru biasa dapat memilih satu atau beberapa mata pelajaran yang diampu
- Role guru dan siswa
- Kelas Kurikulum Merdeka seperti `X 1`, `XI 5`, dan `XII 8`
- Mata pelajaran inti per tingkat X, XI, dan XII, lengkap dengan guru pengampu dan jadwal
- Guru admin dapat mengelola materi dan tugas semua jenjang
- Guru biasa hanya dapat mengelola materi dan tugas pada mata pelajaran yang diampu
- Guru dapat menambahkan mata pelajaran
- Guru dapat menambahkan materi berupa teks atau file pada mata pelajaran
- Guru membuat tugas berdasarkan mata pelajaran
- Siswa melihat materi dan tugas per mata pelajaran
- Upload tugas per mata pelajaran
- Penilaian guru dengan komentar opsional
- Hasil nilai untuk siswa
- SQLite auto-create saat pertama dijalankan
- Password bcrypt
- Session timeout 30 menit
- Upload file maksimal 10 MB

## Jika SQLite Belum Aktif

Jika muncul pesan `pdo_sqlite belum aktif`:

1. Buka:

   ```text
   C:\xampp\php\php.ini
   ```

2. Aktifkan baris ini dengan menghapus tanda `;`:

   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```

3. Simpan, lalu restart Apache.

## Struktur Folder

```text
arah_halaman/    pengarah halaman awal dan halaman setelah login
aset/            CSS tampilan
database/        schema.sql dan tugaskita.sqlite
pengaturan/      konfigurasi dan fungsi bantuan PHP
proses/          proses form, penilaian, upload, dan unduh file
tampilan/        tampilan dashboard, mapel, tugas, dan penilaian
unggahan/        file tugas siswa dan file materi
index.php        pintu masuk aplikasi
```
