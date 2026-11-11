<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IsoRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // A first-time seeding must not lock out accounts which were permitted
        // into the panel before the portal.access gate existed.
        $hadPortalAccessGate = Permission::query()->where('guard_name', 'web')
            ->where('name', 'portal.access')->exists();

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
        // Global system/template administration is never granted to ordinary coordinators.
        Permission::findOrCreate('guided.system.manage_all', 'web');
        Permission::findOrCreate('guided.template.manage_global', 'web');
        // Filament Shield RolePolicy checks role CRUD permissions; these are
        // privileged operations and must never be granted to regular templates.
        $roleAdministrationPermissions = $this->crudPermissions('role');
        foreach ($roleAdministrationPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        if (! $hadPortalAccessGate || config('iso-km.role_seeder_backfill_existing_user_access', false)) {
            User::query()->orderBy('id')->chunkById(250, function ($users): void {
                foreach ($users as $user) {
                    $user->givePermissionTo('portal.access');
                }
            });
        }

        // A coordinator can administer assigned assessments, not every ISO
        // master register nor the separate evidence/risk/management approvals.
        $coordinatorPermissions = array_merge([
            'portal.access', 'guided.assessment.view', 'guided.assessment.manage',
            'guided.answer.submit', 'guided.consolidate', 'guided.report.export',
            'guided.template.manage',
        ],
            $this->readPermissions('standard'),
            $this->readPermissions('requirement'),
            $this->readPermissions('control'),
            $this->readPermissions('compliance::scope'),
            $this->readPermissions('management::system'),
            $this->readPermissions('requirement::assessment'),
            $this->readPermissions('statement::of::applicability'),
            $this->readPermissions('evidence'),
            $this->readPermissions('risk'),
            $this->readPermissions('action::item'),
        );

        $templates = [
            'iso_manager' => $allPermissions,
            'iso_coordinator' => $coordinatorPermissions,
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
            // Guided assessment's role membership is assigned separately per run.
            // These are least-privilege permission presets, not global sign-off.
            'guided_respondent' => array_merge(
                ['portal.access', 'guided.assessment.view', 'guided.answer.submit'],
                $this->readPermissions('action::item'),
            ),
            'guided_consolidator' => array_merge(
                ['portal.access', 'guided.assessment.view', 'guided.consolidate'],
                $this->readPermissions('action::item'),
            ),
            'guided_evidence_reviewer' => array_merge(
                ['portal.access', 'guided.assessment.view', 'guided.evidence.review'],
                $this->readPermissions('evidence'),
                $this->readPermissions('action::item'),
            ),
            'guided_assessor' => array_merge(
                ['portal.access', 'guided.assessment.view', 'guided.assessment.review', 'guided.evidence.review'],
                $this->readPermissions('evidence'),
                $this->readPermissions('requirement::assessment'),
                $this->readPermissions('statement::of::applicability'),
                $this->readPermissions('action::item'),
            ),
            'guided_risk_owner' => array_merge(
                ['portal.access', 'guided.assessment.view', 'guided.risk.approve'],
                $this->readPermissions('risk'),
                $this->readPermissions('action::item'),
            ),
            'guided_management_approver' => array_merge(
                ['portal.access', 'guided.assessment.view', 'guided.management.approve'],
                $this->readPermissions('assurance::snapshot'),
                $this->readPermissions('action::item'),
            ),
        ];

        // Existing role permissions may be intentionally customized. Never
        // revoke them silently, and do not grant a full template baseline to
        // an existing role unless an administrator explicitly opts in.
        $reconcileExisting = (bool) config('iso-km.role_seeder_reconcile_existing', false);
        $guidedPermissions = array_filter($workflowPermissions, fn (string $p) => str_starts_with($p, 'guided.'));
        foreach ($templates as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $grants = array_values(array_unique($rolePermissions));
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($grants);
                continue;
            }

            $additions = $reconcileExisting
                ? $grants
                : array_values(array_intersect($grants, array_merge(['portal.access'], $guidedPermissions)));
            $role->givePermissionTo($additions);

            if ($roleName === 'iso_coordinator'
                && $role->hasPermissionTo('delete_standard')) {
                $this->command?->warn('iso_coordinator masih memiliki izin administratif lama. '
                    .'Seeder sengaja tidak mencabut izin existing; audit dan cabut melalui Shield jika tidak dibutuhkan.');
            }
        }

        Role::findOrCreate('super_admin', 'web')->givePermissionTo(array_merge(
            $allPermissions,
            $roleAdministrationPermissions,
            ['guided.evidence_pack.export_sensitive', 'guided.system.manage_all',
                'guided.template.manage_global'],
        ));
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
