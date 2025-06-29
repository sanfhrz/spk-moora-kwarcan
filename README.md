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
├── config/          # Konfigurasi database
├── includes/        # File include (header, sidebar, dll)
├── admin/           # Panel admin
├── assets/          # CSS, JS, images
├── exports/         # File export
└── uploads/         # File upload
```

## 🔐 Login Default

- **Username:** admin
- **Password:** password

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
