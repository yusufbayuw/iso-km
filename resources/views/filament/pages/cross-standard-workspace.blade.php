<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 md:items-end">
            <div>
                <div class="text-sm font-medium">Target Management System</div>
                <div class="mt-1 text-xs text-gray-500">Sistem mencari verified evidence dari standar lain pada compliance scope yang sama melalui mapping yang sudah approved.</div>
            </div>
            {{ $this->managementSystemForm }}
        </div>
    </x-filament::section>

    @if (! $coverage)
        <x-filament::section><div class="py-8 text-center text-sm text-gray-500">Belum ada management system.</div></x-filament::section>
    @else
        <div class="grid gap-4 md:grid-cols-4">
            <x-filament::section compact><div class="text-xs text-gray-500">Reuse Opportunities</div><div class="text-2xl font-semibold">{{ $coverage['summary']['opportunities'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Accepted</div><div class="text-2xl font-semibold">{{ $coverage['summary']['accepted'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Available</div><div class="text-2xl font-semibold">{{ $coverage['summary']['available'] }}</div></x-filament::section>
            <x-filament::section compact><div class="text-xs text-gray-500">Source Systems</div><div class="text-2xl font-semibold">{{ $coverage['summary']['source_systems'] }}</div></x-filament::section>
        </div>

        <x-filament::section>
            <div class="mb-4">
                <div class="text-base font-semibold">Evidence Reuse Opportunities</div>
                <div class="mt-1 text-xs text-gray-500">Reuse tidak otomatis. Mapping harus approved dan seorang user dengan permission cross_standard_reuse.accept harus menerima evidence untuk target tertentu. Evidence asli tetap menjadi source-of-truth.</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto text-left text-sm">
                    <thead><tr class="border-b dark:border-gray-700">
                        <th class="px-3 py-3">Source</th><th class="px-3 py-3">Evidence</th><th class="px-3 py-3">Target</th><th class="px-3 py-3">Mapping</th><th class="px-3 py-3">Freshness</th><th class="px-3 py-3">Reuse</th><th class="px-3 py-3"></th>
                    </tr></thead>
                    <tbody>
                    @forelse ($coverage['candidates'] as $row)
                        <tr class="border-b align-top dark:border-gray-800">
                            <td class="px-3 py-3"><div class="font-medium">{{ $row['source_standard'] }} · {{ $row['source_system'] }}</div><div class="text-xs text-gray-500">{{ str($row['kind'])->title() }} {{ $row['source_code'] }}</div></td>
                            <td class="px-3 py-3"><div class="font-medium">{{ $row['evidence_title'] }}</div><div class="text-xs text-gray-500">Captured {{ $row['captured_at'] ?: '-' }}</div></td>
                            <td class="px-3 py-3"><div class="font-medium">{{ $row['target_standard'] }} · {{ str($row['kind'])->title() }} {{ $row['target_code'] }}</div></td>
                            <td class="px-3 py-3"><x-filament::badge>{{ str($row['mapping_type'])->title() }}</x-filament::badge><div class="mt-1 text-xs text-gray-500">{{ $row['coverage_percent'] }}% mapped coverage</div></td>
                            <td class="px-3 py-3">{{ $row['expires_at'] ? 'Exp '.$row['expires_at'] : 'No expiry' }}</td>
                            <td class="px-3 py-3"><x-filament::badge :color="$row['reuse_status'] === 'accepted' ? 'success' : ($row['reuse_status'] === 'rejected' ? 'danger' : 'gray')">{{ str($row['reuse_status'])->title() }}</x-filament::badge></td>
                            <td class="px-3 py-3 text-right">
                                @if ($row['reuse_status'] !== 'accepted' && auth()->user()?->can('cross_standard_reuse.accept'))
                                    @if ($row['kind'] === 'requirement')
                                        <x-filament::button size="xs" wire:click="acceptRequirementReuse({{ $row['evidence_id'] }}, {{ $row['target_id'] }}, {{ $row['mapping_id'] }})">Accept Reuse</x-filament::button>
                                    @else
                                        <x-filament::button size="xs" wire:click="acceptControlReuse({{ $row['evidence_id'] }}, {{ $row['target_id'] }}, {{ $row['mapping_id'] }})">Accept Reuse</x-filament::button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500">Belum ada peluang reuse. Pastikan terdapat standar lain pada scope yang sama, mapping sudah approved, dan source evidence verified/fresh.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
