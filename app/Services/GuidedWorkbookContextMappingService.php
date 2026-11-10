<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuidedWorkbookContextMappingService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** Detect generic profile, risk and inventory tables by their COLUMN LABELS. */
    public function suggest(ZipArchive $zip, array $shared, array $parts): array
    {
        $mappings = [];
        $capacities = ['risk_index' => 0, 'asset_index' => 0, 'activity_index' => 0];
        $warnings = [];
        foreach ($parts as $name => $part) {
            $xml = $zip->getFromName($part);
            if (! is_string($xml) || strlen($xml) > 20_000_000) { continue; }
            $dom = new DOMDocument();
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                throw ValidationException::withMessages(['template' => 'Worksheet konteks tidak valid.']);
            }
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('x', self::NS);
            $rows = [];
            foreach ($xpath->query('/x:worksheet/x:sheetData/x:row') as $row) {
                $number = (int) $row->getAttribute('r');
                if ($number > 10000) { break; }
                $rows[$number] = $this->cells($row, $shared);
            }

            // Header-field mapping is independent of the example organization's name.
            foreach ($rows as $number => $values) {
                if ($number > 20) { break; }
                foreach ($values as $column => $value) {
                    $field = match ($this->normalize((string) $value)) {
                        'nama yayasan', 'nama organisasi', 'nama perusahaan', 'nama lembaga', 'nama entitas' => 'organization_name',
                        'unit bagian yang dinilai', 'unit yang dinilai', 'unit kerja yang dinilai' => 'unit_name',
                        'ruang lingkup smki deskripsi singkat', 'deskripsi ruang lingkup', 'ruang lingkup smki' => 'scope_description',
                        'nama asesor' => 'assessor_names',
                        'jabatan unit asesor' => 'assessor_role',
                        'tanggal asesmen' => 'date',
                        default => null,
                    };
                    if ($field) {
                        $mappings[] = ['sheet' => $name,
                            'cell' => $this->offsetColumn($column, 2).$number,
                            'source' => 'assessment', 'field' => $field, 'required' => false];
                    }
                }
            }

            foreach ($rows as $headerNumber => $values) {
                $normalized = [];
                foreach ($values as $column => $text) { $normalized[$column] = $this->normalize((string) $text); }

                $kind = null;
                $definitions = [];
                if (in_array('skenario risiko ancaman', $normalized, true)
                    && in_array('kategori aset', $normalized, true)) {
                    $kind = 'risk_index';
                    $definitions = [
                        'kategori aset' => 'category', 'skenario risiko ancaman' => 'title',
                        'potensi dampak' => 'description', 'kontrol terkait 1' => 'control_1',
                        'kontrol terkait 2' => 'control_2', 'kontrol terkait 3' => 'control_3',
                        'kemungkinan 1 5' => 'likelihood', 'dampak 1 5' => 'impact',
                        'catatan rencana tindak lanjut' => 'treatment_plan',
                    ];
                } elseif (in_array('jenis informasi', $normalized, true)
                    && in_array('kelompok', $normalized, true)) {
                    $kind = 'asset_index';
                    $definitions = [
                        'kelompok' => 'category', 'jenis informasi' => 'title',
                        'dikelola' => 'in_scope', 'klasifikasi' => 'classification',
                        'catatan' => 'notes',
                    ];
                } elseif (in_array('aktivitas', $normalized, true)
                    && in_array('dilakukan', $normalized, true)) {
                    $kind = 'activity_index';
                    $definitions = [
                        'aktivitas' => 'title', 'dilakukan' => 'in_scope',
                        'kontrol lampiran a terkait referensi' => 'related_controls',
                        'catatan' => 'notes',
                    ];
                }

                if ($kind === null) { continue; }
                $numberColumn = array_search('no', $normalized, true);
                if (! is_string($numberColumn)) {
                    $warnings[] = 'Kolom nomor urut tidak ditemukan pada tabel '.$name;
                    continue;
                }
                $fields = [];
                foreach ($definitions as $header => $field) {
                    $column = array_search($header, $normalized, true);
                    if (is_string($column)) { $fields[$column] = $field; }
                }
                foreach ($rows as $rowNumber => $rowValues) {
                    if ($rowNumber <= $headerNumber) { continue; }
                    $index = (string) ($rowValues[$numberColumn] ?? '');
                    if (! ctype_digit($index) || (int) $index < 1 || (int) $index > 10000) {
                        // A finished table is followed by an unrelated section.
                        break;
                    }
                    $capacities[$kind] = max($capacities[$kind], (int) $index);
                    foreach ($fields as $column => $field) {
                        $mappings[] = [
                            'sheet' => $name, 'cell' => $column.$rowNumber,
                            'source' => $kind, 'code' => $index,
                            'field' => $field, 'required' => false,
                        ];
                    }
                }
            }
        }
        return ['mappings' => $mappings, 'capacities' => $capacities, 'warnings' => $warnings];
    }

    private function cells(DOMElement $row, array $shared): array
    {
        $values = [];
        foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $cell) {
            $column = preg_replace('/[0-9]+/', '', $cell->getAttribute('r'));
            if ($cell->getAttribute('t') === 'inlineStr') {
                $string = '';
                foreach ($cell->getElementsByTagNameNS(self::NS, 't') as $node) { $string .= $node->textContent; }
                $values[$column] = $string;
            } else {
                $raw = $cell->getElementsByTagNameNS(self::NS, 'v')->item(0)?->textContent ?? '';
                $values[$column] = $cell->getAttribute('t') === 's'
                    ? ($shared[(int) $raw] ?? '') : $raw;
            }
        }
        return $values;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', mb_strtolower($text)));
    }

    private function offsetColumn(string $column, int $offset): string
    {
        $number = 0;
        foreach (str_split(strtoupper($column)) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }
        $number += $offset;
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)).$letters;
            $number = intdiv($number, 26);
        }
        return $letters;
    }
}
