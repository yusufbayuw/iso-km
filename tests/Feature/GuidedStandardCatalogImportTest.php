<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\GuidedQuestionBank;
use App\Models\Requirement;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedStandardCatalogImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class GuidedStandardCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('iso-km.guided_assessment_enabled', true);
    }

    private function editor(): User
    {
        $user = User::create(['name' => 'Catalog Editor', 'email' => 'catalog@example.test', 'password' => 'password']);
        foreach (['guided.bank.manage', 'update_standard'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo(['guided.bank.manage', 'update_standard']);
        return $user;
    }

    private function sampleCatalog(string $requirementCode = '5.1k', string $controlCode = '5.1'): string
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('iso/guided/catalog-imports');
        $relative = 'iso/guided/catalog-imports/canonical.xlsx';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($relative), ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Klausul" sheetId="1" r:id="rId1"/><sheet name="Kontrol" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
        foreach ([$requirementCode, $controlCode] as $index => $code) {
            $number = $index + 1;
            $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                .'<row r="1"><c r="A1" t="inlineStr"><is><t>Kode</t></is></c><c r="B1" t="inlineStr"><is><t>Judul</t></is></c>'
                .'<c r="C1" t="inlineStr"><is><t>Deskripsi</t></is></c></row>'
                .'<row r="2"><c r="A2" t="inlineStr"><is><t>'.$code.'</t></is></c>'
                .'<c r="B2" t="inlineStr"><is><t>Generic catalog requirement</t></is></c>'
                .'<c r="C2" t="inlineStr"><is><t>Restricted standard text</t></is></c></row>'
                .'</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet'.$number.'.xml', $xml);
        }
        $zip->close();
        return $relative;
    }

    public function test_iso_codes_are_normalized_and_import_is_idempotent_without_copying_descriptions(): void
    {
        $file = $this->sampleCatalog();
        $standard = Standard::create(['code' => 'ISO/IEC 27001', 'name' => 'ISMS', 'edition' => '2022']);
        $service = app(GuidedStandardCatalogImportService::class);
        $actor = $this->editor();
        $result = $service->import($standard, $file, $actor);
        $this->assertSame(['requirements' => 1, 'controls' => 1], $result['inserted']);
        $this->assertDatabaseHas('requirements', ['standard_id' => $standard->id, 'code' => '5.1', 'description' => null]);
        $this->assertDatabaseHas('controls', ['standard_id' => $standard->id, 'code' => 'A.5.1', 'description' => null]);
        $repeat = $service->import($standard, $file, $actor);
        $this->assertSame(['requirements' => 0, 'controls' => 0], $repeat['inserted']);
        $this->assertSame(['requirements' => 1, 'controls' => 1], $repeat['skipped']);
    }

    public function test_other_standards_keep_original_identifiers_without_iso_specific_prefixes(): void
    {
        $file = $this->sampleCatalog('R.5.1', 'C.5.1');
        $standard = Standard::create(['code' => 'GENERIC', 'name' => 'Generic Framework', 'edition' => '1']);
        app(GuidedStandardCatalogImportService::class)->import($standard, $file, $this->editor());
        $this->assertSame('R.5.1', Requirement::query()->firstOrFail()->code);
        $this->assertSame('C.5.1', Control::query()->firstOrFail()->code);
    }

    public function test_existing_published_bank_blocks_catalog_mutation(): void
    {
        $file = $this->sampleCatalog();
        $standard = Standard::create(['code' => 'ISO/IEC 27001', 'name' => 'ISMS', 'edition' => '2022']);
        GuidedQuestionBank::create([
            'standard_id' => $standard->id, 'code' => 'VERSIONED', 'name' => 'Licensed bank',
            'status' => 'published',
        ]);
        $this->expectException(ValidationException::class);
        app(GuidedStandardCatalogImportService::class)->import($standard, $file, $this->editor());
    }
}
