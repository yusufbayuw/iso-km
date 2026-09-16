# ISO-KM

ISO-KM adalah **compliance intelligence & assurance system** berbasis Laravel + Filament untuk menjalankan management system secara operasional. Tujuannya bukan memindahkan spreadsheet ISO ke browser, tetapi membuat data yang sama dapat dipakai ulang untuk assessment, risk, evidence, people competence, supplier assurance, audit, action, dashboard, dan laporan.

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
    ├── Training & Awareness → Assignments
    ├── Competence Register
    ├── Suppliers / Third Parties → Assessments
    ├── Unified Action Items / My Work
    ├── Assurance Snapshot & Reports
    └── Auditor Workspace → Auditor Pack
```

Master standard dipisahkan dari implementasi organisasi. Requirement, control, risk, document, evidence, training, competence, supplier, audit, dan action saling direlasikan; informasi yang sama tidak perlu diketik ulang hanya karena muncul di modul lain.

## Compliance intelligence spine

### 1. Capture

Data operasional dikumpulkan melalui document control, evidence, recurring evidence requests, risk, audit, CAPA, KPI, compliance obligation, management review, training completion, competence assessment, dan supplier assessment.

### 2. Connect

Evidence schedule dapat dimapping ke requirement dan control. Ketika evidence request disubmit, mapping tersebut diwariskan otomatis ke evidence yang dihasilkan.

Training program, competency record, dan supplier juga dapat dimapping ke requirement/control. Supplier evidence dapat direferensikan tanpa upload ulang.

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

Attention counts juga memantau overdue mandatory training, competence yang mendekati expiry, high-risk suppliers, dan supplier review yang jatuh tempo.

Tidak ada satu angka **“ISO compliant %”**. Ketiadaan data risk atau audit juga tidak dianggap sehat; no data menurunkan confidence. Training dan supplier saat ini dipakai sebagai assurance attention signal, bukan faktor otomatis yang mengubah keputusan compliance.

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
- management review dan tindak lanjutnya;
- training assignment;
- competence renewal;
- supplier review;
- supplier reassessment;
- high/critical supplier treatment.

Action yang berasal dari sumber domain ditutup otomatis ketika kondisi sumbernya sudah terselesaikan. Action manual tetap dapat dibuat untuk pekerjaan ad-hoc.

### 5. Assure

Assurance Overview menyimpan snapshot per management system sehingga dashboard dan laporan tidak harus menghitung seluruh graph dari nol setiap kali dibuka.

`Statement of Applicability` memiliki **Assurance 360** yang menampilkan requirement, controlled documents/current revision, evidence, risk, audit findings, dan CAPA dalam satu view.

**Auditor Workspace** memperluas traceability sampai level requirement dan menampilkan:

- linked controls;
- document/effective revision coverage;
- verified/fresh evidence;
- open/high risks;
- training programs;
- competence records;
- suppliers dan high-risk suppliers;
- open findings;
- open CAPA;
- audit attention state.

Assurance 360 dan Auditor Workspace adalah traceability views, bukan keputusan compliance otomatis.

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

Recurring schedule mendukung weekly, monthly, quarterly, semiannual, annual, dan custom interval. Scheduler membuat evidence request berdasarkan `lead_days`; request masuk My Work dan reminder engine. Evidence yang melewati `expires_at` otomatis menjadi expired.

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

## Training, awareness & competence

`TrainingProgram` menyimpan program awareness/training/certification/exercise/onboarding per management system dan dapat dimapping ke requirement/control.

Setiap program mempunyai `TrainingAssignment` per person dan `cycle_key`, sehingga program berulang tidak menimpa histori periode sebelumnya. Assignment menyimpan assigned/due/completed date, result, score, validity, dan evidence.

`CompetencyRecord` menyimpan kompetensi personel berdasarkan training, sertifikasi, pengalaman, atau assessment. Record dapat mempunyai validity period dan evidence serta mapping requirement/control.

Automation:

- training yang belum selesai muncul di My Work;
- mandatory training overdue masuk assurance attention;
- competence yang mendekati expiry menghasilkan renewal action;
- competence due ikut assurance attention.

## Supplier / third-party assurance

Supplier adalah master pihak ketiga yang dapat dipakai oleh beberapa management system. Supplier menyimpan owner internal, criticality, review cycle, linked requirement/control, dan linked evidence.

`SupplierAssessment` disimpan per supplier + management system agar satu supplier dapat memiliki risk posture berbeda pada scope yang berbeda. Assessment menyimpan assessor, dates, score, risk rating, status, evidence, summary, dan required actions.

Automation:

- supplier review yang mendekati due date → My Work;
- supplier reassessment due → My Work;
- assessment high/critical → high-priority supplier treatment;
- high-risk supplier menjadi attention pada requirement traceability;
- supplier evidence dan assessment masuk Auditor Pack.

## My Work

My Work adalah lapisan kerja, bukan duplikasi source-of-truth. Generated action menyimpan morph reference ke object sumber.

User biasa melihat action miliknya. Permission `action_item.view_all` membuka seluruh queue untuk ISO Manager / Top Management sesuai role template.

Prioritas generated work ditentukan dari sifat gap dan due date:

- overdue → critical;
- due ≤ 3 hari → high;
- due ≤ 14 hari → medium;
- high risk / requirement gap / objective at risk / high-risk supplier treatment → minimal high.

## Assurance reporting & Auditor Pack

Assurance Overview menyediakan CSV baseline per management system berisi identitas standard/scope, assurance dimensions, attention counts, risk/finding/action/evidence summary. Export memerlukan `assurance.report`.

Auditor Workspace tersedia di `/portal/auditor-workspace`. Export ZIP membutuhkan `audit_pack.export` dan menghasilkan source-of-truth registers berikut:

```text
manifest.json
traceability-matrix.csv
statement-of-applicability.csv
risk-register.csv
evidence-register.csv
training-register.csv
competence-register.csv
supplier-register.csv
supplier-assessments.csv
audit-register.csv
findings-capa.csv
management-review.csv
```

`manifest.json` menyimpan identitas management system, assurance snapshot, attention counts, traceability summary, daftar register, timestamp, dan disclaimer. Auditor Pack adalah export read-only; tidak membuat tabel laporan kedua.

## Permission & role templates

Role adalah template convenience; **permission adalah source of truth authorization**.

Template awal:

```text
iso_manager          seluruh fungsi ISO
iso_coordinator      gabungan untuk organisasi kecil
document_controller document + evidence control
document_approver    document approval
internal_auditor     audit, assurance, evidence + read people/supplier
process_owner        risk, objective, evidence, finding/CAPA + assigned training
training_coordinator training, awareness, competence register
third_party_owner    supplier dan supplier assessment
top_management       assurance, reports, governance & management review
employee             assigned work + acknowledgement + assigned training
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
auditor_workspace.view
audit_pack.export

audit.perform
finding.respond
capa.manage
capa.verify
risk.manage
objective.manage
management_review.manage

training.manage
training.complete
competency.manage
supplier.manage
supplier.assess

activity_log.view
```

CRUD resource tetap memakai permission Filament Shield seperti `view_any_training::program`, `create_supplier`, `update_supplier::assessment`, dst.

> `IsoRolePermissionSeeder` memakai `syncPermissions()` pada role template bawaan. Pada production yang role template-nya sudah dikustom manual, review perubahan permission sebelum menjalankan seeder ulang.

## Audit trail

Perubahan object keputusan utama dicatat ke audit trail dengan actor, timestamp, subject type/id, event create/update/delete, before/after values, serta IP/user-agent untuk request web.

Audit trail bersifat read-only dan memerlukan `activity_log.view`.

## Scheduler

```text
07:00  iso:refresh-intelligence
07:30  iso:send-reminders
```

`iso:refresh-intelligence` menjalankan:

1. expire evidence yang sudah tidak valid;
2. generate recurring evidence requests;
3. sinkronisasi My Work dari source domain, termasuk training/competence/supplier;
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

### Upgrade existing environment

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=IsoRolePermissionSeeder --force
php artisan iso:refresh-intelligence
php artisan optimize:clear
php artisan optimize
```

PHP extension `zip` diperlukan untuk Auditor Pack. Review catatan `syncPermissions()` di atas sebelum menjalankan permission seeder pada role yang sudah dikustom di production.

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
- assurance analysis dan no-data safety;
- idempotensi `iso:refresh-intelligence`;
- Auditor Workspace render dan traceability;
- Auditor Pack ZIP/source-of-truth exports;
- training assignment / competence renewal → My Work;
- supplier review / reassessment / high-risk treatment → My Work;
- people/supplier attention → assurance + traceability;
- people/supplier registers → Auditor Pack.

GitHub Actions menjalankan test suite dengan SQLite in-memory. Head implementasi people/supplier terakhir tervalidasi dengan **17 tests / 106 assertions**.

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
6. High-risk supplier, expired competence, atau overdue training menjadi attention/action, bukan klaim non-compliance otomatis.
7. AI nantinya hanya membantu klasifikasi, mapping, dan summarization; keputusan compliance tetap manusia.

## Roadmap berikutnya

1. Employee self-service untuk menyelesaikan assigned training, upload evidence, dan acknowledgement tanpa hak edit program.
2. Supplier reassessment workflow yang lebih ketat: request → submission → review → accept/conditional/reject → treatment verification.
3. Delegation, escalation, dan bulk operation untuk evidence/training/supplier pada organisasi besar.
4. External auditor read-only workspace dengan scoped, time-limited access.
5. Integrasi evidence dari sistem internal/API untuk mengurangi input manual.
6. Dashboard per persona dan management heatmap.
7. Cross-standard mapping untuk ISO 27001 / 9001 / 22301 tanpa duplikasi evidence.
8. Backup, retention, log-integrity, dan production hardening.
