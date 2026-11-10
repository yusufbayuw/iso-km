<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\GuidedExportTemplate;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Standard;
use App\Services\GuidedWorkbookMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class GuidedWorkbookMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_clause_and_control_rows_without_using_organization_identity(): void
    {
        Storage::fake('local');
        $standard = Standard::create(['code' => 'GENERIC', 'name' => 'Standar Generic', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'SCOPE', 'name' => 'Unbranded']);
        $system = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id,
            'code' => 'MS', 'name' => 'Management System',
        ]);
        Requirement::create(['standard_id' => $standard->id, 'code' => '5.1', 'title' => 'Requirement']);
        Control::create(['standard_id' => $standard->id, 'code' => 'A.5.1', 'title' => 'Control']);

        $path = Storage::disk('local')->path('templates/anonymous.xlsx');
        Storage::disk('local')->makeDirectory('templates');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Klausul" sheetId="1" r:id="rId1"/><sheet name="Kontrol" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
        foreach (['Klausul' => ['Kelompok Klausul', '5.1k'], 'Kontrol' => ['Kategori', '5.1']] as $sheet => [$type, $code]) {
            $n = $sheet === 'Klausul' ? 1 : 2;
            $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                .'<row r="1"><c r="A1" t="inlineStr"><is><t>Kode</t></is></c>'
                .'<c r="B1" t="inlineStr"><is><t>'.$type.'</t></is></c>'
                .'<c r="E1" t="inlineStr"><is><t>Status Penerapan</t></is></c>'
                .'<c r="G1" t="inlineStr"><is><t>Bukti / Catatan Penerapan</t></is></c>'
                .'<c r="H1" t="inlineStr"><is><t>Alasan Tidak Berlaku (N/A)</t></is></c></row>'
                .'<row r="2"><c r="A2" t="inlineStr"><is><t>'.$code.'</t></is></c><c r="E2"/><c r="G2"/><c r="H2"/></row>'
                .'</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', $xml);
        }
        $zip->close();
        $template = GuidedExportTemplate::create([
            'management_system_id' => $system->id, 'name' => 'Generic',
            'file_path' => 'templates/anonymous.xlsx', 'mappings' => [],
        ]);
        $result = app(GuidedWorkbookMappingService::class)->suggest($template);
        $this->assertSame(2, $result['recognized_targets']);
        $this->assertCount(5, $result['mappings']);
        $this->assertContains([
            'sheet' => 'Klausul', 'cell' => 'E2', 'source' => 'requirement',
            'code' => '5.1', 'field' => 'status', 'required' => true,
        ], $result['mappings']);
        $this->assertContains([
            'sheet' => 'Kontrol', 'cell' => 'G2', 'source' => 'control',
            'code' => 'A.5.1', 'field' => 'notes_and_evidence', 'required' => true,
        ], $result['mappings']);
        $this->assertSame([], $result['warnings']);
    }
}
