<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\AuditFinding;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Risk;

class AssuranceReportService
{
    public function toCsv(ManagementSystem $system): string
    {
        $snapshot = $system->assuranceSnapshot ?: app(AssuranceAnalysisService::class)->refresh($system);
        $rows = [
            ['ISO-KM Assurance Report', $system->name], ['Management System', $system->code],
            ['Standard', trim(($system->standard?->code ?? '').' '.($system->standard?->edition ?? ''))],
            ['Scope', $system->scope?->name ?? '-'], ['Calculated At', $snapshot->calculated_at?->format('Y-m-d H:i:s') ?? '-'], [],
            ['Assurance Dimension', 'Value'], ['Requirement Readiness', $snapshot->requirement_readiness.'%'],
            ['Control Implementation', $snapshot->control_implementation.'%'], ['Verified Evidence Coverage', $snapshot->evidence_coverage.'%'],
            ['Evidence Freshness', $snapshot->evidence_freshness.'%'], ['Risk Health', $snapshot->risk_health.'%'],
            ['Findings Health', $snapshot->findings_health.'%'], ['Action Health', $snapshot->action_health.'%'],
            ['Assurance Confidence', strtoupper($snapshot->confidence_level)], [], ['Attention', 'Count'],
        ];
        foreach ($snapshot->attention_counts ?? [] as $key => $value) { $rows[] = [str_replace('_', ' ', ucfirst($key)), $value]; }
        $rows[] = []; $rows[] = ['Open / High Risks']; $rows[] = ['Code', 'Risk', 'Inherent', 'Residual', 'Status'];
        Risk::query()->where('management_system_id', $system->id)->where('status', '!=', 'closed')->orderByDesc('residual_score')->orderByDesc('inherent_score')->limit(50)->get()->each(function (Risk $risk) use (&$rows): void { $rows[] = [$risk->code, $risk->title, $risk->inherent_score, $risk->residual_score ?? '-', $risk->status]; });
        $rows[] = []; $rows[] = ['Open Findings']; $rows[] = ['Code', 'Finding', 'Severity', 'Status', 'Due'];
        AuditFinding::query()->whereHas('audit', fn ($query) => $query->where('management_system_id', $system->id))->where('status', '!=', 'closed')->limit(50)->get()->each(function (AuditFinding $finding) use (&$rows): void { $rows[] = [$finding->code, $finding->title, $finding->severity, $finding->status, $finding->due_date?->format('Y-m-d') ?? '-']; });
        $rows[] = []; $rows[] = ['Open Actions']; $rows[] = ['Priority', 'Category', 'Action', 'Owner', 'Due'];
        ActionItem::query()->with('owner')->where('management_system_id', $system->id)->where('status', '!=', 'completed')->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")->limit(100)->get()->each(function (ActionItem $item) use (&$rows): void { $rows[] = [$item->priority, $item->category, $item->title, $item->owner?->name ?? '-', $item->due_at?->format('Y-m-d H:i') ?? '-']; });
        $rows[] = []; $rows[] = ['Evidence Summary'];
        $rows[] = ['Total', Evidence::query()->where('management_system_id', $system->id)->count()];
        $rows[] = ['Verified Active', Evidence::query()->where('management_system_id', $system->id)->where('status', 'active')->where('verification_status', 'verified')->count()];
        $rows[] = ['Expired', Evidence::query()->where('management_system_id', $system->id)->where('status', 'expired')->count()];
        $rows[] = ['Pending Verification', Evidence::query()->where('management_system_id', $system->id)->where('status', '!=', 'archived')->where('verification_status', 'pending')->count()];
        $stream = fopen('php://temp', 'r+'); foreach ($rows as $row) { fputcsv($stream, $row); } rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return $csv ?: '';
    }
}
