<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IsoRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $workflowPermissions = [
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

        $entities = [
            'standard',
            'requirement',
            'control',
            'compliance::scope',
            'management::system',
            'requirement::assessment',
            'statement::of::applicability',
            'document',
            'evidence',
            'risk',
            'audit',
            'audit::finding',
            'corrective::action',
            'objective',
            'compliance::obligation',
            'management::review',
        ];

        $crudByEntity = [];
        foreach ($entities as $entity) {
            $crudByEntity[$entity] = $this->crudPermissions($entity);
        }

        $allPermissions = array_values(array_unique(array_merge(
            $workflowPermissions,
            ...array_values($crudByEntity),
        )));

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $templates = [
            'iso_manager' => $allPermissions,
            'iso_coordinator' => $allPermissions,
            'document_controller' => array_merge(
                $crudByEntity['document'],
                $this->readPermissions('evidence'),
                [
                    'document.submit_review', 'document.review', 'document.review_any',
                    'document.make_effective', 'document.obsolete', 'activity_log.view',
                ],
            ),
            'document_approver' => array_merge(
                $this->readPermissions('document'),
                ['document.approve', 'document.approve_any', 'document.make_effective'],
            ),
            'internal_auditor' => array_merge(
                $crudByEntity['audit'],
                $crudByEntity['audit::finding'],
                $this->readPermissions('requirement'),
                $this->readPermissions('control'),
                $this->readPermissions('evidence'),
                ['audit.perform', 'finding.respond', 'activity_log.view'],
            ),
            'process_owner' => array_merge(
                $this->readPermissions('requirement'),
                $this->readPermissions('control'),
                $crudByEntity['risk'],
                $crudByEntity['objective'],
                $crudByEntity['corrective::action'],
                ['finding.respond', 'capa.manage', 'risk.manage', 'objective.manage', 'document.submit_review', 'document.acknowledge'],
            ),
            'top_management' => array_merge(
                $this->readPermissions('document'),
                $this->readPermissions('risk'),
                $this->readPermissions('audit'),
                $crudByEntity['management::review'],
                $crudByEntity['objective'],
                $crudByEntity['compliance::obligation'],
                ['document.approve', 'document.approve_any', 'management_review.manage', 'objective.manage', 'compliance.manage', 'activity_log.view'],
            ),
            'employee' => ['document.acknowledge'],
        ];

        foreach ($templates as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(array_values(array_unique($rolePermissions)));
        }
    }

    private function crudPermissions(string $entity): array
    {
        return [
            "view_any_{$entity}",
            "view_{$entity}",
            "create_{$entity}",
            "update_{$entity}",
            "delete_{$entity}",
            "delete_any_{$entity}",
        ];
    }

    private function readPermissions(string $entity): array
    {
        return ["view_any_{$entity}", "view_{$entity}"];
    }
}
