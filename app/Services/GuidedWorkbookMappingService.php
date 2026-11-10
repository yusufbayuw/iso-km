<?php

namespace App\Services;

use App\Models\Control;
use App\Models\GuidedExportTemplate;
use App\Models\Requirement;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuidedWorkbookMappingService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Suggests mappings from workbook headers and catalog codes, never from an
     * organization's identity. Ambiguous controls/clauses remain unmapped.
     */
    public function suggest(GuidedExportTemplate $template): array
    {
        if (! $template->management_system_id) {
            throw ValidationException::withMessages(['template' => 'Pilih sistem manajemen sebagai konteks deteksi pemetaan.']);
        }
        $path = app(GuidedWorkbookTemplateFileService::class)->path($template->file_path);
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['template' => 'Template XLSX tidak dapat dibuka.']);
        }
        try {
            $standardId = $template->system->standard_id;
            $requirements = Requirement::query()->where('standard_id', $standardId)->pluck('code')->all();
            $controls = Control::query()->where('standard_id', $standardId)->where('is_group', false)->pluck('code')->all();
            $strings = $this->sharedStrings($zip);
            $parts = $this->sheetPaths($zip);
            $mappings = [];
            $warnings = [];
            $identities = [];
            foreach ($parts as $sheetName => $part) {
                $xml = $zip->getFromName($part);
                if ($xml === false || strlen($xml) > 20_000_000) {
                    $warnings[] = 'Worksheet dilewati: '.$sheetName;
                    continue;
                }
                $document = $this->xml($xml);
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('x', self::NS);
                $rows = $xpath->query('/x:worksheet/x:sheetData/x:row');
                $head = [];
                $headerRow = 0;
                foreach ($rows as $row) {
                    if ((int) $row->getAttribute('r') > 8) { break; }
                    $candidate = $this->cells($row, $strings);
                    $code = $this->findColumn($candidate, ['kode', 'code', 'control id', 'clause id']);
                    $status = $this->findColumn($candidate, ['status penerapan', 'implementation status', 'status']);
                    if ($code && $status) { $head = $candidate; $headerRow = (int) $row->getAttribute('r'); break; }
                }
                if (! $head) { continue; }
                $codeColumn = $this->findColumn($head, ['kode', 'code', 'control id', 'clause id']);
                $statusColumn = $this->findColumn($head, ['status penerapan', 'implementation status', 'status']);
                $evidenceColumn = $this->findColumn($head, ['bukti catatan penerapan', 'evidence notes', 'evidence', 'bukti', 'catatan']);
                $naColumn = $this->findColumn($head, ['alasan tidak berlaku n a', 'n a justification', 'not applicable reason']);
                $description = mb_strtolower($sheetName.' '.implode(' ', $head));
                $kind = preg_match('/klausul|clause/u', $description) ? 'requirement'
                    : (preg_match('/kontrol|control|kategori/u', $description) ? 'control' : null);

                foreach ($rows as $row) {
                    $number = (int) $row->getAttribute('r');
                    if ($number <= $headerRow || $number > 100_000) { continue; }
                    $values = $this->cells($row, $strings);
                    $rawCode = trim((string) ($values[$codeColumn] ?? ''));
                    if ($rawCode === '') { continue; }
                    $normalizedRequirement = preg_replace('/k$/i', '', $rawCode);
                    $asRequirement = in_array($normalizedRequirement, $requirements, true) ? $normalizedRequirement : null;
                    $asControl = in_array($rawCode, $controls, true) ? $rawCode
                        : (in_array('A.'.$rawCode, $controls, true) ? 'A.'.$rawCode : null);
                    if ($kind === 'requirement') { $asControl = null; }
                    if ($kind === 'control') { $asRequirement = null; }
                    if (($asRequirement !== null) === ($asControl !== null)) {
                        $warnings[] = $sheetName.'!'.$codeColumn.$number.' kode tidak ditemukan/ambigu: '.$rawCode;
                        continue;
                    }
                    $source = $asRequirement ? 'requirement' : 'control';
                    $code = $asRequirement ?? $asControl;
                    $key = $source.':'.$code;
                    if (isset($identities[$key])) {
                        $warnings[] = 'Duplikasi '.$key.' di '.$sheetName.'; hanya lokasi pertama dipetakan.';
                        continue;
                    }
                    $identities[$key] = true;
                    $mappings[] = ['sheet' => $sheetName, 'cell' => $statusColumn.$number,
                        'source' => $source, 'code' => $code, 'field' => 'status', 'required' => true];
                    if ($evidenceColumn) {
                        $mappings[] = ['sheet' => $sheetName, 'cell' => $evidenceColumn.$number,
                            'source' => $source, 'code' => $code, 'field' => 'notes_and_evidence', 'required' => true];
                    }
                    if ($naColumn && $source === 'control') {
                        $mappings[] = ['sheet' => $sheetName, 'cell' => $naColumn.$number,
                            'source' => $source, 'code' => $code, 'field' => 'na_justification', 'required' => false];
                    }
                }
            }
            return ['mappings' => $mappings, 'recognized_targets' => count($identities),
                'warnings' => array_slice($warnings, 0, 50)];
        } finally {
            $zip->close();
        }
    }

    private function xml(string $contents): DOMDocument
    {
        $document = new DOMDocument();
        if (! $document->loadXML($contents, LIBXML_NONET)) {
            throw ValidationException::withMessages(['template' => 'XML workbook tidak valid.']);
        }
        return $document;
    }

    private function sheetPaths(ZipArchive $zip): array
    {
        $book = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($book === false || $rels === false) {
            throw ValidationException::withMessages(['template' => 'Struktur workbook tidak lengkap.']);
        }
        $references = [];
        foreach ($this->xml($rels)->getElementsByTagNameNS(self::PKG, 'Relationship') as $rel) {
            $target = ltrim($rel->getAttribute('Target'), '/');
            if (str_contains($target, '..') || str_contains($target, chr(92))) { continue; }
            $entry = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            if ($zip->locateName($entry) !== false) { $references[$rel->getAttribute('Id')] = $entry; }
        }
        $sheets = [];
        foreach ($this->xml($book)->getElementsByTagNameNS(self::NS, 'sheet') as $sheet) {
            $id = $sheet->getAttributeNS(self::REL, 'id');
            if (isset($references[$id])) { $sheets[$sheet->getAttribute('name')] = $references[$id]; }
        }
        return $sheets;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) { return []; }
        if (strlen($xml) > 20_000_000) {
            throw ValidationException::withMessages(['template' => 'Shared strings XLSX terlalu besar.']);
        }
        $values = [];
        foreach ($this->xml($xml)->getElementsByTagNameNS(self::NS, 'si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagNameNS(self::NS, 't') as $node) { $text .= $node->textContent; }
            $values[] = $text;
        }
        return $values;
    }

    private function cells(\DOMElement $row, array $shared): array
    {
        $values = [];
        foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $cell) {
            $column = preg_replace('/\d+/', '', $cell->getAttribute('r'));
            $type = $cell->getAttribute('t');
            if ($type === 'inlineStr') {
                $text = '';
                foreach ($cell->getElementsByTagNameNS(self::NS, 't') as $node) { $text .= $node->textContent; }
                $values[$column] = $text;
            } else {
                $value = $cell->getElementsByTagNameNS(self::NS, 'v')->item(0)?->textContent ?? '';
                $values[$column] = $type === 's' ? ($shared[(int) $value] ?? '') : $value;
            }
        }
        return $values;
    }

    private function findColumn(array $headers, array $aliases): ?string
    {
        foreach ($headers as $column => $header) {
            $normalized = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $header)));
            if (in_array($normalized, $aliases, true)) { return $column; }
        }
        return null;
    }
}
