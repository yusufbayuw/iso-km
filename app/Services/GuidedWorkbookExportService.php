<?php

namespace App\Services;

use App\Models\GuidedAssessment;
use App\Models\GuidedExportRecord;
use App\Models\GuidedDecision;
use App\Models\GuidedExportTemplate;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Edits only mapped input cells in an existing XLSX package.
 * Formula cells, charts, drawings and all unrelated ZIP members are left intact.
 */
class GuidedWorkbookExportService
{
    private const SHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const OFFICE_REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    public function export(GuidedAssessment $run, GuidedExportTemplate $template, User $actor, bool $final = true): string
    {
        if (! $actor->can('guided.report.export') || ! $run->isMember($actor, ['coordinator'])
            || ! $template->is_active || ($template->management_system_id && $template->management_system_id !== $run->management_system_id)) {
            throw ValidationException::withMessages(['authorization' => 'Template atau ekspor tidak tersedia untuk asesmen ini.']);
        }
        if ($final && ($run->status !== 'locked' || ! $run->locked_at)) {
            throw ValidationException::withMessages(['assessment' => 'Hasil final hanya dapat diekspor setelah asesmen dikunci.']);
        }
        if (! $template->mappings || count($template->mappings) > 10000) {
            throw ValidationException::withMessages(['mappings' => 'Template harus memiliki pemetaan yang valid.']);
        }
        if ($final && $template->report_type === 'gap_analysis') {
            $this->assertCompleteGapMapping($run, $template->mappings);
        }
        $src = app(GuidedWorkbookTemplateFileService::class)->path($template->file_path);
        $relative = 'iso/exports/assessment-'.$run->id.'-'.($final ? 'FINAL' : 'DRAFT').'-'.Str::random(12).'.xlsx';
        $dest = Storage::disk('local')->path($relative);
        File::ensureDirectoryExists(dirname($dest));
        if (! copy($src, $dest)) {
            throw ValidationException::withMessages(['template' => 'Gagal menyalin template.']);
        }
        try {
            $this->fill($dest, $run, $template, $final);
            GuidedExportRecord::query()->create([
                'guided_assessment_id' => $run->id,
                'guided_export_template_id' => $template->id,
                'generated_by' => $actor->id,
                'is_final' => $final,
                'mapping_snapshot' => $template->mappings,
                'template_sha256' => hash_file('sha256', $src),
                'output_sha256' => hash_file('sha256', $dest),
                'file_path' => $relative,
            ]);
            return $relative;
        } catch (\Throwable $exception) {
            @unlink($dest);
            throw $exception;
        }
    }

    private function fill(string $filename, GuidedAssessment $run, GuidedExportTemplate $template, bool $final): void
    {
        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            throw ValidationException::withMessages(['template' => 'XLSX tidak dapat dibuka.']);
        }
        try {
            $this->validatePackage($zip);
            $sheets = $this->sheetPaths($zip);
            $decisions = $this->decisionsByTarget($run);
            $bySheet = [];
            foreach ($template->mappings as $mapping) {
                $sheet = trim((string) ($mapping['sheet'] ?? ''));
                $cell = strtoupper(trim((string) ($mapping['cell'] ?? '')));
                if (! isset($sheets[$sheet]) || ! preg_match('/^[A-Z]{1,3}[1-9][0-9]{0,6}$/', $cell)) {
                    throw ValidationException::withMessages(['mapping' => 'Worksheet atau alamat sel dalam pemetaan tidak valid: '.$sheet.' '.$cell]);
                }
                if (isset($bySheet[$sheet][$cell])) {
                    throw ValidationException::withMessages(['mapping' => 'Satu sel tidak boleh memiliki lebih dari satu pemetaan.']);
                }
                $bySheet[$sheet][$cell] = $this->resolve($run, $decisions, $mapping, $final);
            }
            foreach ($bySheet as $sheetName => $cells) {
                $entry = $sheets[$sheetName];
                $xml = $zip->getFromName($entry);
                if ($xml === false || strlen($xml) > 20000000) {
                    throw ValidationException::withMessages(['template' => 'XML worksheet tidak tersedia atau terlalu besar.']);
                }
                $document = $this->loadXml($xml);
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('x', self::SHEET_NS);
                foreach ($cells as $cellRef => $value) {
                    $cell = $xpath->query('//x:c[@r="'.$cellRef.'"]')->item(0);
                    if (! $cell instanceof DOMElement) {
                        $cell = $this->createBlankInputCell($document, $xpath, $cellRef, $sheetName);
                    }
                    if ($cell->getElementsByTagNameNS(self::SHEET_NS, 'f')->length > 0) {
                        throw ValidationException::withMessages(['mapping' => 'Sel formula '.$sheetName.'!'.$cellRef.' tidak boleh ditimpa.']);
                    }
                    while ($cell->firstChild) { $cell->removeChild($cell->firstChild); }
                    if ($value === null) {
                        $cell->removeAttribute('t');
                        continue;
                    }
                    if (is_int($value) || is_float($value)) {
                        $cell->setAttribute('t', 'n');
                        $cell->appendChild($document->createElementNS(self::SHEET_NS, 'v', (string) $value));
                    } else {
                        // Literal inline string, never an Excel formula (prevents formula injection).
                        $cell->setAttribute('t', 'inlineStr');
                        $inline = $document->createElementNS(self::SHEET_NS, 'is');
                        $text = $document->createElementNS(self::SHEET_NS, 't');
                        $text->appendChild($document->createTextNode((string) $value));
                        $inline->appendChild($text);
                        $cell->appendChild($inline);
                    }
                }
                $zip->addFromString($entry, $document->saveXML());
            }
            $this->requestRecalculation($zip);
        } finally {
            $zip->close();
        }
    }

    private function assertCompleteGapMapping(GuidedAssessment $run, array $mappings): void
    {
        $provided = [];
        foreach ($mappings as $mapping) {
            $source = (string) ($mapping['source'] ?? '');
            $code = (string) ($mapping['code'] ?? '');
            if (in_array($source, ['control', 'requirement'], true) && $code !== '') {
                $provided[$source.':'.$code][] = $mapping['field'] ?? '';
            }
        }
        $questions = $run->bank->questions()->with(['targets.requirement', 'targets.control'])->get();
        $missing = [];
        foreach ($questions as $question) {
            foreach ($question->targets as $target) {
                $key = $target->control_id ? 'control:'.$target->control?->code : 'requirement:'.$target->requirement?->code;
                $fields = $provided[$key] ?? [];
                if (! in_array('status', $fields, true)
                    || count(array_intersect($fields, ['notes_and_evidence', 'evidence_refs', 'summary', 'rationale'])) === 0) {
                    $missing[] = $key;
                }
            }
        }
        if ($missing) {
            throw ValidationException::withMessages(['mappings' => 'Pemetaan GAP Analysis belum mencakup status dan bukti/catatan untuk: '.implode(', ', array_slice(array_unique($missing), 0, 12))]);
        }
    }

    private function validatePackage(ZipArchive $zip): void
    {
        if ($zip->numFiles > 2000) {
            throw ValidationException::withMessages(['template' => 'Jumlah bagian workbook melebihi batas keamanan.']);
        }
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $name = (string) ($entry['name'] ?? '');
            $expanded += (int) ($entry['size'] ?? 0);
            if ($expanded > 150_000_000 || str_contains($name, '..') || str_contains($name, chr(92))
                || str_starts_with($name, '/')
                || preg_match('~(?:^|/)(?:vbaProject\\.bin|externalLinks|embeddings)(?:/|$)~i', $name)) {
                throw ValidationException::withMessages(['template' => 'Workbook mengandung bagian tidak aman atau terlalu besar.']);
            }
        }
    }

    private function createBlankInputCell(DOMDocument $document, DOMXPath $xpath, string $reference, string $sheet): DOMElement
    {
        $rowNumber = (int) preg_replace('/^[A-Z]+/', '', $reference);
        $row = $xpath->query('/x:worksheet/x:sheetData/x:row[@r="'.$rowNumber.'"]')->item(0);
        if (! $row instanceof DOMElement) {
            throw ValidationException::withMessages(['mapping' => 'Baris '.$sheet.'!'.$reference.' tidak tersedia dalam template.']);
        }
        $cell = $document->createElementNS(self::SHEET_NS, 'c');
        $cell->setAttribute('r', $reference);
        $columnNumber = $this->columnNumber($reference);
        foreach ($row->childNodes as $existing) {
            if ($existing instanceof DOMElement && $existing->localName === 'c'
                && $this->columnNumber($existing->getAttribute('r')) > $columnNumber) {
                $row->insertBefore($cell, $existing);
                return $cell;
            }
        }
        $row->appendChild($cell);
        return $cell;
    }

    private function columnNumber(string $reference): int
    {
        $number = 0;
        foreach (str_split(preg_replace('/[0-9]/', '', strtoupper($reference))) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }
        return $number;
    }

    private function requestRecalculation(ZipArchive $zip): void
    {
        $xml = $zip->getFromName('xl/workbook.xml');
        if ($xml === false) { return; }
        $document = $this->loadXml($xml);
        $calc = $document->getElementsByTagNameNS(self::SHEET_NS, 'calcPr')->item(0);
        if (! $calc instanceof DOMElement) {
            $calc = $document->createElementNS(self::SHEET_NS, 'calcPr');
            $document->documentElement->appendChild($calc);
        }
        $calc->setAttribute('calcMode', 'auto');
        $calc->setAttribute('fullCalcOnLoad', '1');
        $calc->setAttribute('forceFullCalc', '1');
        $zip->addFromString('xl/workbook.xml', $document->saveXML());
    }

    private function loadXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw ValidationException::withMessages(['template' => 'Dokumen XML XLSX rusak.']);
        }
        return $dom;
    }

    private function sheetPaths(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relationships === false) {
            throw ValidationException::withMessages(['template' => 'Struktur workbook tidak lengkap.']);
        }
        $wb = $this->loadXml($workbook);
        $rel = $this->loadXml($relationships);
        $targets = [];
        foreach ($rel->getElementsByTagNameNS(self::PACKAGE_REL_NS, 'Relationship') as $relationship) {
            $target = $relationship->getAttribute('Target');
            $target = ltrim($target, '/');
            if (str_contains($target, '..') || str_contains($target, chr(92))) {
                continue;
            }
            $targets[$relationship->getAttribute('Id')] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }
        $paths = [];
        foreach ($wb->getElementsByTagNameNS(self::SHEET_NS, 'sheet') as $sheet) {
            $id = $sheet->getAttributeNS(self::OFFICE_REL_NS, 'id');
            if (isset($targets[$id]) && $zip->locateName($targets[$id]) !== false) {
                $paths[$sheet->getAttribute('name')] = $targets[$id];
            }
        }
        return $paths;
    }

    private function decisionsByTarget(GuidedAssessment $run): array
    {
        $decisions = GuidedDecision::query()->whereHas('consolidation',
            fn ($q) => $q->where('guided_assessment_id', $run->id)->where('is_current', true))
            ->where('status', 'approved')->whereNotNull('synced_at')
            ->with(['consolidation', 'target.requirement', 'target.control', 'acceptances.evidence'])
            ->get();
        $index = [];
        foreach ($decisions as $decision) {
            $target = $decision->target;
            $key = $target->control_id ? 'control:'.$target->control?->code : 'requirement:'.$target->requirement?->code;
            if (isset($index[$key]) && $index[$key]->id !== $decision->id) {
                throw ValidationException::withMessages(['assessment' => 'Ada lebih dari satu penilaian aktif untuk '.$key.'. Rekonsiliasi diperlukan.']);
            }
            $index[$key] = $decision;
        }
        return $index;
    }

    private function resolve(GuidedAssessment $run, array $decisions, array $mapping, bool $final): string|int|float|null
    {
        $source = (string) ($mapping['source'] ?? '');
        $field = (string) ($mapping['field'] ?? '');
        $code = trim((string) ($mapping['code'] ?? ''));
        if ($source === 'assessment') {
            return match ($field) {
                'name' => $run->name,
                'management_system' => $run->system?->name ?? '',
                'scope' => $run->system?->scope?->name ?? '',
                'date' => $run->locked_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                default => throw ValidationException::withMessages(['mapping' => 'Field asesmen tidak dikenal.']),
            };
        }
        if (! in_array($source, ['requirement', 'control'], true) || $code === '') {
            throw ValidationException::withMessages(['mapping' => 'Mapping harus menggunakan sumber requirement, control, atau assessment.']);
        }
        $decision = $decisions[$source.':'.$code] ?? null;
        if (! $decision) {
            if ($final || ($mapping['required'] ?? false)) {
                throw ValidationException::withMessages(['mapping' => 'Tidak ada penilaian yang disetujui untuk '.$source.':'.$code]);
            }
            return null;
        }
        return match ($field) {
            'status' => match ($decision->proposed_status) {
                'conformant', 'implemented' => 'Diterapkan Penuh',
                'partial', 'in_progress' => 'Diterapkan Sebagian',
                'planned' => 'Tahap Perencanaan',
                'nonconformant', 'not_started' => 'Belum Diterapkan',
                'not_applicable' => 'Tidak Berlaku (N/A)',
                default => '',
            },
            'raw_status' => $decision->proposed_status,
            'rationale' => $decision->rationale,
            'summary' => $decision->consolidation->summary,
            'risk_score' => $decision->risk_score,
            'evidence_refs' => $decision->acceptances->where('status', 'accepted')->map(fn ($a) => 'EVD-'.$a->evidence_id)->implode(', '),
            'notes_and_evidence' => trim($decision->consolidation->summary.' | '.$decision->rationale
                .' | Bukti: '.($decision->acceptances->where('status', 'accepted')->map(fn ($a) => 'EVD-'.$a->evidence_id)->implode(', ') ?: 'Tidak ada file; lihat rekaman penilaian')),
            'na_justification' => $decision->proposed_status === 'not_applicable' ? $decision->rationale : null,
            'approved_at' => $decision->approved_at?->format('Y-m-d') ?? '',
            'applicable' => $decision->applicable === false ? 'Tidak' : 'Ya',
            default => throw ValidationException::withMessages(['mapping' => 'Field penilaian tidak dikenal.']),
        };
    }
}
