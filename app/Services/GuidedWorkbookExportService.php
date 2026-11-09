<?php

namespace App\Services;

use App\Models\GuidedAssessment;
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
        $src = Storage::disk('local')->path($template->file_path);
        if (! is_file($src) || strtolower(pathinfo($src, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw ValidationException::withMessages(['template' => 'File template XLSX tidak tersedia.']);
        }
        $relative = 'iso/exports/assessment-'.$run->id.'-'.($final ? 'FINAL' : 'DRAFT').'-'.Str::random(12).'.xlsx';
        $dest = Storage::disk('local')->path($relative);
        File::ensureDirectoryExists(dirname($dest));
        if (! copy($src, $dest)) {
            throw ValidationException::withMessages(['template' => 'Gagal menyalin template.']);
        }
        try {
            $this->fill($dest, $run, $template, $final);
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
                        throw ValidationException::withMessages(['mapping' => 'Sel '.$sheetName.'!'.$cellRef.' tidak ada dalam template.']);
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
        } finally {
            $zip->close();
        }
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
            'approved_at' => $decision->approved_at?->format('Y-m-d') ?? '',
            'applicable' => $decision->applicable === false ? 'Tidak' : 'Ya',
            default => throw ValidationException::withMessages(['mapping' => 'Field penilaian tidak dikenal.']),
        };
    }
}
