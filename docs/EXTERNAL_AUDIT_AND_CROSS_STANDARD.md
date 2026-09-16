# External Audit Workspace & Cross-Standard Evidence Reuse

Dokumen ini menjelaskan dua capability ISO-KM yang saling melengkapi:

1. **External Auditor Read-Only Workspace** untuk memberikan visibility audit secara terbatas tanpa membuka CRUD internal.
2. **Cross-Standard Mapping & Evidence Reuse** untuk memakai evidence yang sama pada lebih dari satu management system secara terkontrol tanpa menduplikasi source-of-truth.

## 1. External Auditor Read-Only Workspace

URL:

```text
/portal/external-auditor-workspace
```

### Prinsip akses

External auditor tidak memperoleh akses read-only ke seluruh panel. Akses terdiri dari dua lapis:

```text
portal.access
      +
external_auditor_workspace.view
      +
ExternalAuditAccess aktif untuk Management System tertentu
```

`ExternalAuditAccess` menyimpan:

- auditor/user;
- management system yang boleh dilihat;
- grantor;
- `valid_from`;
- `expires_at`;
- apakah External Auditor Pack boleh diexport;
- status;
- revoke actor dan timestamp;
- notes/tujuan akses.

Grant dianggap aktif hanya ketika:

- status = `active`;
- belum direvoke;
- `valid_from` sudah tercapai atau kosong;
- `expires_at` belum terlewati atau kosong.

External auditor hanya melihat management system yang mempunyai grant aktif untuk akun tersebut.

### Panel access

`User::canAccessPanel()` menggunakan permission `portal.access` sebagai gate setelah permission tersebut tersedia.

Untuk menjaga deployment existing tetap aman dari lockout, sebelum `portal.access` pertama kali dibuat oleh seeder, perilaku akses lama tetap dipertahankan. Setelah seeder dijalankan, permission menjadi source-of-truth akses panel.

### External Auditor Pack

Export memerlukan:

```text
external_audit_pack.export
```

serta `allow_audit_pack = true` pada grant yang aktif. Validasi dilakukan server-side, bukan hanya dengan menyembunyikan tombol.

Pack yang dihasilkan sengaja lebih sempit daripada internal Auditor Pack:

```text
manifest.json
traceability-matrix.csv
statement-of-applicability.csv
evidence-index.csv
cross-standard-evidence-reuse.csv
findings-summary.csv
```

Pack ini **tidak memasukkan raw evidence files**. Manifest mencatat `raw_files_included = false`.

Tujuannya adalah memberikan traceability audit tanpa otomatis memberikan seluruh file internal atau data operasional yang tidak relevan.

### Permission preset

Role template `external_auditor` hanya berisi:

```text
portal.access
external_auditor_workspace.view
external_audit_pack.export
```

Role hanya preset. Authorization tetap berbasis permission dan grant per management system.

## 2. Cross-Standard Mapping

ISO-KM mendukung mapping dua lapis:

```text
Requirement ↔ Requirement
Control ↔ Control
```

Mapping harus menghubungkan object dari **dua standard berbeda**. Mapping dalam standard yang sama ditolak.

Tipe mapping:

- `equivalent`
- `partial`
- `supporting`
- `related`

Setiap mapping mempunyai `coverage_percent`, rationale, mapper, reviewer, review timestamp, dan workflow status.

Lifecycle:

```text
Draft
  ↓ review
Approved

Draft / Approved
  ↓ reject
Rejected
  ↓ revise/review
Approved
```

Mapping yang belum `approved` tidak dapat dipakai sebagai dasar evidence reuse.

### Makna coverage

`coverage_percent` adalah metadata mapping dan bukan persentase compliance. Angka tersebut tidak otomatis menaikkan readiness score atau membuat target requirement menjadi conformant.

## 3. Evidence Reuse

Evidence tidak disalin ke management system kedua.

Modelnya:

```text
Source Evidence
     │
     ├── tetap dimiliki source Management System
     │
     └── EvidenceReuse
             ↓
       Target Requirement / Control
             ↓
       Target Management System
```

Dengan demikian:

- file tetap satu source-of-truth;
- verification status tetap mengikuti evidence asli;
- expiry tetap mengikuti evidence asli;
- jika source evidence expired/rejected/inactive, reuse tidak lagi dihitung sebagai fresh verified support;
- auditor dapat membedakan direct evidence dan reused evidence.

### Syarat acceptance

Evidence hanya dapat diterima untuk reuse jika seluruh kondisi berikut benar:

1. mapping sudah `approved`;
2. source dan target berasal dari standard berbeda;
3. source dan target Management System berada pada `ComplianceScope` yang sama;
4. evidence benar-benar terhubung ke requirement/control sumber;
5. evidence berstatus `active`;
6. verification status = `verified`;
7. evidence belum expired;
8. actor mempunyai permission `cross_standard_reuse.accept`.

Reuse tidak pernah terjadi hanya karena code/title requirement terlihat mirip.

## 4. Cross-Standard Coverage Workspace

URL:

```text
/portal/cross-standard-workspace
```

Workspace mencari reuse opportunities berdasarkan:

- target management system;
- approved mapping;
- management system standard lain pada scope yang sama;
- verified/fresh source evidence;
- hubungan source evidence dengan requirement/control sumber.

Hasil ditampilkan sebagai:

```text
Source Standard / System
        ↓
Source Requirement / Control
        ↓ approved mapping
Verified Source Evidence
        ↓ human acceptance
Target Requirement / Control
```

Workspace membedakan state:

- `available`
- `accepted`
- `rejected`

## 5. Traceability

`TraceabilityMatrixService` mempertahankan dua angka berbeda:

- `verified_fresh_evidence`: direct evidence pada target management system;
- `verified_fresh_reused_evidence`: accepted reused evidence yang source-nya masih verified/fresh.

`verified_fresh_evidence_total` adalah support gabungan untuk kebutuhan assurance traceability.

Internal Auditor Workspace dan External Auditor Workspace tetap menampilkan direct dan reused evidence secara terpisah agar provenance tidak hilang.

Accepted reuse dapat menutup kondisi `Needs Evidence` untuk requirement yang assessment-nya sudah conformant, tetapi **tidak mengubah assessment status** dan bukan keputusan compliance otomatis.

## 6. Permission Model

Permission utama fase ini:

```text
portal.access
external_audit_access.manage
external_auditor_workspace.view
external_audit_pack.export
cross_standard_mapping.manage
cross_standard_mapping.approve
cross_standard_workspace.view
cross_standard_reuse.accept
```

Filament CRUD permission untuk register baru tetap dibuat terpisah melalui Shield-style permission:

```text
external::audit::access
requirement::mapping
control::mapping
evidence::reuse
```

## 7. Deployment

Untuk existing installation:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=IsoRolePermissionSeeder --force
php artisan iso:refresh-intelligence
php artisan optimize:clear
php artisan optimize
```

`IsoRolePermissionSeeder` menggunakan role template dan `syncPermissions()` pada role yang dikelolanya. Review terlebih dahulu jika role production sudah dikustom manual.

PHP extension `zip` tetap diperlukan untuk internal maupun external audit pack.

## 8. Security / Governance Notes

- Buat akun external auditor terpisah dari akun operasional internal bila memungkinkan.
- Selalu berikan `expires_at` pada grant auditor untuk engagement audit yang memiliki batas waktu.
- Aktifkan `allow_audit_pack` hanya jika auditor memang perlu membawa export keluar workspace.
- Gunakan rationale mapping untuk menjelaskan batas kesetaraan; hindari memberi tipe `equivalent` hanya karena judul requirement mirip.
- Re-review mapping ketika edition standard berubah.
- Evidence reuse tidak menggantikan professional judgment pada requirement assessment, SoA, audit, maupun sertifikasi.
