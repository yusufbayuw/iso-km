<?php

namespace App\Services;

use App\Models\Control;
use App\Models\GuidedAssessment;
use App\Models\GuidedQuestionBank;
use App\Models\Requirement;
use App\Models\Standard;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuidedStandardCatalogImportService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Import a user-owned catalog, using only code/title by default.
     * Never derives organization defaults, workflow or rights from its content.
     */
    public function import(Standard $standard, string $relativeFile, User $actor, bool $includeDescriptions = false): array
    {
        if (! $actor->can('guided.bank.manage') || ! $actor->can('update_standard')) {
            throw ValidationException::withMessages(['authorization' => 'Diperlukan hak pengelolaan master standar dan bank pertanyaan.']);
        }
        if (GuidedQuestionBank::query()->where('standard_id', $standard->id)->where('status', 'published')->exists()
            || GuidedAssessment::query()->whereHas('bank', fn ($q) => $q->where('standard_id', $standard->id))->exists()) {
            throw ValidationException::withMessages(['catalog' => 'Katalog standar yang telah digunakan asesmen atau bank terbit tidak boleh diubah. Gunakan edisi/versi standar baru.']);
        }
        $file = $this->filePath($relativeFile);
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw ValidationException::withMessages(['catalog' => 'File XLSX tidak dapat dibuka.']);
        }
        try {
            $rows = $this->rows($zip);
        } finally {
            $zip->close();
        }
        if (! $rows['requirements'] && ! $rows['controls']) {
            throw ValidationException::withMessages(['catalog' => 'Tidak ada sheet klausul/kontrol dengan kolom Kode dan Judul.']);
        }

        $inserted = ['requirements' => 0, 'controls' => 0];
        $skipped = ['requirements' => 0, 'controls' => 0];
        DB::transaction(function () use ($standard, $rows, $includeDescriptions, &$inserted, &$skipped): void {
            foreach ($rows['requirements'] as $index => $row) {
                $code = $row['code'];
                if (Requirement::query()->where('standard_id', $standard->id)->where('code', $code)->exists()) {
                    $skipped['requirements']++;
                    continue;
                }
                $major = explode('.', $code)[0];
                $parentId = Requirement::query()->where('standard_id', $standard->id)
                    ->where('code', $major)->value('id');
                Requirement::query()->create([
                    'standard_id' => $standard->id, 'parent_id' => $parentId,
                    'code' => $code, 'title' => $row['title'],
                    'description' => $includeDescriptions ? $row['description'] : null,
                    'type' => 'subclause', 'is_mandatory' => true, 'sort_order' => $index + 1,
                ]);
                $inserted['requirements']++;
            }
            foreach ($rows['controls'] as $index => $row) {
                $code = $row['code'];
                if (Control::query()->where('standard_id', $standard->id)->where('code', $code)->exists()) {
                    $skipped['controls']++;
                    continue;
                }
                $parentCode = implode('.', array_slice(explode('.', $code), 0, 2));
                $parentId = Control::query()->where('standard_id', $standard->id)
                    ->where('code', $parentCode)->value('id');
                Control::query()->create([
                    'standard_id' => $standard->id, 'parent_id' => $parentId,
                    'code' => $code, 'title' => $row['title'],
                    'description' => $includeDescriptions ? $row['description'] : null,
                    'category' => $row['category'],
                    'is_group' => false, 'sort_order' => $index + 1,
                ]);
                $inserted['controls']++;
            }
        });
        return compact('inserted', 'skipped');
    }

    private function filePath(string $relative): string
    {
        if (! str_starts_with($relative, 'iso/guided/catalog-imports/')
            || str_contains($relative, '..') || str_contains($relative, chr(92))
            || strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw ValidationException::withMessages(['catalog' => 'Impor harus menggunakan file XLSX privat.']);
        }
        $root = realpath(Storage::disk('local')->path('iso/guided/catalog-imports'));
        $file = realpath(Storage::disk('local')->path($relative));
        if (! $root || ! $file || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || filesize($file) > 20_000_000) {
            throw ValidationException::withMessages(['catalog' => 'Path atau ukuran file impor tidak valid.']);
        }
        return $file;
    }

    private function rows(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relations = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relations === false || $zip->numFiles > 2000) {
            throw ValidationException::withMessages(['catalog' => 'Paket Excel tidak lengkap atau terlalu besar.']);
        }
        $shared = [];
        $strings = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($strings) && strlen($strings) < 20_000_000) {
            foreach ($this->xml($strings)->getElementsByTagNameNS(self::NS, 'si') as $node) {
                $value = '';
                foreach ($node->getElementsByTagNameNS(self::NS, 't') as $t) { $value .= $t->textContent; }
                $shared[] = $value;
            }
        }
        $refs = [];
        foreach ($this->xml($relations)->getElementsByTagNameNS(self::PKG, 'Relationship') as $rel) {
            $entry = ltrim($rel->getAttribute('Target'), '/');
            if (str_contains($entry, '..') || str_contains($entry, chr(92))) { continue; }
            $entry = str_starts_with($entry, 'xl/') ? $entry : 'xl/'.$entry;
            if ($zip->locateName($entry) !== false) { $refs[$rel->getAttribute('Id')] = $entry; }
        }
        $result = ['requirements' => [], 'controls' => []];
        $seen = ['requirements' => [], 'controls' => []];
        foreach ($this->xml($workbook)->getElementsByTagNameNS(self::NS, 'sheet') as $sheet) {
            $name = mb_strtolower($sheet->getAttribute('name'));
            $kind = preg_match('/requirement|clause|klausul/u', $name) ? 'requirements'
                : (preg_match('/control|kontrol/u', $name) ? 'controls' : null);
            $entry = $refs[$sheet->getAttributeNS(self::REL, 'id')] ?? null;
            if (! $kind || ! $entry) { continue; }
            $xml = $zip->getFromName($entry);
            if ($xml === false || strlen($xml) > 20_000_000) {
                throw ValidationException::withMessages(['catalog' => 'Worksheet katalog terlalu besar.']);
            }
            $document = $this->xml($xml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('x', self::NS);
            $headers = [];
            $headerRow = 0;
            foreach ($xpath->query('/x:worksheet/x:sheetData/x:row') as $row) {
                $index = (int) $row->getAttribute('r');
                if ($index > 8) { break; }
                $values = $this->cells($row, $shared);
                $code = $this->column($values, ['code', 'kode']);
                $title = $this->column($values, ['title', 'judul']);
                if ($code && $title) { $headers = $values; $headerRow = $index; break; }
            }
            if (! $headers) { continue; }
            $codeColumn = $this->column($headers, ['code', 'kode']);
            $titleColumn = $this->column($headers, ['title', 'judul']);
            $descriptionColumn = $this->column($headers, ['description', 'deskripsi persyaratan', 'deskripsi']);
            $categoryColumn = $this->column($headers, ['category', 'kategori']);
            foreach ($xpath->query('/x:worksheet/x:sheetData/x:row') as $row) {
                $number = (int) $row->getAttribute('r');
                if ($number <= $headerRow || $number > 10000) { continue; }
                $cells = $this->cells($row, $shared);
                $code = trim((string) ($cells[$codeColumn] ?? ''));
                $title = trim((string) ($cells[$titleColumn] ?? ''));
                if ($code === '' || $title === '') { continue; }
                $code = $kind === 'requirements'
                    ? preg_replace('/k$/i', '', $code)
                    : (str_starts_with(strtoupper($code), 'A.') ? strtoupper($code) : 'A.'.$code);
                if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $code)) {
                    continue;
                }
                if (isset($seen[$kind][$code])) {
                    throw ValidationException::withMessages(['catalog' => 'Duplikasi kode '.$code.' ditemukan di file sumber.']);
                }
                $seen[$kind][$code] = true;
                $result[$kind][] = [
                    'code' => $code, 'title' => mb_substr($title, 0, 255),
                    'description' => $descriptionColumn ? mb_substr((string) ($cells[$descriptionColumn] ?? ''), 0, 10000) : null,
                    'category' => $categoryColumn ? mb_substr((string) ($cells[$categoryColumn] ?? ''), 0, 100) : null,
                ];
            }
        }
        return $result;
    }

    private function xml(string $source): DOMDocument
    {
        $document = new DOMDocument();
        if (! $document->loadXML($source, LIBXML_NONET)) {
            throw ValidationException::withMessages(['catalog' => 'XML katalog tidak dapat dibaca.']);
        }
        return $document;
    }

    private function cells(\DOMElement $row, array $shared): array
    {
        $cells = [];
        foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $node) {
            $column = preg_replace('/[0-9]+/', '', $node->getAttribute('r'));
            if ($node->getAttribute('t') === 'inlineStr') {
                $value = '';
                foreach ($node->getElementsByTagNameNS(self::NS, 't') as $t) { $value .= $t->textContent; }
            } else {
                $value = $node->getElementsByTagNameNS(self::NS, 'v')->item(0)?->textContent ?? '';
                if ($node->getAttribute('t') === 's') { $value = $shared[(int) $value] ?? ''; }
            }
            $cells[$column] = $value;
        }
        return $cells;
    }

    private function column(array $cells, array $aliases): ?string
    {
        foreach ($cells as $column => $value) {
            $normalized = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $value)));
            if (in_array($normalized, $aliases, true)) { return $column; }
        }
        return null;
    }
}
