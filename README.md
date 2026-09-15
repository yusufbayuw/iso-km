# ISO-KM

ISO-KM adalah **compliance intelligence & assurance system** berbasis Laravel + Filament untuk menjalankan management system secara operasional. Tujuannya bukan memindahkan spreadsheet ISO ke browser, tetapi membuat data yang sama dapat dipakai ulang untuk assessment, risk, evidence, audit, action, dashboard, dan laporan.

> Prinsip produk: **Input once → connect → analyze → act → assure.**

## Arsitektur domain

```text
Standard
├── Requirements (Clause / Subclause / Requirement)
└── Controls (mis. Annex A)

Compliance Scope
└── Management System
    ├── Requirement Assessments
    ├── Statement of Applicability
    ├── Documents & Revisions
    ├── Evidence + Recurring Evidence Requests
    ├── Risks
    ├── Audits → Findings → CAPA
    ├── Objectives & KPI
    ├── Compliance Obligations
    ├── Management Reviews → Actions
    ├── Unified Action Items / My Work
    └── Assurance Snapshot & Reports
```

Master standard dipisahkan dari implementasi organisasi. Requirement, control, risk, document, evidence, audit, dan action saling direlasikan; informasi yang sama tidak perlu diketik ulang hanya karena muncul di modul lain.

## Compliance intelligence spine

### 1. Capture

Data operasional dikumpulkan melalui document control, evidence, recurring evidence requests, risk, audit, CAPA, KPI, compliance obligation, dan management review.

### 2. Connect

Evidence schedule dapat dimapping ke requirement dan control. Ketika evidence request disubmit, mapping tersebut diwariskan otomatis ke evidence yang dihasilkan.

### 3. Analyze

`AssuranceAnalysisService` menghitung beberapa dimensi terpisah:

- Requirement Readiness
- Control Implementation
- Verified Evidence Coverage
- Evidence Freshness
- Risk Health
- Findings Health
- Action Health
- Assurance Confidence

Tidak ada satu angka **“ISO compliant %”**. Ketiadaan data risk atau audit juga tidak dianggap sehat; no data menurunkan confidence.

### 4. Act

`ActionItemSyncService` memproyeksikan pekerjaan lintas modul ke **My Work**:

- evidence collection;
- document review;
- document acknowledgement;
- requirement gap;
- control implementation;
- high-risk treatment;
- audit preparation;
- CAPA;
- objective review;
- compliance obligation review;
- management review dan tindak lanjutnya.

Action yang berasal dari sumber domain ditutup otomatis ketika kondisi sumbernya sudah terselesaikan. Action manual tetap dapat dibuat untuk pekerjaan ad-hoc.

### 5. Assure

Assurance Overview menyimpan snapshot per management system sehingga dashboard dan laporan tidak harus menghitung seluruh graph dari nol setiap kali dibuka.

`Statement of Applicability` memiliki **Assurance 360** yang menampilkan dalam satu view:

- requirement terkait;
- controlled documents dan current revision;
- evidence, verification, lifecycle, dan expiry;
- linked risks;
- audit findings;
- CAPA.

Assurance 360 adalah traceability view, bukan keputusan compliance otomatis.

## Evidence lifecycle

```text
Evidence Schedule
      ↓
Evidence Request
      ↓
Requested / Overdue
      ↓ submit
Submitted
      ↓ independent verification
Verified
```

Evidence memiliki metadata source, confidentiality, captured date, valid-from, expiry, lifecycle, verification status, verifier, verification time, dan verification notes.

Recurring schedule mendukung:

- weekly;
- monthly;
- quarterly;
- semiannual;
- annual;
- custom interval.

Scheduler membuat evidence request berdasarkan `lead_days`. Request muncul di My Work dan reminder engine. Evidence yang melewati `expires_at` otomatis menjadi expired.

Verifikasi tidak dilakukan melalui edit dropdown. Action verify/reject harus melalui `EvidenceLifecycleService`, sehingga verifier, waktu, dan catatan tercatat.

## Document control

Lifecycle revision:

```text
Draft
  ↓ submit
In Review
  ├── reject → Rejected → rework → submit kembali
  ↓ review OK
Reviewed
  ↓ approve
Approved
  ↓ make effective
Effective
  ↓ obsolete / replaced
Obsolete
```

Saat revision baru effective:

1. revision effective sebelumnya menjadi obsolete;
2. hanya satu revision yang `is_current = true`;
3. document review date dihitung ulang;
4. controlled copy didistribusikan ulang;
5. acknowledgement penerima di-reset untuk revision baru;
6. penerima mendapat database notification.

## My Work

My Work adalah lapisan kerja, bukan duplikasi source-of-truth. Generated action menyimpan morph reference ke object sumber.

User biasa melihat action miliknya. Permission `action_item.view_all` membuka seluruh queue untuk ISO Manager / Top Management sesuai role template.

Prioritas generated work ditentukan dari sifat gap dan due date:

- overdue → critical;
- due ≤ 3 hari → high;
- due ≤ 14 hari → medium;
- high risk / requirement gap / objective at risk → minimal high.

## Assurance reporting

Assurance Overview menyediakan CSV baseline per management system yang berisi:

- identitas standard dan scope;
- seluruh assurance dimensions;
- attention counts;
- open/high risks;
- open findings;
- open actions;
- evidence summary.

Export memerlukan permission `assurance.report` karena laporan dapat memuat informasi risk, findings, dan action lintas fungsi.

## Permission & role templates

Role adalah template convenience; **permission adalah source of truth authorization**.

Template awal:

```text
iso_manager          seluruh fungsi ISO
iso_coordinator      gabungan untuk organisasi kecil
document_controller document + evidence control
document_approver    document approval
internal_auditor     audit, assurance, evidence verification
process_owner        risk, objective, evidence, finding & CAPA
top_management       assurance, reports, governance & management review
employee             assigned work + acknowledgement
```

Custom permissions penting antara lain:

```text
document.submit_review
document.review
document.approve
document.make_effective
document.acknowledge

evidence.manage
evidence.verify
evidence_request.view_all

action_item.view_all
assurance.refresh
assurance.report

audit.perform
finding.respond
capa.manage
capa.verify
risk.manage
objective.manage
management_review.manage
activity_log.view
```

CRUD resource tetap memakai permission Filament Shield seperti `view_any_evidence`, `create_evidence`, `update_evidence`, dst.

## Audit trail

Perubahan object keputusan utama dicatat ke audit trail dengan:

- actor;
- timestamp;
- subject type/id;
- event create/update/delete;
- before/after values;
- IP dan user-agent untuk request web.

Audit trail bersifat read-only dan memerlukan `activity_log.view`.

## Scheduler

```php
07:00  iso:refresh-intelligence
07:30  iso:send-reminders
```

`iso:refresh-intelligence` menjalankan urutan:

1. expire evidence yang sudah tidak valid;
2. generate recurring evidence requests;
3. sinkronisasi My Work dari source domain;
4. hitung ulang assurance snapshots.

Server production harus menjalankan Laravel Scheduler:

```cron
* * * * * cd /path/to/iso-km && php artisan schedule:run >> /dev/null 2>&1
```

Perintah manual:

```bash
php artisan iso:refresh-intelligence
php artisan iso:send-reminders
```

Reminder menggunakan threshold D-30, D-14, D-7, D-3, D-1, hari H, lalu mingguan saat overdue. `reminder_dispatches` mencegah threshold yang sama terkirim dua kali.

## Instalasi development

Repo masih greenfield. Untuk database development yang belum berisi data penting:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan shield:generate --all
php artisan iso:refresh-intelligence
php artisan test
npm install
npm run build
php artisan serve
```

> `migrate:fresh` destruktif. Jangan gunakan ketika sudah ada data produksi.

Policy domain utama disimpan di repository. `shield:generate --all` dipakai untuk sinkronisasi resource tambahan, bukan sebagai satu-satunya pengaman aplikasi.

## Testing

Feature tests mencakup antara lain:

- domain ISO core;
- audit / finding / CAPA;
- document lifecycle;
- reminder deduplication;
- recurring evidence → request → submission → verification;
- evidence inheritance ke requirement/control;
- evidence request → My Work;
- requirement gap → generated action → auto close ketika resolved;
- assurance analysis;
- no-data tidak dianggap healthy;
- idempotensi `iso:refresh-intelligence`.

GitHub Actions menjalankan test suite dengan SQLite in-memory.

## Prinsip desain

```text
CAPTURE
   ↓
STRUCTURE & CONNECT
   ↓
ANALYZE
   ↓
ACT
   ↓
ASSURE
```

Aturan implementasi utama:

1. Input data hanya sekali bila fakta yang sama dapat direferensikan.
2. Fact dipisahkan dari assessment.
3. “Evidence exists” tidak identik dengan “compliant”.
4. Tidak ada data tidak boleh menghasilkan assurance hijau palsu.
5. Generated report adalah view atas source-of-truth, bukan form baru yang harus diketik ulang.
6. AI nantinya hanya membantu klasifikasi, mapping, dan summarization; keputusan compliance tetap manusia.

## Roadmap berikutnya

1. Perluas auditor workspace dari Control Assurance 360 menjadi Requirement/Control traceability matrix penuh.
2. Tambahkan SoA, Risk Register, CAPA, Audit Report, Management Review Pack, dan Auditor Pack export terstruktur (PDF/XLSX/ZIP sesuai kebutuhan).
3. Evidence request delegation, escalation, dan bulk verification untuk organisasi besar.
4. Training, awareness, competence, dan effectiveness sebagai source domain baru yang otomatis masuk ke My Work/Assurance.
5. Supplier / third-party security assessment dengan pola yang sama.
6. Integrasi evidence dari sistem internal/API untuk mengurangi input manual.
7. Dashboard per persona dan management heatmap.
8. Backup, retention, log-integrity, dan production hardening.
