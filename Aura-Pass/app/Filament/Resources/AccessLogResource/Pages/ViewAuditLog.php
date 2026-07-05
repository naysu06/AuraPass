<?php

namespace App\Filament\Resources\AccessLogResource\Pages;

use App\Filament\Resources\AccessLogResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\Page;
use Livewire\WithPagination;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use ZipArchive; // REQUIRED for bundling multiple CSVs

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
                ->label('Export CSV') // Updated label
                ->color('primary')
                ->icon('heroicon-o-archive-box-arrow-down') // Updated icon to reflect a zip archive
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

    public function exportCsv(?string $dateFrom, ?string $dateTo)
    {
        $query = AuditLog::with(['user', 'loggable'])->latest();

        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

        // Group the logs by day directly from the database collection
        $groupedLogs = $query->get()->groupBy(fn ($log) => $log->created_at->format('Y-m-d'));

        // Define a temporary zip file path
        $zipFileName = 'aurapass_audit_logs_' . ($dateFrom ?? 'start') . '_to_' . ($dateTo ?? 'end') . '.zip';
        $zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Could not create ZIP file.');
        }

        // Generate a separate CSV file for each day
        foreach ($groupedLogs as $date => $logs) {
            $csvFile = fopen('php://temp', 'r+');
            fputs($csvFile, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            // Injecting empty spacer columns
            fputcsv($csvFile, [
                'ID', ' ', 'Timestamp', ' ', 'Operator', ' ', 'Activity', ' ', 'Target Type', ' ', 'Target ID/Name', ' ', 'IP Address', 'User Agent',
            ]);

            foreach ($logs as $log) {
                // 1. Determine Target Name and Type
                $targetName = $log->details['member_name']
                    ?? $log->details['username']
                    ?? $log->loggable?->name
                    ?? 'N/A';

                $targetType = $log->loggable_type
                    ? class_basename($log->loggable_type)
                    : (str_starts_with($log->activity, 'member.') ? 'Member' : (str_starts_with($log->activity, 'admin.') ? 'Admin' : 'System'));

                // 2. Format the Activity String for the CSV
                $displayActivity = ucwords(str_replace(['.', '_'], ' ', $log->activity));

                // --- MEMBER EVENTS ---
                if ($log->activity === 'member.updated') {
                    if (isset($log->details['note']) && $log->details['note'] === 'Expiry date modified') {
                        $old = \Carbon\Carbon::parse($log->details['old_expiry']);
                        $new = \Carbon\Carbon::parse($log->details['new_expiry']);
                        
                        if ($new->lessThan($old)) {
                            $displayActivity = 'Membership Changed (Until ' . $new->format('M j, Y') . ')';
                        } else {
                            $displayActivity = 'Membership Expiry Extended'; 
                        }
                    } 
                    elseif (isset($log->details['changes']) && is_array($log->details['changes'])) {
                        $changeDescriptions = [];
                        foreach ($log->details['changes'] as $key => $changeData) {
                            $formattedKey = ucwords(str_replace('_', ' ', $key));
                            if ($key === 'profile_photo') {
                                $changeDescriptions[] = "Profile Photo Updated";
                                continue;
                            }
                            $oldVal = $changeData['old'] ?? 'None';
                            $newVal = $changeData['new'] ?? 'None';
                            if (is_string($oldVal)) $oldVal = ucwords((string) $oldVal);
                            if (is_string($newVal)) $newVal = ucwords((string) $newVal);
                            $changeDescriptions[] = "{$formattedKey} Changed ({$oldVal} -> {$newVal})";
                        }
                        $displayActivity = implode('; ', $changeDescriptions);
                    } else {
                        $displayActivity = 'Member Profile Updated';
                    }
                } 
                elseif ($log->activity === 'member.renewed') {
                    if (isset($log->details['new_expiry'])) {
                        $displayActivity = 'Membership Renewed (Until ' . \Carbon\Carbon::parse($log->details['new_expiry'])->format('M j, Y') . ')';
                    } else {
                        $displayActivity = 'Membership Renewed';
                    }
                } 
                elseif ($log->activity === 'member.created') {
                    $displayActivity = 'New Member Registered';
                }
                elseif ($log->activity === 'member.deleted') {
                    $displayActivity = 'Member Account Deleted';
                }
                
                // --- ADMIN EVENTS ---
                elseif ($log->activity === 'admin.updated') {
                    if (isset($log->details['changes']) && is_array($log->details['changes'])) {
                        $changeDescriptions = [];
                        foreach ($log->details['changes'] as $key => $changeData) {
                            // If they just changed their password, hide the hashed string
                            if ($key === 'password') {
                                $changeDescriptions[] = "Password Updated";
                                continue;
                            }
                            $formattedKey = ucwords(str_replace('_', ' ', $key));
                            $oldVal = $changeData['old'] ?? 'None';
                            $newVal = $changeData['new'] ?? 'None';
                            if (is_string($oldVal)) $oldVal = ucwords((string) $oldVal);
                            if (is_string($newVal)) $newVal = ucwords((string) $newVal);
                            $changeDescriptions[] = "{$formattedKey} Changed ({$oldVal} -> {$newVal})";
                        }
                        $displayActivity = implode('; ', $changeDescriptions);
                    } else {
                        $displayActivity = 'Admin Account Modified';
                    }
                }
                elseif ($log->activity === 'admin.created') {
                    $role = isset($log->details['role']) ? ' (' . strtoupper($log->details['role']) . ')' : '';
                    $displayActivity = 'Admin Account Created' . $role;
                }
                elseif ($log->activity === 'admin.deleted') {
                    $deletedAdmin = $log->details['username'] ?? 'Unknown';
                    $displayActivity = "Admin Account Deleted ({$deletedAdmin})";
                }
                elseif ($log->activity === 'admin.logged_in') {
                    $displayActivity = 'Admin Logged In';
                }
                elseif ($log->activity === 'admin.logged_out') {
                    $displayActivity = 'Admin Logged Out';
                }

                // 3. Write the formatted row to the CSV
                fputcsv($csvFile, [
                    $log->id,
                    '', // Spacer
                    $log->created_at->format('Y-m-d H:i:s'),
                    '', // Spacer
                    $log->user?->username ?? $log->details['operator_name'] ?? 'System',
                    '', // Spacer
                    $displayActivity,
                    '', // Spacer
                    $targetType,
                    '', // Spacer
                    $targetName,
                    '', // Spacer
                    $log->ip_address  ?? '127.0.0.1',
                    $log->user_agent  ?? 'N/A',
                ]);
            }

            rewind($csvFile);
            $csvContent = stream_get_contents($csvFile);
            fclose($csvFile);

            $zip->addFromString("{$date}_audit_log.csv", $csvContent);
        }

        if ($groupedLogs->isEmpty()) {
            $zip->addFromString('no_data.txt', 'No audit logs found for the selected date range.');
        }

        $zip->close();

        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }
}