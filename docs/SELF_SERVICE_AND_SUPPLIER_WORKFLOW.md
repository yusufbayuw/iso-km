# Employee Self-Service & Supplier Assessment Workflow

Dokumen ini menjelaskan workflow operasional yang enforced oleh ISO-KM. Status bukan sekadar dropdown; transisi penting dijalankan melalui service dan permission granular agar actor, timestamp, evidence, dan tindak lanjut tetap dapat diaudit.

## Employee self-service

Employee tidak membutuhkan akses CRUD ke master ISO untuk menyelesaikan kewajibannya.

### Training Saya

URL Filament: `/portal/my-training`

Alur:

```text
Assigned
   ↓ employee starts
In Progress
   ↓ employee submits evidence / notes
Submitted
   ↓ coordinator verifies
Completed / Failed / Exempt

Submitted
   ↓ coordinator rejects
Rejected
   ↓ employee reworks and submits again
```

Aturan:

- employee hanya melihat assignment miliknya sendiri;
- `training.complete` diperlukan untuk start/submit;
- bukti yang di-upload dari self-service dibuat sebagai Evidence dengan verification `pending`;
- mapping requirement/control diwarisi dari Training Program;
- submission membuat task `training_verification` untuk owner program;
- verifikasi memerlukan `training.verify`;
- verifier, verification timestamp, result, score, dan notes disimpan;
- evidence ikut menjadi `verified` atau `rejected` berdasarkan hasil verifikasi;
- completion history tidak diedit dari Training Program;
- Training Program yang sudah mempunyai assignment tidak dapat dihapus; gunakan `paused` atau `retired`.

### Dokumen Saya

URL Filament: `/portal/my-documents`

Employee hanya melihat controlled copy yang didistribusikan kepadanya.

Aturan:

- download dilakukan melalui authenticated controlled-copy route, bukan URL storage publik;
- user hanya dapat mengunduh distribution miliknya, kecuali memiliki permission document view;
- acknowledgement memerlukan `document.acknowledge`;
- hanya revision `effective` dengan distribution `pending` yang dapat di-acknowledge;
- saat revision baru menjadi effective, pending distribution revision lama menjadi `superseded`;
- saat document/revision di-obsolete, pending acknowledgement revision tersebut juga menjadi `superseded`;
- My Work hanya mempertahankan acknowledgement yang masih relevan.

## Supplier assessment workflow

Lifecycle:

```text
Planned
   ↓ Request
Requested
   ↓ Start
In Progress
   ↓ Submit
Submitted
   ↓ Review
Under Review
   ↓ Decision
Accepted / Conditional / Rejected

Conditional
   ↓ Treatment Verification
Accepted

Accepted / Rejected
   ↓ Close
Closed
```

### Permissions

- `supplier.assess`: request, start, dan submit assessment;
- `supplier.review`: memulai independent review;
- `supplier.decide`: keputusan accepted/conditional/rejected dan close;
- `supplier.verify_treatment`: verifikasi treatment untuk conditional acceptance.

Role hanyalah preset. Permission tetap menjadi source of truth authorization.

### Data audit workflow

Assessment menyimpan:

- requester + requested timestamp;
- assessor + assessment date;
- submitted timestamp;
- reviewer + reviewed timestamp + review notes;
- decision maker + decided timestamp;
- score dan risk rating;
- required actions;
- treatment verifier + verification timestamp + notes;
- linked evidence dan next review date.

### My Work projection

Supplier workflow diproyeksikan menjadi task berdasarkan state:

- `supplier_assessment_execution`: assessment requested/in progress;
- `supplier_assessment_review`: assessment submitted dan menunggu review;
- `supplier_assessment`: reassessment/review cycle yang jatuh tempo;
- `supplier_treatment`: conditional atau high/critical supplier yang belum memiliki treatment verification.

Setelah treatment diverifikasi, task treatment ditutup tanpa mengubah historical risk rating secara artifisial.

## Ownership integrity

Generated work membutuhkan PIC yang jelas. UI mewajibkan:

- owner pada Training Program;
- owner internal pada Supplier.

Supplier yang sudah mempunyai assessment tidak dapat dihapus dari UI agar histori assurance tidak hilang. Gunakan status `inactive`, `suspended`, atau `terminated` bila hubungan supplier berakhir.

## Testing contract

Feature tests memverifikasi:

- employee training task → submission → coordinator verification;
- evidence training mengikuti hasil verification;
- participant task selesai dan verification task berpindah ke coordinator;
- halaman `Training Saya` dapat dirender;
- authenticated controlled-document download;
- document acknowledgement dan halaman `Dokumen Saya`;
- supplier workflow end-to-end;
- illegal supplier state transition ditolak;
- supplier treatment task otomatis ditutup setelah treatment diverifikasi.
