<?php
require_once '../config/database.php';

class MOORA
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Hitung MOORA lengkap untuk semua kwarcan
     */
    public function hitungMOORA()
    {
        try {
            // 1. Ambil data penilaian lengkap
            $dataPenilaian = $this->getDataPenilaian();

            // 2. Buat matriks keputusan
            $matriks = $this->buatMatriksKeputusan($dataPenilaian);

            // 3. Normalisasi matriks
            $matriksNormalisasi = $this->normalisasiMatriks($matriks);

            // 4. Hitung nilai optimasi
            $hasilOptimasi = $this->hitungOptimasi($matriksNormalisasi);

            // 5. Tentukan ranking
            $ranking = $this->tentukanRanking($hasilOptimasi);

            // 6. Simpan hasil ke database
            $this->simpanHasil($ranking);

            return [
                'success' => true,
                'data' => $ranking,
                'matriks_asli' => $matriks,
                'matriks_normalisasi' => $matriksNormalisasi
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ambil data penilaian dari database
     */
    private function getDataPenilaian()
    {
        $sql = "SELECT 
                    k.id as kwarcan_id,
                    k.kode_kwarcan,
                    k.nama_kwarcan,
                    k.daerah,
                    kr.id as kriteria_id,
                    kr.kode_kriteria,
                    kr.nama_kriteria,
                    kr.bobot,
                    kr.jenis,
                    COALESCE(p.nilai, 0) as nilai
                FROM kwarcan k
                CROSS JOIN kriteria kr
                LEFT JOIN penilaian p ON k.id = p.kwarcan_id AND kr.id = p.kriteria_id
                WHERE k.status = 'aktif'
                ORDER BY k.id, kr.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Buat matriks keputusan
     */
    private function buatMatriksKeputusan($data)
    {
        $matriks = [];
        $kwarcan = [];
        $kriteria = [];

        foreach ($data as $row) {
            $kwarcan_id = $row['kwarcan_id'];
            $kriteria_id = $row['kriteria_id'];

            // Simpan info kwarcan
            if (!isset($kwarcan[$kwarcan_id])) {
                $kwarcan[$kwarcan_id] = [
                    'kode' => $row['kode_kwarcan'],
                    'nama' => $row['nama_kwarcan'],
                    'daerah' => $row['daerah']
                ];
            }

            // Simpan info kriteria
            if (!isset($kriteria[$kriteria_id])) {
                $kriteria[$kriteria_id] = [
                    'kode' => $row['kode_kriteria'],
                    'nama' => $row['nama_kriteria'],
                    'bobot' => $row['bobot'],
                    'jenis' => $row['jenis']
                ];
            }

            // Isi matriks
            $matriks[$kwarcan_id][$kriteria_id] = $row['nilai'];
        }

        return [
            'matriks' => $matriks,
            'kwarcan' => $kwarcan,
            'kriteria' => $kriteria
        ];
    }

    /**
     * Normalisasi matriks menggunakan rumus MOORA
     */
    private function normalisasiMatriks($data)
    {
        $matriks = $data['matriks'];
        $kriteria = $data['kriteria'];
        $matriksNormalisasi = [];

        // Hitung pembagi untuk setiap kriteria (akar dari jumlah kuadrat)
        $pembagi = [];
        foreach ($kriteria as $kr_id => $kr_info) {
            $jumlahKuadrat = 0;
            foreach ($matriks as $kw_id => $nilai_kriteria) {
                $jumlahKuadrat += pow($nilai_kriteria[$kr_id], 2);
            }
            $pembagi[$kr_id] = sqrt($jumlahKuadrat);
        }

        // Normalisasi setiap nilai
        foreach ($matriks as $kw_id => $nilai_kriteria) {
            foreach ($nilai_kriteria as $kr_id => $nilai) {
                if ($pembagi[$kr_id] != 0) {
                    $matriksNormalisasi[$kw_id][$kr_id] = $nilai / $pembagi[$kr_id];
                } else {
                    $matriksNormalisasi[$kw_id][$kr_id] = 0;
                }
            }
        }

        return [
            'matriks_normalisasi' => $matriksNormalisasi,
            'pembagi' => $pembagi,
            'kwarcan' => $data['kwarcan'],
            'kriteria' => $data['kriteria']
        ];
    }

    /**
     * Hitung nilai optimasi MOORA
     */
    private function hitungOptimasi($data)
    {
        $matriksNormalisasi = $data['matriks_normalisasi'];
        $kriteria = $data['kriteria'];
        $kwarcan = $data['kwarcan'];
        $hasilOptimasi = [];

        foreach ($matriksNormalisasi as $kw_id => $nilai_kriteria) {
            $nilaiOptimasi = 0;
            $detailKriteria = [];

            foreach ($nilai_kriteria as $kr_id => $nilaiNormalisasi) {
                $bobot = $kriteria[$kr_id]['bobot'];
                $jenis = $kriteria[$kr_id]['jenis'];

                // Kalikan dengan bobot
                $nilaiTerbobot = $nilaiNormalisasi * $bobot;

                // Untuk benefit (+), untuk cost (-)
                if ($jenis == 'benefit') {
                    $nilaiOptimasi += $nilaiTerbobot;
                } else {
                    $nilaiOptimasi -= $nilaiTerbobot;
                }

                $detailKriteria[$kr_id] = [
                    'nilai_asli' => 0, // akan diisi nanti
                    'nilai_normalisasi' => $nilaiNormalisasi,
                    'nilai_terbobot' => $nilaiTerbobot,
                    'bobot' => $bobot,
                    'jenis' => $jenis
                ];
            }

            $hasilOptimasi[$kw_id] = [
                'kwarcan_info' => $kwarcan[$kw_id],
                'nilai_optimasi' => $nilaiOptimasi,
                'detail_kriteria' => $detailKriteria
            ];
        }

        return $hasilOptimasi;
    }

    /**
     * Tentukan ranking berdasarkan nilai optimasi
     */
    private function tentukanRanking($hasilOptimasi)
    {
        // Urutkan berdasarkan nilai optimasi (descending)
        uasort($hasilOptimasi, function ($a, $b) {
            return $b['nilai_optimasi'] <=> $a['nilai_optimasi'];
        });

        // Berikan ranking
        $ranking = 1;
        $hasilRanking = [];

        foreach ($hasilOptimasi as $kw_id => $data) {
            $data['ranking'] = $ranking;
            $data['kwarcan_id'] = $kw_id;
            $hasilRanking[] = $data;
            $ranking++;
        }

        return $hasilRanking;
    }

    /**
     * Simpan hasil ke database
     */
    private function simpanHasil($ranking)
    {
        // Hapus hasil lama
        $this->db->exec("DELETE FROM hasil_moora");

        // Simpan hasil baru
        $sql = "INSERT INTO hasil_moora (kwarcan_id, nilai_optimasi, ranking) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);

        foreach ($ranking as $hasil) {
            $stmt->execute([
                $hasil['kwarcan_id'],
                $hasil['nilai_optimasi'],
                $hasil['ranking']
            ]);
        }
    }

    /**
     * Ambil hasil MOORA dari database
     */
    public function getHasilMOORA()
    {
        $sql = "SELECT 
                    hm.id,
                    hm.kwarcan_id,
                    hm.nilai_optimasi,
                    hm.ranking,
                    k.kode_kwarcan,
                    k.nama_kwarcan,
                    k.daerah,
                    hm.created_at
                FROM hasil_moora hm
                JOIN kwarcan k ON hm.kwarcan_id = k.id
                ORDER BY hm.ranking ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
