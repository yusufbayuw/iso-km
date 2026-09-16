<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 md:items-end">
            <div>
                <div class="text-sm font-medium">Authorized Management System</div>
                <div class="mt-1 text-xs text-gray-500">Workspace ini read-only. Data dibatasi oleh grant aktif dan tidak menyediakan CRUD internal.</div>
            </div>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="managementSystemId">
                    @foreach ($systems as $system)
                        <option value="{{ $system->id }}">{{ $system->code }} — {{ $system->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        @if ($access)
            <div class="mt-3 text-xs text-gray-500">Access expires: {{ $access->expires_at?->format('d/m/Y H:i') ?? 'no expiry' }} · Audit pack: {{ $access->allow_audit_pack ? 'allowed' : 'not allowed' }}</div>
        @endif
    </x-filament::section>

    @if (! $matrix)
        <x-filament::section><div class="py-8 text-center text-sm text-gray-500">Tidak ada management system yang sedang diizinkan untuk akun ini.</div></x-filament::section>
    @else
        <div class="grid gap-4 md:grid-cols-6">
            <x-filament::section compact><div class="text-xs text-gray-500">Requirement</div><div class="text-2xl font-semibold">{{ $matrix['summary']['requirements'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Assessment Gap</div><div class="text-2xl font-semibold">{{ $matrix['summary']['gaps'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Evidence Gap</div><div class="text-2xl font-semibold">{{ $matrix['summary']['evidence_gaps'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Reused Evidence</div><div class="text-2xl font-semibold">{{ $matrix['summary']['reused_evidence'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Attention</div><div class="text-2xl font-semibold">{{ $matrix['summary']['attention'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Supported</div><div class="text-2xl font-semibold">{{ $matrix['summary']['supported'] }}</div></x-filament::section>
        </div>

        <x-filament::section>
            <div class="mb-4">
                <div class="text-base font-semibold">Read-Only Requirement Traceability</div>
                <div class="mt-1 text-xs text-gray-500">Accepted cross-standard reuse tetap ditampilkan terpisah dari direct evidence. Workspace ini tidak menyatakan hasil sertifikasi.</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto text-left text-sm">
                    <thead><tr class="border-b dark:border-gray-700">
                        <th class="px-3 py-3">Requirement</th><th class="px-3 py-3">Assessment</th><th class="px-3 py-3 text-center">Document</th><th class="px-3 py-3 text-center">Direct Evidence</th><th class="px-3 py-3 text-center">Reused Evidence</th><th class="px-3 py-3 text-center">Risk</th><th class="px-3 py-3 text-center">Finding</th><th class="px-3 py-3 text-center">CAPA</th><th class="px-3 py-3">Attention</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($matrix['rows'] as $row)
                        <tr class="border-b align-top dark:border-gray-800">
                            <td class="px-3 py-3"><div class="font-medium">{{ $row['requirement_code'] }} — {{ $row['requirement_title'] }}</div>@if($row['controls'])<div class="mt-1 text-xs text-gray-500">Control: {{ $row['controls'] }}</div>@endif</td>
                            <td class="px-3 py-3">{{ str($row['assessment_status'])->replace('_', ' ')->title() }}</td>
                            <td class="px-3 py-3 text-center">{{ $row['effective_documents'] }}/{{ $row['documents'] }}</td>
                            <td class="px-3 py-3 text-center">{{ $row['verified_fresh_evidence'] }}/{{ $row['evidences'] }}</td>
                            <td class="px-3 py-3 text-center">{{ $row['verified_fresh_reused_evidence'] }}<div class="text-xs text-gray-500">accepted & fresh</div></td>
                            <td class="px-3 py-3 text-center">{{ $row['open_risks'] }}@if($row['high_risks'])<div class="text-xs">{{ $row['high_risks'] }} high</div>@endif</td>
                            <td class="px-3 py-3 text-center">{{ $row['open_findings'] }}</td>
                            <td class="px-3 py-3 text-center">{{ $row['open_capa'] }}</td>
                            <td class="px-3 py-3"><x-filament::badge :color="$row['attention_state_color']">{{ str($row['attention_state'])->replace('_', ' ')->title() }}</x-filament::badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-gray-500">Belum ada requirement assessment.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
