<div class="space-y-3 text-sm">
    <p><strong>Pertanyaan:</strong> {{ $coverage['question_count'] }}</p>
    <p><strong>Requirement:</strong> {{ $coverage['requirements_covered'] }} / {{ $coverage['requirements_total'] }}</p>
    <p><strong>Control:</strong> {{ $coverage['controls_covered'] }} / {{ $coverage['controls_total'] }}</p>
    <p><strong>Requirement belum terpetakan:</strong> {{ implode(', ', $coverage['missing_requirements']) ?: '-' }}</p>
    <p><strong>Control belum terpetakan:</strong> {{ implode(', ', $coverage['missing_controls']) ?: '-' }}</p>
    <p><strong>Percabangan tidak valid:</strong> {{ implode(', ', $coverage['broken_branches']) ?: '-' }}</p>
    <p><strong>Pertanyaan bermasalah:</strong> {{ implode(', ', $coverage['invalid_questions']) ?: '-' }}</p>
    <p><strong>Target penilaian ganda:</strong> {{ implode(', ', $coverage['duplicate_targets']) ?: '-' }}</p>
    <p><strong>Katalog klausul ISO belum dimuat:</strong> {{ implode(', ', $coverage['catalog_missing_requirements']) ?: '-' }}</p>
    <p><strong>Katalog kontrol ISO belum dimuat:</strong> {{ implode(', ', $coverage['catalog_missing_controls']) ?: '-' }}</p>
    <p class="text-xs text-gray-500">{{ $coverage['catalog_warning'] }}</p>
</div>
