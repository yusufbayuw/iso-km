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
    └── Risks
```

Pemisahan ini memungkinkan satu instalasi menangani beberapa standar, beberapa lingkup organisasi, dan evidence yang saling dipakai tanpa menggabungkan requirement dengan control.

## Modul fondasi

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
- Filament Shield sebagai fondasi permission

## Instalasi pengembangan

Repo ini masih greenfield. Struktur lama `M001/M002/M003` diganti oleh domain model baru. Untuk database development yang masih kosong gunakan:

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

> `migrate:fresh` bersifat destruktif. Gunakan hanya karena versi repo saat ini belum memiliki data produksi yang perlu dipertahankan.

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
Management Review & Continual Improvement
```

Roadmap berikutnya adalah workflow approval dokumen, audit internal, NCR/CAPA, objectives & KPI, compliance obligations, management review, reminders, audit trail, external-auditor portal, dan cross-standard mapping.
