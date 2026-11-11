# Integrasi Otomatis ISO-KM

## Prinsip
- Tidak membuat menu baru. Otomasi berada di model, service, scheduler.
- Register sumber adalah kebenaran; My Work dan assurance hanya proyeksi.
- Persetujuan dan verifikasi manusia tidak boleh otomatis atau diklaim telah dilakukan.
- Tidak ada backfill massal atau penghapusan data historis.

## Yang otomatis
1. Requirement Assessment, SoA, checklist dan finding menolak pemilihan katalog dari standar berbeda. Pilihan form native Filament otomatis terfilter menurut management system.
2. Checklist nonconformity, observation, opportunity menghasilkan satu finding yang terhubung ke checkpoint. Menyimpan ulang checklist tidak membuat duplikat. Mengubah catatan checklist tidak menimpa keputusan pada finding existing.
3. CAPA: pelaksana memilih aksi native Filament "Selesai, Minta Verifikasi"; apabila PIC belum diisi, pelaksana tercatat otomatis sebagai PIC. Verifier harus berbeda dan memiliki izin capa.verify serta akses lingkup. Penolakan mengembalikan pekerjaan; keputusan efektif menutup finding hanya setelah seluruh CAPA efektif dan root cause tersedia. Status finding baru masuk verification ketika seluruh CAPA sudah diajukan atau efektif. CAPA/finding dengan histori tidak dapat dihapus melalui model dan repeater CAPA tidak menyediakan delete.
4. Penyelesaian audit mencatat timestamp dan menolak status completed jika masih ada checklist not_reviewed.
5. My Work memproyeksikan pekerjaan dari register domain. Jika PIC finding dan CAPA yang masih aktif sama, hanya satu kartu CAPA ditampilkan (dengan pengingat root cause bila perlu); pemilik yang berbeda tetap mendapat tanggung jawabnya masing-masing. Verifikasi CAPA masuk antrean bersama reviewer berwenang sesuai lingkup, tidak dipaksakan ke pelaksana sendiri.
6. Assurance menghitung evidence langsung dan accepted reuse per control unik, hanya jika sumber valid dan mapping masih approved.
7. Management Review yang baru otomatis menerima konteks data saat inisiasi bila inputs_summary kosong. Ringkasan dan keputusan manusia tidak ditimpa.
8. Record inti diperiksa berdasarkan ComplianceScope.owner_id yang sudah ada tanpa pengaturan tambahan. Form native Filament memfilter pilihan lingkup/sistem saat membuat, dan penyimpanan Eloquent yang memiliki aktor login menolak penciptaan serta perpindahan ke sistem asing. Record yang sudah ditugaskan tetap menampilkan sistem asalnya pada form. Competence Register menggunakan user_id (bukan owner_id). Role global sesuai baseline tetap global; scope tanpa owner mempertahankan perilaku lama.

## Jadwal
- iso:refresh-intelligence: tiap 30 menit, idempotent. Membarui evidence lifecycle, work queue dan assurance snapshot.
- iso:send-reminders: harian pukul 07.30 sesuai APP_TIMEZONE.
- Pastikan Laravel scheduler dipanggil tiap menit dari cron. Proyeksi tidak real-time; sumber data langsung disimpan.

## Deployment aman
1. Backup database dan file. Audit nilai pemilik scope dan role existing.
2. Merge setelah CI dan UAT, lalu git pull origin main.
3. php artisan migrate --force
4. php artisan optimize:clear
5. Cek cron Laravel schedule:run. Tidak perlu migrate:fresh, shield:generate --all atau seeder ulang.
6. Uji dari browser untuk peran super_admin, ISO coordinator, process owner, auditor, reviewer CAPA, dan external auditor.

## Batasan
- Katalog standar berlisensi, bank pertanyaan, dan Guided Assessment tetap memerlukan konten valid serta aktivasi existing.
- Scope ownership + policy + model hooks pada register inti bukan isolasi multi-tenant penuh. Shared Document/Supplier, jalur create/pivot di luar register inti, layanan tanpa aktor login, dan import/internal service memerlukan audit keamanan sebelum hosting multi-tenant.
- Pivot relasi seperti mapping dokumen/evidence ke standar perlu audit terpisah; validasi otomatis baru fokus pada kolom acuan inti.
- Verifier CAPA tetap harus dipilih manusia; approval tidak otomatis.
- Buka Modul pada My Work menuju resource, bukan semua modal record langsung.
- Management Review merangkum kondisi saat record dibuat; keputusan final tetap manusia.
- CI bukan pengganti UAT browser, tes akses per scope, dan inspeksi workbook nyata.
