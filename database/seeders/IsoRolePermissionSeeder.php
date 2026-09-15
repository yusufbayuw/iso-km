<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IsoRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'document.submit_review',
            'document.review',
            'document.review_any',
            'document.approve',
            'document.approve_any',
            'document.make_effective',
            'document.obsolete',
            'document.acknowledge',
            'audit.manage',
            'audit.perform',
            'finding.respond',
            'capa.manage',
            'capa.verify',
            'risk.manage',
            'objective.manage',
            'compliance.manage',
            'management_review.manage',
            'activity_log.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $templates = [
            'iso_manager' => $permissions,
            'iso_coordinator' => $permissions,
            'document_controller' => [
                'document.submit_review', 'document.review', 'document.review_any',
                'document.make_effective', 'document.obsolete', 'activity_log.view',
            ],
            'document_approver' => [
                'document.approve', 'document.approve_any', 'document.make_effective',
            ],
            'internal_auditor' => [
                'audit.perform', 'finding.respond', 'activity_log.view',
            ],
            'process_owner' => [
                'finding.respond', 'capa.manage', 'risk.manage', 'objective.manage',
                'document.submit_review', 'document.acknowledge',
            ],
            'top_management' => [
                'document.approve', 'document.approve_any', 'management_review.manage',
                'objective.manage', 'compliance.manage', 'activity_log.view',
            ],
            'employee' => ['document.acknowledge'],
        ];

        foreach ($templates as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
