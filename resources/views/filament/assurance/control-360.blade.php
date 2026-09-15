@php
    $summary = $data['summary'];
    $entry = $data['entry'];
    $control = $data['control'];
@endphp

<div class="space-y-6">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @foreach([
            ['Dokumen', $summary['documents']],
            ['Evidence', $summary['evidences']],
            ['Verified + Fresh', $summary['verified_fresh_evidence']],
            ['Open Risk', $summary['open_risks']],
            ['Open Finding', $summary['open_findings']],
            ['Open CAPA', $summary['open_capa']],
        ] as [$label, $value])
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <div class="text-xs text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <div><span class="text-gray-500">Control:</span> <strong>{{ $control->code }} — {{ $control->title }}</strong></div>
            <div><span class="text-gray-500">Applicable:</span> {{ $entry->applicable ? 'Ya' : 'Tidak' }}</div>
            <div><span class="text-gray-500">Implementasi:</span> {{ $entry->implementation_status }}</div>
            <div><span class="text-gray-500">Owner:</span> {{ $entry->owner?->name ?? '-' }}</div>
        </div>
        @if($entry->justification)
            <div class="mt-3 text-sm"><span class="text-gray-500">Justifikasi:</span> {{ $entry->justification }}</div>
        @endif
        @if($entry->implementation_notes)
            <div class="mt-2 text-sm"><span class="text-gray-500">Catatan implementasi:</span> {{ $entry->implementation_notes }}</div>
        @endif
    </div>

    <div>
        <h3 class="mb-2 font-semibold">Requirement terkait</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="p-3">Kode</th><th class="p-3">Requirement</th></tr></thead>
                <tbody>@forelse($data['requirements'] as $requirement)<tr class="border-t border-gray-200 dark:border-white/10"><td class="p-3 font-medium">{{ $requirement->code }}</td><td class="p-3">{{ $requirement->title }}</td></tr>@empty<tr><td class="p-3 text-gray-500" colspan="2">Belum ada mapping requirement.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>

    <div>
        <h3 class="mb-2 font-semibold">Controlled documents</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="p-3">Kode</th><th class="p-3">Dokumen</th><th class="p-3">Status</th><th class="p-3">Revisi</th></tr></thead>
                <tbody>@forelse($data['documents'] as $document)<tr class="border-t border-gray-200 dark:border-white/10"><td class="p-3 font-medium">{{ $document->code }}</td><td class="p-3">{{ $document->title }}</td><td class="p-3">{{ $document->status }}</td><td class="p-3">{{ $document->currentRevision?->revision ?? '-' }}</td></tr>@empty<tr><td class="p-3 text-gray-500" colspan="4">Belum ada controlled document terkait.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>

    <div>
        <h3 class="mb-2 font-semibold">Evidence</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="p-3">Evidence</th><th class="p-3">Owner</th><th class="p-3">Verifikasi</th><th class="p-3">Lifecycle</th><th class="p-3">Berlaku sampai</th></tr></thead>
                <tbody>@forelse($data['evidences'] as $evidence)<tr class="border-t border-gray-200 dark:border-white/10"><td class="p-3">{{ $evidence->title }}</td><td class="p-3">{{ $evidence->owner?->name ?? '-' }}</td><td class="p-3">{{ $evidence->verification_status }}</td><td class="p-3">{{ $evidence->status }}</td><td class="p-3">{{ $evidence->expires_at?->format('d/m/Y') ?? 'Tidak dibatasi' }}</td></tr>@empty<tr><td class="p-3 text-gray-500" colspan="5">Belum ada evidence terkait.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>

    <div>
        <h3 class="mb-2 font-semibold">Risk</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="p-3">Kode</th><th class="p-3">Risk</th><th class="p-3">Owner</th><th class="p-3">Inherent</th><th class="p-3">Residual</th><th class="p-3">Status</th></tr></thead>
                <tbody>@forelse($data['risks'] as $risk)<tr class="border-t border-gray-200 dark:border-white/10"><td class="p-3 font-medium">{{ $risk->code }}</td><td class="p-3">{{ $risk->title }}</td><td class="p-3">{{ $risk->owner?->name ?? '-' }}</td><td class="p-3">{{ $risk->inherent_score }}</td><td class="p-3">{{ $risk->residual_score ?? '-' }}</td><td class="p-3">{{ $risk->status }}</td></tr>@empty<tr><td class="p-3 text-gray-500" colspan="6">Belum ada risk yang dimapping.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>

    <div>
        <h3 class="mb-2 font-semibold">Audit findings & CAPA</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="p-3">Finding</th><th class="p-3">Audit</th><th class="p-3">Severity</th><th class="p-3">Status</th><th class="p-3">CAPA</th></tr></thead>
                <tbody>@forelse($data['findings'] as $finding)<tr class="border-t border-gray-200 dark:border-white/10"><td class="p-3"><span class="font-medium">{{ $finding->code }}</span><br>{{ $finding->title }}</td><td class="p-3">{{ $finding->audit?->code ?? '-' }}</td><td class="p-3">{{ $finding->severity }}</td><td class="p-3">{{ $finding->status }}</td><td class="p-3">{{ $finding->correctiveActions->count() }}</td></tr>@empty<tr><td class="p-3 text-gray-500" colspan="5">Belum ada finding untuk control ini.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-500">Tampilan ini adalah traceability/assurance view. Ia tidak menyatakan control compliant secara otomatis; keputusan formal tetap berada pada assessment dan audit.</p>
</div>
