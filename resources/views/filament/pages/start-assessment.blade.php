<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Mulai / Lanjutkan Asesmen</x-slot>
        <x-slot name="description">
            Isi asesmen secara bertahap, tugaskan kontributor, pantau review, dan unduh hasil dari satu halaman.
        </x-slot>

        <div class="space-y-4">
            @if ($this->runs->isNotEmpty())
                {{ $this->runSelectorForm }}
            @endif

            @if ($this->run && count($this->systems))
                <div class="flex justify-end">
                    <x-filament::button color="gray" icon="heroicon-o-plus" wire:click="newAssessment">
                        Asesmen Baru
                    </x-filament::button>
                </div>
            @endif
        </div>
    </x-filament::section>

    @if (! $this->run)
        <x-filament::section>
            <x-slot name="heading">Mulai Asesmen Baru</x-slot>
            <x-slot name="description">
                Pilih sistem manajemen yang akan dinilai. Pertanyaan akan tersedia setelah bank diterbitkan administrator.
            </x-slot>

            @if (count($this->systems))
                <form wire:submit="start" class="space-y-6">
                    <div class="pb-2">
                        {{ $this->startForm }}
                    </div>

                    @if ($this->bankReadiness)
                        <div class="mt-6 rounded-lg border p-4 {{ $this->bankReadiness['ready'] ? 'border-success-300 bg-success-50 dark:border-success-700 dark:bg-success-950/30' : 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950/30' }}">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <x-filament::badge :color="$this->bankReadiness['ready'] ? 'success' : 'warning'">
                                    {{ $this->bankReadiness['ready'] ? 'Bank Siap Digunakan' : 'Bank Belum Terbit' }}
                                </x-filament::badge>
                                @if ($this->bankReadiness['name'])
                                    <span class="text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $this->bankReadiness['name'] }}
                                        (v{{ $this->bankReadiness['version'] }}, {{ $this->bankReadiness['question_count'] }} pertanyaan)
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-200">
                                {{ $this->bankReadiness['message'] }}
                            </p>
                            @if (! $this->bankReadiness['ready'] && auth()->user()?->can('guided.bank.manage'))
                                <div class="mt-3">
                                    <x-filament::button color="gray" size="sm" tag="a" :href="\App\Filament\Resources\GuidedQuestionBankResource::getUrl('index')" icon="heroicon-o-book-open">
                                        Periksa Bank Pertanyaan
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>
                    @endif

                    @error('startData.system_id')
                        <p class="mt-3 text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</p>
                    @enderror

                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-5 dark:border-gray-700">
                        <span wire:loading wire:target="start" class="text-sm text-gray-500 dark:text-gray-400" role="status">
                            Memeriksa bank dan membuat penugasan...
                        </span>
                        <x-filament::button type="submit" icon="heroicon-o-play"
                            wire:loading.attr="disabled" wire:target="start">
                            Mulai Asesmen
                        </x-filament::button>
                    </div>
                </form>
            @else
                <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Anda belum memiliki kewenangan untuk membuat asesmen.
                    Mintalah koordinator memberikan tugas atau menambahkan sistem manajemen dalam lingkup Anda.
                </div>
            @endif
        </x-filament::section>
    @else
        @php
            $run = $this->run;
            $progress = $this->completeness;
            $links = $this->getReviewLinks();
            $total = $progress['assignments_total'] ?: 0;
            $percent = $total > 0 ? (int) round(100 * $progress['assignments_completed'] / $total) : 0;
            $coordinator = $run->isMember(auth()->user(), ['coordinator']);
        @endphp

        <x-filament::section>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $run->name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $run->system?->name }}
                    </p>
                </div>
                <x-filament::badge :color="$run->locked_at ? 'success' : 'warning'">
                    {{ $run->locked_at ? 'FINAL' : 'Dalam Proses' }}
                </x-filament::badge>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Penugasan selesai</p>
                    <p class="mt-1 text-xl font-semibold">{{ $progress['assignments_completed'] }} / {{ $total }}</p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-primary-600" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Kriteria disetujui</p>
                    <p class="mt-1 text-xl font-semibold">{{ $progress['criteria_approved'] }} / {{ $progress['criteria_total'] }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Memerlukan revisi</p>
                    <p class="mt-1 text-xl font-semibold">{{ $progress['revisions'] }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::tabs label="Tahapan asesmen">
            <x-filament::tabs.item :active="$stage === 'questions'" wire:click="showStage('questions')" icon="heroicon-o-pencil-square">
                Isi Pertanyaan
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$stage === 'context'" wire:click="showStage('context')" icon="heroicon-o-rectangle-stack">
                Konteks & Risiko
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$stage === 'review'" wire:click="showStage('review')" icon="heroicon-o-check-badge">
                Review & Persetujuan
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$stage === 'results'" wire:click="showStage('results')" icon="heroicon-o-arrow-down-tray">
                Unduh Hasil
            </x-filament::tabs.item>
        </x-filament::tabs>

        @if ($stage === 'questions')
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-1">
                    <x-filament::section>
                        <x-slot name="heading">Navigasi Pertanyaan</x-slot>
                        <x-slot name="description">
                            Cari pertanyaan berdasarkan kode atau topik. Pertanyaan yang telah dikirim dapat direvisi.
                        </x-slot>

                        @if ($this->assignments->isNotEmpty())
                            {{ $this->questionPickerForm }}
                            <div class="mt-4 flex flex-wrap gap-2">
                                <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-left" wire:click="relativeQuestion(-1)">
                                    Sebelumnya
                                </x-filament::button>
                                <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-right" icon-position="after" wire:click="relativeQuestion(1)">
                                    Selanjutnya
                                </x-filament::button>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Belum ada penugasan untuk Anda pada periode ini.
                            </p>
                        @endif
                    </x-filament::section>

                    @if ($this->currentAssignment && ! $run->locked_at)
                        <x-filament::section>
                            <x-slot name="heading">Konteks Jawaban</x-slot>
                            <form wire:submit="openContext" class="space-y-6">
                                {{ $this->contextForm }}
                                <div class="flex justify-end">
                                    <x-filament::button type="submit" color="gray" size="sm" icon="heroicon-o-folder-open">
                                        Buka Konteks
                                    </x-filament::button>
                                </div>
                            </form>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                Konteks aktif: <strong>{{ $contextKey }}</strong>
                            </p>
                        </x-filament::section>
                    @endif
                </div>

                <div class="xl:col-span-2">
                    <x-filament::section>
                        @if ($this->currentAssignment && ! $run->locked_at)
                            @php $question = $this->currentAssignment->question; @endphp
                            <x-slot name="heading">
                                {{ $question->code }} — Pertanyaan Asesmen
                            </x-slot>
                            <x-slot name="description">{{ $question->prompt }}</x-slot>

                            @if ($question->help_text)
                                <div class="mb-5 rounded-lg bg-primary-50 p-4 text-sm text-primary-800 dark:bg-primary-950/40 dark:text-primary-200">
                                    {{ $question->help_text }}
                                </div>
                            @endif

                            <div wire:key="wizard-answer-{{ $questionId }}-{{ md5($contextKey) }}">
                                {{ $this->answerForm }}
                            </div>

                            <div class="mt-5 flex flex-wrap items-center gap-3">
                                <x-filament::button color="gray" icon="heroicon-o-cloud-arrow-up" wire:click="saveDraft">
                                    Simpan Draft
                                </x-filament::button>
                                <x-filament::button icon="heroicon-o-paper-airplane" wire:click="submitAnswer">
                                    Kirim Jawaban
                                </x-filament::button>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $saveMessage }}</span>
                            </div>
                            @error('draft')
                                <p class="mt-3 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                            @error('assignment')
                                <p class="mt-3 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        @elseif ($run->locked_at)
                            <x-slot name="heading">Asesmen Telah Final</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Jawaban terkunci sebagai rekaman audit. Gunakan tab Unduh Hasil untuk melihat laporan.
                            </p>
                        @else
                            <x-slot name="heading">Belum Ada Pertanyaan</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Koordinator dapat menambahkan responden dan penugasan melalui tab Review & Persetujuan.
                            </p>
                        @endif
                    </x-filament::section>
                </div>
            </div>
        @elseif ($stage === 'context')
            <x-filament::section>
                <x-slot name="heading">Inventaris Konteks & Risiko</x-slot>
                <x-slot name="description">
                    Masukkan aset serta aktivitas yang benar-benar berada dalam ruang lingkup asesmen.
                    Risiko dinilai melalui Risk Register ISO-KM.
                </x-slot>

                @if ($coordinator && ! $run->locked_at)
                    <form wire:submit="saveScopeItem" class="space-y-6">
                        {{ $this->scopeForm }}
                        <div class="flex justify-end">
                            <x-filament::button type="submit" icon="heroicon-o-plus">
                                Tambah ke Inventaris
                            </x-filament::button>
                        </div>
                    </form>
                @endif

                <div class="mt-5 flex flex-wrap gap-3">
                    <x-filament::button tag="a" :href="$links['inventory']" color="gray" icon="heroicon-o-rectangle-stack">
                        Kelola Inventaris
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$links['risks']" color="gray" icon="heroicon-o-exclamation-triangle">
                        Risk Register
                    </x-filament::button>
                </div>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Aset/aktivitas tersimpan: {{ $run->scopeItems()->count() }}.
                    Sistem tidak mengisi aset atau risiko contoh secara otomatis.
                </p>
            </x-filament::section>
        @elseif ($stage === 'review')
            <div class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">Konsolidasi, Review, dan Persetujuan</x-slot>
                    <x-slot name="description">
                        Penilaian final tetap melalui workflow dan kewenangan ISO-KM yang sudah ada.
                    </x-slot>

                    @if ($progress['reasons'])
                        <div class="rounded-lg bg-warning-50 p-4 text-sm text-warning-900 dark:bg-warning-950/40 dark:text-warning-200">
                            <p class="font-semibold">Asesmen belum siap difinalisasi:</p>
                            <ul class="mt-2 list-disc space-y-1 ps-5">
                                @foreach ($progress['reasons'] as $reason)
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <x-filament::badge color="success">
                            Pemeriksaan kelengkapan awal selesai
                        </x-filament::badge>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-filament::button tag="a" :href="$links['answers']" color="gray">
                            Konsolidasi Jawaban
                        </x-filament::button>
                        <x-filament::button tag="a" :href="$links['consolidations']" color="gray">
                            Usulkan Penilaian
                        </x-filament::button>
                        <x-filament::button tag="a" :href="$links['decisions']" color="gray">
                            Review & Persetujuan
                        </x-filament::button>
                    </div>
                </x-filament::section>

                @if ($coordinator && ! $run->locked_at)
                    <x-filament::section>
                        <x-slot name="heading">Tugaskan Kontributor</x-slot>
                        <x-slot name="description">
                            Pilih pengguna yang memiliki izin menjawab, lalu tentukan pertanyaan atau tugaskan seluruh pertanyaan awal.
                        </x-slot>
                        <form wire:submit="delegate" class="space-y-6">
                            {{ $this->delegateForm }}
                            <div class="flex justify-end">
                                <x-filament::button type="submit" icon="heroicon-o-user-plus">
                                    Tugaskan Kontributor
                                </x-filament::button>
                            </div>
                        </form>
                    </x-filament::section>

                    @if ($progress['ready_to_lock'])
                        <x-filament::section>
                            <x-slot name="heading">Finalisasi</x-slot>
                            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                                Setelah dikunci, jawaban dan keputusan tidak dapat diubah.
                                Sistem akan memeriksa seluruh syarat finalisasi sekali lagi.
                            </p>
                            {{ $this->finalizeAction }}
                        </x-filament::section>
                    @endif
                @endif
            </div>
        @elseif ($stage === 'results')
            <x-filament::section>
                <x-slot name="heading">Unduh Hasil Asesmen</x-slot>
                <x-slot name="description">
                    Hasil draft dapat diunduh selama proses berlangsung. Hasil final hanya tersedia setelah seluruh persetujuan selesai.
                </x-slot>

                @if ($run->locked_at)
                    <x-filament::badge color="success">FINAL — Disetujui dan Dikunci</x-filament::badge>
                @else
                    <x-filament::badge color="warning">DRAFT — BELUM DIVALIDASI</x-filament::badge>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        Bagian yang belum disetujui ditampilkan sebagai belum dinilai.
                        Laporan draft bukan bukti kepatuhan final.
                    </p>
                @endif

                @if ($coordinator && auth()->user()->can('guided.report.export'))
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-filament::button wire:click="downloadWorkbook" icon="heroicon-o-arrow-down-tray">
                            Unduh GAP Analysis {{ $run->locked_at ? 'FINAL' : 'DRAFT' }} (XLSX)
                        </x-filament::button>
                        <x-filament::button tag="a" :href="route('guided.assessment.print', ['assessment' => $run->id])"
                            target="_blank" rel="noopener" color="gray" icon="heroicon-o-printer">
                            Laporan Cetak / Simpan PDF
                        </x-filament::button>
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        Unduhan memerlukan peran koordinator dan izin ekspor.
                    </p>
                @endif
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
