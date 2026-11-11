<?php

namespace Database\Seeders;

use App\Models\Control;
use App\Models\GuidedExportTemplate;
use App\Models\GuidedQuestionBank;
use App\Models\Requirement;
use App\Models\Standard;
use App\Services\GuidedStarterWorkbookService;
use Illuminate\Database\Seeder;

class Iso27001GuidedStarterSeeder extends Seeder
{
    /** Code/title catalog derived from supplied example; never import organization-specific records. */
    private const CLAUSES = <<<'CATALOG'
4.1|Memahami organisasi dan konteksnya
4.2|Memahami kebutuhan pihak berkepentingan
4.3|Menentukan ruang lingkup SMKI
4.4|Sistem manajemen keamanan informasi
5.1|Kepemimpinan dan komitmen
5.2|Kebijakan keamanan informasi
5.3|Peran, tanggung jawab, dan wewenang
6.1.1|Risiko dan peluang: ketentuan umum
6.1.2|Penilaian risiko keamanan informasi
6.1.3|Penanganan risiko keamanan informasi
6.2|Sasaran keamanan informasi dan pencapaiannya
6.3|Perencanaan perubahan
7.1|Sumber daya
7.2|Kompetensi
7.3|Kesadaran
7.4|Komunikasi
7.5.1|Informasi terdokumentasi: umum
7.5.2|Pembuatan dan pemutakhiran dokumen
7.5.3|Pengendalian informasi terdokumentasi
8.1|Perencanaan dan pengendalian operasional
8.2|Penilaian risiko operasional
8.3|Penanganan risiko operasional
9.1|Pemantauan, pengukuran, dan evaluasi
9.2.1|Audit internal: ketentuan umum
9.2.2|Program audit internal
9.3.1|Tinjauan manajemen: umum
9.3.2|Masukan tinjauan manajemen
9.3.3|Hasil tinjauan manajemen
10.1|Perbaikan berkelanjutan
10.2|Ketidaksesuaian dan tindakan korektif
CATALOG;

    private const CONTROLS = <<<'CATALOG'
A.5.1|Kebijakan keamanan informasi
A.5.2|Peran dan tanggung jawab keamanan informasi
A.5.3|Pemisahan tugas
A.5.4|Tanggung jawab manajemen
A.5.5|Kontak dengan otoritas
A.5.6|Kontak dengan kelompok kepentingan khusus
A.5.7|Intelijen ancaman
A.5.8|Keamanan informasi dalam proyek
A.5.9|Inventaris informasi dan aset terkait
A.5.10|Penggunaan informasi dan aset yang dapat diterima
A.5.11|Pengembalian aset
A.5.12|Klasifikasi informasi
A.5.13|Pelabelan informasi
A.5.14|Pemindahan informasi
A.5.15|Pengendalian akses
A.5.16|Manajemen identitas
A.5.17|Informasi autentikasi
A.5.18|Hak akses
A.5.19|Keamanan informasi dalam hubungan pemasok
A.5.20|Keamanan dalam perjanjian pemasok
A.5.21|Keamanan dalam rantai pasok TIK
A.5.22|Pemantauan dan perubahan layanan pemasok
A.5.23|Keamanan penggunaan layanan cloud
A.5.24|Persiapan manajemen insiden
A.5.25|Penilaian peristiwa keamanan informasi
A.5.26|Respons terhadap insiden
A.5.27|Pembelajaran dari insiden
A.5.28|Pengumpulan bukti
A.5.29|Keamanan selama gangguan layanan
A.5.30|Kesiapan TIK untuk keberlangsungan bisnis
A.5.31|Persyaratan hukum, regulasi, dan kontrak
A.5.32|Hak kekayaan intelektual
A.5.33|Perlindungan rekaman
A.5.34|Privasi dan perlindungan data pribadi
A.5.35|Tinjauan keamanan informasi independen
A.5.36|Kepatuhan terhadap kebijakan keamanan informasi
A.5.37|Prosedur operasi terdokumentasi
A.6.1|Pemeriksaan latar belakang
A.6.2|Persyaratan dan ketentuan pekerjaan
A.6.3|Kesadaran, pendidikan, dan pelatihan keamanan
A.6.4|Proses disiplin
A.6.5|Tanggung jawab setelah perubahan pekerjaan
A.6.6|Perjanjian kerahasiaan
A.6.7|Kerja jarak jauh
A.6.8|Pelaporan peristiwa keamanan informasi
A.7.1|Batas keamanan fisik
A.7.2|Akses masuk fisik
A.7.3|Pengamanan kantor, ruangan, dan fasilitas
A.7.4|Pemantauan keamanan fisik
A.7.5|Perlindungan ancaman fisik dan lingkungan
A.7.6|Bekerja di area aman
A.7.7|Meja dan layar bersih
A.7.8|Penempatan dan perlindungan peralatan
A.7.9|Keamanan aset di luar lokasi
A.7.10|Media penyimpanan
A.7.11|Utilitas pendukung
A.7.12|Keamanan kabel
A.7.13|Pemeliharaan peralatan
A.7.14|Pembuangan atau penggunaan kembali peralatan
A.8.1|Perangkat pengguna akhir
A.8.2|Hak akses istimewa
A.8.3|Pembatasan akses informasi
A.8.4|Akses kode sumber
A.8.5|Autentikasi aman
A.8.6|Manajemen kapasitas
A.8.7|Perlindungan perangkat lunak berbahaya
A.8.8|Manajemen kerentanan teknis
A.8.9|Manajemen konfigurasi
A.8.10|Penghapusan informasi
A.8.11|Penyamaran data
A.8.12|Pencegahan kebocoran data
A.8.13|Pencadangan informasi
A.8.14|Redundansi fasilitas pengolahan informasi
A.8.15|Pencatatan log
A.8.16|Pemantauan aktivitas
A.8.17|Sinkronisasi waktu
A.8.18|Penggunaan utilitas berhak istimewa
A.8.19|Instalasi perangkat lunak operasional
A.8.20|Keamanan jaringan
A.8.21|Keamanan layanan jaringan
A.8.22|Pemisahan jaringan
A.8.23|Penyaringan web
A.8.24|Penggunaan kriptografi
A.8.25|Siklus pengembangan yang aman
A.8.26|Persyaratan keamanan aplikasi
A.8.27|Arsitektur dan rekayasa sistem yang aman
A.8.28|Pemrograman aman
A.8.29|Pengujian keamanan pengembangan
A.8.30|Pengembangan pihak ketiga
A.8.31|Pemisahan lingkungan pengembangan dan produksi
A.8.32|Manajemen perubahan
A.8.33|Informasi pengujian
A.8.34|Perlindungan sistem saat audit
CATALOG;

    private function records(string $lines): array
    {
        return array_map(static function (string $line): array {
            [$code, $title] = explode('|', $line, 2);
            return ['code' => trim($code), 'title' => trim($title)];
        }, preg_split('/\r?\n/', trim($lines)));
    }

    public function run(): void
    {
        $requirements = $this->records(self::CLAUSES);
        $controls = $this->records(self::CONTROLS);
        if (count($requirements) !== 30 || count($controls) !== 93) {
            throw new \RuntimeException('Katalog awal ISO/IEC 27001:2022 harus berisi 30 klausul dan 93 kontrol.');
        }
        $standard = Standard::query()->firstOrCreate(
            ['code' => 'ISO/IEC 27001', 'edition' => '2022'],
            ['name' => 'Sistem Manajemen Keamanan Informasi', 'description' =>
                'Katalog identitas berdasarkan dokumen acuan. Materi normatif dan pertanyaan harus direview oleh pemegang lisensi.', 'is_active' => true]
        );

        foreach (range(4, 10) as $group) {
            Requirement::query()->firstOrCreate(
                ['standard_id' => $standard->id, 'code' => (string) $group],
                ['title' => 'Kelompok klausul '.$group, 'type' => 'clause', 'is_mandatory' => true,
                    'sort_order' => $group * 100]
            );
        }
        foreach ([5 => 'Organisasi', 6 => 'Orang', 7 => 'Fisik', 8 => 'Teknologi'] as $group => $label) {
            Control::query()->firstOrCreate(
                ['standard_id' => $standard->id, 'code' => 'A.'.$group],
                ['title' => 'Kelompok '.$label, 'category' => $label, 'sort_order' => $group * 1000,
                    'is_group' => true]
            );
        }

        $bank = GuidedQuestionBank::query()->firstOrCreate(
            ['standard_id' => $standard->id, 'code' => 'ISO27001-2022-WIZARD', 'version' => 1],
            ['name' => 'ISO/IEC 27001:2022 — Bank awal untuk review editor', 'status' => 'draft']
        );
        $editable = $bank->status === 'draft';
        $options = ['Diterapkan Penuh', 'Diterapkan Sebagian', 'Tahap Perencanaan',
            'Belum Diterapkan', 'Belum Mengetahui'];
        foreach ($requirements as $index => $item) {
            $requirement = Requirement::query()->firstOrCreate(
                ['standard_id' => $standard->id, 'code' => $item['code']],
                ['title' => $item['title'], 'type' => 'clause', 'is_mandatory' => true,
                    'sort_order' => 1000 + $index * 10]
            );
            if (! $editable) { continue; }
            $question = $bank->questions()->firstOrCreate(['code' => 'REQ-'.$item['code']], [
                'prompt' => 'Bagaimana pelaksanaan '.$item['title'].' dalam ruang lingkup yang dinilai?',
                'help_text' => 'Ceritakan kondisi nyata, pihak bertanggung jawab, dan bukti. Jika belum tahu, pilih Belum Mengetahui; jangan mengarang.',
                'answer_type' => 'single_choice', 'answer_options' => $options,
                'sort_order' => 1000 + $index * 10, 'is_required' => true,
            ]);
            $question->targets()->firstOrCreate(['requirement_id' => $requirement->id], [
                'criterion' => 'Pemenuhan klausul '.$item['code'], 'evidence_profile' => 'standard',
                'is_required' => true,
            ]);
        }

        foreach ($controls as $index => $item) {
            $group = (int) explode('.', $item['code'])[1];
            $control = Control::query()->firstOrCreate(
                ['standard_id' => $standard->id, 'code' => $item['code']],
                ['title' => $item['title'], 'category' => [5 => 'Organisasi', 6 => 'Orang',
                    7 => 'Fisik', 8 => 'Teknologi'][$group],
                    'sort_order' => 2000 + $index * 10, 'is_group' => false]
            );
            if (! $editable) { continue; }
            $naCode = 'WHY-NA-'.$item['code'];
            $question = $bank->questions()->firstOrCreate(['code' => 'CTRL-'.$item['code']], [
                'prompt' => 'Bagaimana penerapan '.$item['title'].' dalam lingkup asesmen?',
                'help_text' => 'Jelaskan sistem, aset, atau kegiatan terkait serta bukti aktual. N/A hanya usulan yang perlu justifikasi dan review.',
                'answer_type' => 'single_choice',
                'answer_options' => array_merge($options, ['Ajukan Tidak Berlaku (N/A)']),
                'branch_rules' => [['when' => 'Ajukan Tidak Berlaku (N/A)', 'next_code' => $naCode]],
                'sort_order' => 2000 + $index * 10, 'is_required' => true,
            ]);
            $bank->questions()->firstOrCreate(['code' => $naCode], [
                'prompt' => 'Mengapa '.$item['code'].' tidak berlaku untuk ruang lingkup ini?',
                'help_text' => 'Sebutkan batasan lingkup, risiko dan dasar pengecualian. Alasan belum ada anggaran bukan otomatis N/A.',
                'answer_type' => 'text', 'answer_options' => [], 'is_required' => true,
                'sort_order' => 2001 + $index * 10,
            ]);
            $question->targets()->firstOrCreate(['control_id' => $control->id], [
                'criterion' => 'Penerapan kontrol '.$item['code'], 'evidence_profile' => 'standard',
                'is_required' => true,
            ]);
        }

        $report = app(GuidedStarterWorkbookService::class)->ensureTemplate($standard);
        $this->command?->info('ISO 27001 starter: 30 klausul, 93 kontrol, bank DRAFT, template '.$report->name);
        $this->command?->warn('Bank harus diperiksa berdasarkan standar berlisensi lalu diterbitkan sebelum pengguna memulai asesmen.');
    }
}
