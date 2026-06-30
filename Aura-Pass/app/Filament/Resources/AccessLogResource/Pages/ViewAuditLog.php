<?php

namespace App\Filament\Resources\AccessLogResource\Pages;

use App\Filament\Resources\AccessLogResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\Page;
use Livewire\WithPagination;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewAuditLog extends Page
{
    use WithPagination;

    protected static string $resource = AccessLogResource::class;

    protected static string $view = 'filament.resources.access-log-resource.pages.view-audit-log';

    protected static ?string $title = 'Audit Log';

    public string $search = '';
    public string $filter = 'all';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilter(): void { $this->resetPage(); }

    protected function getViewData(): array
    {
        $query = AuditLog::with(['user', 'loggable'])->latest();

        if ($this->filter === 'admin') {
            $query->whereIn('activity', [
                'admin.logged_in',
                'admin.logged_out',
                'admin.created',
                'admin.updated',
                'admin.deleted',
            ]);
        } elseif ($this->filter === 'member') {
            $query->whereIn('activity', [
                'member.created',
                'member.updated',
                'member.renewed',
                'member.deleted',
                'member.checked_in',
                'member.checked_out',
                'member.checked_in_manually',
                'member.checked_out_manually',
                'member.scan.failed',
            ]);
        } elseif ($this->filter === 'system') {
            $query->whereIn('activity', ['system.started', 'system.shutdown']);
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('activity', 'like', "%{$this->search}%")
                  ->orWhere('details->member_name', 'like', "%{$this->search}%")
                  ->orWhere('details->username', 'like', "%{$this->search}%")
                  ->orWhereHas('user', fn ($userQuery) =>
                      $userQuery->where('username', 'like', "%{$this->search}%")
                  )
                  // FIX: withTrashed() so soft-deleted members are still searchable
                  ->orWhereHasMorph('loggable', [\App\Models\Member::class], function ($memberQuery) {
                      $memberQuery->withTrashed()->where('name', 'like', "%{$this->search}%");
                  });
            });
        }

        $paginatedLogs = $query->paginate(100);

        $groupedLogs = collect($paginatedLogs->items())->groupBy(function ($log) {
            return $log->created_at->format('Y-m-d');
        });

        return [
            'groupedLogs' => $groupedLogs,
            'paginator'   => $paginatedLogs,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->color('primary')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    DatePicker::make('date_from')
                        ->label('Start Date')
                        ->default(now()->startOfMonth()),
                    DatePicker::make('date_to')
                        ->label('End Date')
                        ->default(now()->endOfMonth()),
                ])
                ->action(function (array $data) {
                    return $this->exportCsv($data['date_from'], $data['date_to']);
                }),
        ];
    }

    public function exportCsv(?string $dateFrom, ?string $dateTo): StreamedResponse
    {
        $query = AuditLog::with(['user', 'loggable'])->latest();

        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

        $allLogs  = $query->get();
        $filename = 'aurapass_audit_log_' . ($dateFrom ?? 'start') . '_to_' . ($dateTo ?? 'end') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($allLogs) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row — 8 columns
            fputcsv($file, [
                'ID',
                'Timestamp',
                'Operator',
                'Activity',
                'Target Type',
                'Target ID/Name',
                'IP Address',
                'User Agent',
            ]);

            foreach ($allLogs as $log) {
                // FIX: details first — never reads the scrubbed 'Deleted Member' from the DB
                $targetName = $log->details['member_name']
                    ?? $log->details['username']
                    ?? $log->loggable?->name
                    ?? 'N/A';

                // FIX: infer 'Member' from activity string when loggable_type is null (e.g. member.deleted)
                $targetType = $log->loggable_type
                    ? class_basename($log->loggable_type)
                    : (str_starts_with($log->activity, 'member.') ? 'Member' : 'System');

                // Data row — 8 columns matching the header exactly
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->username ?? 'System',
                    $log->activity,                        // was missing in previous version
                    $targetType,
                    $targetName,
                    $log->ip_address  ?? '127.0.0.1',
                    $log->user_agent  ?? 'N/A',
                ]);
            }

            fclose($file);
        }, 200, $headers);
    }
}