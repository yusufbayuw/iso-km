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
- Document Control dengan revision history
- Evidence register
- Risk Register dengan inherent/residual score
- Internal Audit programme dan checklist
- Finding / NCR / OFI
- Corrective Action dan effectiveness verification
- Objectives & KPI measurement
- Compliance Obligations register
- Management Review dan action tracking
- Dashboard readiness, control implementation, high risk, open findings, objective at risk, overdue actions, dan review due
- Filament Shield sebagai fondasi permission
- Feature tests untuk domain core, audit/CAPA, dan performance/review
- GitHub Actions workflow untuk menjalankan Laravel tests

## Instalasi pengembangan

Repo ini masih greenfield. Struktur lama `M001/M002/M003` telah diganti oleh domain model baru. Untuk database development yang masih kosong gunakan:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan shield:generate --all
npm install
npm run build
php artisan serve
```

> `migrate:fresh` bersifat destruktif. Gunakan hanya selama belum ada data produksi yang perlu dipertahankan.

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
2. Perkuat document workflow: draft → review → approval → effective → obsolete, termasuk acknowledgement/distribution.
3. Training & awareness / competence register.
4. Supplier & third-party assessment.
5. Notification dan reminder terjadwal untuk due date, review, expiry, audit, CAPA, dan KPI.
6. Audit trail perubahan data dan histori keputusan penting.
7. Auditor pack / export dan external-auditor read-only portal.
8. Cross-standard mapping untuk ISO 27001, ISO 9001, ISO 22301, dan standar lain.
9. Hardening permission Filament Shield per resource dan role template organisasi.
