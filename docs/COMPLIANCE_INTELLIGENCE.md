# Compliance Intelligence Design

Dokumen ini menjelaskan cara ISO-KM menerjemahkan data operasional menjadi assurance dan action tanpa mengklaim compliance secara otomatis.

## Source of truth

- RequirementAssessment = keputusan readiness requirement.
- StatementOfApplicability = applicability dan implementation statement control.
- Evidence = bukti faktual; verification menyatakan bukti telah diperiksa, bukan otomatis bahwa control compliant.
- Risk = risk state dan treatment.
- AuditFinding = hasil assessment/audit.
- CorrectiveAction = response dan effectiveness.
- ActionItem = projection pekerjaan; bukan source-of-truth domain.
- AssuranceSnapshot = cached analytical view; dapat dibangun ulang dari source data.

## Assurance dimensions

### Requirement Readiness

`conformant/implemented = 1`, `partial/in_progress = 0.5`, status lain = 0. Denominator adalah seluruh requirement assessment pada management system.

### Control Implementation

Jumlah SoA control applicable berstatus implemented/effective dibanding seluruh applicable controls.

### Verified Evidence Coverage

Jumlah applicable control yang memiliki setidaknya satu evidence `active + verified + non-expired` dibanding seluruh applicable controls.

### Evidence Freshness

Evidence non-archived yang belum expired dibanding seluruh evidence non-archived.

### Risk Health

- belum ada risk register → 0, bukan 100;
- risk ada dan tidak ada open risk → 100;
- lainnya diturunkan berdasarkan proporsi high open risk.

### Findings Health

- belum ada completed audit → 0, bukan 100;
- completed audit sudah ada dan tidak ada finding → 100;
- lainnya diturunkan berdasarkan proporsi open finding.

### Action Health

Diturunkan berdasarkan proporsi overdue action terhadap seluruh open action.

## Assurance confidence

Confidence merupakan indikator kekuatan dasar pembuktian, bukan sertifikasi. Point diberikan hanya ketika dimensi memiliki denominator/fakta yang memadai. Ketiadaan risk register atau completed audit tidak menghasilkan point.

## Analyze → Act

Generated Action Items saat ini berasal dari:

- requirement nonconformant/partial;
- applicable control belum implemented;
- high residual/inherent risk;
- recurring evidence request;
- document review;
- document acknowledgement;
- audit preparation;
- CAPA;
- objective at risk / period ending;
- compliance obligation review;
- management review dan actions.

Ketika kondisi sumber tidak lagi memenuhi aturan, generated action otomatis menjadi completed pada sync berikutnya.

## Rebuildability

`action_items` generated dan `assurance_snapshots` adalah projection/cache. Keduanya harus dapat diregenerasi dari source-of-truth dengan:

```bash
php artisan iso:refresh-intelligence
```

Ini sengaja dilakukan agar analytical layer tidak menjadi database kebenaran kedua.

## Safety rule

ISO-KM tidak boleh menampilkan label “certified”, “ISO compliant”, atau “100% compliant” hanya berdasarkan kalkulasi internal. Certification dan formal conformity conclusions berasal dari proses assessment/audit yang berwenang.
