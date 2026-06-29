<?php

namespace App\Filament\Resources\AccessLogResource\Pages;

use App\Filament\Resources\AccessLogResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ViewAuditLog extends Page
{
    protected static string $resource = AccessLogResource::class;

    protected static string $view = 'filament.resources.access-log-resource.pages.view-audit-log';

    protected static ?string $title = 'Audit Log';

    protected ?Collection $logs = null;

    public function mount(): void
    {
        $this->logs = AuditLog::with(['user', 'loggable'])
            ->latest()
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            });
    }

    protected function getViewData(): array
    {
        return [
            'logs' => $this->logs,
        ];
    }
}
