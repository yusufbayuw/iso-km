# ISO-KM

ISO-KM adalah **compliance intelligence & assurance system** berbasis Laravel + Filament untuk menjalankan management system secara operasional. Tujuannya bukan memindahkan spreadsheet ISO ke browser, tetapi membuat data yang sama dapat dipakai ulang untuk assessment, risk, evidence, people competence, supplier assurance, audit, action, dashboard, traceability, dan laporan.

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
    ├── Training & Awareness → Assignments → Verification
    ├── Competence Register
    ├── Suppliers / Third Parties → Controlled Assessments
    ├── Unified Action Items / My Work
    ├── Assurance Snapshot & Reports
    ├── Cross-Standard Mapping → Evidence Reuse
    ├── External Audit Access (scoped & expiring)
    ├── External Auditor Workspace → Sanitized Audit Pack
    └── Internal Auditor Workspace → Auditor Pack
```

Master standard dipisahkan dari implementasi organisasi. Requirement, control, risk, document, evidence, training, competence, supplier, audit, dan action saling direlasikan; informasi yang sama tidak perlu diketik ulang hanya karena muncul di modul lain.

## Compliance intelligence spine

### 1. Capture

Data operasional dikumpulkan melalui document control, evidence, recurring evidence requests, risk, audit, CAPA, KPI, compliance obligation, management review, training completion, competence assessment, dan supplier assessment.

### 2. Connect

Evidence schedule dapat dimapping ke requirement dan control. Ketika evidence request disubmit, mapping tersebut diwariskan otomatis ke evidence yang dihasilkan.

Training program, competency record, dan supplier juga dapat dimapping ke requirement/control. Supplier evidence dapat direferensikan tanpa upload ulang.

Untuk multi-standard, requirement dan control dapat dipetakan lintas standard. Evidence tetap satu source-of-truth dan hanya direuse melalui mapping yang sudah approved serta acceptance manusia pada target management system.

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

Tidak ada satu angka **“ISO compliant %”**. Ketiadaan data risk atau audit juga tidak dianggap sehat; no data menurunkan confidence. Training, supplier, dan cross-standard reuse menjadi assurance context, bukan keputusan compliance otomatis.

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
- training assignment dan verification handoff;
- competence renewal;
- supplier review;
- supplier assessment execution dan review;
- supplier reassessment;
- high/critical supplier treatment.

Action yang berasal dari source domain ditutup otomatis ketika kondisi sumbernya sudah terselesaikan. Action manual tetap dapat dibuat untuk pekerjaan ad-hoc.

### 5. Assure

Assurance Overview menyimpan snapshot per management system sehingga dashboard dan laporan tidak harus menghitung seluruh graph dari nol setiap kali dibuka.

`Statement of Applicability` memiliki **Assurance 360** yang menampilkan requirement, controlled documents/current revision, evidence, risk, audit findings, dan CAPA dalam satu view.

**Internal Auditor Workspace** memperluas traceability sampai level requirement dan menampilkan:

- linked controls;
- document/effective revision coverage;
- direct verified/fresh evidence;
- accepted cross-standard reused evidence secara terpisah;
- open/high risks;
- training programs;
- competence records;
- suppliers dan high-risk suppliers;
- open findings;
- open CAPA;
- audit attention state.

Assurance 360, internal Auditor Workspace, dan External Auditor Workspace adalah traceability views, bukan keputusan compliance otomatis.

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
5. pending acknowledgement revision lama menjadi `superseded`;
6. penerima mendapat database notification.

Employee mengakses controlled copy melalui `/portal/my-documents`. Download memakai authenticated route, bukan URL storage publik.

## Training, awareness & competence

`TrainingProgram` menyimpan awareness/training/certification/exercise/onboarding per management system dan dapat dimapping ke requirement/control.

Setiap program mempunyai `TrainingAssignment` per person dan `cycle_key`, sehingga program berulang tidak menimpa histori periode sebelumnya. Assignment adalah record terkontrol tersendiri dan tidak diedit melalui repeater program.

Self-service tersedia di `/portal/my-training`:

```text
Assigned
  ↓ employee start
In Progress
  ↓ submit evidence / notes
Submitted
  ↓ coordinator verify
Completed / Failed / Exempt

Submitted
  ↓ reject
Rejected → rework → submit kembali
```

Submission memindahkan pekerjaan dari employee ke verifier. Evidence training mengikuti hasil verification. Training Program yang sudah memiliki assignment tidak dapat dihapus; gunakan `paused` atau `retired`.

`CompetencyRecord` menyimpan kompetensi personel berdasarkan training, sertifikasi, pengalaman, atau assessment. Record dapat mempunyai validity period dan evidence serta mapping requirement/control.

## Supplier / third-party assurance

Supplier adalah master pihak ketiga yang dapat dipakai oleh beberapa management system. Supplier menyimpan owner internal, criticality, review cycle, linked requirement/control, dan linked evidence.

`SupplierAssessment` disimpan per supplier + management system agar satu supplier dapat memiliki risk posture berbeda pada scope yang berbeda.

Assessment mengikuti state machine terkontrol:

```text
Planned
  ↓ request
Requested
  ↓ start
In Progress
  ↓ submit
Submitted
  ↓ review
Under Review
  ↓ decision
Accepted / Conditional / Rejected

Conditional
  ↓ treatment verification
Accepted

Accepted / Rejected
  ↓ close
Closed
```

Permission execution, review, decision, dan treatment verification dipisahkan. Supplier yang sudah mempunyai assessment tidak dapat dihapus agar histori assurance tetap utuh; gunakan status lifecycle supplier.

## Cross-standard mapping & evidence reuse

ISO-KM mendukung:

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

Setiap mapping menyimpan `coverage_percent`, rationale, mapper, reviewer, timestamp, dan status review. `coverage_percent` adalah metadata mapping, **bukan persentase compliance**.

Evidence reuse hanya dapat diterima bila:

1. mapping sudah approved;
2. source dan target berada pada compliance scope yang sama tetapi standard berbeda;
3. evidence benar-benar linked ke requirement/control sumber;
4. evidence aktif, verified, dan belum expired;
5. actor memiliki `cross_standard_reuse.accept`.

Evidence **tidak disalin**. `EvidenceReuse` hanya menghubungkan evidence asli ke target requirement/control. Karena itu expiry, verification status, dan provenance tetap mengikuti source evidence.

Coverage workspace tersedia di `/portal/cross-standard-workspace` dan membedakan `available`, `accepted`, serta `rejected` reuse. Traceability selalu menampilkan direct evidence dan reused evidence sebagai dua provenance berbeda.

## My Work

My Work adalah lapisan kerja, bukan duplikasi source-of-truth. Generated action menyimpan morph reference ke object sumber.

User biasa melihat action miliknya. Permission `action_item.view_all` membuka seluruh queue untuk role/permission yang berwenang.

Prioritas generated work ditentukan dari sifat gap dan due date:

- overdue → critical;
- due ≤ 3 hari → high;
- due ≤ 14 hari → medium;
- high risk / requirement gap / objective at risk / high-risk supplier treatment → minimal high.

## Assurance reporting & audit packs

### Internal Auditor Workspace

Internal workspace tersedia di `/portal/auditor-workspace`. Export ZIP membutuhkan `audit_pack.export` dan menghasilkan source-of-truth registers:

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

Auditor Pack adalah export read-only atas source-of-truth; tidak membuat tabel laporan kedua.

### External Auditor Workspace

External workspace tersedia di `/portal/external-auditor-workspace`.

Akses external auditor tidak diberikan ke seluruh panel/data. Akses membutuhkan:

```text
portal.access
+
external_auditor_workspace.view
+
ExternalAuditAccess aktif untuk management system tertentu
```

Grant dapat memiliki `valid_from`, `expires_at`, revoke actor/timestamp, serta flag `allow_audit_pack`.

External Auditor Pack sengaja lebih sempit:

```text
manifest.json
traceability-matrix.csv
statement-of-applicability.csv
evidence-index.csv
cross-standard-evidence-reuse.csv
findings-summary.csv
```

Pack external **tidak memasukkan raw evidence files**. Export memerlukan permission `external_audit_pack.export` dan `allow_audit_pack = true` pada grant aktif; validasi dilakukan server-side.

Dokumentasi governance lengkap tersedia di `docs/EXTERNAL_AUDIT_AND_CROSS_STANDARD.md`.

## Permission & role templates

Role adalah template convenience; **permission adalah source of truth authorization**.

Template awal:

```text
iso_manager           seluruh fungsi ISO
iso_coordinator       gabungan untuk organisasi kecil
document_controller  document + evidence control
document_approver     document approval
internal_auditor      audit, assurance, evidence, traceability
process_owner         risk, objective, evidence, finding/CAPA + assigned work
training_coordinator  training, verification, competence register
third_party_owner     supplier dan supplier assessment
top_management        assurance, reports, governance & management review
employee              assigned work + acknowledgement + assigned training
external_auditor      scoped external audit workspace only
```

Custom permissions penting antara lain:

```text
portal.access

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

external_audit_access.manage
external_auditor_workspace.view
external_audit_pack.export

cross_standard_mapping.manage
cross_standard_mapping.approve
cross_standard_workspace.view
cross_standard_reuse.accept

audit.perform
finding.respond
capa.manage
capa.verify
risk.manage
objective.manage
management_review.manage

training.manage
training.complete
training.verify
competency.manage

supplier.manage
supplier.assess
supplier.review
supplier.decide
supplier.verify_treatment

activity_log.view
```

CRUD resource tetap memakai permission Filament Shield seperti `view_any_training::program`, `create_supplier`, `update_supplier::assessment`, `view_any_requirement::mapping`, dst.

`User::canAccessPanel()` memakai `portal.access` setelah permission tersebut tersedia. Implementasi dibuat deployment-safe: sebelum permission pertama kali disemai, akses existing tidak langsung terkunci; setelah seeder berjalan, permission menjadi gate panel.

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

PHP extension `zip` diperlukan untuk internal maupun external audit pack. Review catatan `syncPermissions()` sebelum menjalankan permission seeder pada role yang sudah dikustom di production.

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
- internal Auditor Workspace render dan traceability;
- internal Auditor Pack ZIP/source-of-truth exports;
- employee training self-service → verification handoff;
- authenticated controlled-document download dan acknowledgement;
- controlled supplier assessment state transitions;
- training / competence / supplier → My Work + assurance + traceability;
- external auditor grant isolation;
- sanitized External Auditor Pack tanpa raw evidence;
- approved cross-standard mapping → evidence reuse tanpa menyalin source evidence;
- direct vs reused evidence traceability;
- rejection terhadap same-standard mapping.

GitHub Actions menjalankan test suite dengan SQLite in-memory. Baseline saat ini tervalidasi dengan **22 tests / 145 assertions**.

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
6. High-risk supplier, expired competence, overdue training, atau reused evidence menjadi context/action; bukan klaim compliance otomatis.
7. Provenance harus tetap terlihat: direct evidence dan reused evidence tidak boleh disamarkan menjadi satu sumber.
8. Akses eksternal harus scoped, revocable, dan sebisa mungkin time-limited.
9. AI nantinya hanya membantu klasifikasi, mapping, dan summarization; keputusan compliance tetap manusia.

## Roadmap berikutnya

1. Delegation, escalation, dan bulk operation untuk evidence, training, supplier, CAPA, serta My Work pada organisasi besar.
2. Integrasi evidence dari sistem internal/API untuk mengurangi input manual dan memperkuat provenance.
3. Dashboard per persona, management heatmap, dan multi-management-system executive view.
4. Import tooling untuk standard catalog dan mapping catalog dari sumber organisasi yang berlisensi/diizinkan.
5. Mapping revalidation ketika edition standard berubah agar reuse lama tidak dianggap valid tanpa review.
6. Periodic access review/recertification untuk role, permission, dan external audit grants.
7. Backup, retention, immutable/log-integrity controls, disaster recovery, dan production hardening.
8. Selective evidence-sharing workflow jika external auditor perlu meminta raw evidence tertentu dengan approval eksplisit, bukan akses massal.
