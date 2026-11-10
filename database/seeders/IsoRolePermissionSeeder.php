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
            'portal.access',
            'guided.bank.manage','guided.template.manage','guided.assessment.view','guided.assessment.manage','guided.answer.submit',
            'guided.consolidate','guided.evidence.review','guided.assessment.review',
            'guided.risk.approve','guided.management.approve','guided.report.export',
            'document.submit_review','document.review','document.review_any','document.approve','document.approve_any','document.make_effective','document.obsolete','document.acknowledge',
            'evidence.manage','evidence.verify','evidence_request.view_all','action_item.view_all','assurance.refresh','assurance.report','auditor_workspace.view','audit_pack.export',
            'external_audit_access.manage','external_auditor_workspace.view','external_audit_pack.export',
            'cross_standard_mapping.manage','cross_standard_mapping.approve','cross_standard_workspace.view','cross_standard_reuse.accept',
            'audit.manage','audit.perform','finding.respond','capa.manage','capa.verify','risk.manage','objective.manage','compliance.manage','management_review.manage',
            'training.manage','training.complete','training.verify','competency.manage',
            'supplier.manage','supplier.assess','supplier.review','supplier.decide','supplier.verify_treatment',
            'activity_log.view',
        ];

        $entities = [
            'standard','requirement','control','compliance::scope','management::system','requirement::assessment','statement::of::applicability',
            'document','evidence','evidence::schedule','evidence::request','action::item','assurance::snapshot','risk','audit','audit::finding','corrective::action',
            'objective','compliance::obligation','management::review','training::program','training::assignment','competency::record','supplier','supplier::assessment',
            'external::audit::access','requirement::mapping','control::mapping','evidence::reuse',
        ];

        $crudByEntity = [];
        foreach ($entities as $entity) { $crudByEntity[$entity] = $this->crudPermissions($entity); }

        $allPermissions = array_values(array_unique(array_merge($workflowPermissions, ...array_values($crudByEntity))));
        foreach ($allPermissions as $permission) { Permission::findOrCreate($permission, 'web'); }
        // Sensitive evidence-pack export is deliberately NOT part of ordinary coordinator roles.
        Permission::findOrCreate('guided.evidence_pack.export_sensitive', 'web');

        $templates = [
            'iso_manager' => $allPermissions,
            'iso_coordinator' => $allPermissions,
            'document_controller' => array_merge(
                ['portal.access'],$crudByEntity['document'],$crudByEntity['evidence'],$crudByEntity['evidence::schedule'],$this->readPermissions('evidence::request'),$this->readPermissions('action::item'),
                ['guided.evidence.review','document.submit_review','document.review','document.review_any','document.make_effective','document.obsolete','evidence.manage','evidence.verify','evidence_request.view_all','activity_log.view']
            ),
            'document_approver' => array_merge(['portal.access'],$this->readPermissions('document'),$this->readPermissions('evidence'),['document.approve','document.approve_any','document.make_effective']),
            'internal_auditor' => array_merge(
                ['portal.access'],$crudByEntity['audit'],$crudByEntity['audit::finding'],$this->readPermissions('requirement'),$this->readPermissions('control'),$this->readPermissions('evidence'),$this->readPermissions('risk'),
                $this->readPermissions('action::item'),$this->readPermissions('assurance::snapshot'),$this->readPermissions('training::program'),$this->readPermissions('training::assignment'),
                $this->readPermissions('competency::record'),$this->readPermissions('supplier'),$this->readPermissions('supplier::assessment'),
                $this->readPermissions('requirement::mapping'),$this->readPermissions('control::mapping'),$this->readPermissions('evidence::reuse'),
                ['guided.assessment.view','guided.evidence.review','guided.assessment.review','audit.perform','finding.respond','evidence.verify','assurance.report','auditor_workspace.view','audit_pack.export','cross_standard_workspace.view','activity_log.view']
            ),
            'process_owner' => array_merge(
                ['portal.access'],$this->readPermissions('requirement'),$this->readPermissions('control'),$this->readPermissions('action::item'),$this->readPermissions('evidence::request'),
                $this->readPermissions('training::program'),$this->readPermissions('competency::record'),$this->readPermissions('supplier'),$this->readPermissions('supplier::assessment'),
                $this->readPermissions('requirement::mapping'),$this->readPermissions('control::mapping'),$this->readPermissions('evidence::reuse'),
                $crudByEntity['evidence'],$crudByEntity['risk'],$crudByEntity['objective'],$crudByEntity['corrective::action'],
                ['guided.assessment.view','guided.answer.submit','guided.risk.approve','finding.respond','capa.manage','risk.manage','objective.manage','document.submit_review','document.acknowledge','training.complete','cross_standard_workspace.view']
            ),
            'training_coordinator' => array_merge(
                ['portal.access'],$crudByEntity['training::program'],$crudByEntity['training::assignment'],$crudByEntity['competency::record'],$this->readPermissions('evidence'),$this->readPermissions('action::item'),
                ['training.manage','training.verify','competency.manage','action_item.view_all']
            ),
            'third_party_owner' => array_merge(
                ['portal.access'],$crudByEntity['supplier'],$crudByEntity['supplier::assessment'],$this->readPermissions('requirement'),$this->readPermissions('control'),$this->readPermissions('evidence'),$this->readPermissions('action::item'),
                ['supplier.manage','supplier.assess','supplier.review','supplier.verify_treatment']
            ),
            'top_management' => array_merge(
                ['portal.access'],$this->readPermissions('document'),$this->readPermissions('risk'),$this->readPermissions('audit'),$this->readPermissions('assurance::snapshot'),$this->readPermissions('action::item'),
                $this->readPermissions('training::program'),$this->readPermissions('training::assignment'),$this->readPermissions('competency::record'),$this->readPermissions('supplier'),$this->readPermissions('supplier::assessment'),
                $this->readPermissions('requirement::mapping'),$this->readPermissions('control::mapping'),$this->readPermissions('evidence::reuse'),
                $crudByEntity['management::review'],$crudByEntity['objective'],$crudByEntity['compliance::obligation'],
                ['guided.assessment.view','guided.management.approve','document.approve','document.approve_any','management_review.manage','objective.manage','compliance.manage','action_item.view_all','assurance.refresh','assurance.report','auditor_workspace.view','audit_pack.export','supplier.decide','cross_standard_workspace.view','activity_log.view']
            ),
            'employee' => array_merge(
                ['portal.access'],$this->readPermissions('action::item'),$this->readPermissions('evidence::request'),$this->readPermissions('training::program'),
                ['guided.assessment.view','guided.answer.submit','document.acknowledge','training.complete']
            ),
            'external_auditor' => ['portal.access','external_auditor_workspace.view','external_audit_pack.export'],
        ];

        // Existing role permissions may have been customized for production.
        // Do not erase those changes when adding the new Guided Assessment feature.
        $guidedPermissions = array_filter($workflowPermissions, fn (string $p) => str_starts_with($p, 'guided.'));
        foreach ($templates as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $grants = array_values(array_unique($rolePermissions));
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($grants);
            } else {
                $role->givePermissionTo(array_values(array_intersect($grants, $guidedPermissions)));
            }
        }

        Role::findOrCreate('super_admin', 'web')->givePermissionTo(array_merge($allPermissions, ['guided.evidence_pack.export_sensitive']));
    }

    private function crudPermissions(string $entity): array
    {
        return ["view_any_{$entity}","view_{$entity}","create_{$entity}","update_{$entity}","delete_{$entity}","delete_any_{$entity}"];
    }

    private function readPermissions(string $entity): array
    {
        return ["view_any_{$entity}","view_{$entity}"];
    }
}
