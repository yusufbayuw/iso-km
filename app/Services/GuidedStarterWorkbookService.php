<?php

namespace App\Services;

use App\Models\GuidedExportTemplate;
use App\Models\Standard;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GuidedStarterWorkbookService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function cell(string $coordinate, ?string $text, bool $header = false): string
    {
        if ($text === null) { return ''; }
        return '<c r="'.$coordinate.'"'.($header ? ' s="1"' : '').' t="inlineStr"><is><t>'
            .$this->escape($text).'</t></is></c>';
    }

    private function formula(string $coordinate, string $expression): string
    {
        return '<c r="'.$coordinate.'"><f>'.$this->escape($expression).'</f></c>';
    }

    private function row(int $number, array $cells, bool $header = false): string
    {
        $contents = '';
        foreach ($cells as $column => $text) {
            $contents .= $this->cell($column.$number, $text === null ? null : (string) $text, $header);
        }
        return '<row r="'.$number.'">'.$contents.'</row>';
    }

    private function sheet(array $rows): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::NS.'">'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews>'
            .'<cols><col min="1" max="1" width="18" customWidth="1"/>'
            .'<col min="2" max="2" width="50" customWidth="1"/>'
            .'<col min="3" max="3" width="32" customWidth="1"/>'
            .'<col min="4" max="4" width="25" customWidth="1"/>'
            .'<col min="5" max="5" width="65" customWidth="1"/>'
            .'<col min="6" max="11" width="34" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData></worksheet>';
    }

    private function scoreFormula(int $row): string
    {
        $cell = 'C'.$row;
        return 'IF('.$cell.'="Diterapkan Penuh",1,IF('.$cell.'="Diterapkan Sebagian",0.667,'
            .'IF('.$cell.'="Tahap Perencanaan",0.333,IF('.$cell.'="Belum Diterapkan",0,""))))';
    }

    /**
     * Generates a valid neutral XLSX package, independent of Python or
     * organization-specific uploaded templates. No macros or remote links.
     */
    public function ensureTemplate(Standard $standard): GuidedExportTemplate
    {
        $path = 'iso/guided/templates/iso27001-wizard-neutral-v1.xlsx';
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            $disk->makeDirectory('iso/guided/templates');
            $this->generate($standard, $disk->path($path));
        }
        $mappings = $this->mappings($standard);

        return GuidedExportTemplate::query()->firstOrCreate(
            ['name' => 'GAP Analysis ISO/IEC 27001:2022 - Template Standar',
             'management_system_id' => null, 'report_type' => 'gap_analysis'],
            ['file_path' => $path, 'mappings' => $mappings, 'is_active' => true]
        );
    }

    public function mappings(Standard $standard): array
    {
        $maps = [
            ['sheet' => 'Profil', 'cell' => 'C5', 'source' => 'assessment', 'field' => 'organization_name'],
            ['sheet' => 'Profil', 'cell' => 'C6', 'source' => 'assessment', 'field' => 'unit_name'],
            ['sheet' => 'Profil', 'cell' => 'C7', 'source' => 'assessment', 'field' => 'scope_description'],
            ['sheet' => 'Profil', 'cell' => 'C8', 'source' => 'assessment', 'field' => 'assessor_names'],
            ['sheet' => 'Profil', 'cell' => 'C9', 'source' => 'assessment', 'field' => 'assessor_role'],
            ['sheet' => 'Profil', 'cell' => 'C10', 'source' => 'assessment', 'field' => 'date'],
            ['sheet' => 'Profil', 'cell' => 'C11', 'source' => 'assessment', 'field' => 'name'],
            ['sheet' => 'Profil', 'cell' => 'C12', 'source' => 'assessment', 'field' => 'report_status'],
        ];

        $groups = [5 => 'Organisasi', 6 => 'Orang', 7 => 'Fisik', 8 => 'Teknologi'];
        foreach ($standard->requirements()->whereNotIn('code', ['4','5','6','7','8','9','10'])
            ->orderBy('sort_order')->get() as $index => $requirement) {
            foreach (['C' => 'status', 'E' => 'notes_and_evidence'] as $column => $field) {
                $maps[] = ['sheet' => 'Klausul', 'cell' => $column.($index + 2),
                    'source' => 'requirement', 'code' => $requirement->code, 'field' => $field,
                    'required' => true];
            }
        }
        foreach ($groups as $group => $sheet) {
            $controls = $standard->controls()->where('is_group', false)
                ->where('code', 'like', 'A.'.$group.'.%')->orderBy('sort_order')->get();
            foreach ($controls as $index => $control) {
                foreach (['C' => 'status', 'E' => 'notes_and_evidence', 'F' => 'na_justification'] as $column => $field) {
                    $maps[] = ['sheet' => $sheet, 'cell' => $column.($index + 2),
                        'source' => 'control', 'code' => $control->code, 'field' => $field,
                        'required' => $field !== 'na_justification'];
                }
            }
        }

        foreach (range(1, 150) as $index) {
            foreach (['B' => 'category', 'C' => 'title', 'D' => 'description',
                'E' => 'control_1', 'F' => 'control_2', 'G' => 'control_3',
                'H' => 'likelihood', 'I' => 'impact', 'K' => 'treatment_plan'] as $column => $field) {
                $maps[] = ['sheet' => 'Risiko', 'cell' => $column.($index + 1),
                    'source' => 'risk_index', 'code' => (string) $index, 'field' => $field];
            }
            foreach (['B' => 'category', 'C' => 'title', 'D' => 'in_scope',
                'E' => 'classification', 'F' => 'notes'] as $column => $field) {
                $maps[] = ['sheet' => 'Aset Informasi', 'cell' => $column.($index + 1),
                    'source' => 'asset_index', 'code' => (string) $index, 'field' => $field];
            }
            foreach (['B' => 'title', 'C' => 'in_scope', 'D' => 'related_controls',
                'E' => 'notes'] as $column => $field) {
                $maps[] = ['sheet' => 'Aktivitas Informasi', 'cell' => $column.($index + 1),
                    'source' => 'activity_index', 'code' => (string) $index, 'field' => $field];
            }
        }
        return $maps;
    }

    private function generate(Standard $standard, string $destination): void
    {
        $sheets = [];
        $profile = [
            $this->row(1, ['A' => 'ISO-KM - GAP Analysis ISO/IEC 27001:2022'], true),
            $this->row(2, ['A' => 'Template standar netral organisasi; hasil final memerlukan review dan persetujuan.']),
            $this->row(5, ['A' => 'Nama Organisasi']),
            $this->row(6, ['A' => 'Unit/Bagian yang Dinilai']),
            $this->row(7, ['A' => 'Ruang Lingkup SMKI (Deskripsi Singkat)']),
            $this->row(8, ['A' => 'Nama Asesor']),
            $this->row(9, ['A' => 'Jabatan/Unit Asesor']),
            $this->row(10, ['A' => 'Tanggal Asesmen']),
            $this->row(11, ['A' => 'Nama Asesmen']),
            $this->row(12, ['A' => 'Jenis Laporan']),
        ];
        $sheets['Profil'] = $this->sheet($profile);

        $makeCriteria = function (string $sheet, $items) use (&$sheets): void {
            $rows = [$this->row(1, ['A' => 'Kode', 'B' => 'Kriteria',
                'C' => 'Status Penerapan', 'D' => 'Skor',
                'E' => 'Bukti / Catatan Penerapan', 'F' => 'Alasan Tidak Berlaku (N/A)'], true)];
            foreach ($items as $index => $item) {
                $number = $index + 2;
                $rows[] = '<row r="'.$number.'">'
                    .$this->cell('A'.$number, $item->code)
                    .$this->cell('B'.$number, $item->title)
                    .$this->formula('D'.$number, $this->scoreFormula($number))
                    .'</row>';
            }
            $sheets[$sheet] = $this->sheet($rows);
        };

        $clauses = $standard->requirements()->whereNotIn('code', ['4','5','6','7','8','9','10'])
            ->orderBy('sort_order')->get()->values();
        $makeCriteria('Klausul', $clauses);
        foreach ([5 => 'Organisasi', 6 => 'Orang', 7 => 'Fisik', 8 => 'Teknologi'] as $group => $name) {
            $controls = $standard->controls()->where('is_group', false)
                ->where('code', 'like', 'A.'.$group.'.%')->orderBy('sort_order')->get()->values();
            $makeCriteria($name, $controls);
        }

        $inventory = [
            'Aset Informasi' => ['A' => 'No', 'B' => 'Kelompok', 'C' => 'Jenis Informasi',
                'D' => 'Dikelola?', 'E' => 'Klasifikasi', 'F' => 'Catatan'],
            'Aktivitas Informasi' => ['A' => 'No', 'B' => 'Aktivitas', 'C' => 'Dilakukan?',
                'D' => 'Kontrol Lampiran A Terkait (referensi)', 'E' => 'Catatan'],
            'Risiko' => ['A' => 'No', 'B' => 'Kategori Aset', 'C' => 'Skenario Risiko / Ancaman',
                'D' => 'Potensi Dampak', 'E' => 'Kontrol Terkait 1', 'F' => 'Kontrol Terkait 2',
                'G' => 'Kontrol Terkait 3', 'H' => 'Kemungkinan (1-5)', 'I' => 'Dampak (1-5)',
                'J' => 'Skor Risiko', 'K' => 'Catatan / Rencana Tindak Lanjut'],
        ];
        foreach ($inventory as $name => $columns) {
            $rows = [$this->row(1, $columns, true)];
            foreach (range(1, 150) as $index) {
                $num = $index + 1;
                $content = $this->cell('A'.$num, (string) $index);
                if ($name === 'Risiko') {
                    $content .= $this->formula('J'.$num, 'IF(AND(H'.$num.'<>"",I'.$num.'<>""),H'.$num.'*I'.$num.',"")');
                }
                $rows[] = '<row r="'.$num.'">'.$content.'</row>';
            }
            $sheets[$name] = $this->sheet($rows);
        }

        $dashboard = [
            $this->row(1, ['A' => 'RINGKASAN KESIAPAN - INDEKS INTERNAL'], true),
            $this->row(2, ['A' => 'Kesiapan klausul']),
            $this->row(3, ['A' => 'Kesiapan kontrol']),
            $this->row(4, ['A' => 'Gabungan (bobot sama, hanya kriteria terisi)']),
            $this->row(6, ['A' => 'Skor bukan pernyataan sertifikasi ISO. N/A hanya sah setelah disetujui.']),
        ];
        $dashboard[1] = str_replace('</row>', $this->formula('B2', 'IFERROR(AVERAGE(Klausul!D2:D31),0)').'</row>', $dashboard[1]);
        $dashboard[2] = str_replace('</row>', $this->formula('B3',
            'IFERROR((AVERAGE(Organisasi!D2:D38)+AVERAGE(Orang!D2:D9)+AVERAGE(Fisik!D2:D15)+AVERAGE(Teknologi!D2:D35))/4,0)').'</row>', $dashboard[2]);
        $dashboard[3] = str_replace('</row>', $this->formula('B4', '(B2+B3)/2').'</row>', $dashboard[3]);
        $sheets['Dashboard'] = $this->sheet($dashboard);

        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak dapat membuat template XLSX ISO-KM.');
        }
        try {
            $sheetParts = '';
            $rels = '';
            $types = '';
            $n = 0;
            foreach ($sheets as $name => $contents) {
                $n++;
                $sheetParts .= '<sheet name="'.$this->escape($name).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
                $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
                $types .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', $contents);
            }
            $zip->addFromString('xl/workbook.xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets>'.$sheetParts.'</sheets><calcPr calcMode="auto" fullCalcOnLoad="1"/></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
            $zip->addFromString('_rels/.rels',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/styles.xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<styleSheet xmlns="'.self::NS.'">'
                .'<fonts count="2"><font><sz val="10"/><name val="Aptos"/></font>'
                .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts>'
                .'<fills count="2"><fill><patternFill patternType="none"/></fill>'
                .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F3655"/><bgColor indexed="64"/></patternFill></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0"/></cellXfs>'
                .'</styleSheet>');
            $zip->addFromString('[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .$types.'</Types>');
        } finally {
            $zip->close();
        }
    }
}
