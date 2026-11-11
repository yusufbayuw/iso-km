<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan Asesmen {{ $run->id }} - {{ $isFinal ? 'FINAL' : 'DRAFT' }}</title>
<style>
:root{color-scheme:light}body{font-family:Arial,Helvetica,sans-serif;margin:2.5rem;color:#202c40;font-size:14px;line-height:1.5}
h1{font-size:25px;color:#163456;margin-bottom:0}h2{font-size:18px;margin-top:2rem;color:#163456}
.muted{color:#5d6b7f}.pill{display:inline-block;padding:.2rem .6rem;background:#e7edf7;border-radius:6px;font-weight:bold}
.badge{padding:.7rem 1rem;background:#fff1cf;border:1px solid #e2ba61;margin:1rem 0}
.final{background:#e7f6e9;border-color:#9ac5a1}
.metrics{display:flex;gap:1rem;flex-wrap:wrap;margin:1rem 0}.metric{padding:1rem;background:#f4f6f9;min-width:150px;flex:1}
.metric strong{display:block;font-size:23px}
table{width:100%;border-collapse:collapse}th,td{padding:9px;text-align:left;border-bottom:1px solid #d9dfeb;vertical-align:top}
thead{background:#e7edf7}.controls{margin:1rem 0}
button{background:#1d4ed8;color:#fff;border:0;border-radius:8px;padding:.7rem 1rem;font-weight:bold;cursor:pointer}
@media print{body{margin:14mm}button{display:none}.metrics{page-break-inside:avoid}tr{page-break-inside:avoid}thead{display:table-header-group}}
</style>
</head>
<body>
<div style="display:flex;justify-content:space-between;align-items:center;gap:2rem">
<div><h1>Laporan Asesmen Keamanan Informasi</h1>
<p class="muted">ISO/IEC 27001:2022 • {{ $run->system?->name }}</p></div>
<span class="pill">{{ $isFinal ? 'FINAL' : 'DRAFT' }}</span>
</div>
<div class="badge {{ $isFinal ? 'final' : '' }}">
@if ($isFinal)
Hasil ini berasal dari keputusan yang telah disetujui dan dipublikasikan sebelum asesmen dikunci.
@else
<strong>DRAFT — BELUM DIVALIDASI.</strong> Isian dan hasil penilaian dapat berubah.
Laporan ini bukan hasil final dan bukan sertifikat ISO.
@endif
</div>
<table>
<tr><th>Nama asesmen</th><td>{{ $run->name }}</td></tr>
<tr><th>Ruang lingkup</th><td>{{ $run->system?->scope?->name }}</td></tr>
<tr><th>Dibuat oleh</th><td>{{ $run->creator?->name }}</td></tr>
<tr><th>Waktu laporan</th><td>{{ $generatedAt->format('d/m/Y H:i') }}</td></tr>
<tr><th>Waktu finalisasi</th><td>{{ $run->locked_at?->format('d/m/Y H:i') ?? 'Belum final' }}</td></tr>
</table>
<h2>Ringkasan Kemajuan</h2>
<div class="metrics">
<div class="metric"><strong>{{ $progress['assignments_completed'] }}/{{ $progress['assignments_total'] }}</strong>Penugasan selesai</div>
<div class="metric"><strong>{{ $progress['criteria_approved'] }}/{{ $progress['criteria_total'] }}</strong>Kriteria dipublikasikan</div>
<div class="metric"><strong>{{ $progress['revisions'] }}</strong>Keputusan memerlukan revisi</div>
</div>
@if ($progress['reasons'])
<h2>Hal-hal yang masih perlu diselesaikan</h2>
<ul>@foreach ($progress['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
@endif
<h2>Ringkasan Kriteria yang Telah Disetujui</h2>
<table>
<thead><tr><th>Kode</th><th>Status</th><th>Ringkasan</th></tr></thead>
<tbody>
@forelse ($approved as $decision)
<tr>
<td>{{ $decision->target->requirement?->code ?? $decision->target->control?->code }}</td>
<td>{{ $decision->proposed_status }}</td>
<td>{{ $decision->consolidation?->summary }}</td>
</tr>
@empty
<tr><td colspan="3">Belum ada keputusan disetujui yang dapat dilaporkan.</td></tr>
@endforelse
</tbody>
</table>
<h2>Penutup</h2>
<p>Hasil asesmen merupakan rekaman pemeriksaan internal sesuai lingkup dan bukti yang tersedia.
Penilaian ini tidak menyatakan atau menggantikan sertifikasi dari lembaga sertifikasi berwenang.</p>
<button onclick="window.print()">Cetak / Simpan sebagai PDF</button>
</body></html>
