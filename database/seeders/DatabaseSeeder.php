<?php

namespace Database\Seeders;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\Standard;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $standard = Standard::query()->updateOrCreate(
            ['code' => 'ISO/IEC 27001', 'edition' => '2022'],
            ['name' => 'Information Security Management Systems', 'description' => 'Master standar untuk implementasi ISMS. Isi requirement dan control dapat dilengkapi sesuai lisensi standar organisasi.', 'is_active' => true]
        );

        foreach ([
            ['code' => '4', 'title' => 'Context of the organization', 'sort_order' => 40],
            ['code' => '5', 'title' => 'Leadership', 'sort_order' => 50],
            ['code' => '6', 'title' => 'Planning', 'sort_order' => 60],
            ['code' => '7', 'title' => 'Support', 'sort_order' => 70],
            ['code' => '8', 'title' => 'Operation', 'sort_order' => 80],
            ['code' => '9', 'title' => 'Performance evaluation', 'sort_order' => 90],
            ['code' => '10', 'title' => 'Improvement', 'sort_order' => 100],
        ] as $item) {
            Requirement::query()->updateOrCreate(['standard_id' => $standard->id, 'code' => $item['code']], $item + ['type' => 'clause', 'is_mandatory' => true]);
        }

        foreach ([
            ['code' => 'A.5', 'title' => 'Organizational controls', 'category' => 'Organizational', 'sort_order' => 500],
            ['code' => 'A.6', 'title' => 'People controls', 'category' => 'People', 'sort_order' => 600],
            ['code' => 'A.7', 'title' => 'Physical controls', 'category' => 'Physical', 'sort_order' => 700],
            ['code' => 'A.8', 'title' => 'Technological controls', 'category' => 'Technological', 'sort_order' => 800],
        ] as $group) {
            Control::query()->updateOrCreate(['standard_id' => $standard->id, 'code' => $group['code']], $group + ['is_group' => true]);
        }

        $scope = ComplianceScope::query()->updateOrCreate(['code' => 'ORG'], ['name' => 'Lingkup Organisasi', 'description' => 'Sesuaikan lingkup ini dengan boundary ISMS yang telah ditetapkan organisasi.', 'is_active' => true]);
        $system = ManagementSystem::query()->updateOrCreate(['code' => 'ISMS'], ['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'name' => 'Information Security Management System', 'status' => 'planning']);

        Requirement::query()->where('standard_id', $standard->id)->each(fn (Requirement $requirement) => RequirementAssessment::query()->firstOrCreate(
            ['management_system_id' => $system->id, 'requirement_id' => $requirement->id],
            ['status' => 'not_assessed']
        ));
    }
}
