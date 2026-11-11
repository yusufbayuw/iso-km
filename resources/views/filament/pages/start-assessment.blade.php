<x-filament-panels::page>
    <style>
        .gw-wrap {display:grid;gap:1rem}
        .gw-card {border:1px solid #dbe2eb;border-radius:12px;padding:1.2rem;background:var(--gw-bg,#fff)}
        .dark .gw-card {--gw-bg:#18181b;border-color:#3f3f46}
        .gw-label {font-weight:600;margin-bottom:.35rem;display:block}
        .gw-input {width:100%;border:1px solid #a6afbe;border-radius:8px;padding:.6rem .75rem;background:transparent}
        .dark .gw-input {border-color:#71717a}
        .gw-actions {display:flex;flex-wrap:wrap;align-items:center;gap:.6rem}
        .gw-btn {border:1px solid #a6afbe;padding:.5rem .85rem;border-radius:8px;font-weight:600}
        .gw-primary {background:#1d4ed8;color:#fff;border-color:#1d4ed8}
        .gw-soft {color:#64748b;font-size:.85rem}
        .dark .gw-soft {color:#a1a1aa}
        .gw-nav {display:flex;flex-wrap:wrap;gap:.55rem}
        .gw-nav button {border:1px solid #a6afbe;padding:.5rem .8rem;border-radius:8px}
        .gw-nav button.active {border-color:#1d4ed8;background:#dbeafe;color:#1d4ed8;font-weight:700}
        .gw-cols {display:grid;grid-template-columns:minmax(230px,300px) minmax(0,1fr);gap:1rem}
        .gw-list {max-height:640px;overflow:auto;display:grid;gap:.3rem}
        .gw-list button {padding:.65rem .5rem;border-bottom:1px solid #e4e4e7;text-align:left;width:100%;border-radius:7px}
        .gw-list button.active {background:#dbeafe;color:#1e40af}
        .gw-title {font-size:1.15rem;font-weight:700}
        .gw-good {color:#15803d}
        .gw-notice {padding:.65rem .9rem;border-left:3px solid #2563eb;background:rgba(59,130,246,.08)}
        .gw-alert {padding:.65rem .9rem;border-left:3px solid #d97706;background:rgba(245,158,11,.09)}
        .gw-progress {height:8px;border-radius:12px;background:#e4e4e7;overflow:hidden}
        .gw-progress > span {display:block;background:#2563eb;height:100%}
        @media (max-width:830px) {.gw-cols {grid-template-columns:1fr}.gw-list {max-height:260px}}
    </style>

    <div class="gw-wrap">
        <div class="gw-card">
            <div class="gw-actions" style="justify-content:space-between">
                <div>
                    <h2 class="gw-title">Mulai / Lanjutkan Asesmen</h2>
                    <p class="gw-soft">Satu pintu untuk mengisi, mengundang kontributor, memantau review, dan mengunduh hasil.</p>
                </div>
                @if (count($this->systems))
                    <button type="button" class="gw-btn" wire:click="newAssessment">+ Asesmen Baru</button>
                @endif
            </div>
            @if ($this->runs->isNotEmpty())
                <div style="margin-top:1rem">
                    <label class="gw-label" for="gw-choose-run">Asesmen yang bisa Anda akses</label>
                    <select class="gw-input" id="gw-choose-run" wire:change="chooseRun($event.target.value)">
                        @foreach ($this->runs as $candidate)
                            <option value="{{ $candidate->id }}" @selected($candidate->id === $runId)>
                                {{ $candidate->name }} — {{ $candidate->system?->name }}
                            </option>
                        @endforeach
                        @if (! $runId)<option selected value="">Pilih asesmen...</option>@endif
                    </select>
                </div>
            @endif
        </div>

        @if (! $this->run)
            <div class="gw-card">
                @if (count($this->systems))
                    <h3 class="gw-title" style="margin-bottom:1rem">Mulai Asesmen Baru</h3>
                    <form wire:submit.prevent="start" class="gw-wrap">
                        <div>
                            <label class="gw-label" for="gw-system">Sistem manajemen yang dinilai</label>
                            <select id="gw-system" class="gw-input" wire:model="systemId">
                                <option value="">Pilih sistem...</option>
                                @foreach ($this->systems as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('systemId')<p class="gw-soft" style="color:#dc2626">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="gw-label" for="gw-name">Nama asesmen</label>
                            <input id="gw-name" class="gw-input" type="text" wire:model="assessmentName"
                                placeholder="Contoh: Asesmen Keamanan Informasi Semester 1" maxlength="255">
                            @error('assessmentName')<p class="gw-soft" style="color:#dc2626">{{ $message }}</p>@enderror
                        </div>
                        <p class="gw-soft">Bank pertanyaan harus ditinjau dan diterbitkan administrator. Pembuatan asesmen akan menugaskan pertanyaan awal kepada Anda.</p>
                        <div><button type="submit" class="gw-btn gw-primary">Mulai Asesmen</button></div>
                    </form>
                @else
                    <div class="gw-notice">Anda belum mempunyai penugasan atau kewenangan untuk memulai asesmen. Minta koordinator menambahkan Anda pada suatu periode asesmen.</div>
                @endif
            </div>
        @else
            @php
                $run = $this->run;
                $progress = $this->completeness;
                $links = $this->getReviewLinks();
                $percent = ($progress['assignments_total'] ?? 0) > 0
                    ? (int) round(100 * $progress['assignments_completed'] / $progress['assignments_total']) : 0;
            @endphp
            <div class="gw-card">
                <div class="gw-actions" style="justify-content:space-between">
                    <div>
                        <h3 class="gw-title">{{ $run->name }}</h3>
                        <p class="gw-soft">{{ $run->system?->name }} · Status: {{ $run->locked_at ? 'Final' : 'Dalam proses' }}</p>
                    </div>
                    <div style="min-width:180px">
                        <div class="gw-soft">{{ $progress['assignments_completed'] }} / {{ $progress['assignments_total'] }} penugasan selesai</div>
                        <div class="gw-progress"><span style="width:{{ $percent }}%"></span></div>
                    </div>
                </div>
            </div>
            <div class="gw-nav">
                @foreach (['questions'=>'Isi Pertanyaan','context'=>'Konteks & Risiko','review'=>'Review & Persetujuan','results'=>'Unduh Hasil'] as $key => $label)
                    <button type="button" wire:click="showStage('{{ $key }}')" class="{{ $stage === $key ? 'active' : '' }}">{{ $label }}</button>
                @endforeach
            </div>

            @if ($stage === 'questions')
                <div class="gw-cols">
                    <div class="gw-card">
                        <div class="gw-label">Daftar pertanyaan saya</div>
                        @if ($this->assignments->isEmpty())
                            <p class="gw-soft">Belum ada tugas untuk Anda. Jika Anda koordinator, tugaskan pertanyaan kepada diri sendiri atau kontributor.</p>
                        @else
                            <div class="gw-list">
                                @foreach ($this->assignments as $assigned)
                                    <button type="button" wire:click="chooseQuestion({{ $assigned->guided_question_id }})"
                                        class="{{ $questionId === $assigned->guided_question_id ? 'active' : '' }}">
                                        <span class="gw-soft">{{ $assigned->question->code }}</span>
                                        <div style="font-size:.85rem">{{ \Illuminate\Support\Str::limit($assigned->question->prompt, 88) }}</div>
                                        <span class="gw-soft">{{ $assigned->status === 'submitted' ? '✓ Terkirim' : 'Belum selesai' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="gw-card gw-wrap">
                        @if ($this->currentAssignment && ! $run->locked_at)
                            @php $question = $this->currentAssignment->question; @endphp
                            <div>
                                <span class="gw-soft">{{ $question->code }}</span>
                                <h3 class="gw-title">{{ $question->prompt }}</h3>
                                <div class="gw-notice gw-soft" style="margin-top:.7rem">
                                    {{ $question->help_text ?: 'Jelaskan kondisi sebenarnya dan bukti yang diketahui.' }}
                                </div>
                            </div>
                            <div class="gw-wrap" style="grid-template-columns:1fr auto;align-items:end;gap:.4rem">
                                <div>
                                    <label class="gw-label" for="gw-context">Konteks / sistem / aset</label>
                                    <input id="gw-context" type="text" class="gw-input" wire:model="newContextKey" maxlength="120">
                                </div>
                                <button type="button" wire:click="openContext" class="gw-btn">Buka Konteks</button>
                            </div>
                            <p class="gw-soft">Konteks aktif: <strong>{{ $contextKey }}</strong>. Gunakan nama lain untuk jawaban aset atau sistem yang berbeda.</p>
                            @if ($question->answer_type === 'single_choice')
                                <div>
                                    <label class="gw-label" for="gw-answer">Jawaban</label>
                                    <select id="gw-answer" class="gw-input" wire:model.live.debounce.900ms="answer">
                                        <option value="">Pilih kondisi...</option>
                                        @foreach (($question->answer_options ?? []) as $choice)
                                            <option value="{{ $choice }}">{{ $choice }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <div>
                                    <label class="gw-label" for="gw-answer">Jawaban / alasan</label>
                                    <textarea id="gw-answer" class="gw-input" rows="4" wire:model.live.debounce.900ms="answer" placeholder="Tulis keadaan atau alasan berdasarkan fakta..."></textarea>
                                </div>
                            @endif
                            <div>
                                <label class="gw-label" for="gw-notes">Keterangan tambahan</label>
                                <textarea id="gw-notes" class="gw-input" rows="3" wire:model.live.debounce.1000ms="notes" placeholder="Jelaskan penanggung jawab, penerapan, kendala, dan tindak lanjut..."></textarea>
                            </div>
                            <div>
                                <label class="gw-label" for="gw-evidence">Evidence yang sudah tersedia</label>
                                <select id="gw-evidence" multiple size="4" class="gw-input" wire:model.live="evidenceIds">
                                    @forelse ($this->evidenceChoices as $id => $title)
                                        <option value="{{ $id }}">{{ $title }}</option>
                                    @empty
                                        <option disabled>Belum ada bukti yang dapat Anda gunakan</option>
                                    @endforelse
                                </select>
                                <p class="gw-soft">Tidak memiliki bukti? Anda tetap boleh mencatat kondisi sebenarnya; reviewer akan menentukan apakah bukti tambahan diperlukan.</p>
                            </div>
                            <div class="gw-actions">
                                <button type="button" wire:click="saveDraft" class="gw-btn">Simpan Draft</button>
                                <button type="button" wire:click="submitAnswer" class="gw-btn gw-primary">Kirim Jawaban</button>
                                <span class="gw-soft">{{ $saveMessage }}</span>
                            </div>
                            @error('draft')<div class="gw-alert">{{ $message }}</div>@enderror
                            @error('answer')<div class="gw-alert">{{ $message }}</div>@enderror
                            @error('context_key')<div class="gw-alert">{{ $message }}</div>@enderror
                            @error('assignment')<div class="gw-alert">{{ $message }}</div>@enderror
                        @elseif ($run->locked_at)
                            <div class="gw-notice">Asesmen ini telah final dan terkunci. Riwayat jawaban tetap tersedia sebagai rekaman audit.</div>
                        @else
                            <div class="gw-notice">Tidak ada penugasan aktif untuk Anda. Koordinator dapat mengundang responden pada bagian Review & Persetujuan.</div>
                        @endif
                    </div>
                </div>
            @elseif ($stage === 'context')
                <div class="gw-card gw-wrap">
                    <h3 class="gw-title">Inventaris konteks & asesmen risiko</h3>
                    <p class="gw-soft">Isi hanya aset dan aktivitas yang benar-benar ada pada ruang lingkup yang dinilai. Risiko bersumber dari Risk Register ISO-KM.</p>
                    @if ($run->isMember(auth()->user(), ['coordinator']) && ! $run->locked_at)
                        <div class="gw-wrap" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.6rem">
                            <div><label class="gw-label">Jenis data</label><select class="gw-input" wire:model="scopeKind"><option value="asset">Aset informasi</option><option value="activity">Aktivitas informasi</option></select></div>
                            <div><label class="gw-label">Nama aset/aktivitas</label><input class="gw-input" wire:model="scopeTitle" maxlength="255"></div>
                            <div><label class="gw-label">Kategori (opsional)</label><input class="gw-input" wire:model="scopeCategory"></div>
                        </div>
                        <div><label class="gw-label">Catatan (opsional)</label><textarea rows="2" class="gw-input" wire:model="scopeNotes"></textarea></div>
                        <div><button type="button" class="gw-btn gw-primary" wire:click="saveScopeItem">Tambahkan ke Inventaris</button></div>
                        @error('scopeTitle')<p class="gw-soft" style="color:#dc2626">{{ $message }}</p>@enderror
                    @endif
                    <div class="gw-actions">
                        <a href="{{ $links['inventory'] }}" class="gw-btn">Kelola Inventaris Konteks</a>
                        <a href="{{ $links['risks'] }}" class="gw-btn">Buka Risk Register</a>
                    </div>
                    <p class="gw-soft">Jumlah aset/aktivitas pada asesmen ini: {{ $run->scopeItems()->count() }}. Tidak ada aset atau risiko contoh yang disalin secara otomatis.</p>
                </div>
            @elseif ($stage === 'review')
                <div class="gw-card gw-wrap">
                    <h3 class="gw-title">Konsolidasi, review bukti, dan persetujuan</h3>
                    <div class="gw-actions">
                        <span class="gw-soft">Kriteria disetujui dan dipublikasikan:</span>
                        <strong>{{ $progress['criteria_approved'] }} / {{ $progress['criteria_total'] }}</strong>
                    </div>
                    @if ($progress['reasons'])
                        <div class="gw-alert">
                            <strong>Belum siap difinalisasi:</strong>
                            <ul style="margin-left:1.2rem;list-style:disc">
                                @foreach ($progress['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach
                            </ul>
                        </div>
                    @else
                        <div class="gw-notice gw-good">Seluruh indikator awal lengkap. Pemeriksaan final server tetap dijalankan sebelum penguncian.</div>
                    @endif
                    <div class="gw-actions">
                        <a class="gw-btn" href="{{ $links['answers'] }}">Konsolidasi Jawaban</a>
                        <a class="gw-btn" href="{{ $links['consolidations'] }}">Usulkan Penilaian</a>
                        <a class="gw-btn" href="{{ $links['decisions'] }}">Review & Persetujuan</a>
                    </div>
                    @if ($run->isMember(auth()->user(), ['coordinator']) && ! $run->locked_at)
                        <h4 class="gw-label">Undang kontributor / tugaskan pertanyaan</h4>
                        <div class="gw-wrap" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.6rem">
                            <select class="gw-input" wire:model="contributorId">
                                <option value="">Pilih pengguna...</option>
                                @foreach (\App\Models\User::query()->orderBy('name')->get(['id','name']) as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <select class="gw-input" wire:model="delegateQuestionId">
                                <option value="">Seluruh pertanyaan awal</option>
                                @foreach ($run->bank->questions()->whereDoesntHave('targets', fn ($query) => $query->whereRaw('1=0'))->get(['id','code']) as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><button type="button" class="gw-btn" wire:click="delegate">Tugaskan Kontributor</button></div>
                        @error('authorization')<p class="gw-soft" style="color:#dc2626">{{ $message }}</p>@enderror
                        @if ($progress['ready_to_lock'])
                            <button type="button" wire:click="finalize" wire:confirm="Kunci asesmen menjadi FINAL? Jawaban tidak dapat diubah setelah itu." class="gw-btn gw-primary">Tetapkan Final</button>
                        @endif
                    @endif
                </div>
            @elseif ($stage === 'results')
                <div class="gw-card gw-wrap">
                    <h3 class="gw-title">Unduh Hasil Asesmen</h3>
                    @if (! $run->locked_at)
                        <div class="gw-notice">Laporan yang tersedia masih <strong>DRAFT — BELUM DIVALIDASI</strong>. Kriteria yang belum disetujui ditampilkan sebagai belum dinilai.</div>
                    @else
                        <div class="gw-notice gw-good">Asesmen telah dikunci. Hasil tersedia sebagai laporan FINAL yang berasal dari keputusan yang disetujui.</div>
                    @endif
                    @if ($run->isMember(auth()->user(), ['coordinator']) && auth()->user()->can('guided.report.export'))
                        <div class="gw-actions">
                            <button type="button" class="gw-btn gw-primary" wire:click="downloadWorkbook">
                                {{ $run->locked_at ? 'Unduh GAP Analysis FINAL (.xlsx)' : 'Unduh GAP Analysis DRAFT (.xlsx)' }}
                            </button>
                        </div>
                    @else
                        <p class="gw-soft">Unduhan laporan memerlukan kewenangan koordinator dan izin ekspor.</p>
                    @endif
                    <p class="gw-soft">Template bersifat netral organisasi; nama organisasi berasal dari konfigurasi deployment. Laporan final memerlukan keputusan yang disetujui dan dipublikasikan.</p>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>