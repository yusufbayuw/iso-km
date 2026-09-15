# ISO-KM

ISO-KM adalah sistem manajemen kepatuhan berbasis Laravel + Filament untuk menjalankan management system secara operasional, bukan sekadar menyimpan dokumen ISO.

## Arsitektur domain

ISO-KM memisahkan **master standar** dari **implementasi organisasi**:

```text
Standard
├── Requirements (Clause / Subclause / Requirement)
└── Controls (mis. Annex A)

Compliance Scope
└── Management System
    ├── Requirement Assessments
    ├── Statement of Applicability
    ├── Documents & Revisions
    ├── Evidence
    ├── Risks
    ├── Audits → Findings → CAPA
    ├── Objectives & KPI
    ├── Compliance Obligations
    └── Management Reviews → Actions
```

Pemisahan ini memungkinkan satu instalasi menangani beberapa standar dan beberapa lingkup organisasi tanpa mencampurkan master requirement/control dengan implementasi organisasi.

## Modul yang sudah tersedia

- Master Standard
- Requirement hierarchy
- Control hierarchy dan mapping ke requirement
- Compliance Scope
- Management System instance
- Readiness / Requirement Assessment
- Statement of Applicability (SoA)
- Document Control dengan lifecycle terkontrol: draft → in review → reviewed → approved → effective → obsolete
- Reviewer dan approver terpisah, termasuk rejection/rework
- Revision history dan controlled-copy replacement
- Distribusi dokumen dan acknowledgement pengguna
- Evidence register
- Risk Register dengan inherent/residual score
- Internal Audit programme dan checklist
- Finding / NCR / OFI
- Corrective Action dan effectiveness verification
- Objectives & KPI measurement
- Compliance Obligations register
- Management Review dan action tracking
- Database notification untuk workflow dokumen
- Reminder terjadwal untuk review dokumen, acknowledgement, audit, CAPA, objective, compliance obligation, management review, dan action item
- Audit trail perubahan untuk dokumen, revision, distribution, readiness assessment, SoA, risk, audit, finding, CAPA, objective, compliance obligation, dan management review
- Dashboard readiness, control implementation, high risk, open findings, objective at risk, overdue actions, dan review due
- Filament Shield + policy repo-native + role template granular
- Feature tests untuk domain core, audit/CAPA, performance/review, document workflow, dan reminder deduplication
- GitHub Actions workflow untuk menjalankan Laravel tests

## Role template

Role hanya template pengelompokan. Otorisasi tetap berbasis permission granular.

```text
iso_manager          seluruh fungsi ISO
iso_coordinator      template gabungan untuk organisasi kecil

document_controller review/control dokumen
document_approver    approval dokumen
internal_auditor     audit & finding
process_owner        risk, objective, finding & CAPA
top_management       approval, objective, compliance & management review
employee             acknowledgement dokumen
```

Contoh custom permission workflow:

```text
document.submit_review
document.review
document.review_any
document.approve
document.approve_any
document.make_effective
document.obsolete
document.acknowledge

audit.perform
finding.respond
capa.manage
capa.verify
risk.manage
objective.manage
compliance.manage
management_review.manage
activity_log.view
```

CRUD resource tetap memakai permission Filament Shield seperti `view_any_document`, `create_document`, `update_document`, dan seterusnya.

## Instalasi development

Repo ini masih greenfield. Struktur lama `M001/M002/M003` telah diganti oleh domain model baru. Untuk database development yang masih kosong:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan shield:generate --all
php artisan db:seed --class=IsoRolePermissionSeeder
php artisan test
npm install
npm run build
php artisan serve
```

Policy domain utama sudah disimpan di repository. `shield:generate --all` digunakan untuk sinkronisasi policy/permission Filament lainnya, bukan sebagai satu-satunya pengaman resource.

> `migrate:fresh` bersifat destruktif. Gunakan hanya selama belum ada data produksi yang perlu dipertahankan.

## Scheduler dan reminder

Reminder dijalankan oleh Laravel Scheduler setiap hari pukul 07:30. Server production harus menjalankan scheduler Laravel, misalnya melalui cron:

```cron
* * * * * cd /path/to/iso-km && php artisan schedule:run >> /dev/null 2>&1
```

Reminder dikirim pada D-30, D-14, D-7, D-3, D-1, hari H, kemudian mingguan saat overdue. `reminder_dispatches` mencegah notifikasi threshold yang sama terkirim dua kali.

Untuk pengujian manual:

```bash
php artisan iso:send-reminders
```

## Document workflow

Status revision adalah source of truth workflow. Status dokumen menunjukkan controlled document yang berlaku.

```text
Draft
  ↓ submit
In Review
  ├── reject → Rejected → revisi diperbaiki → submit kembali
  ↓ review OK
Reviewed
  ↓ approve
Approved
  ↓ make effective
Effective
  ↓ obsolete / digantikan revisi baru
Obsolete
```

Saat revisi baru menjadi effective:

1. revisi effective sebelumnya otomatis menjadi obsolete;
2. hanya satu revision yang memiliki `is_current = true`;
3. `next_review_at` dihitung dari interval review dokumen;
4. controlled document baru didistribusikan kembali kepada daftar penerima;
5. acknowledgement penerima di-reset untuk revision baru;
6. penerima mendapat database notification.

## Audit trail

Audit trail mencatat:

- actor;
- waktu;
- object type dan object id;
- event create/update/delete;
- nilai sebelum dan sesudah;
- IP address dan user agent untuk request web.

Audit trail bersifat read-only di UI dan memerlukan permission `activity_log.view`.

## Prinsip desain

```text
Requirement / Control
       ↓
Implementation
       ↓
Document + Evidence + Risk
       ↓
Assessment / Audit
       ↓
Finding / CAPA
       ↓
Objectives + Management Review
       ↓
Continual Improvement
```

## Roadmap berikutnya

1. Lengkapi katalog ISO/IEC 27001:2022 sesuai lisensi standar organisasi.
2. Training, awareness, competence & training effectiveness register.
3. Supplier / third-party security and compliance assessment.
4. Evidence expiry workflow dan recurring evidence collection.
5. Auditor pack/export serta external-auditor read-only portal.
6. Cross-standard mapping untuk ISO 27001, ISO 9001, ISO 22301, dan standar lain.
7. Dashboard per role dan management heatmap yang lebih matang.
8. Hardening backup, retention, log integrity, dan production deployment baseline.
