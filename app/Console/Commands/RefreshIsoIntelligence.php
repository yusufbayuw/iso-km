<?php

namespace App\Console\Commands;

use App\Services\ActionItemSyncService;
use App\Services\AssuranceAnalysisService;
use App\Services\EvidenceLifecycleService;
use Illuminate\Console\Command;

class RefreshIsoIntelligence extends Command
{
    protected $signature = 'iso:refresh-intelligence';
    protected $description = 'Refresh evidence lifecycle, unified action queue, and assurance snapshots';

    public function handle(EvidenceLifecycleService $evidenceLifecycle, ActionItemSyncService $actionItems, AssuranceAnalysisService $assurance): int
    {
        $expired = $evidenceLifecycle->refreshEvidenceStatuses();
        $requests = $evidenceLifecycle->generateDueRequests();
        $actions = $actionItems->sync();
        $systems = $assurance->refreshAll();
        $this->info("Evidence expired: {$expired}; requests generated: {$requests}; action changes: {$actions}; systems analyzed: {$systems}.");
        return self::SUCCESS;
    }
}
