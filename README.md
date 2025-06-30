# SPK MOORA Kwarcan Asahan

Sistem Pendukung Keputusan Pemilihan Kwarcan Terbaik di Kabupaten Asahan menggunakan Metode MOORA (Multi-Objective Optimization by Ratio Analysis).

## 🚀 Fitur Utama

- **Dashboard Admin** - Monitoring data dan statistik
- **Manajemen Kriteria** - CRUD data kriteria penilaian
- **Manajemen Kwarcan** - CRUD data calon ketua kwarcab
- **Input Penilaian** - Input dan edit nilai penilaian
- **Perhitungan MOORA** - Implementasi algoritma MOORA
- **Ranking & Laporan** - Hasil perangkingan dan export laporan

## 📋 Requirements

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web Server (Apache/Nginx)
- Browser modern

## 🔧 Instalasi

1. **Clone/Download project**
   ```bash
   git clone [repository-url]
   cd moora-kwarcan
   ```

2. **Setup Database**
   - Buat database MySQL dengan nama `spk_kwarcan`
   - Import file SQL yang disediakan
   - Sesuaikan konfigurasi di `config/database.php`

3. **Setup Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 exports/
   ```

4. **Akses Aplikasi**
   - Buka browser: `http://localhost/moora-kwarcan`
   - Login dengan: username `admin`, password `password`

## 📁 Struktur Project

```
moora-kwarcan/
│   .htaccess
│   index.php
│   README.md
│   setup.php
│
├───admin
│   │   cetak-detail.php
│   │   dashboard.php
│   │   hasil-moora.php
│   │   hitung-moora.php
│   │   kriteria.php
│   │   kwarcan.php
│   │   laporan.php
│   │   login.php
│   │   logout.php
│   │   pengaturan.php
│   │   penilaian.php
│   │
│   └───ajax
│           export-kriteria.php
│           export-kwarcan.php
│           get-kriteria-detail.php
│           get-kriteria.php
│           get-kwarcan-detail.php
│           get-kwarcan-stats.php
│           get-kwarcan.php
│           get-penilaian-detail.php
│           get-stats.php
│
├───ajax
│       download-template.php
│       export-matrix.php
│       get-assessment.php
│       get-stats.php
│       import-excel.php
│
├───assets
│   ├───css
│   │       admin.css
│   │       custom.css
│   │       dashboard.css
│   │       responsive.css
│   │       sidebar-enhancement.css
│   │
│   └───js
│           admin.js
│           dashboard.js
│
├───classes
│       MOORA.php
│
├───config
│   │   database.php
│   │
│   └───databases
│           spk_kwarcan.sql
│
├───includes
│       connection.php
│       footer.php
│       functions.php
│       header.php
│       navbar.php
│       sidebar.php
│
└───other
        footer.php
        get-kwarcan-detail.php
        header.php
        navbar.php
        sidebar.php
```

## 🔐 Login Default

- **Username:** 
- **Password:**


contact me : fahrizaihsan06@gmail.com


## 📊 Metode MOORA

Langkah-langkah perhitungan:
1. Normalisasi matriks keputusan
2. Perkalian dengan bobot kriteria
3. Perhitungan nilai optimasi (Yi)
4. Perangkingan berdasarkan nilai Yi tertinggi

## 🛠️ Development

Untuk development lebih lanjut:
- Gunakan environment development
- Enable error reporting
- Backup database secara berkala

## 📞 Support

Jika ada pertanyaan atau masalah, silakan hubungi developer.
