<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 md:items-end">
            <div>
                <div class="text-sm font-medium">Sistem Manajemen</div>
                <div class="mt-1 text-xs text-gray-500">Pilih scope audit. Matrix dibentuk langsung dari source-of-truth yang sama dengan assessment, evidence, risk, people competence, supplier, audit, CAPA, dan cross-standard reuse.</div>
            </div>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="managementSystemId">
                    @foreach ($systems as $system)
                        <option value="{{ $system->id }}">{{ $system->code }} — {{ $system->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    @if (! $matrix)
        <x-filament::section>
            <div class="py-8 text-center text-sm text-gray-500">Belum ada management system yang dapat ditampilkan.</div>
        </x-filament::section>
    @else
        <div class="grid gap-4 md:grid-cols-6">
            <x-filament::section compact><div class="text-xs text-gray-500">Requirement</div><div class="text-2xl font-semibold">{{ $matrix['summary']['requirements'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Gap Assessment</div><div class="text-2xl font-semibold">{{ $matrix['summary']['gaps'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Gap Evidence</div><div class="text-2xl font-semibold">{{ $matrix['summary']['evidence_gaps'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Reused Evidence</div><div class="text-2xl font-semibold">{{ $matrix['summary']['reused_evidence'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Perlu Perhatian</div><div class="text-2xl font-semibold">{{ $matrix['summary']['attention'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Supported</div><div class="text-2xl font-semibold">{{ $matrix['summary']['supported'] }}</div></x-filament::section>
        </div>

        <x-filament::section>
            <div class="mb-4">
                <div class="text-base font-semibold">Requirement Traceability Matrix</div>
                <div class="mt-1 text-xs text-gray-500">“Supported” berarti assessment conformant mempunyai verified/fresh support. Direct evidence dan accepted cross-standard reuse tetap ditampilkan terpisah agar asal assurance tidak tersamarkan. Training, competence, dan supplier memperkaya assurance context tetapi tidak otomatis menetapkan compliance.</div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-auto text-left text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="px-3 py-3">Requirement</th>
                            <th class="px-3 py-3">Assessment</th>
                            <th class="px-3 py-3">PIC</th>
                            <th class="px-3 py-3 text-center">Doc</th>
                            <th class="px-3 py-3 text-center">Direct Evidence</th>
                            <th class="px-3 py-3 text-center">Reused Evidence</th>
                            <th class="px-3 py-3 text-center">Risk</th>
                            <th class="px-3 py-3 text-center">Training</th>
                            <th class="px-3 py-3 text-center">Competence</th>
                            <th class="px-3 py-3 text-center">Third Party</th>
                            <th class="px-3 py-3 text-center">Finding</th>
                            <th class="px-3 py-3 text-center">CAPA</th>
                            <th class="px-3 py-3">Audit Attention</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matrix['rows'] as $row)
                            <tr class="border-b align-top dark:border-gray-800">
                                <td class="px-3 py-3">
                                    <div class="font-medium">{{ $row['requirement_code'] }} — {{ $row['requirement_title'] }}</div>
                                    @if ($row['controls'])<div class="mt-1 text-xs text-gray-500">Control: {{ $row['controls'] }}</div>@endif
                                    @if ($row['notes'])<div class="mt-1 text-xs text-gray-500">{{ $row['notes'] }}</div>@endif
                                </td>
                                <td class="px-3 py-3">{{ str($row['assessment_status'])->replace('_', ' ')->title() }}</td>
                                <td class="px-3 py-3">{{ $row['owner'] ?: '-' }}</td>
                                <td class="px-3 py-3 text-center">{{ $row['effective_documents'] }}/{{ $row['documents'] }}<div class="text-xs text-gray-500">effective/linked</div></td>
                                <td class="px-3 py-3 text-center">{{ $row['verified_fresh_evidence'] }}/{{ $row['evidences'] }}<div class="text-xs text-gray-500">verified fresh/linked</div></td>
                                <td class="px-3 py-3 text-center">{{ $row['verified_fresh_reused_evidence'] }}/{{ $row['reused_evidence'] }}<div class="text-xs text-gray-500">fresh accepted/linked</div></td>
                                <td class="px-3 py-3 text-center">{{ $row['open_risks'] }}@if ($row['high_risks'] > 0)<div class="text-xs font-medium">{{ $row['high_risks'] }} high</div>@endif</td>
                                <td class="px-3 py-3 text-center">{{ $row['training_programs'] }}</td>
                                <td class="px-3 py-3 text-center">{{ $row['competence_records'] }}</td>
                                <td class="px-3 py-3 text-center">{{ $row['suppliers'] }}@if ($row['high_risk_suppliers'] > 0)<div class="text-xs font-medium">{{ $row['high_risk_suppliers'] }} high</div>@endif</td>
                                <td class="px-3 py-3 text-center">{{ $row['open_findings'] }}</td>
                                <td class="px-3 py-3 text-center">{{ $row['open_capa'] }}</td>
                                <td class="px-3 py-3">
                                    <x-filament::badge :color="$row['attention_state_color']">
                                        {{ match ($row['attention_state']) { 'supported' => 'Supported', 'needs_evidence' => 'Needs Evidence', 'attention' => 'Attention', 'gap' => 'Assessment Gap', default => 'Review' } }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-3 py-8 text-center text-gray-500">Belum ada requirement assessment pada management system ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
