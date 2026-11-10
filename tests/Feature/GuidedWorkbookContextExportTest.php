<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\GuidedAssessment;
use App\Models\GuidedConsolidation;
use App\Models\GuidedDecision;
use App\Models\GuidedExportTemplate;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\GuidedQuestionTarget;
use App\Models\GuidedScopeItem;
use App\Models\ManagementSystem;
use App\Models\Risk;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedWorkbookExportService;
use App\Services\GuidedWorkbookMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class GuidedWorkbookContextExportTest extends TestCase
{
    use RefreshDatabase;

    private function cell(string $address, string $value): string
    {
        return '<c r="'.$address.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
    }

    private function row(int $number, array $values): string
    {
        $xml = '<row r="'.$number.'">';
        foreach ($values as $column => $value) {
            $xml .= $this->cell($column.$number, (string) $value);
        }
        return $xml.'</row>';
    }

    private function sheet(array $rows): string
    {
        return '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .implode('', $rows).'</sheetData></worksheet>';
    }

    private function fixture(): array
    {
        Storage::fake('local');
        config()->set('iso-km.organization_name', 'Actual Organization');
        $person = User::create(['name' => 'Coordinator', 'email' => 'context@example.test', 'password' => 'password']);
        Permission::findOrCreate('guided.report.export', 'web');
        $person->givePermissionTo('guided.report.export');
        $standard = Standard::create(['code' => 'GENERIC', 'name' => 'Generic', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'SCOPE', 'name' => 'Actual Scope',
            'description' => 'Actual boundary of management system']);
        $system = ManagementSystem::create(['standard_id' => $standard->id,
            'compliance_scope_id' => $scope->id, 'code' => 'SYS', 'name' => 'Actual Management System']);
        $control = Control::create(['standard_id' => $standard->id,
            'code' => 'A.5.1', 'title' => 'Policy control']);
        $bank = GuidedQuestionBank::create(['standard_id' => $standard->id, 'code' => 'B', 'name' => 'Questions']);
        $question = GuidedQuestion::create(['guided_question_bank_id' => $bank->id, 'code' => 'Q',
            'prompt' => 'Is the policy implemented?']);
        $target = GuidedQuestionTarget::create(['guided_question_id' => $question->id,
            'control_id' => $control->id, 'criterion' => 'Operational policy']);
        $bank->update(['status' => 'published']);
        $run = GuidedAssessment::create(['management_system_id' => $system->id,
            'guided_question_bank_id' => $bank->id, 'name' => 'Assessment',
            'created_by' => $person->id]);
        GuidedScopeItem::create([
            'guided_assessment_id' => $run->id, 'kind' => 'asset',
            'category' => 'Actual Category', 'title' => 'Actual Asset', 'in_scope' => true,
            'classification' => 'internal', 'notes' => 'Source inventory',
        ]);
        GuidedScopeItem::create([
            'guided_assessment_id' => $run->id, 'kind' => 'activity',
            'title' => 'Actual Activity', 'in_scope' => true,
            'related_controls' => ['A.5.1'], 'notes' => 'Confirmed by coordinator',
        ]);
        $risk = Risk::create(['management_system_id' => $system->id,
            'code' => 'R1', 'title' => 'Actual Risk',
            'description' => 'Confirmed operational impact',
            'category' => 'IT', 'likelihood' => 3, 'impact' => 4,
            'treatment_plan' => 'Actual corrective action', 'status' => 'open']);
        $risk->controls()->attach($control);
        $run->update(['status' => 'locked', 'locked_at' => now()]);
        $consolidation = GuidedConsolidation::create([
            'guided_assessment_id' => $run->id, 'guided_question_id' => $question->id,
            'context_key' => 'general', 'summary' => 'Policy approved by assessor',
            'prepared_by' => $person->id,
        ]);
        GuidedDecision::create([
            'guided_consolidation_id' => $consolidation->id,
            'guided_question_target_id' => $target->id, 'proposed_status' => 'implemented',
            'rationale' => 'Evidence reviewed', 'risk_score' => 1,
            'status' => 'approved', 'approved_at' => now(), 'synced_at' => now(),
        ]);

        $path = 'iso/guided/templates/structure.xlsx';
        Storage::disk('local')->makeDirectory('iso/guided/templates');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Kontrol" sheetId="1" r:id="rId1"/><sheet name="Profil" sheetId="2" r:id="rId2"/><sheet name="Risiko" sheetId="3" r:id="rId3"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Target="worksheets/sheet3.xml"/></Relationships>');
        $controlSheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$this->row(1, ['A' => 'Kode', 'B' => 'Kategori', 'E' => 'Status Penerapan', 'G' => 'Bukti / Catatan Penerapan'])
            .$this->row(2, ['A' => '5.1', 'B' => 'Kontrol'])
            .'</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $controlSheet);

        $profileRows = [
            $this->row(5, ['B' => 'Nama Organisasi', 'D' => 'Template Organization']),
            $this->row(6, ['B' => 'Unit/Bagian yang Dinilai']),
            $this->row(7, ['B' => 'Ruang Lingkup SMKI (Deskripsi Singkat)']),
            $this->row(8, ['B' => 'Nama Asesor']),
            $this->row(9, ['B' => 'Jabatan/Unit Asesor']),
            $this->row(10, ['B' => 'Tanggal Asesmen']),
            $this->row(15, ['B' => 'No', 'C' => 'Kelompok', 'D' => 'Jenis Informasi', 'E' => 'Dikelola?', 'F' => 'Klasifikasi', 'G' => 'Catatan']),
            $this->row(16, ['B' => '1', 'C' => 'OLD CATEGORY A', 'D' => 'OLD ASSET A']),
            $this->row(17, ['B' => '2', 'C' => 'OLD CATEGORY B', 'D' => 'OLD ASSET B']),
            $this->row(42, ['B' => 'No', 'C' => 'Aktivitas', 'E' => 'Dilakukan?', 'F' => 'Kontrol Lampiran A Terkait (referensi)', 'G' => 'Catatan']),
            $this->row(43, ['B' => '1', 'C' => 'OLD ACTIVITY A']),
            $this->row(44, ['B' => '2', 'C' => 'OLD ACTIVITY B']),
        ];
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->sheet($profileRows));
        $riskRows = [
            $this->row(1, ['A' => 'No', 'B' => 'Kategori Aset', 'C' => 'Skenario Risiko / Ancaman',
                'D' => 'Potensi Dampak', 'E' => 'Kontrol Terkait 1', 'F' => 'Kontrol Terkait 2',
                'G' => 'Kontrol Terkait 3', 'H' => 'Kemungkinan (1-5)', 'I' => 'Dampak (1-5)',
                'P' => 'Catatan / Rencana Tindak Lanjut']),
            $this->row(2, ['A' => '1', 'B' => 'OLD CATEGORY', 'C' => 'OLD RISK A']),
            $this->row(3, ['A' => '2', 'B' => 'OLD CATEGORY', 'C' => 'OLD RISK B']),
        ];
        $zip->addFromString('xl/worksheets/sheet3.xml', $this->sheet($riskRows));
        $zip->addFromString('xl/charts/chart1.xml', '<chart>unchanged-chart</chart>');
        $zip->close();

        $template = GuidedExportTemplate::create([
            'management_system_id' => $system->id, 'name' => 'Generic Layout',
            'report_type' => 'gap_analysis', 'file_path' => $path,
            'mappings' => [],
        ]);
        $suggestion = app(GuidedWorkbookMappingService::class)->suggest($template);
        $this->assertSame(1, $suggestion['recognized_targets']);
        $this->assertSame(['risk_index' => 2, 'asset_index' => 2, 'activity_index' => 2],
            $suggestion['context_capacities']);
        $template->update(['mappings' => $suggestion['mappings']]);
        return compact('path', 'run', 'person', 'template');
    }

    public function test_scope_inventory_cannot_change_after_assessment_is_locked(): void
    {
        extract($this->fixture());
        $asset = GuidedScopeItem::query()->firstOrFail();
        $this->expectException(ValidationException::class);
        $asset->update(['title' => 'Unauthorized post-lock change']);
    }

    public function test_risk_with_more_controls_than_template_columns_cannot_be_exported_silently(): void
    {
        extract($this->fixture());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)) === true);
        $source = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->addFromString('xl/worksheets/sheet2.xml', str_replace('Template Organization', '', $source));
        $zip->close();
        $risk = Risk::query()->firstOrFail();
        foreach (['A.5.2', 'A.5.3', 'A.5.4'] as $code) {
            $other = Control::create(['standard_id' => $run->system->standard_id,
                'code' => $code, 'title' => 'Additional control']);
            $risk->controls()->attach($other);
        }
        try {
            app(GuidedWorkbookExportService::class)->export($run, $template, $person);
            $this->fail('Fourth linked risk control must not disappear from final report.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template', $exception->errors());
        }
    }

    public function test_final_export_blocks_foreign_organization_and_then_replaces_old_context_rows(): void
    {
        extract($this->fixture());
        $service = app(GuidedWorkbookExportService::class);
        try {
            $service->export($run, $template, $person);
            $this->fail('Foreign organization identity must block a final GAP report.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template', $exception->errors());
        }

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)) === true);
        $source = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->addFromString('xl/worksheets/sheet2.xml', str_replace('Template Organization', '', $source));
        $zip->close();

        $result = $service->export($run, $template, $person);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($result)) === true);
        $profile = $zip->getFromName('xl/worksheets/sheet2.xml');
        $risks = $zip->getFromName('xl/worksheets/sheet3.xml');
        $controls = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Actual Organization', $profile);
        $this->assertStringContainsString('Actual Asset', $profile);
        $this->assertStringContainsString('Actual Activity', $profile);
        $this->assertStringNotContainsString('OLD ASSET', $profile);
        $this->assertStringNotContainsString('OLD ACTIVITY', $profile);
        $this->assertStringContainsString('Actual Risk', $risks);
        $this->assertStringContainsString('Actual corrective action', $risks);
        $this->assertStringNotContainsString('OLD RISK', $risks);
        $this->assertStringContainsString('Diterapkan Penuh', $controls);
        $this->assertSame('<chart>unchanged-chart</chart>', $zip->getFromName('xl/charts/chart1.xml'));
        $zip->close();
    }
}
