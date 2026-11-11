# ISO-KM — Guided Assessment Wizard (integrasi repository)

## Status dan pengaman

Integrasi ini berada pada PR #4 dan default **NONAKTIF**.
Fitur lama ISO-KM tetap menjadi sumber data resmi. Wizard hanya memberikan
satu pintu bagi pengguna awam untuk mulai, melanjutkan, dan memantau asesmen.

Tombol baru: **Guided Assessment → Mulai / Lanjutkan Asesmen**.

Kedua flag harus aktif setelah CI, review katalog dan UAT:

```dotenv
ISO_KM_GUIDED_ASSESSMENT_ENABLED=true
ISO_KM_GUIDED_WIZARD_ENABLED=true
ISO_KM_ORGANIZATION_NAME="Nama organisasi pemasang"
```

Pada instalasi yang menggunakan konfigurasi cache, segarkan cache setelah
mengubah .env. Jangan mengubah flag di production tanpa persetujuan UAT.

## Menyiapkan aplikasi

1. Backup database. Lakukan pull branch yang sudah lolos review pada staging.
2. Jalankan `php artisan migrate --force`. Migration baru hanya menambah
   `guided_answer_drafts`, tidak mengubah tabel jawaban atau SoA existing.
3. Jalankan **seeder katalog ini saja**:

```bash
php artisan db:seed --class=Iso27001GuidedStarterSeeder --force
```

4. Periksa katalog 30 klausul + 93 kontrol dan pertanyaan terhadap standar
   berlisensi. Bank `ISO27001-2022-WIZARD` sengaja berstatus **draft**.
5. Administrator terotorisasi membuka **Bank Pertanyaan**, menilai cakupan,
   menyunting materi panduan jika perlu, kemudian **Terbitkan** bank.
6. Pastikan pengguna mempunyai permission `guided.assessment.manage`,
   `guided.answer.submit`, `guided.assessment.view`, keanggotaan yang
   benar dan hak atas `ComplianceScope`. Editor/koordinator tidak otomatis
   berhak menyetujui penilaian sendiri.
7. Atur konfigurasi nama organisasi dan lakukan UAT seluruh peran di staging.
8. Baru aktifkan kedua feature flag.

Seeder tidak membuat `ORG`, `ISMS`, aset, risiko, jawaban, atau nilai
penerapan yang berasal dari workbook organisasi contoh.

## Alur pengguna

1. Klik **Mulai / Lanjutkan Asesmen**.
2. Pilih sistem manajemen dan isi nama asesmen; sistem memilih bank terbit
   yang sesuai standar serta menugaskan 123 pertanyaan induk.
3. Isi per pertanyaan, pilih konteks (contoh: aplikasi, unit, atau aset),
   tambahkan keterangan dan evidence yang benar-benar tersedia. Draft
   disimpan ke database dengan pemeriksaan versi; ketika sesi lain mengubahnya,
   sistem menolak penimpaan diam-diam.
4. **Kirim Jawaban** membuat catatan baru melalui
   `GuidedAssessmentWorkflowService` existing. Revisi menggunakan
   `supersedes_id`, sehingga jawaban lama tetap ada.
5. Koordinator dapat menugaskan responden lain, menambah inventaris,
   dan menghubungkan risiko melalui Risk Register.
6. Konsolidator, reviewer evidence, asesor, pemilik risiko dan manajemen
   bekerja melalui resource ISO-KM existing yang terhubung dari wizard.
7. Dashboard memperlihatkan hambatan finalisasi. Penguncian final tetap
   dijalankan oleh validasi canonical `GuidedAssessmentWorkflowService::lock()`.
8. Dari **Unduh Hasil**, koordinator dapat mengunduh XLSX draft kapan saja;
   final hanya setelah penguncian. Laporan cetak bisa disimpan sebagai PDF
   dari browser, sesuai hak akses.

## Template laporan bawaan

`GuidedStarterWorkbookService` menghasilkan paket XLSX secara lokal dengan
worksheet Profil, Klausul, Organisasi, Orang, Fisik, Teknologi,
Aset Informasi, Aktivitas Informasi, Risiko, dan Dashboard. Pemetaan sel
dihasilkan otomatis, kapasitas register contoh adalah 150 baris per kategori.
Tidak ada data organisasi bawaan. Jika jumlah data melampaui kapasitas,
ekspor FINAL ditolak daripada memotong data.

Skor dashboard memakai kebijakan internal: 1, 0.667, 0.333, 0; N/A
tidak dihitung. Ini **bukan skor resmi ISO 27001**, dan laporan bukan
sertifikat. Draft menampilkan `Belum Dinilai` untuk kriteria yang belum
disetujui; hasil yang sedang dikonsolidasikan tidak ditampilkan sebagai
keputusan final.

Saat memasang, uji format dan perhitungan di Microsoft Excel desktop,
khususnya template, fungsi formula dan penanganan N/A.

## Batasan sebelum penggunaan produksi

- Browser UAT per peran dan audit akses evidence wajib.
- Bank pertanyaan harus dikaji secara substansi oleh pemegang standar
  berlisensi; katalog kode lengkap bukan bukti kesesuaian normatif.
- Alur revisi rejected→resubmit, soal konflik jawaban lintas konteks,
  dan tindak lanjut risiko tetap menggunakan workflow existing. Wizard
  menautkan halaman review canonical; belum menggantikan seluruh halaman
  reviewer dengan satu form.
- Tidak ada multi-tenant isolasi dalam satu database; white-label
  tersedia per instalasi.
- Generator XLSX bawaan menyertakan rumus dan dashboard ringkas, belum
  mereplikasi seluruh elemen grafis workbook contoh.
- Tidak ada file data dari organisasi contoh yang diimpor sebagai master.

## Pengujian

GitHub Actions menjalankan seluruh tes SQLite dan MySQL 8.0.
Gunakan staging untuk satu siklus mulai→draft→revisi→konsolidasi→
approval→publikasi→lock→XLSX FINAL. CI hijau belum menggantikan UAT.
