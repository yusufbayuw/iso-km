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
use App\Models\ManagementSystem;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedWorkbookExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class GuidedWorkbookExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapped_input_cells_are_updated_without_overwriting_formulas_or_charts(): void
    {
        Storage::fake('local');
        $user = User::create(['name' => 'Coordinator', 'email' => 'xls@example.test', 'password' => 'password']);
        Permission::findOrCreate('guided.report.export', 'web');
        $user->givePermissionTo('guided.report.export');
        $standard = Standard::create(['code' => 'TEST', 'name' => 'Test', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'TEST', 'name' => 'Scope']);
        $system = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'TEST', 'name' => 'System',
        ]);
        $control = Control::create(['standard_id' => $standard->id, 'code' => 'A.8.13', 'title' => 'Backup']);
        $bank = GuidedQuestionBank::create(['standard_id' => $standard->id, 'code' => 'TEST', 'name' => 'Test', 'status' => 'published']);
        $question = GuidedQuestion::create(['guided_question_bank_id' => $bank->id, 'code' => 'Q-1', 'prompt' => 'Backup?']);
        $target = GuidedQuestionTarget::create(['guided_question_id' => $question->id, 'control_id' => $control->id, 'criterion' => 'Backup berjalan']);
        $run = GuidedAssessment::create([
            'management_system_id' => $system->id, 'guided_question_bank_id' => $bank->id,
            'name' => 'Example Run', 'created_by' => $user->id, 'status' => 'locked', 'locked_at' => now(),
        ]);
        $consolidation = GuidedConsolidation::create([
            'guided_assessment_id' => $run->id, 'guided_question_id' => $question->id,
            'context_key' => 'general', 'version' => 1, 'is_current' => true,
            'summary' => 'Backup berjalan', 'prepared_by' => $user->id,
        ]);
        GuidedDecision::create([
            'guided_consolidation_id' => $consolidation->id, 'guided_question_target_id' => $target->id,
            'proposed_status' => 'implemented', 'rationale' => 'Evidence didukung',
            'risk_score' => 3, 'status' => 'approved', 'synced_at' => now(), 'approved_at' => now(),
        ]);

        Storage::disk('local')->makeDirectory('templates');
        $path = Storage::disk('local')->path('templates/example.xlsx');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="/xl/worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Original</t></is></c></row><row r="2"><c r="A2"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>');
        $zip->addFromString('xl/charts/chart1.xml', '<chart>keep-original</chart>');
        $zip->close();

        $template = GuidedExportTemplate::create([
            'management_system_id' => $system->id, 'name' => 'Example',
            'file_path' => 'templates/example.xlsx',
            'mappings' => [['sheet' => 'Sheet1', 'cell' => 'A1', 'source' => 'control', 'code' => 'A.8.13', 'field' => 'status', 'required' => true]],
        ]);
        $output = app(GuidedWorkbookExportService::class)->export($run, $template, $user);
        $this->assertTrue(Storage::disk('local')->exists($output));
        $result = new ZipArchive();
        $this->assertTrue($result->open(Storage::disk('local')->path($output)) === true);
        $sheet = $result->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Diterapkan Penuh', $sheet);
        $this->assertStringContainsString('<f>1+1</f>', $sheet);
        $this->assertSame('<chart>keep-original</chart>', $result->getFromName('xl/charts/chart1.xml'));
        $result->close();

        $template->update(['mappings' => [['sheet' => 'Sheet1', 'cell' => 'A2', 'source' => 'control', 'code' => 'A.8.13', 'field' => 'status']]]);
        $this->expectException(ValidationException::class);
        app(GuidedWorkbookExportService::class)->export($run, $template->refresh(), $user);
    }
}
