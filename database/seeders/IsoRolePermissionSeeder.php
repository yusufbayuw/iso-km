<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IsoRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $workflowPermissions = ['document.submit_review','document.review','document.review_any','document.approve','document.approve_any','document.make_effective','document.obsolete','document.acknowledge','evidence.manage','evidence.verify','evidence_request.view_all','action_item.view_all','assurance.refresh','assurance.report','audit.manage','audit.perform','finding.respond','capa.manage','capa.verify','risk.manage','objective.manage','compliance.manage','management_review.manage','activity_log.view'];
        $entities = ['standard','requirement','control','compliance::scope','management::system','requirement::assessment','statement::of::applicability','document','evidence','evidence::schedule','evidence::request','action::item','assurance::snapshot','risk','audit','audit::finding','corrective::action','objective','compliance::obligation','management::review'];
        $crudByEntity = [];
        foreach ($entities as $entity) { $crudByEntity[$entity] = $this->crudPermissions($entity); }
        $allPermissions = array_values(array_unique(array_merge($workflowPermissions, ...array_values($crudByEntity))));
        foreach ($allPermissions as $permission) { Permission::findOrCreate($permission, 'web'); }
        $templates = [
            'iso_manager' => $allPermissions,
            'iso_coordinator' => $allPermissions,
            'document_controller' => array_merge($crudByEntity['document'],$crudByEntity['evidence'],$crudByEntity['evidence::schedule'],$this->readPermissions('evidence::request'),$this->readPermissions('action::item'),['document.submit_review','document.review','document.review_any','document.make_effective','document.obsolete','evidence.manage','evidence.verify','evidence_request.view_all','activity_log.view']),
            'document_approver' => array_merge($this->readPermissions('document'),$this->readPermissions('evidence'),['document.approve','document.approve_any','document.make_effective']),
            'internal_auditor' => array_merge($crudByEntity['audit'],$crudByEntity['audit::finding'],$this->readPermissions('requirement'),$this->readPermissions('control'),$this->readPermissions('evidence'),$this->readPermissions('risk'),$this->readPermissions('action::item'),$this->readPermissions('assurance::snapshot'),['audit.perform','finding.respond','evidence.verify','assurance.report','activity_log.view']),
            'process_owner' => array_merge($this->readPermissions('requirement'),$this->readPermissions('control'),$this->readPermissions('action::item'),$this->readPermissions('evidence::request'),$crudByEntity['evidence'],$crudByEntity['risk'],$crudByEntity['objective'],$crudByEntity['corrective::action'],['finding.respond','capa.manage','risk.manage','objective.manage','document.submit_review','document.acknowledge']),
            'top_management' => array_merge($this->readPermissions('document'),$this->readPermissions('risk'),$this->readPermissions('audit'),$this->readPermissions('assurance::snapshot'),$this->readPermissions('action::item'),$crudByEntity['management::review'],$crudByEntity['objective'],$crudByEntity['compliance::obligation'],['document.approve','document.approve_any','management_review.manage','objective.manage','compliance.manage','action_item.view_all','assurance.refresh','assurance.report','activity_log.view']),
            'employee' => array_merge($this->readPermissions('action::item'),$this->readPermissions('evidence::request'),['document.acknowledge']),
        ];
        foreach ($templates as $roleName => $rolePermissions) { Role::findOrCreate($roleName, 'web')->syncPermissions(array_values(array_unique($rolePermissions))); }
    }
    private function crudPermissions(string $entity): array { return ["view_any_{$entity}","view_{$entity}","create_{$entity}","update_{$entity}","delete_{$entity}","delete_any_{$entity}"]; }
    private function readPermissions(string $entity): array { return ["view_any_{$entity}","view_{$entity}"]; }
}
